<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Oliver Hader <oliver@typo3.org>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * Generic test helpers.
 *
 * @author Oliver Hader <oliver@typo3.org>
 */
abstract class tx_datahandlertest_Unit_AbstractWorkspaces extends tx_datahandlertest_Unit_Abstract {
	const VALUE_WorkspaceIdIgnore = -1;

	/**
	 * @var tx_version_tcemain|PHPUnit_Framework_MockObject_MockObject
	 */
	protected $versionTceMainHookMock;

	/**
	 * @var tx_version_tcemain_CommandMap
	 */
	protected $versionTceMainCommandMap;

	/**
	 * Sets up this test case.
	 *
	 * @return void
	 */
	protected function setUp() {
		parent::setUp();

		$this->getBackendUser()->workspace = tx_datahandlertest_Service_SimulationService::WORKSPACE_Draft;
		$this->setWorkspacesConsiderReferences(FALSE);
		$this->setWorkspaceChangeStageMode('');
		$this->setWorkspaceSwapMode('');
	}

	/**
	 * Tears down this test case.
	 *
	 * @return void
	 */
	protected function tearDown() {
		parent::tearDown();

		unset($this->versionTceMainCommandMap);
		unset($this->versionTceMainHookMock);
	}

	/**
	 * Initializes a test database.
	 *
	 * @return resource
	 */
	protected function initializeDatabase() {
		$hasDatabase = parent::initializeDatabase();

		if ($hasDatabase) {
			$this->importExtensions(array('version'));
			$this->importExtensions(array('workspaces'));

			$this->importDataSet($this->getPath() . 'Fixtures/data_sys_workspace.xml');
		}
	}

	/**
	 * Asserts that accordant workspace version exist for live versions.
	 *
	 * @param array $tables Table names with list of ids to be edited
	 * @param integer $workspaceId Workspace to be used
	 * @param boolean $expected The expected value
	 * @return void
	 */
	protected function assertWorkspaceVersions(array $tables, $workspaceId = tx_datahandlertest_Service_SimulationService::WORKSPACE_Draft, $expected = TRUE) {
		foreach ($tables as $tableName => $idList) {
			$ids = t3lib_div::trimExplode(',', $idList, TRUE);
			foreach ($ids as $id) {
				$workspaceVersion = t3lib_BEfunc::getWorkspaceVersionOfRecord($workspaceId, $tableName, $id);
				$this->assertTrue(
					($expected ? $workspaceVersion !== FALSE : $workspaceVersion === FALSE),
					'Workspace version for ' . $tableName . ':' . $id . ($expected ? ' not' : '') . ' availabe'
				);
			}
		}
	}

	/**
	 * Gets a tx_version_tcemain mock.
	 *
	 * @param integer $expectsGetCommandMap (optional) Expects number of invokations to getCommandMap method
	 * @return tx_version_tcemain
	 */
	protected function getVersionTceMainHookMock($expectsGetCommandMap = NULL) {
		$this->versionTceMainHookMock = $this->getMock('tx_version_tcemain', array('getCommandMap'));

		if (is_integer($expectsGetCommandMap) && $expectsGetCommandMap >= 0) {
			$this->versionTceMainHookMock->expects($this->exactly($expectsGetCommandMap))->method('getCommandMap')
				->will($this->returnCallback(array($this, 'getVersionTceMainCommandMapCallback')));
		} elseif (!is_null($expectsGetCommandMap)) {
			$this->fail('Expected invokation of getCommandMap must be integer >= 0.');
		}

		return $this->versionTceMainHookMock;
	}

	/**
	 * Gets access to the command map.
	 *
	 * @param integer $expectsGetCommandMap Expects number of invokations to getCommandMap method
	 * @return void
	 */
	protected function getCommandMapAccess($expectsGetCommandMap) {
		$hookReferenceString = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['version'];
		$GLOBALS['T3_VAR']['getUserObj'][$hookReferenceString] = $this->getVersionTceMainHookMock($expectsGetCommandMap);
	}

	/**
	 * @param string $tableName
	 * @param integer $id
	 * @param integer $workspaceId
	 * @param boolean $directLookup
	 * @return boolean
	 */
	protected function getWorkpaceVersionId($tableName, $id, $workspaceId = tx_datahandlertest_Service_SimulationService::WORKSPACE_Draft, $directLookup = FALSE) {
		if ($directLookup) {
			$records = $this->getAllRecords($tableName);
			foreach ($records as $record) {
				if (($workspaceId === self::VALUE_WorkspaceIdIgnore || $record['t3ver_wsid'] == $workspaceId) && $record['t3ver_oid'] == $id) {
					return $record['uid'];
				}
			}
		} else {
			$workspaceVersion = t3lib_BEfunc::getWorkspaceVersionOfRecord($workspaceId, $tableName, $id);
			if ($workspaceVersion !== FALSE) {
				return $workspaceVersion['uid'];
			}
		}

		return FALSE;
	}

	/**
	 * Asserts the existence of a delete placeholder record.
	 *
	 * @param array $tables
	 * @return void
	 */
	protected function assertHasDeletePlaceholder(array $tables) {
		foreach ($tables as $tableName => $idList) {
			$records = $this->getAllRecords($tableName);

			$ids = t3lib_div::trimExplode(',', $idList, TRUE);
			foreach ($ids as $id) {
				$failureMessage = 'Delete placeholder of "' . $tableName . ':' . $id . '"';
				$versionizedId = $this->getWorkpaceVersionId($tableName, $id);
				$this->assertTrue(isset($records[$versionizedId]), $failureMessage . ' does not exist');
				$this->assertEquals($id, $records[$versionizedId]['t3_origuid'], $failureMessage . ' has wrong relation to live workspace');
				$this->assertEquals($id, $records[$versionizedId]['t3ver_oid'], $failureMessage . ' has wrong relation to live workspace');
				$this->assertEquals(2, $records[$versionizedId]['t3ver_state'], $failureMessage . ' is not marked as DELETED');
				$this->assertEquals('DELETED!', $records[$versionizedId]['t3ver_label'], $failureMessage . ' is not marked as DELETED');
			}
		}
	}

	/**
	 * @param array $tables
	 * @return void
	 */
	protected function assertIsDeleted(array $tables) {
		foreach ($tables as $tableName => $idList) {
			$records = $this->getAllRecords($tableName);

			$ids = t3lib_div::trimExplode(',', $idList, TRUE);
			foreach ($ids as $id) {
				$failureMessage = 'Workspaace version "' . $tableName . ':' . $id . '"';
				$this->assertTrue(isset($records[$id]), $failureMessage . ' does not exist');
				$this->assertEquals(0, $records[$id]['t3ver_state']);
				$this->assertEquals(1, $records[$id]['deleted']);
			}
		}
	}

	/**
	 * @param array $tables
	 * @return void
	 */
	protected function assertIsCleared(array $tables) {
		foreach ($tables as $tableName => $idList) {
			$records = $this->getAllRecords($tableName);

			$ids = t3lib_div::trimExplode(',', $idList, TRUE);
			foreach ($ids as $id) {
				$failureMessage = 'Workspaace version "' . $tableName . ':' . $id . '"';
				$this->assertTrue(isset($records[$id]), $failureMessage . ' does not exist');
				$this->assertEquals(0, $records[$id]['t3ver_state'], $failureMessage . ' has wrong state value');
				$this->assertEquals(0, $records[$id]['t3ver_wsid'],  $failureMessage . ' is still in offline workspace');
				$this->assertEquals(-1, $records[$id]['pid'],  $failureMessage . ' has wrong pid value');
			}
		}
	}

	/**
	 * @param array $assertions
	 * @param integer $workspaceId
	 */
	protected function assertRecords(array $assertions, $workspaceId = NULL) {
		foreach ($assertions as $table => $elements) {
			$records = $this->getAllRecords($table);

			foreach ($elements as $uid => $data) {
				$intersection = array_intersect_assoc($data, $records[$uid]);
				$differences = array_intersect_key($records[$uid], array_diff_assoc($data, $records[$uid]));

				$this->assertTrue(
					count($data) === count($intersection),
					'Expected ' . $this->elementToString($data) . ' got differences in ' . $this->elementToString($differences) . ' for table ' . $table
				);

				if (is_integer($workspaceId)) {
					$workspaceVersionId = $this->getWorkpaceVersionId($table, $uid, $workspaceId, TRUE);
					$intersection = array_intersect_assoc($data, $records[$workspaceVersionId]);
					$differences = array_intersect_key($records[$workspaceVersionId], array_diff_assoc($data, $records[$workspaceVersionId]));

					$this->assertTrue(
						count($data) === count($intersection),
						'Expected ' . $this->elementToString($data) . ' got differences in ' . $this->elementToString($differences) . ' for table ' . $table
					);
				}
			}
		}
	}

	/**
	 * Sets the User TSconfig property options.workspaces.considerReferences.
	 *
	 * @param boolean $workspacesConsiderReferences
	 * @return void
	 */
	protected function setWorkspacesConsiderReferences($workspacesConsiderReferences = TRUE) {
		$this->getBackendUser()->userTS['options.']['workspaces.']['considerReferences'] = ($workspacesConsiderReferences ? 1 : 0);
	}

	/**
	 * Sets the User TSconfig property options.workspaces.swapMode.
	 *
	 * @param string $workspaceSwapMode
	 * @return void
	 */
	protected function setWorkspaceSwapMode($workspaceSwapMode = 'any') {
		$this->getBackendUser()->userTS['options.']['workspaces.']['swapMode'] = $workspaceSwapMode;
	}

	/**
	 * Sets the User TSconfig property options.workspaces.changeStageMode.
	 *
	 * @param string $workspaceChangeStateMode
	 * @return void
	 */
	protected function setWorkspaceChangeStageMode($workspaceChangeStateMode = 'any') {
		$this->getBackendUser()->userTS['options.']['workspaces.']['changeStageMode'] = $workspaceChangeStateMode;
	}

	public function getVersionTceMainCommandMapCallback(t3lib_TCEmain $tceMain, array $commandMap) {
		$this->versionTceMainCommandMap = t3lib_div::makeInstance('tx_version_tcemain_CommandMap', $this->versionTceMainHookMock, $tceMain, $commandMap);
		return $this->versionTceMainCommandMap;
	}

	/**
	 * @return tx_version_tcemain_CommandMap
	 */
	protected function getCommandMap() {
		return $this->versionTceMainCommandMap;
	}
}

?>
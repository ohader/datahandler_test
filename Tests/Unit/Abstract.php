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
abstract class tx_datahandlertest_Unit_Abstract extends Tx_Phpunit_Database_TestCase {
	const TABLE_Pages = 'pages';

	const PROPERTY_LocalizeReferencesAtParentLocalization = 'localizeReferencesAtParentLocalization';
	const BEHAVIOUR_LocalizeChildrenAtParentLocalization = 'localizeChildrenAtParentLocalization';
	const BEHAVIOUR_LocalizationMode = 'localizationMode';

	const VALUE_LocalizationMode_Keep = 'keep';
	const VALUE_LocalizationMode_Select = 'select';

	/**
	 * @var string
	 */
	private $path;

	/**
	 * @var integer
	 */
	private $expectedLogEntries = 0;

	/**
	 * @var array
	 */
	private $originalConvVars;

	/**
	 * @var array
	 */
	private $originalTca;

	/**
	 * @var t3lib_beUserAuth
	 */
	private $originalBackendUser;

	/**
	 * @var t3lib_beUserAuth
	 */
	private $backendUser;

	/**
	 * Sets up this test case.
	 *
	 * @return void
	 */
	protected function setUp() {
		$this->expectedLogEntries = 0;

		$this->originalTca = $GLOBALS['TCA'];

		$this->originalBackendUser = clone $GLOBALS['BE_USER'];
		$this->backendUser = $GLOBALS['BE_USER'];
		$this->backendUser->workspace = 0;
		$this->fixBackendUser();

		$this->originalConvVars = $GLOBALS['TYPO3_CONF_VARS'];
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['sqlDebug'] = 1;

		$this->initializeDatabase();
		$this->fixReleatedExtensions();

		$this->flushCaches();
	}

	/**
	 * Tears down this test case.
	 *
	 * @return void
	 */
	protected function tearDown() {
		$this->assertNoLogEntries();

		$this->expectedLogEntries = 0;

		$GLOBALS['TCA'] = $this->originalTca;
		unset($this->originalTca);

		$GLOBALS['TYPO3_CONF_VARS'] = $this->originalConvVars;
		unset($this->originalConvVars);

		$GLOBALS['BE_USER'] = $this->originalBackendUser;
		unset($this->originalBackendUser);
		unset($this->backendUser);

		unset($GLOBALS['T3_VAR']['getUserObj']);

		t3lib_div::purgeInstances();
		$this->dropDatabase();
	}

	/**
	 * Flush all caches that are e.g. used in the workspace module.
	 *
	 * @return void
	 */
	private function flushCaches() {
		$GLOBALS['typo3CacheManager']->flushCaches();
	}

	/**
	 * Since CLI mode does not allow admin user, but setting up ACL for
	 * the unit test is too complex, the cloned user will have admin
	 * permission on the test database.
	 *
	 * @return void
	 */
	private function fixBackendUser() {
		if (defined('TYPO3_REQUESTTYPE_CLI') && TYPO3_REQUESTTYPE_CLI) {
			$this->getBackendUser()->user['admin'] = 1;
		}
	}

	/**
	 * Some extensions register hooks for t3lib_TCEmain that might be executed
	 * during these tests (not a problem) - however the SQL tables have to be
	 * initialized in the test database then.
	 *
	 * @return void
	 */
	private function fixReleatedExtensions() {
		$relatedExtensions = array();
		$hooks =& $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php'];

		if (is_array($hooks)) {
			foreach ($hooks as $hookExtensions) {
				if (is_array($hookExtensions)) {
					foreach ($hookExtensions as $hookHandler) {
						$matches = array();
						if (is_string($hookHandler) && preg_match('#^EXT:([^/]+)/#', $hookHandler, $matches)) {
							$relatedExtensions[$matches[1]] = TRUE;
						}
					}
				}
			}
		}

		if (count($relatedExtensions)) {
			$this->importExtensions(
				array_keys($relatedExtensions)
			);
		}
	}

	/**
	 * @return boolean
	 */
	protected function createAndUseDatabase() {
		$hasDatabase = $this->createDatabase();

		if ($hasDatabase === TRUE) {
			$this->useTestDatabase();
		} else {
			$this->fail('No test database available');
		}

		return $hasDatabase;
	}

	/**
	 * Initializes a test database.
	 *
	 * @return resource
	 */
	protected function initializeDatabase() {
		$hasDatabase = $this->createAndUseDatabase();

		if ($hasDatabase) {
			$this->importStdDB();
			$this->importExtensions(array('cms'));

			$this->importDataSet($this->getPath() . 'Fixtures/data_pages.xml');
			$this->importDataSet($this->getPath() . 'Fixtures/data_sys_language.xml');
		}

		return $hasDatabase;
	}

	/**
	 * Gets the path to the test directory.
	 *
	 * @return string
	 */
	protected function getPath() {
		if (!isset($this->path)) {
			$this->path = t3lib_extMgm::extPath('datahandler_test') . 'Tests/Unit/';
		}

		return $this->path;
	}

	/**
	 * @return t3lib_beUserAuth
	 */
	protected function getBackendUser() {
		return $this->backendUser;
	}

	/**
	 * Sets the number of expected log entries.
	 *
	 * @param integer $count
	 * @return void
	 */
	protected function setExpectedLogEntries($count) {
		$count = intval($count);

		if ($count > 0) {
			$this->expectedLogEntries = $count;
		}
	}

	/**
	 * Gets the last log entry.
	 *
	 * @return array
	 */
	protected function getLastLogEntryMessage() {
		$message = '';

		$logEntries = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', 'sys_log', 'error IN (1,2)', '', '', 1);

		if (is_array($logEntries) && count($logEntries)) {
			$message = $logEntries[0]['details'];
		}

		return $message;
	}

	/**
	 * @param  array $itemArray
	 * @return array
	 */
	protected function getElementsByItemArray(array $itemArray) {
		$elements = array();

		foreach ($itemArray as $item) {
			$elements[$item['table']][$item['id']] = t3lib_BEfunc::getRecord($item['table'], $item['id']);
		}

		return $elements;
	}

	/**
	 * Gets all records of a table.
	 *
	 * @param string $table Name of the table
	 * @param string $indexField Field of the primary index value (uid)
	 * @param string $orderBy The ORDER BY statement
	 * @return array
	 */
	protected function getAllRecords($table, $indexField = 'uid', $orderBy = '') {
		return $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', $table, '1=1', '', $orderBy, '', $indexField);
	}

	/**
	 * Gets the TCE configuration of a field.
	 *
	 * @param  $tableName
	 * @param  $fieldName
	 * @return array
	 */
	protected function getTcaFieldConfiguration($tableName, $fieldName) {
		if (!isset($GLOBALS['TCA'][$tableName]['columns'])) {
			t3lib_div::loadTCA($tableName);
		}

		if (!isset($GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'])) {
			$this->fail('TCA definition for field ' . $tableName . '.' . $fieldName . ' not available');
		}

		return $GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'];
	}

	/**
	 * @param string $tableName
	 * @param string $fieldName
	 * @param string $propertyName
	 * @param mixed $value
	 * @return void
	 */
	protected function setTcaFieldConfiguration($tableName, $fieldName, $propertyName, $value) {
		if (!isset($GLOBALS['TCA'][$tableName]['columns'])) {
			t3lib_div::loadTCA($tableName);
		}

		if (isset($GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'])) {
			$GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'][$propertyName] = $value;
		}
	}

	/**
	 * @param string $tableName
	 * @param string $fieldName
	 * @param string $behaviourName
	 * @param mixed $value
	 * @return void
	 */
	protected function setTcaFieldConfigurationBehaviour($tableName, $fieldName, $behaviourName, $value) {
		if (!isset($GLOBALS['TCA'][$tableName]['columns'])) {
			t3lib_div::loadTCA($tableName);
		}

		if (isset($GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'])) {
			if (!isset($GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config']['behaviour'])) {
				$GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config']['behaviour'] = array();
			}

			$GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config']['behaviour'][$behaviourName] = $value;
		}
	}

	/**
	 * Gets the field value of a record.
	 *
	 * @param  $tableName
	 * @param  $id
	 * @param  $fieldName
	 * @return string
	 */
	protected function getFieldValue($tableName, $id, $fieldName) {
		$record = t3lib_BEfunc::getRecord($tableName, $id, $fieldName);

		if (!is_array($record)) {
			$this->fail('Record ' . $tableName . ':' . $id . ' not available');
		}

		return $record[$fieldName];
	}

	/**
	 * Gets instance of t3lib_loadDBGroup.
	 *
	 * @return t3lib_loadDBGroup
	 */
	protected function getLoadDbGroup() {
		$loadDbGroup = t3lib_div::makeInstance('t3lib_loadDBGroup');

		return $loadDbGroup;
	}

	/**
	 * Assert that no sys_log entries had been written.
	 *
	 * @return void
	 */
	protected function assertNoLogEntries() {
		$logEntries = $this->getLogEntries();

		if (count($logEntries) > $this->expectedLogEntries) {
			var_dump(array_values($logEntries)); ob_flush();
			$this->fail('The sys_log table contains unexpected entries.');
		} elseif (count($logEntries) < $this->expectedLogEntries) {
			$this->fail('Expected count of sys_log entries no reached.');
		}
	}

	/**
	 * Asserts the correct order of elements.
	 *
	 * @param string $table
	 * @param string $field
	 * @param array $expectedOrderOfIds
	 * @param string $message
	 * @return void
	 */
	protected function assertSortingOrder($table, $field, $expectedOrderOfIds, $message = NULL) {
		$expectedOrderOfIdsCount = count($expectedOrderOfIds);
		$elements = $this->getAllRecords($table);

		if ($message === NULL) {
			$message = 'Sorting order does not match in table ' . $table;
		}

		foreach ($expectedOrderOfIds as $expectedUid) {
			$this->assertNotEmpty(
				$elements[$expectedUid],
				'Element ' . $table . ':' . $expectedUid . ' not found'
			);
		}

		for ($i = 0; $i < $expectedOrderOfIdsCount-1; $i++) {
			$this->assertLessThan(
				$elements[$expectedOrderOfIds[$i+1]][$field],
				$elements[$expectedOrderOfIds[$i]][$field],
				$message
			);
		}
	}

	/**
	 * Asserts reference index elements.
	 *
	 * @param array $assertions
	 * @param boolean $expected
	 */
	protected function assertReferenceIndex(array $assertions, $expected = TRUE) {
		$references = $this->getAllRecords('sys_refindex', 'hash');

		foreach ($assertions as $parent => $children) {
			foreach ($children as $child) {
				$parentItems = explode(':', $parent);
				$childItems = explode(':', $child);

				$assertion = array(
					'tablename' => $parentItems[0],
					'recuid' => $parentItems[1],
					'field' => $parentItems[2],
					'ref_table' => $childItems[0],
					'ref_uid' => $childItems[1],
				);

				$this->assertTrue(
					($expected === $this->executeAssertionOnElements($assertion, $references)),
					'Expected reference index element for ' . $parent . ' -> ' . $child
				);
			}
		}
	}

	/**
	 * @param string $parentTableName
	 * @param integer $parentId
	 * @param string $parentFieldName
	 * @param array $assertions
	 * @param string $mmTable
	 * @param boolean $expected
	 * @return void
	 */
	protected function assertChildren($parentTableName, $parentId, $parentFieldName, array $assertions, $mmTable = '', $expected = TRUE) {
		$tcaFieldConfiguration = $this->getTcaFieldConfiguration($parentTableName, $parentFieldName);

		$loadDbGroup = $this->getLoadDbGroup();
		$loadDbGroup->start(
			$this->getFieldValue($parentTableName, $parentId, $parentFieldName),
			$tcaFieldConfiguration['foreign_table'],
			$mmTable,
			$parentId,
			$parentTableName,
			$tcaFieldConfiguration
		);

		$elements = $this->getElementsByItemArray($loadDbGroup->itemArray);

		foreach ($assertions as $index => $assertion) {
			$this->assertTrue(
				($expected === $this->executeAssertionOnElements($assertion, $elements)),
				'Assertion #' . $index . ' failed'
			);
		}
	}

	/**
	 * @return array
	 */
	protected function getLogEntries() {
		return $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', 'sys_log', 'error IN (1,2)');
	}

	/**
	 * @param  array $assertion
	 * @param  array $elements
	 * @return boolean
	 */
	protected function executeAssertionOnElements(array $assertion, array $elements) {
		if (!empty($assertion['tableName'])) {
			$tableName = $assertion['tableName'];
			unset($assertion['tableName']);
			$elements = (array) $elements[$tableName];
		}

		foreach ($elements as $element) {
			$result = FALSE;

			foreach ($assertion as $field => $value) {
				if ($element[$field] == $value) {
					$result = TRUE;
				} else {
					$result = FALSE;
					break;
				}
			}

			if ($result === TRUE) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * @param mixed $element
	 * @return string
	 */
	protected function elementToString($element) {
		$result = preg_replace(
			'#\n+#',
			' ',
			var_export($element, TRUE)
		);

		return $result;
	}

	/**
	 * @return string
	 */
	protected function combine() {
		return implode(':', func_get_args());
	}

	/**
	 * @return tx_datahandlertest_Service_SimulationService
	 */
	protected function getSimulationService() {
		return t3lib_div::makeInstance(
			'tx_datahandlertest_Service_SimulationService'
		);
	}

}

?>
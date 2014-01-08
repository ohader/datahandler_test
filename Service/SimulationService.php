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
 * Generic element data.
 *
 * @author Oliver Hader <oliver@typo3.org>
 */
class tx_datahandlertest_Service_SimulationService {

	const VALUE_Pid = 99999;
	const VALUE_TimeStamp = 1250000000;
	const VALUE_Language = 9;

	const WORKSPACE_Live = 0;
	const WORKSPACE_Draft = 9;

	const COMMAND_Copy = 'copy';
	const COMMAND_Localize = 'localize';
	const COMMAND_Delete = 'delete';
	const COMMAND_Move = 'move';
	const COMMAND_Version = 'version';
	const COMMAND_Version_New = 'new';
	const COMMAND_Version_Swap = 'swap';
	const COMMAND_Version_Flush = 'flush';
	const COMMAND_Version_Clear = 'clearWSID';

	/**
	 * @var integer
	 */
	protected $modifiedTimeStamp;

	/**
	 * @var array
	 */
	protected $recentElementUids = array();

	/**
	 * @param string $table
	 * @param array $fields
	 * @return tx_datahandlertest_Structure_Element
	 */
	public function createWorkspaceElement($table, array $fields = array()) {
		return $this->createElement($table, $fields, self::WORKSPACE_Draft, FALSE);
	}

	/**
	 * @param string $table
	 * @param array $fields
	 * @return tx_datahandlertest_Structure_Element
	 */
	public function createLiveElement($table, array $fields = array()) {
		return $this->createElement($table, $fields, self::WORKSPACE_Live, FALSE);
	}

	/**
	 * @param string $table
	 * @param array $fields
	 * @return tx_datahandlertest_Structure_Element
	 */
	public function appendWorkspaceElement($table, array $fields = array()) {
		return $this->createElement($table, $fields, self::WORKSPACE_Draft, TRUE);
	}

	/**
	 * @param string $table
	 * @param array $fields
	 * @return tx_datahandlertest_Structure_Element
	 */
	public function appendLiveElement($table, array $fields = array()) {
		return $this->createElement($table, $fields, self::WORKSPACE_Live, TRUE);
	}

	/**
	 * @param string $table
	 * @param integer $uid
	 * @return tx_datahandlertest_Structure_Element
	 */
	public function localizeLiveElement($table, $uid) {
		return $this->localizeElement($table, $uid, self::WORKSPACE_Live);
	}

	/**
	 * @param string $table
	 * @param integer $uid
	 * @return tx_datahandlertest_Structure_Element
	 */
	public function localizeWorkspaceElement($table, $uid) {
		return $this->localizeElement($table, $uid, self::WORKSPACE_Draft);
	}

	public function moveElementAfter($table, $uid, $after) {
		$tceMain = $this->simulateCommand(
			self::COMMAND_Move,
			-$after,
			array($table => array($uid))
		);

		var_dump($tceMain->autoVersionIdMap);
		var_dump($tceMain->substNEWwithIDs);
		ob_flush();
	}

	/*********************************************
	 * Internal methods
	 */

	/**
	 * @param string $table
	 * @param array $fields
	 * @param integer $workspace
	 * @param boolean $append
	 * @return tx_datahandlertest_Structure_Element
	 */
	protected function createElement($table, array $fields = array(), $workspace, $append = FALSE) {
		$currentWorkspace = $this->getBackendUser()->workspace;
		$this->getBackendUser()->workspace = $workspace;

		$newElementId = uniqid('NEW');
		$pid = self::VALUE_Pid;

		if ($append && !empty($this->recentElementUids[$table])) {
			$pid = -$this->recentElementUids[$table];
		}

		$tceMain = $this->simulateEditingByStructure(
			array(
				$table => array(
					$newElementId => array_merge(
						array('pid' => $pid),
						$fields
					),
				),
			)
		);

		$element = new tx_datahandlertest_Structure_Element();
		$element->uid = $tceMain->substNEWwithIDs[$newElementId];

		if (!empty($workspace)) {
			$element->placeholderUid = $tceMain->substNEWwithIDs[$newElementId];
			$element->versionedUid = $tceMain->getAutoVersionId($table, $element->placeholderUid);
		}

		$this->recentElementUids[$table] = $element->uid;
		$this->getBackendUser()->setWorkspace($currentWorkspace);

		return $element;
	}

	/**
	 * @param string $table
	 * @param integer $uid
	 * @param integer $workspace
	 * @return tx_datahandlertest_Structure_Element
	 */
	protected function localizeElement($table, $uid, $workspace) {
		$currentWorkspace = $this->getBackendUser()->workspace;
		$this->getBackendUser()->workspace = $workspace;

		$tceMain = $this->simulateCommand(
			self::COMMAND_Localize,
			self::VALUE_Language,
			array($table => array($uid))
		);

		$element = new tx_datahandlertest_Structure_Element();
		$element->uid = $tceMain->copyMappingArray_merged[$table][$uid];

		if (!empty($workspace)) {
			$element->placeholderUid = $tceMain->copyMappingArray_merged[$table][$uid];
			$element->versionedUid = $tceMain->getAutoVersionId($table, $element->placeholderUid);
		}

		$this->getBackendUser()->workspace = $currentWorkspace;

		return $element;
	}

	/**
	 * Gets a modified timestamp to ensure that a record is changed.
	 *
	 * @return integer
	 */
	public function getModifiedTimeStamp() {
		if (!isset($this->modifiedTimeStamp)) {
			$this->modifiedTimeStamp = self::VALUE_TimeStamp + 100;
		}

		return $this->modifiedTimeStamp;
	}

	/**
	 * Gets an element structure of tables and ids used to simulate editing with TCEmain.
	 *
	 * @param array $tables Table names with list of ids to be edited
	 * @return array
	 */
	protected function getElementStructureForEditing(array $tables) {
		$editStructure = array();

		foreach ($tables as $tableName => $idList) {
			$ids = t3lib_div::trimExplode(',', $idList, TRUE);
			foreach ($ids as $id) {
				$editStructure[$tableName][$id] = array(
					'tstamp' => $this->getModifiedTimeStamp(),
				);
			}
		}

		return $editStructure;
	}

	/**
	 * @param string $command
	 * @param mixed $value
	 * @param array $tables Table names with list of ids to be edited
	 * @return array
	 */
	protected function getElementStructureForCommands($command, $value, array $tables) {
		$commandStructure = array();

		foreach ($tables as $tableName => $idList) {
			if (is_array($idList)) {
				$ids = $idList;
			} else {
				$ids = t3lib_div::trimExplode(',', $idList, TRUE);
			}
			foreach ($ids as $id) {
				$commandStructure[$tableName][$id] = array(
					$command => $value
				);
			}
		}

		return $commandStructure;
	}

	/**
	 * Simulates executing commands by using t3lib_TCEmain.
	 *
	 * @param  array $elements The cmdmap to be delivered to t3lib_TCEmain
	 * @return t3lib_TCEmain
	 */
	protected function simulateCommandByStructure(array $elements) {
		$tceMain = $this->getTceMain();
		$tceMain->start(array(), $elements);
		$tceMain->process_cmdmap();

		return $tceMain;
	}

	/**
	 * @param  array $tables Table names with list of ids to be edited
	 * @return t3lib_TCEmain
	 */
	protected function simulateEditing(array $tables) {
		return $this->simulateEditingByStructure($this->getElementStructureForEditing($tables));
	}

	/**
	 * @param string $command
	 * @param mixed $value
	 * @param array $tables Table names with list of ids to be edited
	 * @return t3lib_TCEmain
	 */
	protected function simulateCommand($command, $value, array $tables) {
		return $this->simulateCommandByStructure(
			$this->getElementStructureForCommands($command, $value, $tables)
		);
	}

	/**
	 * Simulates editing by using t3lib_TCEmain.
	 *
	 * @param  array $elements The datamap to be delivered to t3lib_TCEmain
	 * @return t3lib_TCEmain
	 */
	protected function simulateEditingByStructure(array $elements) {
		$tceMain = $this->getTceMain();
		$tceMain->start($elements, array());
		$tceMain->process_datamap();

		return $tceMain;
	}

	/**
	 * @param array $commands
	 * @param array $tables
	 * @return t3lib_TCEmain
	 */
	protected function simulateVersionCommand(array $commands, array $tables) {
		return $this->simulateCommand(
			self::COMMAND_Version,
			$commands,
			$tables
		);
	}

	/**
	 * Simulates editing and command by structure.
	 *
	 * @param array $editingElements
	 * @param array $commandElements
	 * @return t3lib_TCEmain
	 */
	protected function simulateByStructure(array $editingElements, array $commandElements) {
		$tceMain = $this->getTceMain();
		$tceMain->start($editingElements, $commandElements);
		$tceMain->process_datamap();
		$tceMain->process_cmdmap();

		return $tceMain;
	}

	/**
	 * Gets an instance of t3lib_TCEmain.
	 *
	 * @return t3lib_TCEmain
	 */
	protected function getTceMain() {
		$tceMain = t3lib_div::makeInstance('t3lib_TCEmain');
		return $tceMain;
	}

	/**
	 * @return t3lib_beUserAuth
	 */
	protected function getBackendUser() {
		return $GLOBALS['BE_USER'];
	}

}

?>
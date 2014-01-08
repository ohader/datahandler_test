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
 * Testcase for 1:n ff relations.
 *
 * @author Oliver Hader <oliver@typo3.org>
 */
class tx_datahandlertest_RegularWorkspacesTest extends tx_datahandlertest_Unit_AbstractWorkspaces {

	/****************************************************************
	 * CREATE Behaviour
	 ****************************************************************/

	/**
	 * @test
	 */
	public function newWorkspaceElementsAreCreated() {
		$simulationService = $this->getSimulationService();

		$first = $simulationService->appendWorkspaceElement('tt_content', array('header' => 'First'));
		$second = $simulationService->appendWorkspaceElement('tt_content', array('header' => 'Second'));
		$third = $simulationService->appendWorkspaceElement('tt_content', array('header' => 'Third'));

		$this->assertSortingOrder(
			'tt_content', 'sorting',
			array(
				$first->placeholderUid,
				$second->placeholderUid,
				$third->placeholderUid,
			)
		);

		$this->assertSortingOrder(
			'tt_content', 'sorting',
			array(
				$first->versionedUid,
				$second->versionedUid,
				$third->versionedUid,
			)
		);
	}

	/**
	 * @test
	 */
	public function existingLiveElementIsMoved() {
		$simulationService = $this->getSimulationService();

		$first = $simulationService->appendLiveElement('tt_content', array('header' => 'First'));
		$second = $simulationService->appendLiveElement('tt_content', array('header' => 'Second'));
		$third = $simulationService->appendLiveElement('tt_content', array('header' => 'Third'));

		// Moving first after second
		$simulationService->moveElementAfter('tt_content', $first->uid, $second->uid);
		$simulationService->moveElementAfter('tt_content', $second->uid, $first->uid);

		var_dump($this->getAllRecords('tt_content'));
		ob_flush();
	}

	/**
	 * @test
	 */
	public function existingLiveDefaultLanguageIsMoved() {
		$simulationService = $this->getSimulationService();

		$first = $simulationService->appendLiveElement('tt_content', array('header' => 'First', 'colPos' => 1));
		$second = $simulationService->appendLiveElement('tt_content', array('header' => 'Second', 'colPos' => 1));
		$third = $simulationService->appendLiveElement('tt_content', array('header' => 'Third', 'colPos' => 1));

		$firstLocalized = $simulationService->localizeLiveElement('tt_content', $first->uid);
		$secondLocalize = $simulationService->localizeLiveElement('tt_content', $second->uid);
		$thirdLocalized = $simulationService->localizeLiveElement('tt_content', $third->uid);

		$simulationService->moveElementAfter('tt_content', $first->uid, $second->uid);

		var_dump($this->getAllRecords('tt_content'));
		ob_flush();
	}

}

?>
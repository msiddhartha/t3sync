<?php
/***************************************************************
 *  Copyright notice
*
*  (c) 2012  (info@studioneun.de)
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
 * Scheduler task to simulate user actions recorded on a DB level for table sys_refindex for DAM index refs for softref_key: \'media\', \'mediatag\'

* @author <info@studioneun.de>
* @package TYPO3
* @subpackage st9fissync
*
*/

if (defined ('TYPO3_MODE') && TYPO3_MODE === 'BE') {

    class tx_st9fissync_tasks_add_sysrefindex_syncqueue extends tx_scheduler_Task
    {
        private $syncAPI;

        /**
         * execute
         * Execute (main) method of the scheduler task
         *
         * @return boolean
         */
        public function execute()
        {
            $this->syncAPI = tx_st9fissync::getInstance();
            $sysRefDAMRefMediaElements = $this->syncAPI->getSyncDBOperationsManager()->getSysRefDAMRefMediaElements();
            $tableName = $this->syncAPI->getSyncDBOperationsManager()->getDAMRecRTERefTableName();

            foreach ($sysRefDAMRefMediaElements as $sysRefDAMRefMediaElement) {
                $queryToBeExec = $this->syncAPI->compileRootRevisionQueryForATableRow($sysRefDAMRefMediaElement, $tableName);
                $recordRowUid = array('NEW_REC' =>  $sysRefDAMRefMediaElement);
                $this->syncAPI->runVersioningRulesEngine($this->syncAPI->buildVersionTipsArray($tableName, 'exec_INSERTquery', $queryToBeExec, 0, null, $recordRowUid, 1));
            }

            return TRUE;
        }

    }
}

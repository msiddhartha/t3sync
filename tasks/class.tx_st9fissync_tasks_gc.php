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
 * This task is to trigger sync garbage collector actions recorded on a DB level and replaying them on a remote T3 instance

* @author <info@studioneun.de>
* @package TYPO3
* @subpackage st9fissync
*
*/

if (defined ('TYPO3_MODE') && TYPO3_MODE === 'BE') {

    class tx_st9fissync_tasks_gc extends tx_scheduler_Task
    {
        /**
         * execute
         * Execute (main) method of the scheduler task
         *
         * @return boolean
         */
        public function execute()
        {
            // Getting sync objects
            $objSync = tx_st9fissync::getInstance();
            $objSyncConfig = $objSync->getSyncConfigManager();

            // Older than days
            $days = $objSyncConfig->getGCOlderThanDays();

            if ($days != '' && $days > 0) {
                // Converting to timestamp
                $days = $days + 1;
                $tilltimestamp = mktime(23, 59, 59, date("m"), (date("d") - $days), date("Y"));

                // executing GC
                $objGC = tx_st9fissync_gc::getGCInstance();
                $objGC->executeGC($tilltimestamp);

                return true;
            }
        }
    }
}

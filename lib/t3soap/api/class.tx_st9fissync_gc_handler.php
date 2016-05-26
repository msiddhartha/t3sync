<?php
/***************************************************************
 *  Copyright notice
*
*  (c) 2012 André Spindler <info@studioneun.de>
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
 * GC service class for 'st9fissync' extension.
 *
 * @author	André Spindler <sp@studioneun.de>
* @package	TYPO3
* @subpackage	st9fissync
*/
class tx_st9fissync_gc_handler
{
    /**
     * Function for getting the successfully synced uids isynced=1 and issyncscheduled=1
     * @param  array $arrparam
     * @return mixed
     */
    public function getSuccessfullySyncedUIDs($arrparam)
    {
        return tx_st9fissync_gc::getGCInstance()->getAlreadySyncedUIDs($arrparam['timestamp']);
    }

    /**
     * Function for taking table backup based on whereclause
     * @param $arrparam [tablename, gcprocid, backuptime, whereclause]
     * @return array|boolean
     */
    public function takeTableBackup($arrparam)
    {
        $arrResult = false;
        if (trim($arrparam['tablename']) != '' && trim($arrparam['whereclause']) != '' && trim($arrparam['gcprocid']) != '' && trim($arrparam['backuptime']) != '') {
            tx_st9fissync::getInstance()->setSyncRuntimeMaxExecTime();
            $arrResult = tx_st9fissync::getInstance()->doTableBackup($arrparam);
            tx_st9fissync::getInstance()->resetToOrigRuntimeMaxExecTime();
        }

        return $arrResult;
    }

    /**
     * Function for deleting the sync records based whereclause
     * @param $arrparam
     * @return array
     */
    public function deleteRecordsFromTable($arrparam)
    {
        $deleteRecords = false;
        $tablename = trim($arrparam['tablename']);
        $whereclause = trim($arrparam['whereclause']);

        if (trim($tablename) != '' && trim($whereclause) != '') {
            $deleteRecords = tx_st9fissync::getInstance()->getSyncDBOperationsManager()->deleteRecordsFromTable($tablename, $whereclause);
        }

        return array('deleteRecords'=>$deleteRecords);
    }
}

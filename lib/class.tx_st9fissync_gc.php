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
 * Garbage collector tx_st9fissync.
 *
 * @author	André Spindler <info@studioneun.de>
* @package	TYPO3
* @subpackage	tx_st9fissync
*/

class tx_st9fissync_gc
{
    const GCSTAGE_INITIAL = 0; // still not launched
    const GCSTAGE_EXECUTING = 1;
    const GCSTAGE_COMPLETED = 2;

    const GCSTATUS_UNKNOWN = 0;
    const GCSTATUS_SUCCESS = 1;
    const GCSTATUS_ABORT = 2;
    const GCSTATUS_FAILURE = 3;
    const GCSTATUS_FATAL = 4;

    /**
     * @var tx_st9fissync_gc
     */
    protected static $gcinstance;

    /**
     * GC process Id
     * @var int
     */
    private $gcProcId = null;

    /**
     * GC status
     * @var int
     */
    private $gcStatus = null;

    /**
     * GC soap session id
     */
    private $gcSoapSessionId = null;

    /**
     * GC soap client
     */
    private $gcSOAPClient = null;

    /**
     * @var $gcSummary;
     */
    public $gcSummary;

    /**
     * @var $globalUidArray;
     */
    public $globalUidArray;

    /**
     * @var $backupTimestamp
     */
    public $backupTimestamp;

    /**
     * @var $backupFolderPath
     */
    public $backupFolderPath;

    /**
     * @var $remoteBackupFolderPath
     */
    public $remoteBackupFolderPath;

    /**
     * Getting GC Instance
     * @static
     * @return tx_st9fissync_gc
     */
    public static function getGCInstance()
    {
        if (tx_st9fissync_gc::$gcinstance == null) {
            tx_st9fissync_gc::$gcinstance = t3lib_div::makeInstance('tx_st9fissync_gc');
            self::getSyncInstance()->setStageGC();
        }

        return tx_st9fissync_gc::$gcinstance;
    }

    public function setGCStatus($gcStatus)
    {
        if (!is_null($gcStatus) && in_array($gcStatus, array(tx_st9fissync_gc::GCSTATUS_ABORT,
                tx_st9fissync_gc::GCSTATUS_FAILURE,
                tx_st9fissync_gc::GCSTATUS_FATAL,
                tx_st9fissync_gc::GCSTATUS_SUCCESS,
                tx_st9fissync_gc::GCSTATUS_UNKNOWN))) {

            $this->gcStatus = $gcStatus;
        }

        return $this->gcStatus;
    }

    public function getGCStatus()
    {
        return $this->gcStatus;
    }

    /**
     * Function for getting DB object
     *
     * @return tx_st9fissync
     */
    public function getSyncInstance()
    {
        return tx_st9fissync::getInstance();
    }

    /**
     * Function for getting GLOBAL DB Object
     *
     * @return t3lib_DB
     */
    public function getDBObject()
    {
        return $this->getSyncInstance()->getSyncDBObject();
    }

    /**
     * Function for getting DB Object
     *
     * @return tx_st9fissync_db
     */
    public function getSyncDBOperationsManagerObject()
    {
        return $this->getSyncInstance()->getSyncDBOperationsManager();
    }

    /**
     * Function for getting GC active process
     *
     * @return tx_st9fissync_db
     */
    public function getGCActiveProcesses()
    {
        return $this->getSyncDBOperationsManagerObject()->getGCActiveProcesses();
    }

    /**
     * Get isGCProcNotActive
     * @return boolean
     */
    public function isGCProcNotActive()
    {
        $activeGCProcesses = $this->getGCActiveProcesses();
        if ((!$activeGCProcesses) || (is_array($activeGCProcesses) && count($activeGCProcesses) <= 0)) {
            // Message
            $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.no_active_process')));

            return true;
        }

        // Message
        $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.currently_active')));

        return false;
    }

    /**
     * Get GC process id
     */
    public function getGCProcId()
    {
        return $this->gcProcId;
    }

    /**
     * Function for checking if the
     * @param  int     $timestamp
     * @return boolean
     */
    public function checkValidTimestamp($timestamp)
    {
        $prevdaytimestamp = mktime(23, 59, 59, date("m"), (date("d") - 1), date("Y"));
        if ($timestamp <= $prevdaytimestamp) {
            // Message
            $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.valid_timestamp')));

            return true;
        }

        // Message
        $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.error_timestamp_less_than_today')));

        return false;
    }

    /**
     * Function for executing the garbage collector
     *
     * @param  int  $tillDatetime unix timestamp
     * @return void
     */
    public function executeGC($tillDatetime, $runAnyway = false)
    {
        // Add error handler
        $this->getSyncInstance()->initializeSyncErrorContext($this, 'handleGCRuntimeErrors', 'handleFatalGCRuntimeError');
        $this->getSyncInstance()->setSyncRuntimeMaxExecTime();
        // table backup file time
        $this->backupTimestamp = $this->getSyncInstance()->getMicroTime();

        // Adding the process
        //	&&  Checking the soap client && check time should less then today
        if (($runAnyway || $this->isGCProcNotActive()) && $this->addGCProcess() && $this->checkGCSoapClient() && $this->checkValidTimestamp($tillDatetime)) {

            $this->updateGCProcess(array('gcproc_dest_sysid'=> $this->getGCSoapResultResponse('tx_st9fissync_service_handler','getSystemId')));

            // Message
            $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.started')));

            // Checking the 'tx_st9fissync_dbversioning_query' table size condition
            if ($this->getSyncInstance()->checkGCTableSize()) {

                if ($tillDatetime > 0) {
                    // Get the db versioning uids where (isynced=1 and issyncscheduled=1)
                    $this->processQuery_1_1($tillDatetime);

                    // Get the db versioning uids where (isynced=0 and issyncscheduled=0)
                    $this->processQuery_0_0($tillDatetime);

                    // Get the db versioning uids where (query_affectedrows = 0)
                    $this->processQuery_Affectedrows_0($tillDatetime);

                    // Get the db versioning uids where (query_error_number > 0)
                    $this->processQuery_Errornumber_0($tillDatetime);

                    // Get GC table ids
                    $this->processQuery_GCProcess($tillDatetime);

                    // take data backup
                    $tableBackupFlag = $this->doTableBackupForGCResult(array('timestamp'=>$tillDatetime));

                    // Delete data from tables
                    if ($tableBackupFlag) {
                        $this->deleteRecordsForGCResult(array('timestamp'=>$tillDatetime));
                    }
                } else {
                    // Message
                    $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.unspecified.timerange')));
                    $this->setGCStatus(tx_st9fissync_gc::GCSTATUS_FAILURE);
                }
            } else {
                // Message
                $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.table_size_limit')));

            }

            // Message
            $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.completed')));

            // Completing process
            $this->updateGCProcess(array('folderpath'=>$this->backupFolderPath,
                    'gc_stage'=>tx_st9fissync_gc::GCSTAGE_COMPLETED,
                    'gc_status'=> $this->gcStatus == tx_st9fissync_gc::GCSTATUS_UNKNOWN ?
                    $this->setGCStatus(tx_st9fissync_gc::GCSTATUS_SUCCESS) :
                    $this->gcStatus
            ));

        } else {
            // Message
            $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.aborted')));

            // Completing process
            $this->updateGCProcess(array(
                    'gc_stage'=> tx_st9fissync_gc::GCSTAGE_COMPLETED,
                    'gc_status'=> $this->setGCStatus(tx_st9fissync_gc::GCSTATUS_ABORT)));
        }

        // Sending GC Email
        $this->sendGCEmail();
        $this->getSyncInstance()->resetToOrigRuntimeMaxExecTime();
    }

    /**
     * Function for processing GC for already synced queries (isynced=1 and issyncscheduled=1)
     * @param  int  $timestamp
     * @return void
     */
    protected function processQuery_1_1($timestamp)
    {
        // Message
        $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.start_processquery_1_1')));

        if ($timestamp > 0) {
            // SYNC
            // Getting already/successfully synced Ids
            $arrAlreadySyncedUids = $this->getAlreadySyncedUIDs($timestamp);

            // Getting Synced request uids which can be deleted
            $arrSyncedRequestUidsCanBeDeleted = $this->getRequestUidsWhichCanBeDeleted($arrAlreadySyncedUids);

            // RE-SYNC
            // Getting successfully re-synced queries
            $arrAlreadyReSyncedUids = $this->getGCSoapResultResponse('tx_st9fissync_gc_handler', 'getSuccessfullySyncedUIDs', array('timestamp'=>$timestamp));

            // Getting request uids which can be deleted
            $arrReSyncedRequestUidsCanBeDeleted = $this->getRequestUidsWhichCanBeDeleted($arrAlreadyReSyncedUids, "1");

            // Final request ids which can be deleted
            $arrRequestUidsCanBeDeleted = array();
            if ($arrSyncedRequestUidsCanBeDeleted && is_array($arrSyncedRequestUidsCanBeDeleted) && count($arrSyncedRequestUidsCanBeDeleted) > 0) {
                $arrRequestUidsCanBeDeleted = $arrSyncedRequestUidsCanBeDeleted;
            }
            if ($arrReSyncedRequestUidsCanBeDeleted && is_array($arrReSyncedRequestUidsCanBeDeleted) && count($arrReSyncedRequestUidsCanBeDeleted) > 0) {
                $arrRequestUidsCanBeDeleted = array_merge($arrRequestUidsCanBeDeleted, $arrReSyncedRequestUidsCanBeDeleted);
            }

            // Getting the procids, remote_handle for request which can be deleted
            if ($arrRequestUidsCanBeDeleted && is_array($arrRequestUidsCanBeDeleted) && count($arrRequestUidsCanBeDeleted) > 0) {

                // request uids can be deleted
                $strRequestUidsCanBeDeleted = implode(",", $arrRequestUidsCanBeDeleted);

                // getting the procids and remote_handle from request details for request uids which can be deleted
                $arrProcAndRemoteHandleIds = $this->getProcAndRemoteHandleForCanBeDeletedRequestUIds($strRequestUidsCanBeDeleted);

                // Getting the process ids which can be deleted
                if ($arrProcAndRemoteHandleIds && is_array($arrProcAndRemoteHandleIds) && count($arrProcAndRemoteHandleIds) > 0) {

                    // checking if process can be deleted
                    $arrProcIdsCanBeDeleted = $this->getProcIdsWhichCanBeDeleted($arrProcAndRemoteHandleIds['procid'], $strRequestUidsCanBeDeleted);

                    // Message
                    $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.proc_ids_with_request_can_be_deleted') . (($arrProcIdsCanBeDeleted)?implode(",", $arrProcIdsCanBeDeleted):'') ));
                }
            }

            // Getting procids for which there is no request ids (procids generated when there is nothing to sync)
            $arrProcIdsWhereNoRequestIds = $this->getProcIdsWhereNoRequestIds($timestamp);

            // Merging procids
            if ($arrProcIdsWhereNoRequestIds && is_array($arrProcIdsWhereNoRequestIds) && count($arrProcIdsWhereNoRequestIds) > 0) {
                if ($arrProcIdsCanBeDeleted && is_array($arrProcIdsCanBeDeleted) && count($arrProcIdsCanBeDeleted) > 0) {
                    $arrProcIdsCanBeDeleted = array_merge($arrProcIdsCanBeDeleted, $arrProcIdsWhereNoRequestIds);
                } else {
                    $arrProcIdsCanBeDeleted = $arrProcIdsWhereNoRequestIds;
                }
                $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.proc_ids_without_request_can_be_deleted') . (($arrProcIdsWhereNoRequestIds)?implode(",", $arrProcIdsWhereNoRequestIds):'') ));
            }

            // Message
            $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.synced_ids_can_be_deleted') . (($arrAlreadySyncedUids)?implode(",", $arrAlreadySyncedUids):'') ));
            $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.resynced_ids_can_be_deleted') . (($arrAlreadyReSyncedUids)?implode(",", $arrAlreadyReSyncedUids):'') ));
            $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.synced_request_ids_can_be_deleted') . (($arrSyncedRequestUidsCanBeDeleted)?implode(",", $arrSyncedRequestUidsCanBeDeleted):'') ));
            $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.resynced_request_ids_can_be_deleted') . (($arrReSyncedRequestUidsCanBeDeleted)?implode(",", $arrReSyncedRequestUidsCanBeDeleted):'') ));
            $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.all_request_ids_can_be_deleted') . (($arrRequestUidsCanBeDeleted)?implode(",", $arrRequestUidsCanBeDeleted):'') ));
            $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.proc_ids_can_be_deleted') . (($arrProcIdsCanBeDeleted)?implode(",", $arrProcIdsCanBeDeleted):'') ));
            $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.remotehandler_ids_can_be_deleted') . (($arrProcAndRemoteHandleIds['remote_handle'])?implode(",", $arrProcAndRemoteHandleIds['remote_handle']):'')));

            // Setting values in global array
            $this->globalUidArray['syncedUidsBy_1_1'] = $arrAlreadySyncedUids;
            $this->globalUidArray['syncedUidsCanBeDeleted'] = $arrAlreadySyncedUids;
            $this->globalUidArray['alreadyReSyncedUidsCanBeDeleted'] = $arrAlreadyReSyncedUids;
            $this->globalUidArray['requestUidsCanBeDeleted'] = $arrRequestUidsCanBeDeleted;
            $this->globalUidArray['procIdsCanBeDeleted'] = $arrProcIdsCanBeDeleted;
            $this->globalUidArray['remoteHandlerUidsCanBeDeleted'] = $arrProcAndRemoteHandleIds['remote_handle'];
        }

        // Message
        $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.end_processquery_1_1')));
    }

    /**
     * Function for processing GC for queries (isynced=0 and issyncscheduled=0)
     * @param  int  $timestamp
     * @return void
     */
    protected function processQuery_0_0($timestamp)
    {
        // Message
        $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.start_processquery_0_0')));

        if ($timestamp > 0) {
            $whereclause = ' ( issynced = 0 AND issyncscheduled = 0 AND timestamp < ' . $timestamp .') ';
            $arrUids = $this->processQuery($whereclause);
            $this->globalUidArray['syncedUidsBy_0_0'] = $arrUids;
        }

        // Message
        $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.synced_ids_can_be_deleted') . (($arrUids)?implode(",", $arrUids):'')));
        $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.end_processquery_0_0')));
    }

    /**
     * Function for processing GC for queries (query_affectedrows=0)
     * @param  int  $timestamp
     * @return void
     */
    protected function processQuery_Affectedrows_0($timestamp)
    {
        // Message
        $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.start_processquery_affectedrows_0')));

        if ($timestamp > 0) {
            $whereclause = ' ( query_affectedrows = 0 AND timestamp < ' . $timestamp .') ';
            $arrUids = $this->processQuery($whereclause);
            $this->globalUidArray['affectedRowsUidsBy_0'] = $arrUids;
        }

        // Message
        $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.affectedrows_0_ids_can_be_deleted') . (($arrUids)?implode(",", $arrUids):'')));
        $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.end_processquery_affectedrows_0')));
    }

    /**
     * Function for processing GC for queries (query_error_number > 0)
     * @param  int  $timestamp
     * @return void
     */
    protected function processQuery_Errornumber_0($timestamp)
    {
        // Message
        $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.start_processquery_errornumber_0')));

        if ($timestamp > 0) {
            $whereclause = ' ( query_error_number > 0 AND timestamp < ' . $timestamp .') ';
            $arrUids = $this->processQuery($whereclause);
            $this->globalUidArray['errorNumberUidsBy_0'] = $arrUids;
        }

        // Message
        $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.errornumber_0_ids_can_be_deleted') . (($arrUids)?implode(",", $arrUids):'')));
        $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.end_processquery_errornumber_0')));
    }

    /**
     * Function for getting GC process ids based on timestamp
     * @param  int           $timestamp
     * @return array|boolean
     */
    protected function processQuery_GCProcess($timestamp)
    {
        // Message
        $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.start_processquery_gcprocess')));

        if ($timestamp > 0) {
            $arrGCProcUids = $this->getSyncDBOperationsManagerObject()->getGCProcIdsByTimestamp($timestamp);
            if ($arrGCProcUids && is_array($arrGCProcUids) && count($arrGCProcUids) > 0) {
                $arrGCProcUids = array_keys($arrGCProcUids);
                $this->globalUidArray['gcProcessUids'] = $arrGCProcUids;
            }
        }

        // Message
        $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.gcprocess_ids_can_be_deleted') . (($arrGCProcUids)?implode(",", $arrGCProcUids):'')));
        $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.end_processquery_gcprocess')));
    }

    /**
     * Function for processing queries
     * @param  string $whereclause
     * @return array
     */
    private function processQuery($whereclause)
    {
        $arrUids = array();

        if ($whereclause != '') {
            $arrDBVersionUids = $this->getSyncDBOperationsManagerObject()->getSyncedQueriesByWhereClause($whereclause);

            if ($arrDBVersionUids && is_array($arrDBVersionUids) && count($arrDBVersionUids) > 0) {

                // getting dbversion uids
                $arrUids = array_keys($arrDBVersionUids);

                if (isset($this->globalUidArray['syncedUidsCanBeDeleted']) && is_array($this->globalUidArray['syncedUidsCanBeDeleted']) && count($this->globalUidArray['syncedUidsCanBeDeleted']) > 0) {
                    $this->globalUidArray['syncedUidsCanBeDeleted'] = array_merge($this->globalUidArray['syncedUidsCanBeDeleted'], $arrUids);
                } else {
                    $this->globalUidArray['syncedUidsCanBeDeleted'] = $arrUids;
                }
            }
        }

        return $arrUids;
    }

    /**
     * Function for getting synced uids based on isynced=1 and issyncscheduled=1
     *
     * @param  int           $timestamp
     * @return boolean|array
     */
    public function getAlreadySyncedUIDs($timestamp)
    {
        $arrAlreadySyncedUids = false;

        if ($timestamp > 0) {
            // Getting already/successfully synced Ids
            $whereclause = ' ( issynced = 1 AND issyncscheduled = 1 AND timestamp < ' . $timestamp .') ';
            $arrAlreadySyncedUids = $this->getSyncDBOperationsManagerObject()->getSyncedQueriesByWhereClause($whereclause);

            if ($arrAlreadySyncedUids && is_array($arrAlreadySyncedUids) && count($arrAlreadySyncedUids) > 0) {
                // getting dbversion uids
                $arrAlreadySyncedUids = array_keys($arrAlreadySyncedUids);
            }
        }

        return $arrAlreadySyncedUids;
    }

    /**
     * Getting the UIDs of the 'tx_st9fissync_request' table wich can be deleted after successful sync
     * taking the queries into consideration for which error has occurred
     *
     * @param  array         $arrAlreadySyncedUids
     * @param  int           $isRemote
     * @return boolean|array
     */
    private function getRequestUidsWhichCanBeDeleted($arrAlreadySyncedUids, $isRemote=0)
    {
        $arrRequestUidsCanBeDeleted = false;

        $strSyncedUids = '';
        if ($arrAlreadySyncedUids && is_array($arrAlreadySyncedUids) && count($arrAlreadySyncedUids) > 0) {
            // successfully synced Ids
            $strSyncedUids = implode(",", $arrAlreadySyncedUids);
        }

        if (trim($strSyncedUids) != '') {
            // Getting distinct request local uids
            $arrRequestLocalUids = $this->getSyncDBOperationsManagerObject()->getLocalUIDsForSyncedRecordFromRequest($strSyncedUids, $isRemote);
            if ($arrRequestLocalUids && is_array($arrRequestLocalUids) && count($arrRequestLocalUids) > 0) {
                $arrRequestLocalUids = array_keys($arrRequestLocalUids);
                $strRequestLocalUids = implode(",", $arrRequestLocalUids);

                // Check if there is any record in 'tx_st9fissync_request_dbversioning_query_mm' table for request local uids,
                // then don't delete records for 'tx_st9fissync_request' table
                $arrRequestLocalUidsRequired = $this->getSyncDBOperationsManagerObject()->getLocalUIDsForNotSyncedRecordFromRequest($strSyncedUids, $strRequestLocalUids, $isRemote);
                if ($arrRequestLocalUidsRequired && is_array($arrRequestLocalUidsRequired) && count($arrRequestLocalUidsRequired) > 0) {
                    $arrRequestLocalUidsRequired = array_keys($arrRequestLocalUidsRequired);
                }

                // Checking the request uids which can be deleted
                if ($arrRequestLocalUidsRequired && is_array($arrRequestLocalUidsRequired) && count($arrRequestLocalUidsRequired) > 0) {
                    $arrRequestUidsCanBeDeleted = array_diff($arrRequestLocalUids, $arrRequestLocalUidsRequired);
                } else {
                    $arrRequestUidsCanBeDeleted = $arrRequestLocalUids; // as no request uids required found
                }
            }
        }

        return $arrRequestUidsCanBeDeleted;
    }

    /**
     * Function for getting the procid and remote handle for can be deleted requested ids
     *
     * @param  string        $strRequestUidsCanBeDeleted
     * @return boolean|array
     */
    private function getProcAndRemoteHandleForCanBeDeletedRequestUIds($strRequestUidsCanBeDeleted)
    {
        $arrProcAndRemoteHandleIds = false;
        $arrProcIds = $arrRemoteHandleIds = array();

        if (trim($strRequestUidsCanBeDeleted) != '') {
            $arrRequestDetails = $this->getSyncDBOperationsManagerObject()->getRequestDetailsBasedOnUids($strRequestUidsCanBeDeleted);
            if ($arrRequestDetails && is_array($arrRequestDetails) && count($arrRequestDetails) > 0) {
                foreach ($arrRequestDetails as $requestUid=>$arrRequestDetail) {
                    $procid = $arrRequestDetail['procid'];
                    $remoteHandle = $arrRequestDetail['remote_handle'];

                    if (!in_array($procid, $arrProcIds)) {
                        $arrProcIds[] = $procid;
                    }

                    $arrRemoteHandleIds[] = $remoteHandle;
                }

                $arrProcAndRemoteHandleIds['procid'] = $arrProcIds;
                $arrProcAndRemoteHandleIds['remote_handle'] = $arrRemoteHandleIds;
            }
        }

        return $arrProcAndRemoteHandleIds;
    }

    /**
     * Function for getting the process ids which can be deleted / process ids doesn't exists for other
     * request other than strRequestUidsCanBeDeleted
     *
     * @param array  $arrProcIds
     * @param string $strRequestUidsCanBeDeleted
     */
    private function getProcIdsWhichCanBeDeleted($arrProcIds, $strRequestUidsCanBeDeleted)
    {
        $arrProcIdsCanBeDeleted = false;
        $arrProcIdsCannotBeDeleted = array();

        // procids
        $strProcIds = implode(",", $arrProcIds);

        if (trim($strProcIds) != '' && trim($strRequestUidsCanBeDeleted) != '') {
            // getting procids which exists in db other then RequestUidsCanBeDeleted
            $arrRequestDetails = $this->getSyncDBOperationsManagerObject()->getProcIdsOtherThanRequestUids($strProcIds, $strRequestUidsCanBeDeleted);

            if ($arrRequestDetails && is_array($arrRequestDetails) && count($arrRequestDetails) > 0) {
                foreach ($arrRequestDetails as $requestUid=>$arrRequestDetail) {
                    $arrProcIdsCannotBeDeleted[] = $arrRequestDetail['procid'];
                }
            }

            // Checking the proc ids which can be deleted
            if ($arrProcIdsCannotBeDeleted && is_array($arrProcIdsCannotBeDeleted) && count($arrProcIdsCannotBeDeleted) > 0 && $arrProcIds && is_array($arrProcIds) && count($arrProcIds) > 0) {
                $arrProcIdsCanBeDeleted = array_diff($arrProcIds, $arrProcIdsCannotBeDeleted);

                // If both array $arrProcIds & $arrProcIdsCannotBeDeleted having same values
                if ($arrProcIdsCanBeDeleted && is_array($arrProcIdsCanBeDeleted) && count($arrProcIdsCanBeDeleted) <= 0) {
                    $arrProcIdsCanBeDeleted = false;
                }
            } else {
                $arrProcIdsCanBeDeleted = $arrProcIds; // as no proc ids found
            }
        }

        return $arrProcIdsCanBeDeleted;
    }

    /**
     * Function for getting all sync proc ids when there is no request ids
     * @param  int           $timestamp
     * @return boolean|array
     */
    private function getProcIdsWhereNoRequestIds($timestamp)
    {
        $arrProcIds = false;
        $strUids = 0;

        // Getting request table proc ids
        $arrRequestProcIds = $this->getSyncDBOperationsManagerObject()->getRequestProcIds();
        if ($arrRequestProcIds && is_array($arrRequestProcIds) && count($arrRequestProcIds) > 0) {
            $arrUids = array_keys($arrRequestProcIds);
            $strUids = implode(",", $arrUids);
        }

        // Getting proc ids not in request table
        $arrSyncProcDetails = $this->getSyncDBOperationsManagerObject()->getProcIdsWhereNoRequestIds($strUids, $timestamp);
        if ($arrSyncProcDetails && is_array($arrSyncProcDetails) && count($arrSyncProcDetails) > 0) {
            $arrProcIds = array_keys($arrSyncProcDetails);
        }

        return $arrProcIds;
    }

    /**
     * Function for taking table backup
     *
     * @param  array   $arrData [ timestamp ]
     * @return boolean
     */
    protected function doTableBackupForGCResult($arrData)
    {
        // Message
        $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.start_tablebackup')));

        // table backup flag
        $tableBackupFlag = true;

        // table backup folder path
        $this->backupFolderPath = $this->getSyncInstance()->makeFolderPathForTimeStamp($this->getSyncInstance()->getSyncConfigManager()->getGCBackupFolderRoot(), $this->backupTimestamp);

        // table backup param
        $arrparam = array();
        $arrparam['backuptime'] = $this->backupTimestamp;
        $arrparam['gcprocid'] = $this->gcProcId;

        // Can delete the records from 'tx_st9fissync_request_handler' via WS based on uids i.e. $this->globalUidArray['remoteHandlerUidsCanBeDeleted']
        // Doing table backup WS call from website
        if ($this->backupFolderPath) {

            $this->getSyncInstance()->addOkMessage(
                    $this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.backupfolder.exists') .
                            $this->backupFolderPath));

            $arrRemoteHandlerUidsCanBeDeleted = $this->globalUidArray['remoteHandlerUidsCanBeDeleted'];
            if ($tableBackupFlag && $arrRemoteHandlerUidsCanBeDeleted && is_array($arrRemoteHandlerUidsCanBeDeleted) && count($arrRemoteHandlerUidsCanBeDeleted) > 0) {

                // Breaking array into chunks
                $arrBackupUidChunks = array_chunk($arrRemoteHandlerUidsCanBeDeleted, $this->getSyncInstance()->getSyncConfigManager()->getGCTableRowNumPerFile());

                if ($arrBackupUidChunks && is_array($arrBackupUidChunks) && count($arrBackupUidChunks) > 0) {
                    foreach ($arrBackupUidChunks as $key=>$arrChunkUids) {

                        $remoteHandlerBackUpMessage = $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.tablename') . $this->getSyncDBOperationsManagerObject()->getSyncRequestHandlerTableName();
                        $remoteHandlerBackUpMessage .= ' / ' . $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.filenumber') . ($key + 1);
                        $remoteHandlerBackUpMessage .= ' / ' . $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup_status') . " ";

                        $strUids = implode(",", $arrChunkUids);
                        $whereClause = ' ( uid IN ('.$strUids.') OR (request_received_tstamp < '.$arrData['timestamp'].') ) ';

                        // table backup param
                        $arrparam['tablename'] = $this->getSyncDBOperationsManagerObject()->getSyncRequestHandlerTableName();
                        $arrparam['whereclause'] = $whereClause;
                        $arrparam['filenumber'] = ($key + 1);

                        // calling WS
                        $arrResult = $this->getGCSoapResultResponse('tx_st9fissync_gc_handler', 'takeTableBackup', $arrparam);
                        if ($arrResult) {
                            if (is_array($arrResult) && count($arrResult) > 0) {
                                $tableBackupFlag = $arrResult['status'];

                                if ($arrResult['backupFolderPath'] != '') {
                                    if ($this->remoteBackupFolderPath != $arrResult['backupFolderPath']) {
                                        $this->remoteBackupFolderPath = $arrResult['backupFolderPath'];
                                        $this->getSyncInstance()->addOkMessage(
                                                $this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.remote.backupfolder.exists') .
                                                        $this->remoteBackupFolderPath));
                                        $this->updateGCProcess(array('remotefolderpath'=> $this->remoteBackupFolderPath));
                                    }
                                } else {
                                    $unableToCreateRemoteBackUpFolder = $this->getSyncInstance()->getSyncLabels('stage.gc.proc.remote.backupfolder.doesnotexist');
                                    $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($unableToCreateRemoteBackUpFolder));
                                    $this->setGCStatus(tx_st9fissync_gc::GCSTATUS_FAILURE);
                                }

                                if (!$tableBackupFlag) {
                                    $remoteHandlerBackUpMessage .= $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup_status.fail');
                                    $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($remoteHandlerBackUpMessage));
                                    $this->setGCStatus(tx_st9fissync_gc::GCSTATUS_FAILURE);
                                    $remoteHandlerBackUpMessage .= ' / ' . $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.command') . $arrResult['cmd'];
                                    $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($remoteHandlerBackUpMessage));
                                    break; // exiting foreach loop if error occured while backup
                                }
                            }
                        } else {
                            $tableBackupFlag = false;
                        }

                        //Messages
                        if ($tableBackupFlag) {
                            $remoteHandlerBackUpMessage .= " ".$this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup_status.pass');
                            $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($remoteHandlerBackUpMessage));
                        } else {
                            $remoteHandlerBackUpMessage .= $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup_status.fail');
                            $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($remoteHandlerBackUpMessage));
                            $this->setGCStatus(tx_st9fissync_gc::GCSTATUS_FAILURE);
                        }
                        $remoteHandlerBackUpMessage .= ' / ' . $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.command') . $arrResult['cmd'];
                        $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($remoteHandlerBackUpMessage));
                    }
                } else {
                    $remoteHandlerBackUpMessage =  $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.tablename')  . $this->getSyncDBOperationsManagerObject()->getSyncRequestHandlerTableName();
                    $remoteHandlerBackUpMessage .=  " ".$this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.batch.null');
                    $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($remoteHandlerBackUpMessage));
                    $this->setGCStatus(tx_st9fissync_gc::GCSTATUS_FAILURE);
                }
            } else {
                $remoteHandlerBackUpMessage =  $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.tablename')  . $this->getSyncDBOperationsManagerObject()->getSyncRequestHandlerTableName();
                $remoteHandlerBackUpMessage .=  " ".$this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.nothingtoprocess');
                $this->getSyncInstance()->addInfoMessage($this->getGCMesgPrefixed($remoteHandlerBackUpMessage));
            }

            // Can delete the records from 'tx_st9fissync_dbversioning_query' via WS based on uids i.e. $this->globalUidArray['alreadyReSyncedUidsCanBeDeleted']
            // Doing table backup WS call from website
            if ($tableBackupFlag) {
                $tablename = $this->getSyncDBOperationsManagerObject()->getQueryVersioningTable();
                $tableBackupFlag = $this->takeBackup($this->globalUidArray['alreadyReSyncedUidsCanBeDeleted'], $tablename, $arrparam, 'uid', "1");
            }

            // Can delete the records from 'tx_st9fissync_dbversioning_query_tablerows_mm' via WS based on uids i.e. $this->globalUidArray['alreadyReSyncedUidsCanBeDeleted']
            // Doing table backup WS call from website
            if ($tableBackupFlag) {
                $tablename = $this->getSyncDBOperationsManagerObject()->getQueryVersioningRefRecordsTable();
                $tableBackupFlag = $this->takeBackup($this->globalUidArray['alreadyReSyncedUidsCanBeDeleted'], $tablename, $arrparam, 'uid_local', "1");
            }

            // Doing table backup, can delete the records from 'tx_st9fissync_dbversioning_query' (intranet) based on uids i.e. $this->globalUidArray['syncedUidsCanBeDeleted']
            if ($tableBackupFlag) {
                $tablename = $this->getSyncDBOperationsManagerObject()->getQueryVersioningTable();
                $tableBackupFlag = $this->takeBackup($this->globalUidArray['syncedUidsCanBeDeleted'], $tablename, $arrparam, 'uid');
            }

            // Doing table backup, can delete the records from 'tx_st9fissync_dbversioning_query_tablerows_mm' (intranet) based on uids i.e. $this->globalUidArray['syncedUidsCanBeDeleted']
            if ($tableBackupFlag) {
                $tablename = $this->getSyncDBOperationsManagerObject()->getQueryVersioningRefRecordsTable();
                $tableBackupFlag = $this->takeBackup($this->globalUidArray['syncedUidsCanBeDeleted'], $tablename, $arrparam, 'uid_local');
            }

            // Doing table backup, can delete the records from 'tx_st9fissync_request' (intranet) based on uids i.e. $this->globalUidArray['requestUidsCanBeDeleted']
            if ($tableBackupFlag) {
                $tablename = $this->getSyncDBOperationsManagerObject()->getSyncRequestTableName();
                $tableBackupFlag = $this->takeBackup($this->globalUidArray['requestUidsCanBeDeleted'], $tablename, $arrparam, 'uid');
            }

            // Doing table backup, can delete the records from 'tx_st9fissync_request' (intranet) based on uids i.e. $this->globalUidArray['requestUidsCanBeDeleted']
            if ($tableBackupFlag) {
                $tablename = $this->getSyncDBOperationsManagerObject()->getSyncReqRefQVRecTableName();
                $tableBackupFlag = $this->takeBackup($this->globalUidArray['requestUidsCanBeDeleted'], $tablename, $arrparam, 'uid_local');
            }

            // Doing table backup, can delete the records from 'tx_st9fissync_process' (intranet) based on uids i.e. $this->globalUidArray['procIdsCanBeDeleted']
            if ($tableBackupFlag) {
                $tablename = $this->getSyncDBOperationsManagerObject()->getSyncProcTableName();
                $tableBackupFlag = $this->takeBackup($this->globalUidArray['procIdsCanBeDeleted'], $tablename, $arrparam, 'uid');
            }

            // Doing table backup, can delete the records from 'tx_st9fissync_log' (intranet) based on uids i.e. $this->globalUidArray['procIdsCanBeDeleted']
            if ($tableBackupFlag) {
                $tablename = $this->getSyncDBOperationsManagerObject()->getLogTable();
                $tableBackupFlag = $this->takeBackup($this->globalUidArray['procIdsCanBeDeleted'], $tablename, $arrparam, 'procid');
            }

            // Doing table backup, can delete the records from 'tx_st9fissync_gc_log' (intranet) based on uids
            if ($tableBackupFlag) {
                $tablename = $this->getSyncDBOperationsManagerObject()->getGCLogTable();
                $tableBackupFlag = $this->takeBackup($this->globalUidArray['gcProcessUids'], $tablename, $arrparam, 'procid');
            }

            // Doing table backup, can delete the records from 'tx_st9fissync_gc_process' (intranet) based on timestamp
            if ($tableBackupFlag) {
                $tablename = $this->getSyncDBOperationsManagerObject()->getGCProcessTable();
                $whereClause = ' ( crdate < '.$arrData['timestamp'].' ) ';

                // table backup param
                $arrparam['tablename'] = $tablename;
                $arrparam['whereclause'] = $whereClause;

                $arrResult = $this->getSyncInstance()->doTableBackup($arrparam);
                if ($arrResult) {
                    if (is_array($arrResult) && count($arrResult) > 0) {
                        $tableBackupFlag = $arrResult['status'];
                    }
                } else {
                    $tableBackupFlag = false;
                }

                //Messages
                if ($tableBackupFlag) {
                    $remoteHandlerBackUpMessage = $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.tablename') ." ". $arrparam['tablename']." ".$this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup_status.pass');
                    $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($remoteHandlerBackUpMessage));
                } else {
                    $remoteHandlerBackUpMessage = $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.tablename') . " ". $arrparam['tablename'] ." ". $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup_status.fail');
                    $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($remoteHandlerBackUpMessage));
                    $this->setGCStatus(tx_st9fissync_gc::GCSTATUS_FAILURE);
                }
                $remoteHandlerBackUpMessage .= ' / ' . $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.command') . $arrResult['cmd'];
                $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($remoteHandlerBackUpMessage));
            }
        } else {
            $unableToCreateBackUpFolder = $this->getSyncInstance()->getSyncLabels('stage.gc.proc.backupfolder.doesnotexist');
            $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($unableToCreateBackUpFolder));
            $this->setGCStatus(tx_st9fissync_gc::GCSTATUS_FAILURE);
        }

        // Message
        if ($tableBackupFlag) {
            $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.success_gc_table_backup')));
        } else {
            $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.err_gc_table_backup')));
        }
        $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.end_tablebackup')));

        return $tableBackupFlag;
    }

    /**
     * Function for taking backup
     * @param  array   $arrData
     * @param  string  $tablename
     * @param  array   $arrparam
     * @param  string  $whereClauseFieldname
     * @param  int     $ws
     * @return boolean
     */
    private function takeBackup($arrData, $tablename, $arrparam, $whereClauseFieldname, $ws=0)
    {
        $tableBackupFlag = true;

        // Doing table backup, can delete the records from table (intranet) based on uids
        if ($arrData && is_array($arrData) && count($arrData) > 0) {

            // Breaking array into chunks
            $arrBackupUidChunks = array_chunk($arrData, $this->getSyncInstance()->getSyncConfigManager()->getGCTableRowNumPerFile());

            if ($arrBackupUidChunks && is_array($arrBackupUidChunks) && count($arrBackupUidChunks) > 0) {
                foreach ($arrBackupUidChunks as $key=>$arrChunkUids) {

                    $remoteHandlerBackUpMessage = $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.tablename') . $tablename;
                    $remoteHandlerBackUpMessage .= ' / ' . $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.filenumber') . ($key + 1);
                    $remoteHandlerBackUpMessage .= ' / ' . $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup_status'). " ";

                    $strUids = implode(",", $arrChunkUids);
                    $whereClause = ' ( '.$whereClauseFieldname.' IN ('.$strUids.') ) ';

                    // table backup param
                    $arrparam['tablename'] = $tablename;
                    $arrparam['whereclause'] = $whereClause;
                    $arrparam['filenumber'] = ($key + 1);

                    if ($ws > 0) {
                        $arrResult = $this->getGCSoapResultResponse('tx_st9fissync_gc_handler', 'takeTableBackup', $arrparam);
                    } else {
                        $arrResult = $this->getSyncInstance()->doTableBackup($arrparam);
                    }

                    if ($arrResult) {
                        if (is_array($arrResult) && count($arrResult) > 0) {
                            $tableBackupFlag = $arrResult['status'];
                            if (!$tableBackupFlag) {
                                $remoteHandlerBackUpMessage .= $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup_status.fail');
                                $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($remoteHandlerBackUpMessage));
                                $this->setGCStatus(tx_st9fissync_gc::GCSTATUS_FAILURE);
                                $remoteHandlerBackUpMessage .= ' / ' . $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.command') . $arrResult['cmd'];
                                $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($remoteHandlerBackUpMessage));
                                break; // exiting foreach loop if error occured while backup
                            }
                        }
                    } else {
                        $tableBackupFlag = false;
                    }

                    //Messages
                    if ($tableBackupFlag) {
                        $remoteHandlerBackUpMessage .= $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup_status.pass');
                        $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($remoteHandlerBackUpMessage));
                    } else {
                        $remoteHandlerBackUpMessage .= $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup_status.fail');
                        $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($remoteHandlerBackUpMessage));
                        $this->setGCStatus(tx_st9fissync_gc::GCSTATUS_FAILURE);
                    }
                    $remoteHandlerBackUpMessage .= ' / ' . $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.command') . $arrResult['cmd'];
                    $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($remoteHandlerBackUpMessage));
                }
            } else {
                $remoteHandlerBackUpMessage =  $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.tablename')  . $tablename;
                $remoteHandlerBackUpMessage .=  $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.batch.null');
                $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($remoteHandlerBackUpMessage));
                $this->setGCStatus(tx_st9fissync_gc::GCSTATUS_FAILURE);
            }
        } else {
            $remoteHandlerBackUpMessage =  $this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.tablename')  . $tablename;
            $remoteHandlerBackUpMessage .=  " ".$this->getSyncInstance()->getSyncLabels('stage.gc.proc.tablebackup.nothingtoprocess');
            $this->getSyncInstance()->addInfoMessage($this->getGCMesgPrefixed($remoteHandlerBackUpMessage));
        }

        return $tableBackupFlag;
    }

    /**
     * Function for taking table backup
     *
     * @param  array   $arrData [ timestamp ]
     * @return boolean
     */
    protected function deleteRecordsForGCResult($arrData)
    {
        // record delete flag
        $deleteRecordFlag = true;

        // Message
        $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.start_deleteprocess')));

        // Can delete the records from 'tx_st9fissync_request_handler' via WS based on uids i.e. $this->globalUidArray['remoteHandlerUidsCanBeDeleted']
        // Doing table backup WS call from website
        $arrRemoteHandlerUidsCanBeDeleted = $this->globalUidArray['remoteHandlerUidsCanBeDeleted'];
        if ($deleteRecordFlag && $arrRemoteHandlerUidsCanBeDeleted && is_array($arrRemoteHandlerUidsCanBeDeleted) && count($arrRemoteHandlerUidsCanBeDeleted) > 0) {

            $strUids = implode(",", $arrRemoteHandlerUidsCanBeDeleted);
            $whereClause = ' ( uid IN ('.$strUids.') OR (request_received_tstamp < '.$arrData['timestamp'].') ) ';

            // table backup param
            $arrparam['tablename'] = $this->getSyncDBOperationsManagerObject()->getSyncRequestHandlerTableName();
            $arrparam['whereclause'] = $whereClause;

            // calling WS
            $arrResponse = $this->getGCSoapResultResponse('tx_st9fissync_gc_handler', 'deleteRecordsFromTable', $arrparam);
            $arrDeleteRecords = $arrResponse['deleteRecords'];
            $deleteRecordFlag = $arrDeleteRecords['deleteQueries'];
            $affectedRows = $arrDeleteRecords['affectedRows'];

            // Message
            $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.delete_query_affected_rows') . $arrparam['tablename'] . ": ".$affectedRows));

            if (!$deleteRecordFlag) {
                // Message
                $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.deleteprocess_error') .$arrparam['tablename']));
                $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.deleteprocess_error') .$arrparam['tablename'] . " - " . $this->getSyncInstance()->getSyncLabels('stage.gc.proc.deleteprocess_whereclause') . $arrparam['whereclause']));
            } else {
                // Message
                $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.deleteprocess_success') .$arrparam['tablename']));
                $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.deleteprocess_success') .$arrparam['tablename'] . " - " . $this->getSyncInstance()->getSyncLabels('stage.gc.proc.deleteprocess_whereclause') . $arrparam['whereclause']));
            }
        }

        // Delete the records from 'tx_st9fissync_request_dbversioning_query_mm' (intranet) based on uids i.e. $this->globalUidArray['requestUidsCanBeDeleted']
        if ($deleteRecordFlag && $this->globalUidArray['requestUidsCanBeDeleted'] && is_array($this->globalUidArray['requestUidsCanBeDeleted']) && count($this->globalUidArray['requestUidsCanBeDeleted']) > 0) {
            $tablename = $this->getSyncDBOperationsManagerObject()->getSyncReqRefQVRecTableName();
            $deleteRecordFlag = $this->deleteRecords($this->globalUidArray['requestUidsCanBeDeleted'], $tablename, 'uid_local');
        }

        // Delete the records from 'tx_st9fissync_request' (intranet) based on uids i.e. $this->globalUidArray['requestUidsCanBeDeleted']
        if ($deleteRecordFlag && $this->globalUidArray['requestUidsCanBeDeleted'] && is_array($this->globalUidArray['requestUidsCanBeDeleted']) && count($this->globalUidArray['requestUidsCanBeDeleted']) > 0) {
            $tablename = $this->getSyncDBOperationsManagerObject()->getSyncRequestTableName();
            $deleteRecordFlag = $this->deleteRecords($this->globalUidArray['requestUidsCanBeDeleted'], $tablename, 'uid');
        }

        // Delete the records from 'tx_st9fissync_log' (intranet) based on uids i.e. $this->globalUidArray['procIdsCanBeDeleted']
        if ($deleteRecordFlag && $this->globalUidArray['procIdsCanBeDeleted'] && is_array($this->globalUidArray['procIdsCanBeDeleted']) && count($this->globalUidArray['procIdsCanBeDeleted']) > 0) {
            $tablename = $this->getSyncDBOperationsManagerObject()->getLogTable();
            $deleteRecordFlag = $this->deleteRecords($this->globalUidArray['procIdsCanBeDeleted'], $tablename, 'procid');
        }

        // Delete the records from 'tx_st9fissync_process' (intranet) based on uids i.e. $this->globalUidArray['procIdsCanBeDeleted']
        if ($deleteRecordFlag && $this->globalUidArray['procIdsCanBeDeleted'] && is_array($this->globalUidArray['procIdsCanBeDeleted']) && count($this->globalUidArray['procIdsCanBeDeleted']) > 0) {
            $tablename = $this->getSyncDBOperationsManagerObject()->getSyncProcTableName();
            $deleteRecordFlag = $this->deleteRecords($this->globalUidArray['procIdsCanBeDeleted'], $tablename, 'uid');
        }

        // Delete the records from 'tx_st9fissync_dbversioning_query_tablerows_mm' via WS based on uids i.e. $this->globalUidArray['alreadyReSyncedUidsCanBeDeleted']
        if ($deleteRecordFlag && $this->globalUidArray['alreadyReSyncedUidsCanBeDeleted'] && is_array($this->globalUidArray['alreadyReSyncedUidsCanBeDeleted']) && count($this->globalUidArray['alreadyReSyncedUidsCanBeDeleted']) > 0) {
            $tablename = $this->getSyncDBOperationsManagerObject()->getQueryVersioningRefRecordsTable();
            $deleteRecordFlag = $this->deleteRecords($this->globalUidArray['alreadyReSyncedUidsCanBeDeleted'], $tablename, 'uid_local', "1");
        }

        // Delete the records from 'tx_st9fissync_dbversioning_query' via WS based on uids i.e. $this->globalUidArray['alreadyReSyncedUidsCanBeDeleted']
        if ($deleteRecordFlag && $this->globalUidArray['alreadyReSyncedUidsCanBeDeleted'] && is_array($this->globalUidArray['alreadyReSyncedUidsCanBeDeleted']) && count($this->globalUidArray['alreadyReSyncedUidsCanBeDeleted']) > 0) {
            $tablename = $this->getSyncDBOperationsManagerObject()->getQueryVersioningTable();
            $deleteRecordFlag = $this->deleteRecords($this->globalUidArray['alreadyReSyncedUidsCanBeDeleted'], $tablename, 'uid', "1");
        }

        // Delete the records from 'tx_st9fissync_dbversioning_query_tablerows_mm' (intranet) based on uids i.e. $this->globalUidArray['syncedUidsCanBeDeleted']
        if ($deleteRecordFlag && $this->globalUidArray['syncedUidsCanBeDeleted'] && is_array($this->globalUidArray['syncedUidsCanBeDeleted']) && count($this->globalUidArray['syncedUidsCanBeDeleted']) > 0) {
            $tablename = $this->getSyncDBOperationsManagerObject()->getQueryVersioningRefRecordsTable();
            $deleteRecordFlag = $this->deleteRecords($this->globalUidArray['syncedUidsCanBeDeleted'], $tablename, 'uid_local');
        }

        // Delete the records from 'tx_st9fissync_dbversioning_query' (intranet) based on uids i.e. $this->globalUidArray['syncedUidsCanBeDeleted']
        if ($deleteRecordFlag && $this->globalUidArray['syncedUidsCanBeDeleted'] && is_array($this->globalUidArray['syncedUidsCanBeDeleted']) && count($this->globalUidArray['syncedUidsCanBeDeleted']) > 0) {
            $tablename = $this->getSyncDBOperationsManagerObject()->getQueryVersioningTable();
            $deleteRecordFlag = $this->deleteRecords($this->globalUidArray['syncedUidsCanBeDeleted'], $tablename , 'uid');
        }

        // Delete the records from 'tx_st9fissync_gc_log' (intranet) based on uids i.e. $this->globalUidArray['gcProcessUids']
        if ($deleteRecordFlag && $this->globalUidArray['gcProcessUids'] && is_array($this->globalUidArray['gcProcessUids']) && count($this->globalUidArray['gcProcessUids']) > 0) {
            $tablename = $this->getSyncDBOperationsManagerObject()->getGCLogTable();
            $deleteRecordFlag = $this->deleteRecords($this->globalUidArray['gcProcessUids'], $tablename , 'procid');
        }

        // Delete the records from 'tx_st9fissync_gc_process' (intranet) based on uids i.e. $this->globalUidArray['gcProcessUids']
        if ($deleteRecordFlag && $this->globalUidArray['gcProcessUids'] && is_array($this->globalUidArray['gcProcessUids']) && count($this->globalUidArray['gcProcessUids']) > 0) {
            $tablename = $this->getSyncDBOperationsManagerObject()->getGCProcessTable();
            $deleteRecordFlag = $this->deleteRecords($this->globalUidArray['gcProcessUids'], $tablename , 'uid');
        }

        // Message
        $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.end_deleteprocess')));
    }

    /**
     * Function for taking backup
     * @param  array   $arrData
     * @param  string  $tablename
     * @param  string  $whereClauseFieldname
     * @param  int     $ws
     * @return boolean
     */
    private function deleteRecords($arrData, $tablename, $whereClauseFieldname, $ws=0)
    {
        $deleteRecordFlag = true;

        // Doing table backup, can delete the records from table (intranet) based on uids
        if ($arrData && is_array($arrData) && count($arrData) > 0) {

            $strUids = implode(",", $arrData);
            $whereClause = ' ( '.$whereClauseFieldname.' IN ('.$strUids.') ) ';

            if ($ws > 0) {
                $arrResponse = $this->getGCSoapResultResponse('tx_st9fissync_gc_handler', 'deleteRecordsFromTable', array('tablename'=>$tablename,'whereclause'=>$whereClause));
                $arrDeleteRecords = $arrResponse['deleteRecords'];
                $deleteRecordFlag = $arrDeleteRecords['deleteQueries'];
                $affectedRows = $arrDeleteRecords['affectedRows'];
            } else {
                $arrDeleteRecords = $this->getSyncInstance()->deleteRecordsFromTable($tablename, $whereClause);
                $deleteRecordFlag = $arrDeleteRecords['deleteQueries'];
                $affectedRows = $arrDeleteRecords['affectedRows'];
            }

            // Message
            $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.delete_query_affected_rows') . $tablename . ": ".$affectedRows));
        }

        // Message
        if (!$deleteRecordFlag) {
            $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.deleteprocess_error') .$tablename));
            $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.deleteprocess_error') .$tablename." - " . $this->getSyncInstance()->getSyncLabels('stage.gc.proc.deleteprocess_whereclause') . $whereClause));
        } else {
            $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.deleteprocess_success') .$tablename));
            $this->getSyncInstance()->addDebugMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.deleteprocess_success') .$tablename." - " . $this->getSyncInstance()->getSyncLabels('stage.gc.proc.deleteprocess_whereclause') . $whereClause));
        }

        return $deleteRecordFlag;
    }

    /**
     * Prefix a message string with the current process identifiers and stage details
     *
     * @param  string $message
     * @return string
     */
    public function getGCMesgPrefixed($message)
    {
        $prefixedMessage = $this->getSyncInstance()->getSyncLabels('stage.gc.proc.processid'). ' [' . $this->gcProcId . ']- ' . $message;

        return $prefixedMessage;
    }

    /**
     * Function for adding GC process
     * @return boolean
     */
    public function addGCProcess()
    {
        // GC process data
        $arrData = array();
        $arrData['pid'] = $this->getSyncInstance()->getVersioningStoragePid();
        $arrData['crdate'] = $this->backupTimestamp;
        $arrData['gcproc_src_sysid'] = $this->getSyncInstance()->getSyncConfigManager()->getSyncSystemId();
        $arrData['gc_status'] = $this->setGCStatus(tx_st9fissync_gc::GCSTATUS_UNKNOWN);
        $arrData['gc_stage'] = tx_st9fissync_gc::GCSTAGE_EXECUTING;

        // adding the GC process
        if ($this->getSyncDBOperationsManagerObject()->addGCProcess($arrData)) {
            $this->gcProcId = $this->getDBObject()->sql_insert_id();

            // Message
            $this->getSyncInstance()->addOkMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.id') . $this->gcProcId));

            return true;
        }

        return false;
    }

    /**
     * Function for updating GC process
     * @param $arrData
     * @return boolean
     */
    public function updateGCProcess($arrData)
    {
        if (is_array($arrData) && count($arrData) > 0 && $this->gcProcId > 0) {
            $whereclause = " uid = ".$this->gcProcId;
            $arrData['timestamp'] = $this->getSyncInstance()->getMicroTime();
            if ($this->getSyncDBOperationsManagerObject()->updateGCProcess($arrData, $whereclause)) {
                // Message
                $this->getSyncInstance()->addInfoMessage($this->getGCMesgPrefixed($this->getSyncInstance()->getSyncLabels('stage.gc.proc.updated') . $this->gcProcId));

                return true;
            }
        }

        return false;
    }

    /**
     * Error handler for the GC process
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param int    $errline
     * @param array  $errcontext
     *
     * @return void
     */
    public function handleGCRuntimeErrors($errno, $errstr, $errfile = null, $errline = null, array $errcontext = null)
    {
        $gcRunTimeException = $this->getSyncInstance()->handlePhpErrors($errno, $errstr, $errfile, $errline, $errcontext);
        $gcRunTimeException = $this->getSyncInstance()->getSyncLabels('stage.gc.proc.error') . '[' . $gcRunTimeException->getMessage() . ']';
        $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($gcRunTimeException));
        $this->setGCStatus(tx_st9fissync_gc::GCSTATUS_FAILURE);
    }

    /**
     * GC shutdown function
     */
    public function handleFatalGCRuntimeError()
    {
        $gcFatalException = $this->getSyncInstance()->handleFatalError();

        //	Exception
        if ($gcFatalException instanceof tx_st9fissync_exception) {

            $gcFatalExceptionMesg = $this->getSyncInstance()->getSyncLabels('stage.gc.proc.fatalerror') . '[' . $gcFatalException->getMessage() . ']';
            $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($gcFatalExceptionMesg));

            // Setting the GC status
            $this->setGCStatus(tx_st9fissync_gc::GCSTATUS_FATAL);

            // Updating GC process status and stage
            $this->updateGCProcess(array(
                    'gc_stage'=> tx_st9fissync_gc::GCSTAGE_COMPLETED,
                    'gc_status'=> $this->gcStatus
            ));

            $this->sendGCEmail();

            $this->getSyncInstance()->resetToOrigRuntimeMaxExecTime();
            $this->getSyncInstance()->resetErrorContext();
        }
    }

    /**
     * Function for setting and checking the SOAP client for GC
     *
     * @return boolean
     */
    public function checkGCSoapClient()
    {
        $allowed = false;

        try {
            $this->gcSOAPClient = $this->getSyncInstance()->getSyncSOAPClient();
            if ($this->gcSOAPClient == null) {
                throw new tx_st9fissync_exception($this->getSyncInstance()->getSyncLabels('stage.gc.proc.soap.client.initfail'));
            }

            if ($this->getSyncInstance()->isSOAPSecured($this->gcSOAPClient->isSOAPSecure())) {
                //refresh and get a secured SOAP channel
                $this->gcSOAPClient = $this->getSyncInstance()->getSyncSOAPClient(true);
                $allowed = true;
            } else {
                unset($this->gcSOAPClient);

                $channelNotSecureMessage = $this->getSyncInstance()->getSyncLabels('stage.gc.proc.soap.channel.notsecured');
                $channelNotSecureMessage .= $this->getSyncInstance()->getSyncLabels('stage.gc.proc.caretakerinstance.misconfig');
                $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($channelNotSecureMessage));

                $allowed = false;
            }
        } catch (SoapFault $s) {

            $errorMesg = $this->getSyncInstance()->getSyncLabels('stage.gc.proc.eligibility.error') . '[' . $s->faultcode . '] ' . $s->faultstring . '/';
            $errorMesg .=  $this->getSyncInstance()->getSyncLabels('stage.gc.proc.id') . '[' .$this->gcProcId . ']';
            $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($errorMesg));

            $allowed = false;
        } catch (tx_st9fissync_exception $e) {

            $errorMesg = $this->getSyncInstance()->getSyncLabels('stage.gc.proc.eligibility.error') . $e->getMessage();
            $errorMesg .=  $this->getSyncInstance()->getSyncLabels('stage.gc.proc.id') . '[' .$this->gcProcId . ']';
            $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($errorMesg));

            $allowed = false;
        }

        return $allowed;
    }

    /**
     *
     * Gets results from SOAP service providers
     *
     * @param string $serviceProvider
     * @param string $serviceName
     * @param mixed  $requestArtifact
     *
     * @return mixed
     */
    public function getGCSoapResultResponse($serviceProvider, $serviceName, $requestArgs = null)
    {
        $soapResult = null;

        try {
            if ($this->gcSoapSessionId == null) {
                //initialize gc soap session
                $this->gcSoapSessionId = $this->gcSOAPClient->login($this->getSyncInstance()->getSyncConfigManager()->getRemoteSyncBEUser(), $this->getSyncInstance()->getSyncConfigManager()->getRemoteSyncBEPassword());
            }

            if ($this->gcSoapSessionId) {
                //gets overwritten with each request
                $soapResult = $this->gcSOAPClient->call($this->gcSoapSessionId, $serviceProvider, $serviceName, $requestArgs);
            }
        } catch (SoapFault $s) {
            $gcProcErrorMesg = 'GC SOAP-fault: [' . $s->faultcode . '] ' . $s->faultstring;
            $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($gcProcErrorMesg));
            $this->setGCStatus(tx_st9fissync_gc::GCSTATUS_FAILURE);
        } catch (tx_st9fissync_exception $e) {
            $gcProcErrorMesg = $e->getMessage();
            $this->getSyncInstance()->addErrorMessage($this->getGCMesgPrefixed($gcProcErrorMesg));
            $this->setGCStatus(tx_st9fissync_gc::GCSTATUS_FAILURE);
        }

        if ($this->gcStatus != tx_st9fissync_gc::GCSTATUS_UNKNOWN) {
            $this->updateGCProcess(array('gc_status'=> $this->gcStatus));
        }

        return $soapResult['responseRes'];
    }

    /**
     * Function for sending GC email
     */
    protected function sendGCEmail()
    {
        $messages = trim(tx_st9fissync_messagequeue::renderFlashMessages());

        $arrData = array();
        $arrData['message'] = $messages;
        $arrData['templatefile'] = 'gc_email.html';
        $arrData['subject'] = $this->getSyncInstance()->getSyncLabels('stage.gc.proc.email_subject');
        $arrData['senderEmail'] = $this->getSyncInstance()->getSyncConfigManager()->getSyncSenderEMail();
        $arrData['recipients'] = $this->getSyncInstance()->getSyncConfigManager()->getSyncNotificationEMail();

        $this->getSyncInstance()->sendEmail($arrData);
    }
}

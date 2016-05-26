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
 * The real Sync process
 *
 * @author <info@studioneun.de>
 * @package TYPO3
* @subpackage st9fissync
*
*/

class tx_st9fissync_sync
{
    const STATUS_UNKNOWN = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_ABORT = 2; // not due to sync itself
    const STATUS_FAILURE = 3; // failure due to sync itself
    const STATUS_FATAL = 4;

    const STAGE_INIT = 0; // still not launched
    const STAGE_RUNNING = 1;
    const STAGE_FINISHED = 2;

    const ESCALATION_NONE 	= 0; //None
    const ESCALATION_CRITICAL 	= 1;	// Most critical immediate notification
    const ESCALATION_HIGH 		= 2;	// ...

    public $syncAPI;

    private $procId = null;

    private $syncProcObject = null;

    private $syncProcNumOfReq = 0;

    private $syncProcRemoteReqHandlerId = 0;

    private $syncSessionId = null;

    private $SOAPClient = null;

    private $clearRemoteCache = false;

    public function __construct()
    {
        //add process info to db
        //syncproc_stage - 0
        //syncproc_status - 0
        //requests - 0
        $this->syncAPI = tx_st9fissync::getInstance();
        $this->syncAPI->setStageSync();

        $this->syncProcObject = $this->syncAPI->buildSyncProcessArtifact();
        if ($this->syncAPI->getSyncDBOperationsManager()->addSyncProcessInfo($this->syncProcObject)) {
            $this->procId = $this->syncAPI->getSyncDBObject()->sql_insert_id();
        }

    }

    /**
     *
     * Sync process id
     * @return int
     */
    public function getProcId()
    {
        return $this->procId;
    }

    /**
     * Update Sync Process state and persist to DB if $persist true
     *
     * @param array   $procAttributesArray
     * @param boolean $refresh
     * @param boolean $persist
     *
     * @return void
     */
    public function updateSyncProcState($procAttributesArray = null, $refresh = true, $persist = true)
    {
        //update process object for UPDATES, refresh
        //dummy values set for proper update of object
        $this->syncProcObject->set('syncproc_starttime','');
        $this->syncProcObject->set('client_ip','');
        $this->syncProcObject = $this->syncAPI->buildSyncProcessArtifact($this->syncProcObject,$refresh);

        foreach ($procAttributesArray as $attribKey => $attribVal) {
            $this->syncProcObject->set($attribKey,$attribVal);
        }

        if ($persist) {
            //update this to DB
            $condition = $this->syncAPI->getSyncDBOperationsManager()->getSyncProcTableName() . '.uid = ' . $this->procId;
            $this->syncAPI->getSyncDBOperationsManager()->updateSyncProcessInfo($this->syncProcObject, $condition);
        }

    }

    /**
     * Build and pre form a request object before the actual request
     *
     * @return tx_st9fissync_request
     */
    public function requestDetailsPreProcess()
    {
        $requestDetailsArtifact = $this->syncAPI->buildRequestDetailsArtifact();
        $requestDetailsArtifact->set('procid',$this->procId);

        return $requestDetailsArtifact;
    }

    /**
     *
     * Post process of a $requestDetailsArtifact object and add to DB
     *
     * @param tx_st9fissync_request
     *
     * @return int|boolean
     */
    public function requestDetailsPostProcess(tx_st9fissync_request $requestDetailsArtifact, $requestArgs, $postProcessCallBackMethod = null)
    {
        $requestDetailsArtifact->set('response_received_tstamp',  $this->syncAPI->getMicroTime());
        $requestDetailsArtifact->set('request_sent',  $this->SOAPClient->getLastRequest());
        $requestDetailsArtifact->set('response_received',  $this->SOAPClient->getLastResponse());
        $requestDetailsArtifact->set('remote_handle' , $this->syncAPI->getSyncResultResponseDTO()->getSyncLastRequestHandler());

        if (!is_null($postProcessCallBackMethod) && method_exists($this, $postProcessCallBackMethod)) {

            $requestDetailsArtifact = call_user_func_array(array($this, $postProcessCallBackMethod), array($requestDetailsArtifact, $requestArgs));

            if (!$requestDetailsArtifact) {
                $failedReqPostProcMesg = $this->syncAPI->getSyncLabels('stage.sync.request.postproc.fail');
                $failedReqPostProcMesg .= $this->syncAPI->getSyncLabels('stage.sync.request.postproc.callbackmethod') . ' [' . $postProcessCallBackMethod . '] / ';
                $failedReqPostProcMesg .= $this->syncAPI->getSyncLabels('stage.sync.request.postproc.callbackmethod.class') . ' [' . get_class($this) . ']';
                $this->handleSyncError($failedReqPostProcMesg,tx_st9fissync_sync::STATUS_FAILURE);
            }
        }

        return $this->syncAPI->getSyncDBOperationsManager()->addSyncRequestDetails($requestDetailsArtifact);
    }

    /**
     * Prefix a message string with the current Sync process identifiers and stage details
     *
     * @param  string $message
     * @return string
     */
    public function getSyncMesgPrefixed($message)
    {
        $prefixedMessage = $this->syncAPI->getSyncModeAsLabel() . 'Process Id: [' . $this->procId . ']- ' . $message;

        return $prefixedMessage;
    }

    /**
     *
     * A central function to handle all errors
     *
     * @param string $error
     * @param int    $syncprocStatus
     * @param int    $syncProcStage
     */
    public function handleSyncError($error, $syncprocStatus = null, $syncProcStage = null)
    {
        $this->syncAPI->addErrorMessage($this->getSyncMesgPrefixed($error));

        $updatedProcInfo = array(
                'requests' => $this->syncProcNumOfReq,
        );

        if (!is_null($syncprocStatus) && in_array($syncprocStatus, array(tx_st9fissync_sync::STATUS_UNKNOWN, tx_st9fissync_sync::STATUS_SUCCESS, tx_st9fissync_sync::STATUS_ABORT, tx_st9fissync_sync::STATUS_FAILURE))) {
            $updatedProcInfo['syncproc_status'] = $syncprocStatus;
        }

        if (!is_null($syncProcStage) && in_array($syncProcStage, array(tx_st9fissync_sync::STAGE_FINISHED, tx_st9fissync_sync::STAGE_INIT, tx_st9fissync_sync::STAGE_RUNNING))) {
            $updatedProcInfo['syncproc_stage'] = $syncProcStage;
        }

        $this->updateSyncProcState($updatedProcInfo);
    }

    private function _getSyncSessionId()
    {
        if ($this->syncSessionId == null) {
            //initialize a sync session
            $this->syncProcNumOfReq++;
            $this->syncSessionId = $this->SOAPClient->login($this->syncAPI->getSyncConfigManager()->getRemoteSyncBEUser(),$this->syncAPI->getSyncConfigManager()->getRemoteSyncBEPassword());

        }

        return $this->syncSessionId;
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
    public function getSyncResultResponse($serviceProvider, $serviceName, $requestArgs = null, $trackRequests = false, $postProcessCallBackMethod = null)
    {
        $resultArtifact = null;
        $compositeResults = null;
        $requestPreProcess = null;

        try {
            if ($trackRequests) {
                $requestPreProcess = $this->requestDetailsPreProcess();
            }

            if ($this->_getSyncSessionId()) {

                //all 'calls' should now send a session id
                $this->syncProcNumOfReq++;

                //gets overwritten with each request
                $resultArtifact = $this->SOAPClient->call($this->_getSyncSessionId(), $serviceProvider, $serviceName, $requestArgs);

                //set raw result artifact
                //of course not used in smaller transactions
                $this->syncAPI->getSyncResultResponseDTO(true)->setSyncResponse($resultArtifact);

            }

            //update the number of requests per sync process
            $updatedProcInfo = array(
                    'requests' => $this->syncProcNumOfReq,
            );
            $this->updateSyncProcState($updatedProcInfo);

            //only track those requests which have been explicitly asked to
            if ($trackRequests) {
                if ($this->requestDetailsPostProcess($requestPreProcess, $requestArgs, $postProcessCallBackMethod)) {
                    //Got response and stored in table
                }
            }

        } catch (SoapFault $s) {
            $syncProcErrorMesg = 'SOAP fault: [' . $s->faultcode . '] ' . $s->faultstring;
            $this->handleSyncError($syncProcErrorMesg,tx_st9fissync_sync::STATUS_FAILURE);
        } catch (tx_st9fissync_exception $e) {
            $syncProcErrorMesg = $e->getMessage();
            $this->handleSyncError($syncProcErrorMesg,tx_st9fissync_sync::STATUS_FAILURE);
        }

        return $resultArtifact['responseRes'];
    }

    /**
     * Mode: Sync
     *
     * Assumptions:
     * 1.Find all queries in the Intranet instance except the ones related to tables
     *  a. 'tx_dam'
     *
     * 1.1.Execute them in the website instance
     * 1.2 Log all the requests vis-a-vis:
     *  the tablename (which is always 'tx_st9fissync_dbversioning_query' currently)
     *  sysid
     *  isremote
     *  error_message
     *
     * 2.Find all queries in the intranet instance, the ones related to table 'tx_dam'
     * 2.1 Put all the DAM files from the Intranet-->Website instance
     * 2.2 Execute the queries related to the DAM indexes (Do not version/sequence this) for which the file transfer has been successful,
     * 	   this is done so they are re-tried in the next sync cycle if a file transfer fails
     *
     */
    public function stage1_1()
    {
        $this->syncAPI->setSyncMode(tx_st9fissync::SYNC_MODE_SYNC);

        $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($this->syncAPI->getSyncLabels('stage.sync.stage1_1.start')));

        //query for all syncable objects from Intranet --> Website
        $syncableQueryDTOs = $this->syncAPI->getSyncDataTransferObject(true)->getSyncableQueryDTOs(true);

        $numOfObjectsToProcess = $this->syncAPI->getSyncLabels('stage.sync.numqueryobjects') . ' [' . count($syncableQueryDTOs) . ']';
        $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($numOfObjectsToProcess));

        //break them into batch size as set from config
        $batchsize = $this->syncAPI->getSyncConfigManager()->getSyncQuerySetBatchSize();
        $syncQueryBatches = array_chunk($syncableQueryDTOs,  $batchsize, true);

        $batchSizeMesg = $this->syncAPI->getSyncLabels('stage.sync.querybatchsize') . ' [' . $batchsize . ']';
        $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($batchSizeMesg));

        $numOfBatchesToProcess = $this->syncAPI->getSyncLabels('stage.sync.numbatches') . ' [' . count($syncQueryBatches) . ']';
        $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($numOfBatchesToProcess));

        $passSyncedCount = 0;
        $failedSyncedCount = 0;

        //process each batch
        foreach ($syncQueryBatches as $queryBatch) {

            //send each batch to replay on the other instance (Website)
            $this->getSyncResultResponse('tx_st9fissync_service_handler','replayActions', $queryBatch,true, 'stage1_1_postProcess');

            //the negative results i.e. errors already handled in the post process
            //results of such an execution on the remote end
            $queryExecRes = $this->syncAPI->getSyncResultResponseDTO()->getSyncResults();

            if ($queryExecRes != null) {
                $qvIds = array();
                for ($queryExecRes->rewind(); $queryExecRes->valid(); $queryExecRes->next()) {

                    $currentRes = $queryExecRes->current();
                    $currentKey = $queryExecRes->key();

                    /**
                     *
                     * form status messages for synced fail/pass
                     */
                    $syncToRemoteStatusMessage = $this->syncAPI->getSyncLabels('stage.sync.querytracking.id') . '[' . $currentKey . '] / ';
                    $syncToRemoteStatusMessage .= $this->syncAPI->getSyncLabels('stage.sync.querytracking.details');
                    $syncToRemoteStatusMessage .= $this->syncAPI->getSyncLabels('stage.sync.querytracking.tablename') . '[' . $syncableQueryDTOs[$currentKey]['tablenames'] . '] / ';
                    $tableRowId = intval($syncableQueryDTOs[$currentKey]['uid_foreign']);
                    if ($tableRowId > 0) {
                        $syncToRemoteStatusMessage .= $this->syncAPI->getSyncLabels('stage.sync.querytracking.tablerowid') . '[' . $tableRowId . ']';
                    } else {
                        $syncToRemoteStatusMessage .= $this->syncAPI->getSyncLabels('stage.sync.querytracking.mmtablerowid');
                    }

                    if ($currentRes['synced']) {
                        $qvIds[] = $currentKey;
                        $syncToRemoteStatusMessage = $this->syncAPI->getSyncLabels('stage.sync.querytracking.toremotesuccess') . $syncToRemoteStatusMessage;
                        $passSyncedCount++;
                    } else {
                        $syncToRemoteStatusMessage = $this->syncAPI->getSyncLabels('stage.sync.querytracking.toremotefail') . $syncToRemoteStatusMessage;
                        $failedSyncedCount++;
                    }

                    $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($syncToRemoteStatusMessage));

                }

                if (count($qvIds) > 0 ) {
                    $updated = $this->syncAPI->getSyncDBOperationsManager()->setSyncIsSyncedForIndexedQueries($qvIds);
                }
            }
        }

        if ($passSyncedCount > 0) {
            $this->clearRemoteCache = true;
        }

        $passSyncedCountMessage = $this->syncAPI->getSyncLabels('stage.sync.querycount.pass') . '[' . $passSyncedCount . ']';
        $failedSyncedCountMessage = $this->syncAPI->getSyncLabels('stage.sync.querycount.fail'). '[' . $failedSyncedCount . ']';
        $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($passSyncedCountMessage));
        $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($failedSyncedCountMessage));
        $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($this->syncAPI->getSyncLabels('stage.sync.stage1_1.finished')));
    }

    /**
     * SOAP request post proc callback for query syncing in the Intranet --> Website
     *
     * @param tx_st9fissync_request $requestDetailsArtifact
     * @param array                 $requestArgsBatch
     *
     * @return tx_st9fissync_request
     */
    public function stage1_1_postProcess(tx_st9fissync_request $requestDetailsArtifact, $requestArgsBatch)
    {
        $versionedQueryCount = 0;

        foreach ($requestArgsBatch as $requestArg) {

            $requestRefQueryRecArtifact = t3lib_div::makeInstance('tx_st9fissync_request_dbversioning_query_mm');
            $requestRefQueryRecArtifact->set('uid_foreign',$requestArg['uid']);
            //  $requestRefQueryRecArtifact->set('sysid', $this->syncAPI->getSyncConfigManager()->getSyncSystemId());
            $requestRefQueryRecArtifact->set('isremote', 0);

            $errorForQueryDTO = $this->syncAPI->getSyncResultResponseDTO()->getSyncErrorsByDTO($requestArg['uid']);

            if ($errorForQueryDTO != null) {

                $requestRefQueryRecArtifact->set('error_message',$errorForQueryDTO);

                /**
                 *
                 * Form notification messages for errors
                 */
                $syncToRemoteError = $errorForQueryDTO . ' / ';
                $syncToRemoteError .= $this->syncAPI->getSyncLabels('stage.sync.querytracking.id') . '[' . $requestArg['uid'] . '] / ';
                $syncToRemoteError .= $this->syncAPI->getSyncLabels('stage.sync.querytracking.details');
                $syncToRemoteError .= $this->syncAPI->getSyncLabels('stage.sync.querytracking.tablename') . '[' . $requestArg['tablenames'] . '] / ';
                $tableRowId = intval($requestArg['uid_foreign']);
                if ($tableRowId > 0) {
                    $syncToRemoteError .= $this->syncAPI->getSyncLabels('stage.sync.querytracking.tablerowid') . '[' . $tableRowId . ']';
                } else {
                    $syncToRemoteError .= $this->syncAPI->getSyncLabels('stage.sync.querytracking.mmtablerowid');
                }
                $this->handleSyncError($syncToRemoteError,tx_st9fissync_sync::STATUS_FAILURE);
            }

            $requestDetailsArtifact->addVersionedQueryArtifact($this->syncAPI->buildRequestRefQueryRecArtifact($requestRefQueryRecArtifact));
            $versionedQueryCount++;
        }

        $requestDetailsArtifact->set('versionedqueries',$versionedQueryCount);

        return $requestDetailsArtifact;
    }

    /**
     * Mode: Sync
     *
     * Pure file handling and transfer
     * Based on successful transfer of a particular file
     * its corresponding query related to its corresponding DAM index
     * is sent for replay re-execution by SOAP from Intranet --> Website
     *
     *  File transfer is done either as single file transfers or as batched transfers
     *  based on the limit set in the configuration for a maximum number of data bytes to be transferred per request/response cycle
     *
     *
     */
    public function stage1_2()
    {
        //set again anyway
        $this->syncAPI->setSyncMode(tx_st9fissync::SYNC_MODE_SYNC);

        $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($this->syncAPI->getSyncLabels('stage.sync.stage1_2.start')));

        //Preprocess and make arguments for DAM related sync processing
        //this holds the DAM indexes and is filtered whenever a file transfer is unsuccessful, look for unset(..)
        $syncableDAMQueryResDTOs = $this->syncAPI->getSyncDAMDataTransferObject(true)->getSyncableDAMQueryResDTOs(true);

        $numOfDAMIndexObjectsToProcess = $this->syncAPI->getSyncLabels('stage.sync.dam.numqueryobjects') . ' [' . count($syncableDAMQueryResDTOs) . ']';
        $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($numOfDAMIndexObjectsToProcess));

        //only uid_foreign and tablenames first, so first clone and then trim
        //holds a map of Query Versioning Keys <---> DAM index  <--> DAM Index meta data
        $syncableDAMQueryResPreProcessTrim = array();

        foreach ($syncableDAMQueryResDTOs as $key => $val) {
            $damIndex = $syncableDAMQueryResDTOs[$key]['uid_foreign'];
            $syncableDAMQueryResPreProcessTrim[$key][$damIndex]['metadata'] = tx_dam::meta_getDataByUid($damIndex);
        }

        /**
         *  block transfer of unneeded DAM files / indexes
         *  (even if they have been marked as eligible to be synced inaccurately during the recording process).
         *
         */
        $damIndexEligibilityRes = $this->getSyncResultResponse('tx_st9fissync_service_handler','confirmSyncEligibleDAMRes', $syncableDAMQueryResPreProcessTrim);
        if ($damIndexEligibilityRes != null) {
            //only if there are any "eligible" files, then proceed to transfer files and DAM index related queries
            $damIndexEligibilityRes = $damIndexEligibilityRes->get('eligibility');

            //Now form the files transfer payload &  Query transfer payload
            // then transfer the files and after that the queries

            $damIndexQVKeyMap = array();
            $fileTransferArray = array();
            $totalPayLoad = 0;
            $batches = array();
            $i=0;
            $fileSetSize = 0;
            $fileBatchMaxSizeBytes = $this->syncAPI->getSyncConfigManager()->getSyncFileSetMaxSize();

            //number of DAM objects/files to process
            $numOfDAMIndexObjectsToProcess = $this->syncAPI->getSyncLabels('stage.sync.dam.numqueryobjects') . ' [' . count($syncableDAMQueryResDTOs) . ']';
            $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($numOfDAMIndexObjectsToProcess));

            //file set transfer batch max size
            $fileBatchMaxSizeBytesMesg = $this->syncAPI->getSyncLabels('stage.sync.transfer.filedatamaxsize') . ' [' . $fileBatchMaxSizeBytes . ']';
            $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($fileBatchMaxSizeBytesMesg));

            foreach ($syncableDAMQueryResDTOs as $qvKey => $qvData) {

                foreach ($syncableDAMQueryResPreProcessTrim[$qvKey] as $damRes) {

                    if ($damIndexEligibilityRes->get($qvKey)) {

                        if (isset($damRes['metadata']) && is_array($damRes['metadata'])) {

                            //refresh meta Data, this is live data and not stale from the DB
                            $refreshedMetaData = tx_dam::file_compileInfo(tx_dam::file_absolutePath($damRes['metadata']));

                            /**
                             *
                             * Logging and notification
                             *
                             */
                            $DAMIndexedFileToProcess = $this->syncAPI->getSyncLabels('stage.sync.dam.file.proc');
                            $DAMIndexedFileToProcess .= ' [' . $damRes['metadata']['file_path'];
                            $DAMIndexedFileToProcess .= $damRes['metadata']['file_name'] . '] / ';
                            $DAMIndexedFileToProcess .= $this->syncAPI->getSyncLabels('stage.sync.dam.file.size');
                            $DAMIndexedFileToProcess .= '[' . $damRes['metadata']['file_size'] . '] / ';
                            $DAMIndexedFileToProcess .= $this->syncAPI->getSyncLabels('stage.sync.dam.index.id');
                            $DAMIndexedFileToProcess .= '[' . $damRes['metadata']['uid'] . '] / ';
                            $DAMIndexedFileToProcess .= $this->syncAPI->getSyncLabels('stage.sync.dam.index.tracking.id');
                            $DAMIndexedFileToProcess .= '[' . $qvKey . '] / ';
                            $DAMIndexedFileToProcess .= $this->syncAPI->getSyncLabels('stage.sync.dam.file.exists');

                            //not required and will cause problems on the remote end, so unset here
                            unset($refreshedMetaData['file_path_absolute']);

                            if ($refreshedMetaData['__exists']) {

                                /**
                                 * Logging and notification continues
                                 */
                                $DAMIndexedFileToProcess .= $this->syncAPI->getSyncLabels('stage.sync.dam.file.exists.yes');
                                $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($DAMIndexedFileToProcess));

                                $fileSize = $refreshedMetaData['file_size'];
                                $bytesWritten = 0;
                                $damIndex = $damRes['metadata']['uid'];

                                /**
                                 * temp data struct for composition of all file transfer arguments
                                 */
                                $transferData = array();

                                if ($fileSize > $fileBatchMaxSizeBytes) {

                                    $DAMIndexedFileToProcess .= $this->syncAPI->getSyncLabels('stage.sync.dam.transfer.single');
                                    $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($DAMIndexedFileToProcess));

                                    $singleFileTransferArray = array();
                                    $singleFileTransferArray[$damIndex] = array('metadata' => $refreshedMetaData, 'transferdata' => null);
                                    $chunkedFileTransferError = false;

                                    /**
                                     * Here a single file is broken into chunks to transfer
                                     */
                                    for ($totalPayLoad = 0; $totalPayLoad <= $fileSize;  $totalPayLoad = $totalPayLoad + $fileBatchMaxSizeBytes) {
                                        $data = file_get_contents(tx_dam::file_absolutePath($damRes['metadata']), false, null, $totalPayLoad, $fileBatchMaxSizeBytes);
                                        $transferData['data'] = base64_encode($data);
                                        unset($data);

                                        if ($totalPayLoad == 0) {
                                            $transferData['cmd'] = 'w+';
                                        } else {
                                            $transferData['cmd'] = 'a+';
                                        }

                                        $singleFileTransferArray[$damIndex]['transferdata'] = $transferData;

                                        $DAMIndexedFileToProcessChunk = $this->syncAPI->getSyncLabels('stage.sync.dam.fileread.offset');
                                        $DAMIndexedFileToProcessChunk .= '[' . $totalPayLoad . '] / ';
                                        $DAMIndexedFileToProcessChunk .= $this->syncAPI->getSyncLabels('stage.sync.dam.fileread.maxlen');
                                        $DAMIndexedFileToProcessChunk .= '[' . $fileBatchMaxSizeBytes . '] / ';
                                        $DAMIndexedFileToProcessChunk .= $this->syncAPI->getSyncLabels('stage.sync.dam.filetransfer.start');

                                        $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($DAMIndexedFileToProcess . $DAMIndexedFileToProcessChunk));

                                        $res = $this->getSyncResultResponse('tx_st9fissync_service_handler','handleFileTransfer', $singleFileTransferArray);

                                        if (is_a($res, 'tx_lib_object')) {

                                            //error handling
                                            if (is_a($res->get('errors'), 'tx_lib_object')) {
                                                if ($res->get('errors')->get($damIndex)) {
                                                    $chunkedFileTransferError = $res->get('errors')->get($damIndex);
                                                    //if errors in any cycle, plain abort this file's transfer and move on
                                                    //of course the corresponding tx_dam index query will not be synced,
                                                    //see below for the unsetting

                                                    $DAMIndexedFileToProcess .= $this->syncAPI->getSyncLabels('stage.sync.dam.filetransfer.chunkerror');
                                                    $DAMIndexedFileToProcess .= '[' . $chunkedFileTransferError . ']';
                                                    break;
                                                }
                                            }

                                            if (is_a($res->get('bytes'), 'tx_lib_object')) {
                                                //track bytes written till now
                                                $bytesWritten += $res->get('bytes')->get($damIndex);

                                                $DAMIndexedFileToProcessChunk = $this->syncAPI->getSyncLabels('stage.sync.dam.filetransfer.byteswritten');
                                                $DAMIndexedFileToProcessChunk .= '[' . $bytesWritten . ']';
                                                $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($DAMIndexedFileToProcess . $DAMIndexedFileToProcessChunk));
                                            }
                                        }
                                    }

                                    if ($bytesWritten == $fileSize) {
                                        //file sent
                                        $DAMIndexedFileToProcess .= $this->syncAPI->getSyncLabels('stage.sync.dam.filetransfer.success');
                                        $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($DAMIndexedFileToProcess));

                                    } elseif ($chunkedFileTransferError || $bytesWritten != $fileSize) {

                                        //if error corresponding tx_dam related query not to be synced, so unset it

                                        $DAMIndexedFileToProcess .= $this->syncAPI->getSyncLabels('stage.sync.dam.filetransfer.fail');

                                        if ($bytesWritten != $fileSize) {
                                            $DAMIndexedFileToProcess .= '[' . $this->syncAPI->getSyncLabels('stage.sync.dam.filetransfer.byteswritten.error');
                                            $DAMIndexedFileToProcess .=  $bytesWritten . '/' . $fileSize . ']';
                                        }

                                        $this->handleSyncError($DAMIndexedFileToProcess,tx_st9fissync_sync::STATUS_FAILURE);

                                        //errors for this file, so do not sync corresponding DAM index
                                        // and keep it in queue for next cycle
                                        unset($syncableDAMQueryResDTOs[$qvKey]);

                                        $DAMIndexedTrackingIdRejectSync = $this->syncAPI->getSyncLabels('stage.sync.dam.tracking.id.reject');
                                        $DAMIndexedTrackingIdRejectSync .= '[' . $qvKey . ']';
                                        $this->handleSyncError($DAMIndexedTrackingIdRejectSync,tx_st9fissync_sync::STATUS_FAILURE);


                                    }
                                    //go easy on memory consumption
                                    unset($singleFileTransferArray); //empty

                                } else {

                                    //transfer set of files (other than the ones wherein a singular file itself is larger than the batchsize)
                                    //in batches

                                    $fileTransferArray[$damIndex] = array('metadata' => $refreshedMetaData, 'transferdata' => null);

                                    //try adding to this batch
                                    $fileSetSize += $fileSize;

                                    //check if it fits in the current batch
                                    if ($fileSetSize > $fileBatchMaxSizeBytes) {
                                        //if current batch is exhausted,
                                        //add file to next batch and of course also already consume its share of size
                                        $i++;
                                        $fileSetSize = $fileSize;
                                    }

                                    /**
                                     *
                                     * defer file reading until batch processing for each file sets
                                     * disabling loading of file content here
                                     */

                                    /*$data = file_get_contents(tx_dam::file_absolutePath($damRes['metadata']));
                                     $transferData['data'] = base64_encode($data);
                                    */

                                    $transferData['cmd'] = 'w+';
                                    $fileTransferArray[$damIndex]['transferdata'] = $transferData;
                                    $batches[$i][$damIndex] = $fileTransferArray[$damIndex];
                                    unset($fileTransferArray[$damIndex]);// save memory as already copied

                                    $DAMIndexedFileToProcess .= $this->syncAPI->getSyncLabels('stage.sync.dam.file.addtobatch');
                                    $DAMIndexedFileToProcess .= '[' . ($i +1) . ']';
                                    $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($DAMIndexedFileToProcess));

                                    $damIndexQVKeyMap[$damIndex]['qvKeys'][] = $qvKey;
                                    $damIndexQVKeyMap[$damIndex]['file_size'] = $fileSize;

                                }
                                unset($transferData);//empty
                            }

                        } else {
                            /**
                             * Logging and notification continues
                             */
                            $DAMIndexedFileToProcess .= $this->syncAPI->getSyncLabels('stage.sync.dam.file.exists.no');
                            $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($DAMIndexedFileToProcess));

                            //should we sync DAM index ??
                        }
                    }
                    unset($syncableDAMQueryResPreProcessTrim[$qvKey]);
                }
            }

            /**
             *
             * Woooh!!
             *
             */

            $numOfBatchesToProcess = $this->syncAPI->getSyncLabels('stage.sync.dam.numfilebatches') . ' [' . count($batches) . ']';
            $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($numOfBatchesToProcess));

            /**
             * Batches of files continues, here is real file transfer
             *
             */
            foreach ($batches as $batchId => $batchPacket) {

                /**
                 *
                 * Batch Id
                 */
                $batchToProcess = $this->syncAPI->getSyncLabels('stage.sync.dam.file.batch.id') . ' [' . ($batchId + 1). ']';
                $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($batchToProcess));


                //$this->syncAPI->debugToFile(' -- $batchId -- ' . $batchId);
                //$this->syncAPI->debugToFile($batchPacket);

                /**
                 *
                 * deferred file reading to be done now for this batch of files
                 * before transfer, so loop over the packet
                 */
                foreach ($batchPacket as $damIndex => $fileTransferArray) {
                    $data = file_get_contents(tx_dam::file_absolutePath($fileTransferArray['metadata']));
                    //now load file data here
                    $batchPacket[$damIndex]['transferdata']['data'] = base64_encode($data);
                }


                /**
                 *
                 * A sample $batchPacket item now looks like --
                 *
                 *
                 *  [8212] => Array
                 (
                 [metadata] => Array
                 (
                 [__type] => file
                 [__exists] => 1
                 [file_name] => filename
                 [file_extension] => zip
                 [file_title] => sampletitle
                 [file_path] => fileadmin/kordoba/
                 [file_path_relative] => fileadmin/kordoba/
                 [file_accessable] => 1
                 [file_mtime] => 1361796688
                 [file_ctime] => 1361796688
                 [file_inode] => 7744035
                 [file_size] => 1140287
                 [file_owner] => 70
                 [file_perms] => 33270
                 [file_writable] => 1
                 [file_readable] => 1
                 )

                 [transferdata] => Array
                 (
                 [cmd] => w+
                 [data] => file contents
                 )

                 )
                 *
                 *
                 */

                //transfer a batch
                $batchRes = $this->getSyncResultResponse('tx_st9fissync_service_handler','handleFileTransfer', $batchPacket);

                //$this->syncAPI->debugToFile($batchRes);

                if ($batchRes && ($batchRes instanceof tx_lib_object )) {
                    /**
                     * post-processing for Errors and result handling
                     *
                     */

                    $positiveResults = true;

                    foreach ($damIndexQVKeyMap as $damIndex => $map) {

                        /**
                         *
                         * Notification & logging
                         */
                        $DAMIndexedFileProcessed = $this->syncAPI->getSyncLabels('stage.sync.dam.file.proc');
                        $DAMIndexedFileProcessed .= ' [' . $batchPacket[$damIndex]['metadata']['file_path'];
                        $DAMIndexedFileProcessed .= $batchPacket[$damIndex]['metadata']['file_name'] . ' / ';
                        $DAMIndexedFileProcessed .= $this->syncAPI->getSyncLabels('stage.sync.dam.file.size');
                        $DAMIndexedFileProcessed .= $batchPacket[$damIndex]['metadata']['file_size'] . ' / ';
                        $DAMIndexedFileProcessed .= $this->syncAPI->getSyncLabels('stage.sync.dam.index.id');
                        $DAMIndexedFileProcessed .= $damIndex . '] / ';
                        $DAMIndexedFileProcessed .= $this->syncAPI->getSyncLabels('stage.sync.dam.index.tracking.id');
                        $DAMIndexedFileProcessed .= '[' . implode(',', $map['qvKeys']) . '] / ';
                        $DAMIndexedFileProcessed = $batchToProcess . ' / ' . $DAMIndexedFileProcessed;

                        /**
                         * Only pure errors here,
                         * if error stop DAM index queries syncing
                         */
                        if (is_a($batchRes->get('errors'), 'tx_lib_object')) {
                            $batchErrors = $batchRes->get('errors');
                            if ($batchErrors->offsetExists($damIndex)) {

                                /**
                                 * Logging and notification continues
                                 */
                                $DAMIndexedFileProcessed .= $this->syncAPI->getSyncLabels('stage.sync.dam.filetransfer.batcherror');
                                $DAMIndexedFileProcessed .= '[' . $batchErrors->get($damIndex) . ']';
                                $this->handleSyncError($DAMIndexedFileProcessed, tx_st9fissync_sync::STATUS_FAILURE);

                                /**
                                 * Alert!!
                                 */
                                foreach ($map['qvKeys'] as $qvKey) {
                                    //as explained if file transfer failed also stop its corresponding DAM index queries to be synced
                                    unset($syncableDAMQueryResDTOs[$qvKey]);

                                    /**
                                     * Logging and notification continues
                                     */
                                    $DAMIndexedTrackingIdRejectSync = $this->syncAPI->getSyncLabels('stage.sync.dam.tracking.id.reject');
                                    $DAMIndexedTrackingIdRejectSync .= '[' . $qvKey . ']';
                                    $this->handleSyncError($DAMIndexedTrackingIdRejectSync,tx_st9fissync_sync::STATUS_FAILURE);
                                }

                            }
                            $positiveResults = false ;
                        }

                        /**
                         * Error handling use-case to check w.r.t filesize integrity,
                         * so real file size vis-a-vis num of bytes written to at the server end point of sync
                         *
                         * Any other such integrity use-case ??
                         */
                        if (is_a($batchRes->get('bytes'), 'tx_lib_object')) {

                            $batchResults = $batchRes->get('bytes');

                            if ($batchResults->offsetExists($damIndex)) {

                                if ($damIndexQVKeyMap[$damIndex]['file_size'] == $batchResults->get($damIndex)) {

                                    $DAMIndexedFileProcessed .= $this->syncAPI->getSyncLabels('stage.sync.dam.filetransfer.success');
                                    $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($DAMIndexedFileProcessed));

                                } else {

                                    /**
                                     * Logging and notification continues
                                     */
                                    $DAMIndexedFileProcessed .=  $this->syncAPI->getSyncLabels('stage.sync.dam.filetransfer.byteswritten.error');
                                    $DAMIndexedFileProcessed .= '[' . $batchResults->get($damIndex) . '/' . $damIndexQVKeyMap[$damIndex]['file_size'] . ']';
                                    $this->handleSyncError($DAMIndexedFileProcessed, tx_st9fissync_sync::STATUS_FAILURE);

                                    foreach ($map['qvKeys'] as $qvKey) {
                                        if (isset($syncableDAMQueryResDTOs[$qvKey])) {
                                            unset($syncableDAMQueryResDTOs[$qvKey]);

                                            /**
                                             * Logging and notification continues
                                             */
                                            $DAMIndexedTrackingIdRejectSync = $this->syncAPI->getSyncLabels('stage.sync.dam.tracking.id.reject');
                                            $DAMIndexedTrackingIdRejectSync .= '[' . $qvKey . ']';
                                            $this->handleSyncError($DAMIndexedTrackingIdRejectSync,tx_st9fissync_sync::STATUS_FAILURE);
                                        }
                                    }
                                    $positiveResults = false ;
                                }
                            }

                        } else {

                            /**
                             * Logging and notification continues
                             */
                            //this batch failed to fetch any valid response (results should contain information about number of bytes written @ 'bytes') , so reject all qvkeys related to this batch of DAM files <--> indexes
                            if (in_array($damIndex, $batchPacket)) {

                                $DAMIndexedFileProcessed .=  $this->syncAPI->getSyncLabels('stage.sync.dam.filetransfer.byteswritten.nullerror');
                                $this->handleSyncError($DAMIndexedFileProcessed, tx_st9fissync_sync::STATUS_FAILURE);
                                //reject list in the dam index Qv Map
                                foreach ($damIndexQVKeyMap[$damIndex]['qvKeys'] as $qvKeyInMap) {
                                    //as explained if file transfer failed also stop its corresponding DAM index queries to be synced
                                    unset($syncableDAMQueryResDTOs[$qvKeyInMap]);
                                    /**
                                     * Logging and notification continues
                                     */
                                    $DAMIndexedTrackingIdRejectSync = $this->syncAPI->getSyncLabels('stage.sync.dam.tracking.id.reject');
                                    $DAMIndexedTrackingIdRejectSync .= '[' . $qvKeyInMap . ']';
                                    $this->handleSyncError($DAMIndexedFileProcessed . ' / ' . $DAMIndexedTrackingIdRejectSync,tx_st9fissync_sync::STATUS_FAILURE);
                                }

                                $positiveResults = false ;
                            }

                        }
                        // End of a batch result processing
                    }

                    $statusString = '';
                    if ($positiveResults) {
                        $statusString = $this->syncAPI->getSyncLabels('stage.sync.dam.file.batch.passed');
                    } else {
                        $statusString = $this->syncAPI->getSyncLabels('stage.sync.dam.file.batch.mixedreswerrors');
                    }

                    $batchToProcess = $this->syncAPI->getSyncLabels('stage.sync.dam.file.batch.id') . ' [' . ($batchId + 1) . '] - ';
                    $batchToProcess .= $statusString;
                    $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($batchToProcess));

                } else {

                    //this batch failed to fetch any response, so reject all qvkeys related to this batch of DAM files <--> indexes
                    foreach ($batchPacket as $damIndex => $fileTransferArray) {
                        //reject list in the dam index Qv Map
                        foreach ($damIndexQVKeyMap[$damIndex]['qvKeys'] as $qvKeyInMap) {
                            //as explained if file transfer failed also stop its corresponding DAM index queries to be synced
                            unset($syncableDAMQueryResDTOs[$qvKeyInMap]);
                            /**
                             * Logging and notification continues
                             */
                            $DAMIndexedTrackingIdRejectSync = $this->syncAPI->getSyncLabels('stage.sync.dam.tracking.id.reject');
                            $DAMIndexedTrackingIdRejectSync .= '[' . $qvKeyInMap . ']';
                            $this->handleSyncError($DAMIndexedTrackingIdRejectSync,tx_st9fissync_sync::STATUS_FAILURE);
                        }
                    }

                    $batchToProcess = $this->syncAPI->getSyncLabels('stage.sync.dam.file.batch.id') . ' [' . ($batchId + 1). ']';
                    $batchToProcess .= ' / ' . $this->syncAPI->getSyncLabels('stage.sync.dam.file.batch.failed');
                    $this->handleSyncError($batchToProcess, tx_st9fissync_sync::STATUS_FAILURE);
                }
                //End of a batch
            }

            /**
             * Now for the tx_dam queries
             *
             * 1.filter out ineligible queries from the sync args queue to SOAP service replayActions
             * 1.1.Mark them as not scheduled to be sync
             *
             *
             */
            $noneligibleqvIds = array();
            $noneligibleqvIdDAMIndexMap = array();
            foreach ($syncableDAMQueryResDTOs as $qvKey => $qvData) {

                if ($damIndexEligibilityRes->get($qvKey) == tx_st9fissync::DAM_INDEX_TRANSFER_INELIGIBLE ||
                        ($damIndexEligibilityRes->get($qvKey) == tx_st9fissync::DAM_FILE_TRANSFER_ELIGIBLE &&
                                $syncableDAMQueryResDTOs[$qvKey]['query_type'] == tx_st9fissync_dbversioning_t3service::INSERT)) {

                    $noneligibleqvIds[] = $qvKey;
                    $noneligibleqvIdDAMIndexMap[$qvKey] = $syncableDAMQueryResDTOs[$qvKey]['uid_foreign'];
                    unset($syncableDAMQueryResDTOs[$qvKey]);
                }
            }
            if (count($noneligibleqvIds) >0 ) {

                //Reset Sync Eligibility / Mark as already synced for DAM indexes not required to be transferred
                $this->syncAPI->getSyncDBOperationsManager()->setSyncIsSyncedForIndexedQueries($noneligibleqvIds);

                $nonEligibleDAMIndexesWarning = $this->syncAPI->getSyncLabels('stage.sync.dam.index.ineligible.reason.alreadysynced');
                $nonEligibleDAMIndexesWarning .= '[' . $this->syncAPI->printArrayElementsByDelimiter($noneligibleqvIdDAMIndexMap) . ']';
                $this->syncAPI->addWarningMessage($this->getSyncMesgPrefixed($nonEligibleDAMIndexesWarning));

            }


            /**
             *
             * 2. Process eligible tx_dam related queries
             * 2.1 Mark such queries as synced
             *
             */
            $batchsize = $this->syncAPI->getSyncConfigManager()->getSyncQuerySetBatchSize();

            //count of eligible DAM indexes
            $numOfDAMIndexObjectsToProcess = $this->syncAPI->getSyncLabels('stage.sync.dam.numqueryobjects.final') . ' [' . count($syncableDAMQueryResDTOs) . ']';
            $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($numOfDAMIndexObjectsToProcess));

            $batchSizeMesg = $this->syncAPI->getSyncLabels('stage.sync.querybatchsize') . ' [' . $batchsize . ']';
            $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($batchSizeMesg));

            //break them into batch size as set from config
            $syncDAMQueryBatches = array_chunk($syncableDAMQueryResDTOs,  $batchsize, true);

            $numOfDAMQueryBatchesToProcess = $this->syncAPI->getSyncLabels('stage.sync.dam.numbatches') . ' [' . count($syncDAMQueryBatches) . ']';
            $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($numOfDAMQueryBatchesToProcess));

            $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($this->syncAPI->getSyncLabels('stage.sync.dam.index.sync.start')));

            $passDAMIndexSyncedCount = 0;
            $failedDAMIndexSyncedCount = 0;

            foreach ($syncDAMQueryBatches as $damQueryBatch) {

                $this->getSyncResultResponse('tx_st9fissync_service_handler','replayActions', $damQueryBatch,true, 'stage1_1_postProcess');

                $queryExecRes = $this->syncAPI->getSyncResultResponseDTO()->getSyncResults();
                if ($queryExecRes != null) {
                    $qvIds = array();
                    for ($queryExecRes->rewind(); $queryExecRes->valid(); $queryExecRes->next()) {

                        $currentRes = $queryExecRes->current();
                        $currentKey = $queryExecRes->key();

                        /**
                         *
                         * form status messages for synced fail/pass
                         */
                        $syncDAMIndexToRemoteStatusMessage = $this->syncAPI->getSyncLabels('stage.sync.dam.index.querytracking.id') . '[' . $currentKey . ']. / ';
                        $syncDAMIndexToRemoteStatusMessage .= $this->syncAPI->getSyncLabels('stage.sync.dam.index.querytracking.details');
                        $syncDAMIndexToRemoteStatusMessage .= $this->syncAPI->getSyncLabels('stage.sync.dam.index.querytracking.tablename') . '[' . $syncableDAMQueryResDTOs[$currentKey]['tablenames'] . '] / ';
                        $tableRowId = intval($syncableDAMQueryResDTOs[$currentKey]['uid_foreign']);
                        if ($tableRowId > 0) {
                            $syncDAMIndexToRemoteStatusMessage .= $this->syncAPI->getSyncLabels('stage.sync.dam.index.querytracking.tablerowid') . '[' . $tableRowId . ']';
                        } else {
                            $syncDAMIndexToRemoteStatusMessage .= $this->syncAPI->getSyncLabels('stage.sync.dam.index.querytracking.mmtablerowid');
                        }

                        if ($currentRes['synced']) {
                            $qvIds[] = $currentKey;
                            $syncDAMIndexToRemoteStatusMessage = $this->syncAPI->getSyncLabels('stage.sync.dam.index.querytracking.toremotesuccess') . $syncDAMIndexToRemoteStatusMessage;
                            $passDAMIndexSyncedCount++;
                        } else {
                            $syncDAMIndexToRemoteStatusMessage = $this->syncAPI->getSyncLabels('stage.sync.dam.index.querytracking.toremotefail') . $syncDAMIndexToRemoteStatusMessage;
                            $failedDAMIndexSyncedCount++;
                        }

                        $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($syncDAMIndexToRemoteStatusMessage));

                    }
                    if (count($qvIds) > 0 ) {
                        $updated = $this->syncAPI->getSyncDBOperationsManager()->setSyncIsSyncedForIndexedQueries($qvIds);

                    }
                }
            }

            if ($passDAMIndexSyncedCount) {
                $this->clearRemoteCache = true;
            }

            $passDAMIndexSyncedCountMessage = $this->syncAPI->getSyncLabels('stage.sync.dam.index.querycount.pass') . '[' . $passDAMIndexSyncedCount . ']';
            $failedDAMIndexSyncedCountMessage = $this->syncAPI->getSyncLabels('stage.sync.dam.index.querycount.fail'). '[' . $failedDAMIndexSyncedCount . ']';
            $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($passDAMIndexSyncedCountMessage));
            $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($failedDAMIndexSyncedCountMessage));
            $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($this->syncAPI->getSyncLabels('stage.sync.dam.index.sync.finished')));

        } else {

            $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($this->syncAPI->getSyncLabels('stage.sync.dam.confirmation.ineligible.all')));
        }
        $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($this->syncAPI->getSyncLabels('stage.sync.stage1_2.finished')));
    }


    /**
     * Mode: Re-Sync
     *
     * Assumptions:
     * 1.Find all queries in the Website instance except the ones related to tables
     *  a. 'tx_dam' (strictly by specification)
     * 	b. 'tx_st9fissupport_domain_model_upload' (this is done in stage 4)
     *
     * 1.1.Execute them in the intranet instance
     * 1.2 Log all the requests vis-a-vis:
     *  the tablename (which is always 'tx_st9fissync_dbversioning_query' currently)
     *  sysid
     *  isremote
     *  error_message
     *
     * 2.Find all queries in the Website instance, the ones related to table 'tx_st9fissupport_domain_model_upload'
     * 2.1 Execute all these queries on the intranet instance (Do not version it or sequence it)
     * 2.2 Get all the support archive files fromt the Website instance
     * 2.3 Create DAM indexes (Do not version this, but sequence it)
     * 2.4 Create support record and DAM indexes relationship (Do not version this, nothing to sequence in MM table)
     *
     *
     */
    public function stage2_1()
    {
        $this->syncAPI->setSyncMode(tx_st9fissync::SYNC_MODE_RESYNC);

        $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($this->syncAPI->getSyncLabels('stage.resync.stage2_1.start')));

        /**
         * Manual tracking of requests for resync
         */
        $requestDetailsArtifact = $this->requestDetailsPreProcess();

        //first get all queries
        $this->getSyncResultResponse('tx_st9fissync_service_handler','fetchRecordedActions');

        $resyncable = $this->syncAPI->getSyncResultResponseDTO()->getSyncResults();
        $fetchrecordedActionsRes = null;
        if ($resyncable) {
            $fetchrecordedActionsRes = $resyncable->get('resyncable');
        }

        $passReSyncedCount = 0;
        $failedReSyncedCount = 0;

        if ($fetchrecordedActionsRes && is_array($fetchrecordedActionsRes)) {

            $numOfObjectsToProcess = $this->syncAPI->getSyncLabels('stage.resync.numqueryobjects') . ' [' . count($fetchrecordedActionsRes) . ']';
            $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($numOfObjectsToProcess));

            if (count($fetchrecordedActionsRes) > 0) {

                $resultArtifact =  $this->syncAPI->getSyncServiceHandler()->replayActions($fetchrecordedActionsRes);
                $queryExecErrors = $resultArtifact->get('errors');
                $queryExecRes = $resultArtifact->get('res');

                $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($this->syncAPI->getSyncLabels('stage.resync.post.replayactions')));


                /**
                 * Manual tracking of requests for resync
                 */
                $requestDetailsArtifact->set('response_received_tstamp',  $this->syncAPI->getMicroTime());
                $requestDetailsArtifact->set('request_sent',  $this->SOAPClient->getLastRequest());
                $requestDetailsArtifact->set('response_received',  $this->SOAPClient->getLastResponse());
                $requestDetailsArtifact->set('remote_handle' , $this->syncAPI->getSyncResultResponseDTO()->getSyncLastRequestHandler());

                $versionedQueryCount = 0;

                foreach ($fetchrecordedActionsRes as $remoteRecordedAction) {

                    $requestRefQueryRecArtifact = t3lib_div::makeInstance('tx_st9fissync_request_dbversioning_query_mm');
                    $requestRefQueryRecArtifact->set('uid_foreign',$remoteRecordedAction['uid']);
                    //  $requestRefQueryRecArtifact->set('sysid', $this->remoteSystemId);
                    $requestRefQueryRecArtifact->set('isremote', 1);


                    /**
                     * -ve results handling
                     *
                     */
                    if ($queryExecErrors != null) {
                        $error_msg = $queryExecErrors->get($remoteRecordedAction['uid']);
                        $requestRefQueryRecArtifact->set('error_message',$error_msg);

                        /**
                         *
                         * Form notification messages for errors
                         */
                        $syncFromRemoteError = $error_msg . ' / ';
                        $syncFromRemoteError .= $this->syncAPI->getSyncLabels('stage.resync.querytracking.id') . '[' . $remoteRecordedAction['uid'] . '] / ';

                        $syncFromRemoteError .= $this->syncAPI->getSyncLabels('stage.resync.querytracking.details');

                        $syncFromRemoteError .= $this->syncAPI->getSyncLabels('stage.resync.querytracking.tablename') . '[' . $remoteRecordedAction['tablenames'] . '] / ';

                        $tableRowId = intval($remoteRecordedAction['uid_foreign']);

                        if ($tableRowId > 0) {

                            $syncFromRemoteError .= $this->syncAPI->getSyncLabels('stage.resync.querytracking.tablerowid') . '[' . $tableRowId . ']';

                        } else {

                            $syncFromRemoteError .= $this->syncAPI->getSyncLabels('stage.resync.querytracking.mmtablerowid');

                        }
                        $this->handleSyncError($syncFromRemoteError,tx_st9fissync_sync::STATUS_FAILURE);

                    }

                    $requestDetailsArtifact->addVersionedQueryArtifact($this->syncAPI->buildRequestRefQueryRecArtifact($requestRefQueryRecArtifact));
                    $versionedQueryCount++;
                }

                $requestDetailsArtifact->set('versionedqueries',$versionedQueryCount);
                $this->syncAPI->getSyncDBOperationsManager()->addSyncRequestDetails($requestDetailsArtifact);


                /**
                 * +ve results handling
                 *
                 */
                if ($queryExecRes != null) {
                    $qvIds = array();
                    for ($queryExecRes->rewind(); $queryExecRes->valid(); $queryExecRes->next()) {

                        $currentRes = $queryExecRes->current();
                        $currentKey = $queryExecRes->key();

                        /**
                         *
                         * form status messages for synced fail/pass
                         */
                        $syncFromRemoteMessage = $this->syncAPI->getSyncLabels('stage.resync.querytracking.id') . '[' . $currentKey . '] / ';
                        $syncFromRemoteMessage .= $this->syncAPI->getSyncLabels('stage.resync.querytracking.details');

                        $syncFromRemoteMessage .= $this->syncAPI->getSyncLabels('stage.resync.querytracking.tablename') . '[' . $fetchrecordedActionsRes[$currentKey]['tablenames'] . '] / ';
                        $tableRowId = intval($fetchrecordedActionsRes[$currentKey]['uid_foreign']);
                        if ($tableRowId > 0) {
                            $syncFromRemoteMessage .= $this->syncAPI->getSyncLabels('stage.resync.querytracking.tablerowid') . '[' . $tableRowId . ']';
                        } else {
                            $syncFromRemoteMessage .= $this->syncAPI->getSyncLabels('stage.resync.querytracking.mmtablerowid');
                        }


                        if ($currentRes['synced']) {
                            $qvIds[] = $currentKey;
                            $syncFromRemoteMessage = $this->syncAPI->getSyncLabels('stage.resync.querytracking.fromremotesuccess') . $syncFromRemoteMessage;
                            $passReSyncedCount++;
                        } else {
                            $syncFromRemoteMessage = $this->syncAPI->getSyncLabels('stage.resync.querytracking.fromremotefail') . $syncFromRemoteMessage;
                            $failedReSyncedCount++;
                        }

                        $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($syncFromRemoteMessage));
                    }
                    if (count($qvIds) >0 ) {
                        $this->getSyncResultResponse('tx_st9fissync_service_handler','setSyncIsSyncedTrackedQueries',$qvIds);

                    }
                }
            }
        } else {

            $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($this->syncAPI->getSyncLabels('stage.resync.queryobjects.res.invalid')));

        }

        $passReSyncedCountMessage = $this->syncAPI->getSyncLabels('stage.resync.querycount.pass') . '[' . $passReSyncedCount . ']';
        $failedReSyncedCountMessage = $this->syncAPI->getSyncLabels('stage.resync.querycount.fail'). '[' . $failedReSyncedCount . ']';
        $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($passReSyncedCountMessage));
        $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($failedReSyncedCountMessage));
        $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($this->syncAPI->getSyncLabels('stage.resync.stage2_1.finished')));

    }


    /**
     * Again a re-sync scenario to sync only support module items
     *
     * Goes like this:
     * 1.Fetch info about all files and queries related to syncable support ticket items from website instance
     * 2.Decide which support ticket to create and/or reject based on the following:
     * 2.1 If corresponding support ticket file if exists but is not transferred successully abort ticket creation
     * 2.1.1 File transfer errors can be of 2 kinds:
     * 		a. In communication fail
     * 		b. After transfer write/creation fail
     *
     *
     */
    public function stage2_2()
    {
        $this->syncAPI->setSyncMode(tx_st9fissync::SYNC_MODE_RESYNC);

        /**
         * Manual tracking of requests for resync
         */
        $requestDetailsArtifact = $this->requestDetailsPreProcess();

        $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($this->syncAPI->getSyncLabels('stage.resync.stage2_2.start')));

        //first get all queries
        $this->getSyncResultResponse('tx_st9fissync_service_handler','getSt9fisSupportRes');
        $resyncable = $this->syncAPI->getSyncResultResponseDTO()->getSyncResults();
        $st9fisSupportRes = null;
        if ($resyncable != null) {
            $st9fisSupportRes = $resyncable->get('resyncable');
        }

        /**
         *
         * Sample Support Result Set
         *
         * Array
         (
         [supportUploadFileInfo] => Array
         (
         [uploads/tx_st9fissupport/zipfile/95f8961959f778e14beaaf142204660c.zip] => Array
         (
         [__type] => file
         [__exists] => 1
         [file_name] => 95f8961959f778e14beaaf142204660c.zip
         [file_extension] => zip
         [file_title] => 95f8961959f778e14beaaf142204660c.zip
         [file_path] => uploads/tx_st9fissupport/zipfile/
         [file_path_relative] => uploads/tx_st9fissupport/zipfile/
         [file_accessable] =>
         [file_mtime] => 1362478068
         [file_ctime] => 1362478068
         [file_inode] => 12848887
         [file_size] => 5920600
         [file_owner] => 70
         [file_perms] => 33188
         [file_writable] => 1
         [file_readable] => 1
         [qvIds] => Array
         (
         [0] => 50
         [1] => 51
         )

         )

         [uploads/tx_st9fissupport/zipfile/db112c0c69913eb47bb9355c61038542.zip] => Array
         (
         [__type] => file
         [__exists] => 1
         [file_name] => db112c0c69913eb47bb9355c61038542.zip
         [file_extension] => zip
         [file_title] => db112c0c69913eb47bb9355c61038542.zip
         [file_path] => uploads/tx_st9fissupport/zipfile/
         [file_path_relative] => uploads/tx_st9fissupport/zipfile/
         [file_accessable] =>
         [file_mtime] => 1362478208
         [file_ctime] => 1362478208
         [file_inode] => 12849138
         [file_size] => 251104
         [file_owner] => 70
         [file_perms] => 33188
         [file_writable] => 1
         [file_readable] => 1
         [qvIds] => Array
         (
         [0] => 53
         [1] => 54
         )

         )

         )

         [supportUploadQueries] => Array
         (
         [50] => Array
         (
         [uid] => 50
         [query_text] => INSERT INTO tx_st9fissupport_domain_model_upload (files_raw,files,c.....
         [query_type] => 1
         [uid_foreign] => 81
         [tablenames] => tx_st9fissupport_domain_model_upload
         )

         [51] => Array
         (
         [uid] => 51
         [query_text] => UPDATE tx_st9fissupport_domain_model_upload SET confirmation='O:42:\"tx_st9fisutility_domain_model_emailcontent\":8:
         [query_type] => 3
         [uid_foreign] => 81
         [tablenames] => tx_st9fissupport_domain_model_upload
         )

         [53] => Array
         (
         [uid] => 53
         [query_text] => INSERT INTO tx_st9fissupport_domain_model_upload (files_raw,files,comment,customer,pid,crdate,tstamp,uid) VALUES ('uploads/tx_st9fissupport/zipfile/db112c0c69913eb47bb9355c61038542.zip','1',....
         [query_type] => 1
         [uid_foreign] => 83
         [tablenames] => tx_st9fissupport_domain_model_upload
         )

         [54] => Array
         (
         [uid] => 54
         [query_text] => UPDATE tx_st9fissupport_domain_model_upload SET confirmation='O:42:\"tx_st9fisutility_domain_model_emailcontent\":8:{s:10....
         [query_type] => 3
         [uid_foreign] => 83
         [tablenames] => tx_st9fissupport_domain_model_upload
         )

         )

         )
         *
         *
         *
         *
         *
         */


        /**
         * Manual tracking of requests for resync
         */
        $requestDetailsArtifact->set('response_received_tstamp',  $this->syncAPI->getMicroTime());
        $requestDetailsArtifact->set('request_sent',  $this->SOAPClient->getLastRequest());
        $requestDetailsArtifact->set('response_received',  $this->SOAPClient->getLastResponse());
        $requestDetailsArtifact->set('remote_handle' , $this->syncAPI->getSyncResultResponseDTO()->getSyncLastRequestHandler());

        if ($st9fisSupportRes && is_array($st9fisSupportRes)) {

            $supportUploadFileInfoList = $st9fisSupportRes['supportUploadFileInfo'];
            $supportUploadQueryList = $st9fisSupportRes['supportUploadQueries'];


            /**
             *
             * Logging count of support uploads queries & files
             */
            $supportFilesReSyncCountMessage = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.filecount') . '[' . count($supportUploadFileInfoList) . ']';
            $supportQueryReSyncCountMessage = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.querycount'). '[' . count($supportUploadQueryList) . ']';
            $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($supportFilesReSyncCountMessage));
            $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($supportQueryReSyncCountMessage));


            $supportTicketQVKeyMap = array();
            $newFilesAddToDAMIndex = array();
            $fileTransferArray = array();
            $totalPayLoad = 0;
            $batches = array();
            $i=0;
            $fileSetSize = 0;
            $fileBatchMaxSizeBytes = $this->syncAPI->getSyncConfigManager()->getSyncFileSetMaxSize();//1048576;9533012;//

            foreach ($supportUploadFileInfoList as $filePath => $fileInfo) {

                /**
                 *
                 * Logging and notification
                 *
                 */
                $toBeDAMIndexedSupportFileToProcess = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.file.proc');
                $toBeDAMIndexedSupportFileToProcess .= ' [' .  $filePath . '] / ';


                if ($fileInfo && is_array($fileInfo)) {
                    $toBeDAMIndexedSupportFileToProcess .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.file.size');
                    $toBeDAMIndexedSupportFileToProcess .= '[' . $fileInfo['file_size'] . '] / ';
                    $toBeDAMIndexedSupportFileToProcess .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.query.tracking.id');
                    $toBeDAMIndexedSupportFileToProcess .= '[' . implode(',', $fileInfo['qvIds']) . '] / ';
                } else {
                    $toBeDAMIndexedSupportFileToProcess .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.file.metainfo.null');
                    $this->handleSyncError($toBeDAMIndexedSupportFileToProcess);
                }

                $toBeDAMIndexedSupportFileToProcess .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.file.exists') . ' / ';

                if ($fileInfo['__exists'] > 0) {

                    $toBeDAMIndexedSupportFileToProcess .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.file.exists.yes');
                    $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($toBeDAMIndexedSupportFileToProcess));

                    $bytesWritten = 0;
                    $pullFilesArgsArray = array();
                    $fileSize = $fileInfo['file_size'];

                    if ($fileSize > $fileBatchMaxSizeBytes) {

                        $toBeDAMIndexedSupportFileToProcess .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.filetransfer.single');
                        $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($toBeDAMIndexedSupportFileToProcess));

                        $chunkedFileTransferError = false;

                        /**
                         * Here a single file is broken into chunks to transfer
                         */
                        for ($totalPayLoad = 0; $totalPayLoad <= $fileSize;  $totalPayLoad = $totalPayLoad + $fileBatchMaxSizeBytes) {
                            $pullFilesArgsArray[$filePath] = array('continue' => 1,
                                    'transferFilesArgs' => array(
                                            'filename' => $filePath,
                                            'use_include_path' => null,
                                            'context' => null,
                                            'offset' => $totalPayLoad,
                                            'maxlen' => $fileBatchMaxSizeBytes,
                                    )
                            );

                            $toBeDAMIndexedFileToProcessChunk = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.fileread.offset');
                            $toBeDAMIndexedFileToProcessChunk .= '[' . $totalPayLoad . '] / ';
                            $toBeDAMIndexedFileToProcessChunk .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.fileread.maxlen');
                            $toBeDAMIndexedFileToProcessChunk .= '[' . $fileBatchMaxSizeBytes . '] / ';
                            $toBeDAMIndexedFileToProcessChunk .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.filetransfer.start');
                            $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($toBeDAMIndexedSupportFileToProcess . $toBeDAMIndexedFileToProcessChunk));

                            $this->getSyncResultResponse('tx_st9fissync_service_handler','fetchFileData', $pullFilesArgsArray);

                            $syncSupportFileRemoteErrors = $this->syncAPI->getSyncResultResponseDTO()->getSyncErrors();
                            if($syncSupportFileRemoteErrors)
                                $chunkedFileTransferError = $syncSupportFileRemoteErrors->get($filePath);

                            if ($chunkedFileTransferError) {
                                //if error see below
                                $toBeDAMIndexedSupportFileToProcess .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.filetransfer.chunkerror');
                                $toBeDAMIndexedSupportFileToProcess .= '[' . $chunkedFileTransferError . ']';
                                break;
                            }

                            //if no error while file transfer progress

                            //chunked file data
                            $transferFileData =  $this->syncAPI->getSyncResultResponseDTO()->getSyncResults()->get($filePath);

                            $fileTransferArray[$filePath]['transferdata']['data'] = $transferFileData['fileTransferData'];
                            if ($totalPayLoad == 0) {
                                $fileTransferArray[$filePath]['transferdata']['cmd'] = 'w+';
                            } else {
                                $fileTransferArray[$filePath]['transferdata']['cmd'] = 'a+';
                            }
                            $fileTransferArray[$filePath]['metadata'] = $fileInfo;

                            //now write this chunk of file data
                            $writeFileChunkRes = $this->syncAPI->getSyncServiceHandler()->handleFileTransfer($fileTransferArray);

                            if (is_a($writeFileChunkRes, 'tx_lib_object')) {

                                //error handling
                                if (is_a($writeFileChunkRes->get('errors'), 'tx_lib_object')) {
                                    if ($writeFileChunkRes->get('errors')->get($filePath)) {
                                        $chunkedFileTransferError = $writeFileChunkRes->get('errors')->get($filePath);

                                        //if errors in any cycle, plain abort this file's transfer and move on
                                        //of course the corresponding support ticket query will not be synced,

                                        $toBeDAMIndexedSupportFileToProcess .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.filetransfer.chunkwriteerror');
                                        $toBeDAMIndexedSupportFileToProcess .= '[' . $chunkedFileTransferError . ']';
                                        break;
                                    }
                                }

                                if (is_a($writeFileChunkRes->get('bytes'), 'tx_lib_object')) {
                                    $bytesWritten += $writeFileChunkRes->get('bytes')->get($filePath);
                                    $toBeDAMIndexedFileToProcessChunk = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.filetransfer.byteswritten');
                                    $toBeDAMIndexedFileToProcessChunk .= '[' . $bytesWritten . ']';
                                    $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($toBeDAMIndexedSupportFileToProcess . $toBeDAMIndexedFileToProcessChunk));
                                }
                            }

                        }

                        if ($bytesWritten == $fileSize) {
                            //logger file sent
                            $newFilesAddToDAMIndex[] = $filePath;
                            foreach ($fileInfo['qvIds'] as $qvId) {
                                $supportTicketQVKeyMap['no_errors'][$qvId] = $qvId ;
                            }

                            $toBeDAMIndexedSupportFileToProcess .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.filetransfer.success');
                            $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($toBeDAMIndexedSupportFileToProcess));

                        } elseif ($chunkedFileTransferError || $bytesWritten != $fileSize) {
                            //log error
                            //if error corresponding tx_dam related query not to be synced, so unset it

                            $toBeDAMIndexedSupportFileToProcess .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.filetransfer.fail');
                            if ($bytesWritten != $fileSize) {
                                $toBeDAMIndexedSupportFileToProcess .= '[' . $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.filetransfer.byteswritten.error');
                                $toBeDAMIndexedSupportFileToProcess .=  $bytesWritten . '/' . $fileSize . ']';
                            }

                            $this->handleSyncError($toBeDAMIndexedSupportFileToProcess,tx_st9fissync_sync::STATUS_FAILURE);

                            foreach ($fileInfo['qvIds'] as $qvId) {
                                $supportTicketQVKeyMap['errors'][$qvId]['priority'] = tx_st9fissync_sync::ESCALATION_HIGH;
                                $supportTicketQVKeyMap['errors'][$qvId]['message'] = $chunkedFileTransferError ;

                                $toBeDAMIndexedSupportQueryTrackingIdRejectSync = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.query.tracking.id.reject');
                                $toBeDAMIndexedSupportQueryTrackingIdRejectSync .= '[' . $qvId . ']';
                                $this->handleSyncError($toBeDAMIndexedSupportQueryTrackingIdRejectSync,tx_st9fissync_sync::STATUS_FAILURE);
                            }

                        }

                    } else {

                        //transfer set of files (other than the use-case ones wherein a singular file itself is larger than the batchsize)
                        //in batches

                        $pullFilesArgsArray[$filePath] = array('continue' => 1,
                                'transferFilesArgs' => array(
                                        'filename' => $filePath,
                                        'use_include_path' => null,
                                        'context' => null,
                                        'offset' => 0,
                                        'maxlen' => $fileSize,
                                )
                        );

                        $fileSetSize += $fileSize;

                        if ($fileSetSize > $fileBatchMaxSizeBytes) {
                            $i++;
                            $fileSetSize = $fileSize;
                        }

                        //Setup the batches
                        $batches[$i][$filePath] = $pullFilesArgsArray[$filePath];

                        /**
                         * Add suppport file to transfer to batch transfer
                         * Log & notification
                         *
                         */
                        $toBeDAMIndexedSupportFileToProcess .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.file.addtobatch');
                        $toBeDAMIndexedSupportFileToProcess .= '[' . ($i +1) . ']';
                        $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($toBeDAMIndexedSupportFileToProcess));
                    }

                } else {
                    //if file does not exist
                    //(no need to / is there a need ?) Create those support tickets which have no corresponding files to be synced
                    //mark them for warning??

                    /**
                     * Logging and notification continues
                     */
                    $toBeDAMIndexedSupportFileToProcess .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.file.exists.no');
                    $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($toBeDAMIndexedSupportFileToProcess));

                    foreach ($fileInfo['qvIds'] as $qvId) {
                        if ($fileInfo['__exists'] == -1) {
                            //file is not there and so it does not, so should not be marked as error
                            $supportTicketQVKeyMap['no_errors'][$qvId] = $qvId ;

                            $supporFileNotUploaded= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.file.notuploaded');
                            $supporFileNotUploaded .= '[' . $filePath .'] / ' ;
                            $supporFileNotUploaded .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.id');
                            $supporFileNotUploaded .= '[' . $supportUploadQueryList[$qvId]['uid_foreign'] .'] /';
                            $supporFileNotUploaded .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.corresponding.tracking.id');
                            $supporFileNotUploaded .=  '[' . $qvId .']';

                            $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($supporFileNotUploaded));

                        } else {
                            $supportTicketQVKeyMap['errors'][$qvId]['priority'] = tx_st9fissync_sync::ESCALATION_HIGH;

                            $supporFileMissingError = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.file.remote.exists.no');
                            $supporFileMissingError .= '[' . $filePath .'] / ' ;
                            $supporFileMissingError .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.id.reject');
                            $supporFileMissingError .= '[' . $supportUploadQueryList[$qvId]['uid_foreign'] .'] /';
                            $supporFileMissingError .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.corresponding.tracking.id');
                            $supporFileMissingError .=  '[' . $qvId .']';

                            $toBeDAMIndexedSupportQueryTrackingIdRejectSync = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.query.tracking.id.reject');
                            $toBeDAMIndexedSupportQueryTrackingIdRejectSync .= '[' . $qvId . ']';

                            $supportTicketQVKeyMap['errors'][$qvId]['message'] = $supporFileMissingError . ' / ' . $toBeDAMIndexedSupportQueryTrackingIdRejectSync;

                            $this->handleSyncError($supporFileMissingError . ' / ' .$toBeDAMIndexedSupportQueryTrackingIdRejectSync,tx_st9fissync_sync::STATUS_FAILURE);

                        }
                    }
                }

            }

            /**
             * Batches of files continues, here is real file transfer
             *
             */

            $numOfSupportFilesBatchesToProcess = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.numfilebatches') . ' [' . count($batches) . ']';
            $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($numOfSupportFilesBatchesToProcess));

            foreach ($batches as $batchId => $batchPacket) {

                /**
                 *
                 * Batch Id
                 */
                $batchToProcess = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.file.batch.id') . ' [' . ($batchId + 1). ']';
                $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($batchToProcess));

                $this->getSyncResultResponse('tx_st9fissync_service_handler','fetchFileData', $batchPacket);

                $filesTransferError = $this->syncAPI->getSyncResultResponseDTO()->getSyncErrors();

                //this batch files data
                $transferFilesData =  $this->syncAPI->getSyncResultResponseDTO()->getSyncResults();

                $fileTransferArray = array();

                foreach ($transferFilesData as $filePath => $transferFileData) {

                    $fileTransferArray[$filePath]['transferdata']['data'] = $transferFileData['fileTransferData'];
                    $fileTransferArray[$filePath]['transferdata']['cmd'] = 'w+';
                    $fileTransferArray[$filePath]['metadata'] = $supportUploadFileInfoList[$filePath];

                    $toBeDAMIndexedSupportFileToProcess = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.file.proc');
                    $toBeDAMIndexedSupportFileToProcess .= ' [' .  $filePath . '] / ';

                    if ($supportUploadFileInfoList[$filePath] && is_array($supportUploadFileInfoList[$filePath])) {
                        $toBeDAMIndexedSupportFileToProcess .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.file.size');
                        $toBeDAMIndexedSupportFileToProcess .= '[' . $supportUploadFileInfoList[$filePath]['file_size'] . '] / ';
                        $toBeDAMIndexedSupportFileToProcess .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.query.tracking.id');
                        $toBeDAMIndexedSupportFileToProcess .= '[' . implode(',', $supportUploadFileInfoList[$filePath]['qvIds']) . '] / ';
                    } else {
                        $toBeDAMIndexedSupportFileToProcess .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.file.metainfo.null');
                        $this->handleSyncError($toBeDAMIndexedSupportFileToProcess);
                    }

                    $toBeDAMIndexedSupportFileToProcess = $batchToProcess . ' / ' . $toBeDAMIndexedSupportFileToProcess;

                    //now write this file's data
                    $fileRes = $this->syncAPI->getSyncServiceHandler()->handleFileTransfer($fileTransferArray);

                    if ($fileRes && ($fileRes instanceof tx_lib_object )) {

                        /**
                         * post-processing for Errors and result handling
                         *
                         */

                        $positiveResults = true;

                        //error handling
                        if (is_a($fileRes->get('errors'), 'tx_lib_object')) {
                            if ($fileRes->get('errors')->get($filePath)) {
                                $fileWriteError = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.filetransfer.filewriteerror');
                                $fileWriteError .= '[' . $fileRes->get('errors')->get($filePath) . ']';

                                foreach ($supportUploadFileInfoList[$filePath]['qvIds'] as $qvId) {
                                    $supportTicketQVKeyMap['errors'][$qvId]['priority'] = tx_st9fissync_sync::ESCALATION_HIGH;
                                    $supportTicketQVKeyMap['errors'][$qvId]['message'] = $fileWriteError ;
                                }

                                /**

                                * Logging and notification continues

                                */
                                $toBeDAMIndexedSupportFileToProcess .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.filetransfer.batcherror');
                                $this->handleSyncError($toBeDAMIndexedSupportFileToProcess . ' / ' . $fileWriteError, tx_st9fissync_sync::STATUS_FAILURE);

                            }

                            $positiveResults = false ;
                        }

                        if (is_a($fileRes->get('bytes'), 'tx_lib_object')) {

                            $fileResults = $fileRes->get('bytes');

                            if ($fileResults->offsetExists($filePath) && $supportUploadFileInfoList[$filePath]['file_size'] != $fileResults->get($filePath)) {

                                $bytesWrittenError =  $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.filetransfer.byteswritten.error');
                                $bytesWrittenError .=  $supportUploadFileInfoList[$filePath]['file_size']  . ' / ' . $fileResults->get($filePath);
                                $toBeDAMIndexedSupportFileToProcess .= '[' . $bytesWrittenError . ']';
                                $this->handleSyncError($toBeDAMIndexedSupportFileToProcess,tx_st9fissync_sync::STATUS_FAILURE);

                                foreach ($supportUploadFileInfoList[$filePath]['qvIds'] as $qvId) {
                                    $supportTicketQVKeyMap['errors'][$qvId]['priority'] = tx_st9fissync_sync::ESCALATION_HIGH;
                                    //$supportTicketQVKeyMap['errors'][$qvId]['message'] = $bytesWrittenError ;

                                    $supporFileBytesWrittenError = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.id.reject');
                                    $supporFileBytesWrittenError .= '[' . $supportUploadQueryList[$qvId]['uid_foreign'] .'] /';
                                    $supporFileBytesWrittenError .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.corresponding.tracking.id');
                                    $supporFileBytesWrittenError .=  '[' . $qvId .']';

                                    $toBeDAMIndexedSupportQueryTrackingIdRejectSync = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.query.tracking.id.reject');
                                    $toBeDAMIndexedSupportQueryTrackingIdRejectSync .= '[' . $qvId . ']';

                                    $supportTicketQVKeyMap['errors'][$qvId]['message'] = $supporFileBytesWrittenError .  ' / ' . $toBeDAMIndexedSupportQueryTrackingIdRejectSync;

                                    $this->handleSyncError($supporFileBytesWrittenError . ' / ' .$toBeDAMIndexedSupportQueryTrackingIdRejectSync,tx_st9fissync_sync::STATUS_FAILURE);


                                }



                            } else {
                                $newFilesAddToDAMIndex[] = $filePath;
                                foreach ($supportUploadFileInfoList[$filePath]['qvIds'] as $qvId) {
                                    $supportTicketQVKeyMap['no_errors'][$qvId] = $qvId ;
                                }

                                $toBeDAMIndexedSupportFileToProcess .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.filetransfer.success');
                                $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($toBeDAMIndexedSupportFileToProcess));

                            }
                        }

                    }
                }
            }

            //Add files to DAM
            //Later
            //add support item
            //add relationship with support item to DAM
            //add this suppor


            $toBeDAMIndexedSupportFilesCount = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.files.todamindex.count');
            $toBeDAMIndexedSupportFilesCount .= '[' . count($newFilesAddToDAMIndex) . ']';
            $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($toBeDAMIndexedSupportFilesCount));

            $toBeDAMIndexedSupportFilesList = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.files.todamindex');
            $toBeDAMIndexedSupportFilesList .= implode(',', $newFilesAddToDAMIndex);
            $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($toBeDAMIndexedSupportFilesList));

            /**
             *
             * Sample $newFilesAddToDAMIndex
             *
             * Array
             (
             [0] => uploads/tx_st9fissupport/zipfile/57d712ae4b3799dd865dd668e52957c0.zip
             [1] => uploads/tx_st9fissupport/zipfile/198847875f5b1dd2111efa01c4cc012b.zip
             [2] => 0
             )
             *
             *
             *
             *
             *
             *
             */
            $indexedFilesRes = $this->syncAPI->indexFiles($newFilesAddToDAMIndex);


            /**
             * Demo $indexedFilesRes --
             *
             * Array
             (
             [0] => Array
             (
             [uid] => 486
             [title] => 57d712ae4b3799dd865dd668e52957c0
             [file_name] => 57d712ae4b3799dd865dd668e52957c0.zip
             [file_path] => uploads/tx_st9fissupport/zipfile/
             [reindexed] => 2
             )

             [1] => Array
             (
             [uid] => 488
             [title] => 198847875f5b1dd2111efa01c4cc012b
             [file_name] => 198847875f5b1dd2111efa01c4cc012b.zip
             [file_path] => uploads/tx_st9fissupport/zipfile/
             [reindexed] => 2
             )

             )
             *
             *
             *
             *
             */

            $successfullyDAMIndexedSupportFilesCount = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.files.damindex.successful.count');
            $successfullyDAMIndexedSupportFilesCount .= '[' . count($indexedFilesRes) . ']';
            $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($successfullyDAMIndexedSupportFilesCount));

            foreach ($indexedFilesRes as $indexedFile) {
                $successfullyDAMIndexedSupportFilesList = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.files.damindex.successful');
                $successfullyDAMIndexedSupportFilesList .= '[' . $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.files.damindex.id') . $indexedFile['uid'] . ' / ';
                $successfullyDAMIndexedSupportFilesList .=  $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.files.damindex.title') . $indexedFile['title'] . ' / ';
                $successfullyDAMIndexedSupportFilesList .=  $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.files.damindex.file_name') . $indexedFile['file_name'] . ' / ';
                $successfullyDAMIndexedSupportFilesList .=  $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.files.damindex.file_path') . $indexedFile['file_path'] . ' / ';
                $successfullyDAMIndexedSupportFilesList .=  $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.files.damindex.status') . $indexedFile['reindexed'] . ']';
                $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($successfullyDAMIndexedSupportFilesList));
            }


            $supportUploadQueryListFiltered = $supportUploadQueryList;

            //filter support upload query
            //rejecting the queries, that are not going to be executed locally


            if (count($supportTicketQVKeyMap['errors']) > 0) {
                foreach ($supportTicketQVKeyMap['errors'] as $key => $valArr) {

                    $supportQueryObjectsRejectMessage = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.item.reject.final');
                    $supportQueryObjectsRejectMessage .= '[' . $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.query.tracking.id') . $key;
                    $supportQueryObjectsRejectMessage .=  ' / ' . $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.item.id') . $supportUploadQueryListFiltered[$key]['uid_foreign'] . ' ]';

                    unset($supportUploadQueryListFiltered[$key]);
                    $this->handleSyncError($supportQueryObjectsRejectMessage, tx_st9fissync_sync::STATUS_FAILURE);
                }
            }

            //execute those queries which have no file issues
            $resultArtifact =  $this->syncAPI->getSyncServiceHandler()->replayActions($supportUploadQueryListFiltered);

            $passReSyncedSupportItemsCount = 0;
            $failedReSyncedSupportItemsCount = 0;

            if ($resultArtifact != null) {

                $queryExecErrors = $resultArtifact->get('errors');
                $queryExecRes = $resultArtifact->get('res');

                /**
                 * Manual tracking of requests for resync
                 * Now add the requests tracked to repos
                 * Files fetching request/response not considered to be request
                 */

                $versionedQueryCount = 0;
                foreach ($supportUploadQueryList as $remoteSupportUploadAction) {

                    $requestRefQueryRecArtifact = t3lib_div::makeInstance('tx_st9fissync_request_dbversioning_query_mm');
                    $requestRefQueryRecArtifact->set('uid_foreign',$remoteSupportUploadAction['uid']); //tracking id from remote for support
                    // $requestRefQueryRecArtifact->set('sysid', $this->remoteSystemId);
                    $requestRefQueryRecArtifact->set('isremote', 1);

                    $error_message = '';
                    if ($queryExecErrors != null) {
                        $error_message .= $queryExecErrors->get($remoteSupportUploadAction['uid']);
                    }

                    if (isset($supportTicketQVKeyMap['errors'][$remoteSupportUploadAction['uid']])) {
                        $error_message .= $supportTicketQVKeyMap['errors'][$remoteSupportUploadAction['uid']]['message'];
                    }

                    if ($error_message != '') {
                        $reSyncSupportErrorMessage = 'Re-Sync Error: '.	$error_message .	', support ticket version/tracking id: [' . $remoteSupportUploadAction['uid'] . ']';
                    }

                    $requestRefQueryRecArtifact->set('error_message',$error_message);
                    //	$this->handleSyncError($reSyncSupportErrorMessage,tx_st9fissync_sync::STATUS_FAILURE);

                    $requestDetailsArtifact->addVersionedQueryArtifact($this->syncAPI->buildRequestRefQueryRecArtifact($requestRefQueryRecArtifact));
                    $versionedQueryCount++;
                }

                $requestDetailsArtifact->set('versionedqueries',$versionedQueryCount);
                $this->syncAPI->getSyncDBOperationsManager()->addSyncRequestDetails($requestDetailsArtifact);


                /**
                 * -ve results handling
                 *
                 */
                if ($queryExecErrors != null) {
                    for ($queryExecErrors->rewind(); $queryExecErrors->valid(); $queryExecErrors->next()) {
                        $qvKey = $queryExecErrors->key();
                        $currentError = 'Error creating Support item - ' . $supportUploadQueryList[$qvKey]['uid_foreign'] . ' <br> ';
                        $currentError .= ' / Query Tracking ID - [' . $qvKey . ']';
                        $currentError .= ' / Error Details - ' . $queryExecErrors->current() . ' <br> ';
                        $this->handleSyncError($currentError,tx_st9fissync_sync::STATUS_FAILURE);
                    }
                }

                /**
                 * +ve results handling
                 *
                 */
                if ($queryExecRes != null) {
                    $qvIds = array();
                    for ($queryExecRes->rewind(); $queryExecRes->valid(); $queryExecRes->next()) {

                        $currentRes = $queryExecRes->current();
                        $currentKey = $queryExecRes->key();

                        /**
                         *
                         * form status messages for synced fail/pass
                         */
                        $syncSupportFromRemoteMessage = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.query.tracking.id') . '[' . $currentKey . '] / ';
                        $syncSupportFromRemoteMessage .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.query.tracking.details');
                        $syncSupportFromRemoteMessage .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.query.tracking.tablename') . '[' . $supportUploadQueryList[$currentKey]['tablenames'] . '] / ';
                        $tableRowId = intval($supportUploadQueryList[$currentKey]['uid_foreign']);
                        if ($tableRowId > 0) {
                            $syncSupportFromRemoteMessage .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.query.tracking.tablerowid') . '[' . $tableRowId . ']';
                        } else {
                            $syncSupportFromRemoteMessage .= $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.query.tracking.mmtablerowid');
                        }

                        if ($currentRes['synced']) {
                            $passReSyncedSupportItemsCount++;
                            $qvId = $currentKey;
                            $syncSupportFromRemoteMessage = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.query.tracking.fromremotesuccess') . $syncSupportFromRemoteMessage;

                            $setSyncIsSynced = true;

                            foreach ($supportUploadFileInfoList as $filePath => $inFo) {

                                foreach ($inFo['qvIds'] as $qvIdOther) {

                                    if ($qvIdOther == $qvId) {

                                        foreach ($indexedFilesRes as $indexedItem) {

                                            $filePathOther = $indexedItem['file_path'] . $indexedItem['file_name'];

                                            if ($filePathOther == $filePath) {
                                                $uid_local = $indexedItem['uid'];

                                                $supportItemsDAMMMRef = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.item.dam.mmref');
                                                $supportItemsDAMMMRef .= ' / ' . $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.file.dam.index');
                                                $supportItemsDAMMMRef .= '[' . $uid_local . ']';
                                                $supportItemsDAMMMRef .= ' / ' . $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.item.id');
                                                $supportItemsDAMMMRef .= '[' . $supportUploadQueryList[$qvId]['uid_foreign'] . ']';


                                                $relationStatus = $this->syncAPI->getSyncDBOperationsManager()->createSupportUploadsReferences($uid_local, $supportUploadQueryList[$qvId]['uid_foreign'],1);

                                                if ($relationStatus > 0) {
                                                    //mm ref new
                                                    $supportItemsDAMMMRef .=  ' / ' . $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.item.dam.mmref.pass');
                                                    $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($supportItemsDAMMMRef));


                                                } elseif ($relationStatus == -1) {
                                                    //mm ref existing
                                                    $supportItemsDAMMMRef .= ' / ' . $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.item.dam.mmref.alreadyexists');
                                                    $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($supportItemsDAMMMRef));

                                                } else {
                                                    //mm ref creation fail
                                                    $setSyncIsSynced = false;
                                                    $supportItemsDAMMMRef .= ' / ' . $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.item.dam.mmref.fail');
                                                    $this->handleSyncError($supportItemsDAMMMRef);
                                                }

                                            }
                                        }
                                    }
                                }
                            }

                            if ($setSyncIsSynced) {
                                $qvIds[] = $qvId;

                            } else {
                                // mm ref not created successfully for qvID
                            }

                        } else {
                            // support ticket related query not synced locally
                            $syncSupportFromRemoteMessage = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.query.tracking.fromremotefail') . $syncSupportFromRemoteMessage;
                            $failedReSyncedSupportItemsCount++;
                        }

                        $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($syncSupportFromRemoteMessage));
                    }
                    if (count($qvIds) >0 ) {
                        $setSyncIsSyncedRes = $this->getSyncResultResponse('tx_st9fissync_service_handler','setSyncIsSyncedTrackedQueries',$qvIds);
                    }
                }
            } else {
                //result artifact null

                $this->syncAPI->addInfoMessage($this->getSyncMesgPrefixed($this->syncAPI->getSyncLabels('stage.resync.st9fissupport.queryobjects.res.null')));

            }

        } else {

            $this->handleSyncError($this->syncAPI->getSyncLabels('stage.resync.st9fissupport.queryobjects.res.invalid'), tx_st9fissync_sync::STATUS_FAILURE);

        }

        $passReSyncedSupportItemsCountMessage = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.querycount.pass') . '[' . $passReSyncedSupportItemsCount . ']';
        $failedReSyncedSupportItemsCountMessage = $this->syncAPI->getSyncLabels('stage.resync.st9fissupport.querycount.fail'). '[' . $failedReSyncedSupportItemsCount . ']';
        $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($passReSyncedSupportItemsCountMessage));
        $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($failedReSyncedSupportItemsCountMessage));
        $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed($this->syncAPI->getSyncLabels('stage.resync.stage2_2.finished')));
    }

    /**
     * Is Sync process allowed to be launched
     *
     * 1.check if file/db lock exists
     * 2.check if a secured SOAP channel exists
     *
     * If both true then proceed with sync launch
     *
     * @return boolean
     */
    public function isAllowed()
    {
        $allowed = false;

        if ($this->procId) {

            //1. write whatever logic here to block another sync process
            //check for another running sync process
            $activeSyncProcesses = $this->syncAPI->getSyncDBOperationsManager()->getActiveSyncProcesses($this->procId);

            if ($activeSyncProcesses && count($activeSyncProcesses) > 0) {

                //active sync found
                foreach ($activeSyncProcesses as $procId =>  $activeSyncProc) {
                    $currentlyRunningSyncProc = $this->syncAPI->getSyncLabels('stage.sync.proc.active') . '[' . $procId .  ']';
                    $this->handleSyncError($currentlyRunningSyncProc);
                }

                $allowed = false;

            } else {
                //2. check if SOAP channel secured
                try {
                    $this->SOAPClient = $this->syncAPI->getSyncSOAPClient();
                    if ($this->SOAPClient == null) {
                        throw new tx_st9fissync_exception($this->syncAPI->getSyncLabels('stage.sync.proc.soap.client.initfail'));
                    }
                    $this->syncProcNumOfReq++;
                    if ($this->syncAPI->isSOAPSecured($this->SOAPClient->isSOAPSecure())) {
                        //refresh and get a secured SOAP channel
                        $this->SOAPClient = $this->syncAPI->getSyncSOAPClient(true);
                        $allowed = true;
                    } else {
                        unset($this->SOAPClient);

                        $channelNotSecureMessage = $this->syncAPI->getSyncLabels('stage.sync.proc.soap.channel.notsecured') . '[' . $this->procId . ']. ';
                        $channelNotSecureMessage .= $this->syncAPI->getSyncLabels('stage.sync.proc.caretakerinstance.misconfig');
                        $this->handleSyncError($channelNotSecureMessage,tx_st9fissync_sync::STATUS_ABORT);

                        $allowed = false;
                    }
                } catch (SoapFault $s) {

                    $errorMesg = $this->syncAPI->getSyncLabels('stage.sync.proc.eligibility.error') . '[' . $s->faultcode . '] ' . $s->faultstring . '/';
                    $errorMesg .=  $this->syncAPI->getSyncLabels('stage.sync.proc.id') . '[' .$this->procId . ']';
                    $this->handleSyncError($errorMesg,tx_st9fissync_sync::STATUS_FAILURE);

                    $allowed = false;
                } catch (tx_st9fissync_exception $e) {

                    $errorMesg = $this->syncAPI->getSyncLabels('stage.sync.proc.eligibility.error') . $e->getMessage();
                    $errorMesg .=  $this->syncAPI->getSyncLabels('stage.sync.proc.id') . '[' .$this->procId . ']';
                    $this->handleSyncError($errorMesg,tx_st9fissync_sync::STATUS_FAILURE);

                    $allowed = false;
                }
            }
        }

        return $allowed;
    }

    /**
     *
     * Triggers the sync & other startup tasks, basically a single sync cycle
     *
     * Also a controller function for the whole sync process
     *
     * @return boolean
     */
    public function launch()
    {
        $this->syncAPI->initializeSyncErrorContext($this, 'handleSyncRuntimeErrors', 'handleFatalSyncRuntimeError');

        $this->syncAPI->setSyncT3SOAPWSDLCacheEnable();

        //$this->origEnvironMemLimit = ini_get('memory_limit');
        //ini_set('memory_limit', '-1');
        $this->syncAPI->setSyncRuntimeMemoryLimit();

        //$this->origEnvironMaxExecTime = ini_get('max_execution_time');
        //ini_set('max_execution_time', 36000);
        $this->syncAPI->setSyncRuntimeMaxExecTime();

        //$this->origEnvironSocketTimeout = ini_get('default_socket_timeout');
        //ini_set('default_socket_timeout', 6000);

        if ($this->isAllowed()) {
            //proceed
            //secure SOAP channel ready, if not secured sync cannot be launched and
            //would have been already evaluated in the isAllowed() method

            try {

                $this->remoteSystemId = $this->getSyncResultResponse('tx_st9fissync_service_handler','getSystemId');
                $updatedProcInfo = array('syncproc_dest_sysid' => $this->remoteSystemId,
                        'syncproc_stage' => tx_st9fissync_sync::STAGE_RUNNING,
                        'requests' => $this->syncProcNumOfReq,
                );
                $this->updateSyncProcState($updatedProcInfo);


                //Get all syncable items for intranet --> website, exclude file processing/ DAM indexes transfer
                $this->stage1_1();

                //Get all syncable items for intranet --> website, only file processing/ DAM indexes transfer
                $this->stage1_2();

                //Purge all remote Caches
                if ($this->_getSyncSessionId() && $this->clearRemoteCache) {
                    $res = $this->SOAPClient->purgeVariousCaches($this->_getSyncSessionId());
                    $this->syncAPI->addOkMessage($this->getSyncMesgPrefixed(
                            $this->syncAPI->getSyncLabels('stage.sync.clear.remote.cache')));
                }

                //Get all syncable items for intranet <-- website, exclude file processing / DAM indexes transfer / 'st9fissupport' items
                $this->stage2_1();

                //Get all syncable items for intranet <-- website, only file/query processing for 'st9fissupport'
                $this->stage2_2();

                $this->syncAPI->setSyncMode(null);

            } catch (tx_st9fissync_exception $e) {
                $syncProcErrorMesg = $this->syncAPI->getSyncLabels('stage.sync.proc.error') . $e->getMessage();
                $this->handleSyncError($syncProcErrorMesg,tx_st9fissync_sync::STATUS_FAILURE);
            }

        } else {
            //launch not allowed
            //must send email here
            //various reasons can include SOAP channel not secured
            // or simply another sync process in progress
            $syncProcBlocked = $this->syncAPI->getSyncLabels('stage.sync.proc.blocked') ;
            $this->handleSyncError($syncProcBlocked, tx_st9fissync_sync::STATUS_ABORT);
        }

        $this->syncAPI->notify();


        //Current Sync marked as finished
        $updatedProcInfo = array(
                'syncproc_endtime' => $this->syncAPI->getMicroTime(),
                'syncproc_stage' => tx_st9fissync_sync::STAGE_FINISHED,
                'requests' => $this->syncProcNumOfReq,
        );
        $this->updateSyncProcState($updatedProcInfo);

        define('SYNC_EXECUTION_COMPLETE', true);

        $this->syncAPI->resetToOrigSOAPWSDLCacheEnable();
        $this->syncAPI->resetToOrigRuntimeMemoryLimit();
        $this->syncAPI->resetToOrigRuntimeMaxExecTime();

        $this->syncAPI->resetErrorContext();

        //ini_set('memory_limit', $this->origEnvironMemLimit);
        //ini_set('max_execution_time', $this->origEnvironMaxExecTime );
        //ini_set('default_socket_timeout', $this->origEnvironSocketTimeout);


    }

    /**
     * Error handler for the sync process
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param int    $errline
     * @param array  $errcontext
     *
     * @return void
     */
    public function handleSyncRuntimeErrors($errno, $errstr, $errfile = null, $errline = null, array $errcontext = null)
    {
        $syncRunTimeException = $this->syncAPI->handlePhpErrors($errno, $errstr, $errfile, $errline, $errcontext);
        $syncRunTimeException = $this->syncAPI->getSyncLabels('stage.sync.proc.error') . '[' . $syncRunTimeException->getMessage() . ']';
        $this->handleSyncError($syncRunTimeException,tx_st9fissync_sync::STATUS_FAILURE);
    }

    /**
     * Sync shutdown function
     */
    public function handleFatalSyncRuntimeError()
    {
        $syncFatalException = $this->syncAPI->handleFatalError();

        //	Exception

        if ($syncFatalException instanceof tx_st9fissync_exception) {

            $syncFatalExceptionMesg = $this->syncAPI->getSyncLabels('stage.sync.proc.fatalerror') . '[' . $syncFatalException->getMessage() . ']';

            $this->handleSyncError($syncFatalExceptionMesg,tx_st9fissync_sync::STATUS_FATAL);

            $this->syncAPI->notify();

            if (!defined('SYNC_EXECUTION_COMPLETE')) {

                $updatedProcInfo = array(
                        'syncproc_endtime' => $this->syncAPI->getMicroTime(),
                        'syncproc_stage' => tx_st9fissync_sync::STAGE_FINISHED,
                        'syncproc_status' => tx_st9fissync_sync::STATUS_FATAL,
                        'requests' => $this->syncProcNumOfReq,
                );
                $this->updateSyncProcState($updatedProcInfo);

                $this->syncAPI->resetToOrigSOAPWSDLCacheEnable();
                $this->syncAPI->resetToOrigRuntimeMemoryLimit();
                $this->syncAPI->resetToOrigRuntimeMaxExecTime();

                $this->syncAPI->resetErrorContext();

                //ini_set('memory_limit', $this->origEnvironMemLimit);
                //ini_set('max_execution_time', $this->origEnvironMaxExecTime );
                //ini_set('default_socket_timeout', $this->origEnvironSocketTimeout);
            }

        }

    }

}

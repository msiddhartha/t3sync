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
 * Sync service class for 'st9fissync' extension.
 *
 * @author	André Spindler <sp@studioneun.de>
* @package	TYPO3
* @subpackage	st9fissync
*/

class tx_st9fissync_service_handler
{
    /**
     * Returns sync system Id
     *
     * @return string
     */
    public function getSystemId()
    {
        return tx_st9fissync::getInstance()->getSyncConfigManager()->getSystemId();
    }

    /**
     * @param array $replayActions
     *
     * @return mixed
     */
    public function replayActions($replayActions)
    {
        $replayActionsResultsCollection = null;

        foreach ($replayActions as $actionArtifact) {

            switch ($actionArtifact['query_type']) {
                case tx_st9fissync_dbversioning_t3service::INSERT:
                    $replayActionsResultsCollection = tx_st9fissync::getInstance()->getSyncResultResponseDTO()->acceptResultResponseDTO($this->replayInsert($actionArtifact),$actionArtifact['uid']);
                    break;
                case tx_st9fissync_dbversioning_t3service::MULTIINSERT:
                    $replayActionsResultsCollection = tx_st9fissync::getInstance()->getSyncResultResponseDTO()->acceptResultResponseDTO($this->replayMultiInsert($actionArtifact),$actionArtifact['uid']);
                    break;
                case tx_st9fissync_dbversioning_t3service::UPDATE:
                    $replayActionsResultsCollection = tx_st9fissync::getInstance()->getSyncResultResponseDTO()->acceptResultResponseDTO($this->replayUpdate($actionArtifact),$actionArtifact['uid']);
                    break;
                case tx_st9fissync_dbversioning_t3service::DELETE:
                    $replayActionsResultsCollection = tx_st9fissync::getInstance()->getSyncResultResponseDTO()->acceptResultResponseDTO($this->replayDelete($actionArtifact),$actionArtifact['uid']);
                    break;
                case tx_st9fissync_dbversioning_t3service::TRUNCATE:
                    $replayActionsResultsCollection = tx_st9fissync::getInstance()->getSyncResultResponseDTO()->acceptResultResponseDTO($this->replayTruncate($actionArtifact),$actionArtifact['uid']);
                    break;
                default:
                    $unrecognizedAction = "Unrecognized action replay request: [" . $actionArtifact['query_type']."] for versioned artifact Id: [" . $actionArtifact['uid'] ."]";
                    // log ??
                    $replayActionsResultsCollection = tx_st9fissync::getInstance()->getSyncResultResponseDTO()->acceptResultResponseDTO(array('errors' => $unrecognizedAction),$actionArtifact['uid']);
                    break;

            }
        }

        return tx_st9fissync::getInstance()->getSyncResultResponseDTO()->getResultResponseDTO();

    }

    /**
     * For all the replay Query type methods we can have have specific processing, so separation of concerns
     * Currently all have same implementation and have been handled in a generic manner in method replayQuery(..)
     *
     */

    public function replayQuery($query, $uids = null, $tableNames = null)
    {
        $res = array();

        try {
            tx_st9fissync::getInstance()->getSyncDBObject()->sql_query($query);
            if (tx_st9fissync::getInstance()->getSyncDBObject()->sql_errno() > 0) {
                throw new tx_st9fissync_t3soap_expectationfailed_exception("Replay query error: [" . tx_st9fissync::getInstance()->getSyncDBObject()->sql_errno() . "] - " . tx_st9fissync::getInstance()->getSyncDBObject()->sql_error());
            } else {
                $res['res'] = array('synced' => 1);
            }

        } catch (tx_st9fissync_t3soap_expectationfailed_exception $ex) {

            $errorMesg = $ex->getMessage();

            if ($uids) {
                $errorMesg .= ' / UID(s): [';
                if (is_array($uids)) {
                    $errorMesg .= implode(',', $uids);
                } else {
                    $errorMesg .= $uids;
                }
                $errorMesg .=  ']';
            }

            if ($tableNames) {
                $errorMesg .= ' / Table Name(s): [';
                if (is_array($tableNames)) {
                    $errorMesg .= implode(',', $tableNames);
                } else {
                    $errorMesg .= $tableNames;
                }
                $errorMesg .=  ']';
            }

            $res['errors'] = $errorMesg;
            $res['res'] = array('synced' => 0);

            //log ??

        }

        return $res;
    }

    /**
     * @param array $actionArtifact
     *
     * @return array $res
     */
    public function replayInsert($actionArtifact)
    {
        return $this->replayQuery($actionArtifact['query_text'], $actionArtifact['uid_foreign'], $actionArtifact['tablenames']);
    }

    /**
     * @param array $actionArtifact
     *
     * @return array $res
     */
    public function replayMultiInsert($actionArtifact)
    {
        return $this->replayQuery($actionArtifact['query_text'], $actionArtifact['uid_foreign'], $actionArtifact['tablenames']);
    }

    /**
     * @param array $actionArtifact
     *
     * @return array $res
     */
    public function replayUpdate($actionArtifact)
    {
        return $this->replayQuery($actionArtifact['query_text'], $actionArtifact['uid_foreign'], $actionArtifact['tablenames']);
    }

    /**
     * @param array $actionArtifact
     *
     * @return array $res
     */
    public function replayDelete($actionArtifact)
    {
        return $this->replayQuery($actionArtifact['query_text'], $actionArtifact['uid_foreign'], $actionArtifact['tablenames']);
    }

    /**
     * @param array $actionArtifact
     *
     * @return array $res
     */
    public function replayTruncate($actionArtifact)
    {
        return $this->replayQuery($actionArtifact['query_text'], $actionArtifact['uid_foreign'], $actionArtifact['tablenames']);
    }

    /**
     *
     * @param array $damQVMapArray
     *
     * @return mixed
     */
    public function confirmSyncEligibleDAMRes($damQVMapArray = array())
    {
        foreach ($damQVMapArray as $qvKey => $damQVMap) {
            tx_st9fissync::getInstance()->getSyncResultResponseDTO()->acceptResultResponseDTO($res['res'] = array('eligibility' => tx_st9fissync::getInstance()->isSyncEligibleByDAMTransferRules($damQVMap)),$qvKey);
        }

        return tx_st9fissync::getInstance()->getSyncResultResponseDTO()->getResultResponseDTO();

    }

    /**
     *
     * @param array $fileTransferArray
     *
     * @return array
     */
    public function handleFileTransfer($fileTransferArray)
    {
        $currentIndex = 0;

        foreach ($fileTransferArray as $index => $details) {

            $currentIndex = $index;
            $fileDirPath = '';
            $filePath = '';

            try {
                $fileData = base64_decode($details['transferdata']['data']);
                $filePath = tx_dam::file_absolutePath($details['metadata']);

                $fileDirPath = tx_dam::file_dirname($filePath);
                $dirExists = false;
                if (!is_dir($fileDirPath)) {
                    $dirExists = mkdir($fileDirPath, octdec($GLOBALS['TYPO3_CONF_VARS']['BE']['folderCreateMask']), true);
                    if ($dirExists) {
                        t3lib_div::fixPermissions($fileDirPath);
                    }
                } else {
                    $dirExists = true;
                }

                if ($dirExists) {
                    $fp=fopen($filePath,$details['transferdata']['cmd']);

                    ///tx_st9fissync::getInstance()->debugToFile('---- start --');
                    //	tx_st9fissync::getInstance()->debugToFile($filePath);
                    //	tx_st9fissync::getInstance()->debugToFile($details['transferdata']['cmd']);

                    if ($fp) {
                        $res['res'] = array('bytes' => fwrite($fp,$fileData));
                        tx_st9fissync::getInstance()->getSyncResultResponseDTO()->acceptResultResponseDTO($res['res'],$index);
                        fclose($fp);
                    } else {
                        $errors = error_get_last();
                        throw new tx_st9fissync_exception($errors['message']);
                    }
                    t3lib_div::fixPermissions($filePath);
                } else {
                    throw new tx_st9fissync_exception('Directory could not be created: ['. $fileDirPath . '], for file: [' . $filePath . ']');
                }

            } catch (tx_st9fissync_exception $handleFileTransferException) {
                $res['res'] = array('errors' => $handleFileTransferException->getMessage());
                tx_st9fissync::getInstance()->getSyncResultResponseDTO()->acceptResultResponseDTO($res['res'],$currentIndex);
                //log ??
            }

        }

        return tx_st9fissync::getInstance()->getSyncResultResponseDTO()->getResultResponseDTO();
    }

    /**
     * Used by re-sync
     *
     * @param string $whereClause Not used right now
     *
     * @return mixed
     */
    public function fetchRecordedActions($whereClause='')
    {
        $reSyncableQueryDTOs = null;

        try {
            tx_st9fissync::getInstance()->setSyncMode(tx_st9fissync::SYNC_MODE_RESYNC);
            $reSyncableQueryDTOs = tx_st9fissync::getInstance()->getSyncDataTransferObject(true)->getSyncableQueryDTOs(true);
            $res['res'] = array('resyncable' => $reSyncableQueryDTOs);
            tx_st9fissync::getInstance()->getSyncResultResponseDTO()->acceptResultResponseDTO($res);
        } catch (tx_st9fissync_exception $fetchRecordedActions) {
            $res['errors'] = $fetchRecordedActions->getMessage();
            tx_st9fissync::getInstance()->getSyncResultResponseDTO()->acceptResultResponseDTO($res);
        }

        return tx_st9fissync::getInstance()->getSyncResultResponseDTO()->getResultResponseDTO();
    }

    /**
     * @return mixed
     */
    public function getSt9fisSupportRes()
    {
        $reSyncableSupportUploadsQueryDTOs = null;

        try {
            $reSyncableSupportUploadsQueryDTOs = tx_st9fissync::getInstance()->getSyncSupportUploadsDTO(true)->getSyncableSupportUploadsDTOs(true);
            $res['res'] = array('resyncable' => $reSyncableSupportUploadsQueryDTOs);
            tx_st9fissync::getInstance()->getSyncResultResponseDTO()->acceptResultResponseDTO($res);

        } catch (tx_st9fissync_exception $fetchst9fisSupportResEx) {
            $res['errors'] = $fetchst9fisSupportResEx->getMessage();
            tx_st9fissync::getInstance()->getSyncResultResponseDTO()->acceptResultResponseDTO($res);
        }

        return tx_st9fissync::getInstance()->getSyncResultResponseDTO()->getResultResponseDTO();

    }

    public function setSyncIsSyncedTrackedQueries($indexedQueryUids = array())
    {
        return tx_st9fissync::getInstance()->getSyncDBOperationsManager()->setSyncIsSyncedForIndexedQueries($indexedQueryUids);
    }

    public function fetchFileData($transferFilesArgsArray)
    {
        foreach ($transferFilesArgsArray as $filePath => $transferFilesArgs) {
            try {

                //read the data from the file
                $handle = fopen(tx_dam::file_absolutePath($filePath), 'r');
                $buffer = '';

                $seek = fseek($handle, $transferFilesArgs['transferFilesArgs']['offset'],SEEK_SET);
                if ($seek !=-1) {
                    $buffer = fread($handle, $transferFilesArgs['transferFilesArgs']['maxlen']);
                    $encodedDataBuffer =  base64_encode($buffer);
                    $res['res'] = array('fileTransferData' => $encodedDataBuffer);
                    tx_st9fissync::getInstance()->getSyncResultResponseDTO()->acceptResultResponseDTO($res,$filePath);
                } else {
                    throw new tx_st9fissync_t3soap_expectationfailed_exception("Coult not seek offset: [" . $transferFilesArgs['transferFilesArgs']['offset'] . "]");
                }
                fclose($handle);
            } catch (tx_st9fissync_exception $fetchFileDataEx) {
                $res['errors'] = "Fetch file data error for file: [" . $filePath . '], ';
                $res['errors'] .= 'Arguments: seek offset - [' . $transferFilesArgs['transferFilesArgs']['offset'] .  ']; ';
                $res['errors'] .= 'max length - [' . $transferFilesArgs['transferFilesArgs']['maxlen'] . '] / ';
                $res['errors'] .= " Details - " . $fetchFileDataEx->getMessage();
                tx_st9fissync::getInstance()->getSyncResultResponseDTO()->acceptResultResponseDTO($res,$filePath);
            }

        }

        return tx_st9fissync::getInstance()->getSyncResultResponseDTO()->getResultResponseDTO();

    }

}

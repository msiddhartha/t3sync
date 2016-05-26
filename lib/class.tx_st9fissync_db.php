<?php
/***************************************************************
 *	Copyright notice
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
 *
 * Base DB functions for st9fissync
 * @author	André Spindler <info@studioneun.de>
* @package	TYPO3
* @subpackage	tx_st9fissync
*/

class tx_st9fissync_db
{
    /*
     * ****************************
    * 		Table Definition
    * ****************************
    */

    /**
     * Table for recording sequencing information
     * @var string
     */
    private $sequencerTable = 'tx_st9fissync_dbsequencer_sequencer';

    /**
     * Table for recording log
     * @var string
     */
    private $logTable = 'tx_st9fissync_log';

    /**
     * Table for recording sync configuration
     * @var string
     */
    private $syncConfigHistoryTable = 'tx_st9fissync_confighistory';

    /**
     * Main table for recording DB actions/queries
     * @var string
     */
    private $queryVersioningTable = 'tx_st9fissync_dbversioning_query';

    /**
     * MM table for associating DB actions/queries to specific tables/corresponding records/rows
     *
     * @var string
     */
    private $queryVersioningMMTable = 'tx_st9fissync_dbversioning_query_tablerows_mm';

    /**
     *
     * Main table for Sync processes
     *
     * @var string
     */
    private $syncProcessTable = 'tx_st9fissync_process';

    /**
     * Main table for Sync requests
     *
     * @var string
     */
    private $syncRequestTable = 'tx_st9fissync_request';

    /**
     *
     * MM table to store relations between requests and versioned queries
     *
     * @var string
     */
    private $syncRequestQueryVersioningMM = 'tx_st9fissync_request_dbversioning_query_mm';

    /**
     *
     * Sync requests handler table
     *
     * @var string
     */
    private $syncRequestHandlerTable = 'tx_st9fissync_request_handler';

    /**
     * Dummy table used to restore LAST_INSERT_ID/mysql_insert_id() to complete T3 insert action,
     * typically used in TCE forms reload
     *
     * @see typo3/alt_doc.php
     * @var string
     */
    private $tceformsOnselectInsertDummy = 'tx_st9fissync_dbversioning_insertdummy';

    /**
     * DAM indexing main table
     * @see tx_dam
     *
     * @var string
     */
    private $damMainTable = 'tx_dam';

    /**
     * DAM indexing main record ref table
     * @see tx_dam
     *
     * @var string
     */
    private $damMMRefTable = 'tx_dam_mm_ref';

    /**
     * DAM indexing record ref table for RTE references
     *
     * @var string
     */
    private $sysRefIndexTable = 'sys_refindex';

    /**
     *
     * @var string
     */
    private $supportDomainModelTable = 'tx_st9fissupport_domain_model_upload';

    /**
     * Garbage collector process
     * @var string
     */
    private $gcProcessTable = 'tx_st9fissync_gc_process';

    /**
     * GC Log table
     * @var string
     */
    private $gcLogTable = 'tx_st9fissync_gc_log';

    /*
     * ****************************
    * 		Associated Methods
    * ****************************
    */

    /**
     * Get sequencer tablename
     *
     * @return string
     */
    public function getSequencerTable()
    {
        return $this->sequencerTable;
    }

    /**
     * Get the next auto index uid value of a Table
     *
     * @param  string $tableName
     * @return int
     */
    public function getNextAutoIndexIdForTable($tableName)
    {
        $tableStatQuery = 'show table status where name = \''. $tableName . '\'';
        $statRes = tx_st9fissync::getInstance()->getSyncDBObject()->sql_query($tableStatQuery);
        $statRow=array();
        if (tx_st9fissync::getInstance()->getSyncDBObject()->sql_num_rows($statRes) > 0) {
            while ($row = tx_st9fissync::getInstance()->getSyncDBObject()->sql_fetch_assoc($statRes)) {
                $statRow = $row;
            }
        }

        return $statRow['Auto_increment'];

    }

    public function getSpaceUsageInBytesForTable($tableName, $includeIndexUsage = true)
    {
        $tableStatQuery = 'show table status where name = \''. $tableName . '\'';
        $statRes = tx_st9fissync::getInstance()->getSyncDBObject()->sql_query($tableStatQuery);
        $statRow=array();
        if (tx_st9fissync::getInstance()->getSyncDBObject()->sql_num_rows($statRes) > 0) {
            while ($row = tx_st9fissync::getInstance()->getSyncDBObject()->sql_fetch_assoc($statRes)) {
                $statRow = $row;
            }
        }

        if ($includeIndexUsage) {
            return intval($statRow['Data_length']) + intval($statRow['Index_length']);
        } else {
            return intval($statRow['Data_length']);
        }

    }

    public function optimizeTable($tablename)
    {
        $optimizeTableQuery = 'OPTIMIZE TABLE \''. $tableName . '\'';

        return tx_st9fissync::getInstance()->getSyncDBObject()->sql_query($optimizeTableQuery);
    }

    public function registerTableForSequencing($newTable, $start, $offset)
    {
        $success = FALSE;

        $tableRegistrationArray = array(
                'tablename' => tx_st9fissync::getInstance()->getSyncDBObject()->quoteStr($newTable,''),
                'current' => $start,
                'offset' => $offset,
                'timestamp' => $GLOBALS ['EXEC_TIME'],
        );

        try {
            $registrationStatus = tx_st9fissync::getInstance()->getSyncDBObject()->exec_INSERTquery($this->sequencerTable,$tableRegistrationArray);
            if ($registrationStatus) {
                $success = TRUE;
            } else {
                throw new Exception ("Could not register table `".$newTable."` for Sequencing. Error: ".tx_st9fissync::getInstance()->getSyncDBObject()->sql_error());
            }
        } catch (Exception $e) {
            //log Error $e->getMessage()
            $success = FALSE;
        }

        return $success;
    }

    public function getSequencerDetailsForTable($tableName)
    {
        $select = '*';
        $where = 'tablename = ' . tx_st9fissync::getInstance()->getSyncDBObject()->fullQuoteStr($tableName, $this->sequencerTable);
        $sequencerInfo = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTgetSingleRow($select, $this->sequencerTable, $where);

        return $sequencerInfo;
    }

    public function updateCurrentSequencerFootprint($tableName, $newCurrentValue, $oldTimeStamp)
    {
        $updateTableFootPrint = array(
                'current' => $newCurrentValue,
                'timestamp' => $GLOBALS ['EXEC_TIME'],
        );

        $where = $this->sequencerTable . '.timestamp = ' . $oldTimeStamp;
        $where .= ' AND ' . $this->sequencerTable . '.tablename = ' . tx_st9fissync::getInstance()->getSyncDBObject()->fullQuoteStr($tableName, $this->sequencerTable);
        $updatedFootprintRes = tx_st9fissync::getInstance()->getSyncDBObject()->exec_UPDATEquery($this->sequencerTable, $where, $updateTableFootPrint);

        try {
            if (!$updatedFootprintRes) {
                throw new Exception ("Could not update sequencer table `".$this->sequencerTable."` for table `".$tableName."` Error: ".tx_st9fissync::getInstance()->getSyncDBObject()->sql_error());
            }
        } catch (Exception $e) {
            //log Error $e->getMessage()
        }

        return $updatedFootprintRes;
    }

    public function getPidForTableRecord($recordId, $tableName, $pidColName = 'pid', $uidColName='uid')
    {
        //check for recordID and tablename
        $selectFields = $tableName . '.' . $pidColName;
        $whereClause = $tableName . '.' . $uidColName . " = " . $recordId;
        $recPid = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTgetSingleRow($selectFields, $tableName, $whereClause);

        return $recPid;
    }

    public function handleTCEFormsOnSelect($recId)
    {
        $truncInsertDummyQ = tx_st9fissync::getInstance()->getSyncDBObject()->exec_TRUNCATEquery($this->tceformsOnselectInsertDummy);
        $dummyInsertArray = array('uid' => intval($recId));
        $GLOBALS['TYPO3_DB']->disableSequencer();
        $GLOBALS['TYPO3_DB']->disableVersioning();
        $dummyLastInsertIdQ = $GLOBALS['TYPO3_DB']->exec_INSERTquery($this->tceformsOnselectInsertDummy,$dummyInsertArray);

        try {
            if (!$dummyLastInsertIdQ) {
                throw new Exception ("Could not insert data into table `".$this->tceformsOnselectInsertDummy."` Error: ".tx_st9fissync::getInstance()->getSyncDBObject()->sql_error());
            }
        } catch (Exception $e) {
            //log Error $e->getMessage()
        }

        $GLOBALS['TYPO3_DB']->enableSequencer();
        $GLOBALS['TYPO3_DB']->enableVersioning();

        return $dummyLastInsertIdQ;
    }

    public function setSyncScheduledForIndexedQueries($indexedQueryUids = array())
    {
        $setScheduleSync['updtuser_id'] = tx_st9fissync::getInstance()->getUserForT3Mode();
        $setScheduleSync['updt_typo3_mode'] = tx_st9fissync::getInstance()->getT3Mode();
        $setScheduleSync['issyncscheduled'] = 1;
        $setScheduleSync['issynced'] = 0;
        $setScheduleSync['timestamp'] = tx_st9fissync::getInstance()->getMicroTime();
        $whereScheduleSync = $this->queryVersioningTable . '.uid' . ' IN (' .implode(',', $indexedQueryUids) .')' ;
        $updateScheduleSyncIndexRes = tx_st9fissync::getInstance()->getSyncDBObject()->exec_UPDATEquery($this->queryVersioningTable, $whereScheduleSync, $setScheduleSync);

        return $updateScheduleSyncIndexRes;
    }

    public function resetSyncScheduledForIndexedQueries($indexedQueryUids = array())
    {
        $resetScheduleSync['updtuser_id'] = tx_st9fissync::getInstance()->getUserForT3Mode();
        $resetScheduleSync['updt_typo3_mode'] = tx_st9fissync::getInstance()->getT3Mode();
        $resetScheduleSync['issyncscheduled'] = 0;
        $resetScheduleSync['timestamp'] = tx_st9fissync::getInstance()->getMicroTime();
        $whereResetScheduleSync = $this->queryVersioningTable . '.uid' . ' IN (' .implode(',', $indexedQueryUids) .')' ;
        $updateResetScheduleSyncIndexRes = tx_st9fissync::getInstance()->getSyncDBObject()->exec_UPDATEquery($this->queryVersioningTable, $whereResetScheduleSync, $resetScheduleSync);

        return $updateResetScheduleSyncIndexRes;
    }

    public function setSyncIsSyncedForIndexedQueries($indexedQueryUids = array())
    {
        $setSync['updtuser_id'] = tx_st9fissync::getInstance()->getUserForT3Mode();
        $setSync['updt_typo3_mode'] = tx_st9fissync::getInstance()->getT3Mode();
        $setSync['issynced'] = 1;
        $setSync['timestamp'] = tx_st9fissync::getInstance()->getMicroTime();
        $whereSyncIsSynced = $this->queryVersioningTable . '.uid' . ' IN (' .implode(',', $indexedQueryUids) .')' ;
        $updateSyncIsSyncedIndexRes = tx_st9fissync::getInstance()->getSyncDBObject()->exec_UPDATEquery($this->queryVersioningTable, $whereSyncIsSynced, $setSync);

        return $updateSyncIsSyncedIndexRes;

    }

    public function checkUpdateConfigHistory(tx_st9fissync_config $currentConfig)
    {
        $addToConfigHistory = FALSE;
        $lastactiveRowConfig = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTgetSingleRow('*',$this->syncConfigHistoryTable,'','','UID DESC');
        if ($currentConfig->compareTo($lastactiveRowConfig)) {
            $saveNewConfig = array(
                    'crdate' => tx_st9fissync::getInstance()->getMicroTime(),
                    'config' => serialize($currentConfig->getArrayCopy()),
            );
            $addToConfigHistory =  tx_st9fissync::getInstance()->getSyncDBObject()->exec_INSERTquery($this->syncConfigHistoryTable,$saveNewConfig);

            try {
                if (!$addToConfigHistory) {
                    throw new Exception ("Could not insert data into table `".$this->syncConfigHistoryTable."` Error: ".tx_st9fissync::getInstance()->getSyncDBObject()->sql_error());
                }
            } catch (Exception $e) {
                //log Error $e->getMessage()
            }

        }

        return $addToConfigHistory;
    }

    public function getDAMMainTableName()
    {
        return $this->damMainTable;
    }

    public function getDAMRecRefTableName()
    {
        return $this->damMMRefTable;
    }

    public function getDAMRecRTERefTableName()
    {
        return $this->sysRefIndexTable;
    }

    public function getIndexedDAMInsertQuery($damRecId)
    {
        $select =  $this->compileFieldList($this->getIndexedQueryInfoFieldList());
        $select .= ', ' . $this->damMainTable . '.uid AS ' .$this->damMainTable . '_uid';
        //$select .= tx_dam_db::getMetaInfoFieldList();
        $local_table = $this->queryVersioningTable;
        $mm_table = $this->queryVersioningMMTable;
        $foreign_table = $this->damMainTable;
        $where = 'AND ' . $this->damMainTable . '.uid =' . intval($damRecId);
        $where = $where . ' AND ' . $this->queryVersioningTable . '.query_error_number = 0';
        $where = $where . ' AND ' . $this->queryVersioningMMTable . '.tablenames = ' . tx_st9fissync::getInstance()->getSyncDBObject()->fullQuoteStr($this->damMainTable, $this->queryVersioningMMTable);
        $groupBy = '';
        $orderBy = $this->queryVersioningTable . '.timestamp DESC';

        //$query = tx_st9fissync::getInstance()->getSyncDBObject()->SELECT_mm_query($select, $local_table, $mm_table, $foreign_table,  $where);
        $damRecordRevisionRes = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECT_mm_query($select, $local_table, $mm_table, $foreign_table,  $where);

        try {
            if (!$damRecordRevisionRes) {
                throw new Exception ("Error: ".tx_st9fissync::getInstance()->getSyncDBObject()->sql_error());
            }
        } catch (Exception $e) {
            //log Error $e->getMessage()
        }

        $damRecordRevisions = $this->getRowsForResultSet($damRecordRevisionRes);

        return $damRecordRevisions;
    }

    public function getQueryVersioningRefRecordsTable()
    {
        return $this->queryVersioningMMTable;
    }

    public function getQueryVersioningTable()
    {
        return $this->queryVersioningTable;
    }

    /**
     *
     * @param  tx_st9fissync_dbversioning_query $queryArtifact
     * @throws Exception
     */
    public function addQueryArtifactToVersion(tx_st9fissync_dbversioning_query $queryArtifact = NULL)
    {
        $addToVersionControl = false;

        if (is_null($queryArtifact)) {
            return false;
        } else {
            //add query to version control
            $addToVersionControl =  tx_st9fissync::getInstance()->getSyncDBObject()->exec_INSERTquery($this->queryVersioningTable,$queryArtifact->getArrayCopy(),array('client_ip'));

            try {
                if ($addToVersionControl) {
                    //fetch all ref record for MM
                    $refIterator = $queryArtifact->getTableRows();
                    $uid_local = tx_st9fissync::getInstance()->getSyncDBObject()->sql_insert_id();
                    $addToVersionControl = $uid_local;

                    for ($refIterator->rewind(); $refIterator->valid(); $refIterator->next()) {
                        try {

                            $refObject = $refIterator->current();
                            $refObject->set('uid_local',$uid_local);
                            tx_st9fissync::getInstance()->getSyncDBObject()->exec_INSERTquery($this->queryVersioningMMTable,$refObject->getArrayCopy());

                        } catch (Exception $exception) {
                            continue;
                        }
                    }
                } else {
                    throw new Exception ("Could not insert data into table `".$this->queryVersioningTable."` Error: ".tx_st9fissync::getInstance()->getSyncDBObject()->sql_error());
                }
            } catch (Exception $e) {

            }

        }

        return $addToVersionControl;
    }

    public function updateQueryArtifactToVersion(tx_st9fissync_dbversioning_query $queryArtifact = NULL, $whereClause = '')
    {
        $success = FALSE;
        if (is_null($queryArtifact)) {
            return $success;
        } else {

            try {
                $updateQueryArtifact = tx_st9fissync::getInstance()->getSyncDBObject()->exec_UPDATEquery($this->queryVersioningTable, $whereClause, $queryArtifact->getArrayCopy());
                if ($updateQueryArtifact) {
                    $success = TRUE;
                } else {
                    throw new Exception ("Could not update data into table `".$this->queryVersioningTable."` Error: ".tx_st9fissync::getInstance()->getSyncDBObject()->sql_error());
                }
            } catch (Exception $e) {
                $success = FALSE;
                // $e->getMessage()
            }

        }

        return $success;
    }

    /**
     *
     * @param mixed $qvIds
     *
     * @return boolean
     */
    public function deleteFromVersioningByUid($qvIds)
    {
        if (is_array($qvIds)) {
            $qvIds = implode(',', $qvIds);
        }

        $deleteClauseWhereMM = 'uid_local in (' . $qvIds . ')';
        if (tx_st9fissync::getInstance()->getSyncDBObject()->exec_DELETEquery($this->queryVersioningMMTable, $deleteClauseWhereMM)) {
            $deleteClauseWhere = 'uid in (' . $qvIds . ')';

            return tx_st9fissync::getInstance()->getSyncDBObject()->exec_DELETEquery($this->queryVersioningTable, $deleteClauseWhere);
        }

        return false;
    }

    public function getIndexedInsertQuery($recId,$foreignTable, $additionalWhereClause='')
    {
        $select = $this->compileFieldList($this->getIndexedQueryInfoFieldList());
        $local_table = $this->queryVersioningTable;
        $mm_table = $this->queryVersioningMMTable;
        $foreign_table = $foreignTable;
        $where = 'AND ' . $this->queryVersioningMMTable . '.uid_foreign = ' . intval($recId);
        $where = $where . ' AND ' . $this->queryVersioningTable . '.query_type = ' . tx_st9fissync_dbversioning_t3service::INSERT;
        $where = $where . ' AND ' . $this->queryVersioningTable . '.query_error_number = 0';
        $where = $where . ' AND ' . $this->queryVersioningMMTable . '.tablenames = ' . tx_st9fissync::getInstance()->getSyncDBObject()->fullQuoteStr($foreignTable,$this->queryVersioningMMTable);
        $where = $where . $additionalWhereClause;

        //$query = tx_st9fissync::getInstance()->getSyncDBObject()->SELECT_mm_query($select, $local_table, $mm_table, $foreign_table,  $where);
        $res = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECT_mm_query($select, $local_table, $mm_table, $foreign_table,  $where);

        try {
            if (!$res) {
                throw new Exception ("Error: ".tx_st9fissync::getInstance()->getSyncDBObject()->sql_error());
            }
        } catch (Exception $e) {
            // $e->getMessage()
        }

        $rows = $this->getRowsForResultSet($res);

        return $rows;
    }

    public function getIndexedQueries($recId,$foreignTable, $additionalWhereClause='')
    {
        $select = $this->compileFieldList($this->getIndexedQueryInfoFieldList());
        $local_table = $this->queryVersioningTable;
        $mm_table = $this->queryVersioningMMTable;
        $foreign_table = $foreignTable;
        $where = 'AND ' . $this->queryVersioningMMTable . '.uid_foreign = ' . intval($recId);
        $where = $where . ' AND ' . $this->queryVersioningTable . '.query_error_number = 0';
        $where = $where . ' AND ' . $this->queryVersioningMMTable . '.tablenames = ' . tx_st9fissync::getInstance()->getSyncDBObject()->fullQuoteStr($foreignTable,$this->queryVersioningMMTable);
        $where = $where . $additionalWhereClause;
        $res = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECT_mm_query($select, $local_table, $mm_table, $foreign_table,  $where);

        try {
            if (!$res) {
                throw new Exception ("Error: ".tx_st9fissync::getInstance()->getSyncDBObject()->sql_error());
            }
        } catch (Exception $e) {
            //$e->getMessage()
        }

        $rows = $this->getRowsForResultSet($res);

        return $rows;
    }

    public function getIndexedQueryInfoFieldList()
    {
        $fields = array();
        $fields[] = $this->getQueryVersioningFieldList();
        $fields[] = $this->getQueryVersioningRefRecordFieldList();

        return $fields;
    }

    public function getQueryVersioningFieldList()
    {
        $queryVersioningFieldList = array();
        $queryVersioningFieldList['uid'] = 'uid';
        $queryVersioningFieldList['pid'] = 'pid';
        $queryVersioningFieldList['sysid'] = 'sysid';
        $queryVersioningFieldList['crdate'] = 'crdate';
        $queryVersioningFieldList['crmsec'] = 'crmsec';
        $queryVersioningFieldList['timestamp'] = 'timestamp';
        $queryVersioningFieldList['cruser_id'] = 'cruser_id';
        $queryVersioningFieldList['updtuser_id'] = 'updtuser_id';
        $queryVersioningFieldList['query_text'] = 'query_text';
        $queryVersioningFieldList['query_type'] = 'query_type';
        $queryVersioningFieldList['query_affectedrows'] = 'query_affectedrows';
        $queryVersioningFieldList['query_info'] = 'query_info';
        $queryVersioningFieldList['query_exectime'] = 'query_exectime';
        $queryVersioningFieldList['query_error_number'] = 'query_error_number';
        $queryVersioningFieldList['query_error_message'] = 'query_error_message';
        $queryVersioningFieldList['workspace'] = 'workspace';
        $queryVersioningFieldList['typo3_mode'] = 'typo3_mode';
        $queryVersioningFieldList['updt_typo3_mode'] = 'updt_typo3_mode';
        $queryVersioningFieldList['issynced'] = 'issynced';
        $queryVersioningFieldList['issyncscheduled'] = 'issyncscheduled';
        $queryVersioningFieldList['request_url'] = 'request_url';
        $queryVersioningFieldList['client_ip'] = 'client_ip';
        $queryVersioningFieldList['tables'] = 'tables';
        $queryVersioningFieldList['rootid'] = 'rootid';
        $queryVersioningFieldList['excludeid'] = 'excludeid';

        $queryVersioningTable = array();
        $queryVersioningTable['tableName'] = $this->queryVersioningTable;
        $queryVersioningTable['fieldList'] = $queryVersioningFieldList;

        return $queryVersioningTable;

    }

    public function getQueryVersioningRefRecordFieldList()
    {
        $queryVersioningRefRecordFieldList = array();
        $queryVersioningRefRecordFieldList['uid_local'] = 'uid_local';
        $queryVersioningRefRecordFieldList['uid_foreign'] = 'uid_foreign';
        $queryVersioningRefRecordFieldList['recordRevision'] = 'recordRevision';
        $queryVersioningRefRecordFieldList['tablenames'] = 'tablenames';

        $queryVersioningRefRecordTable = array();
        $queryVersioningRefRecordTable['tableName'] = $this->queryVersioningMMTable;
        $queryVersioningRefRecordTable['fieldList'] = $queryVersioningRefRecordFieldList;

        return $queryVersioningRefRecordTable;

    }

    public function getSyncProcTableName()
    {
        return $this->syncProcessTable;
    }

    public function getSyncRequestTableName()
    {
        return $this->syncRequestTable;
    }

    public function getSyncReqRefQVRecTableName()
    {
        return $this->syncRequestQueryVersioningMM;
    }

    public function getSyncRequestHandlerTableName()
    {
        return $this->syncRequestHandlerTable;
    }

    public function getSyncableQueries($whereCondition='')
    {
        $select = $this->compileFieldList($this->getIndexedQueryInfoFieldList());
        $local_table = $this->queryVersioningTable;
        $mm_table = $this->queryVersioningMMTable;
        $foreign_table = '';
        $where = $where . ' AND ' . $this->queryVersioningTable . '.query_affectedrows > 0';
        $where = $where . ' AND ' . $this->queryVersioningTable . '.query_error_number = 0';
        $where = $where . ' AND ' . $this->queryVersioningTable . '.issyncscheduled = 1';
        $where = $where . ' AND ' . $this->queryVersioningTable . '.issynced = 0';
        $where = $where . ' AND ' . $this->queryVersioningMMTable . '.tablenames != ' . tx_st9fissync::getInstance()->getSyncDBObject()->fullQuoteStr($this->damMainTable,$this->queryVersioningMMTable);
        $where = $where . $whereCondition;
        $groupBy = '';
        $orderBy = $this->queryVersioningTable . '.uid ASC';

        //		$syncqueries = tx_st9fissync::getInstance()->getSyncDBObject()->SELECT_mm_query($select, $local_table, $mm_table, $foreign_table,  $where, $groupBy, $orderBy);
        $res = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECT_mm_query($select, $local_table, $mm_table, $foreign_table,  $where, $groupBy, $orderBy);

        if (!$res) {
            //$e->getMessage()
        } else {
            $rows = $this->getRowsForResultSet($res);

            return $rows;
        }

        return $res;
    }

    /**
     * Only for DAM indexed recorded queries
     * @param  string         $whereCondition
     * @return multitype:NULL |unknown
     */
    public function getSyncableDAMIndexQueries($whereCondition='')
    {
        $select = $this->compileFieldList($this->getIndexedQueryInfoFieldList());
        $local_table = $this->queryVersioningTable;
        $mm_table = $this->queryVersioningMMTable;
        $foreign_table = '';
        $where = $where . ' AND ' . $this->queryVersioningTable . '.query_affectedrows > 0';
        $where = $where . ' AND ' . $this->queryVersioningTable . '.query_error_number = 0';
        $where = $where . ' AND ' . $this->queryVersioningTable . '.issyncscheduled = 1';
        $where = $where . ' AND ' . $this->queryVersioningTable . '.issynced = 0';
        $where = $where . ' AND ' . $this->queryVersioningMMTable . '.tablenames = ' . tx_st9fissync::getInstance()->getSyncDBObject()->fullQuoteStr($this->damMainTable,$this->queryVersioningMMTable);
        $where = $where . $whereCondition;
        $groupBy = '';
        $orderBy = $this->queryVersioningTable . '.uid ASC';

        $res = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECT_mm_query($select, $local_table, $mm_table, $foreign_table,  $where, $groupBy, $orderBy);

        if (!$res) {
            //$e->getMessage()
        } else {
            $rows = $this->getRowsForResultSet($res);

            return $rows;
        }

        return $res;
    }

    public function getReSyncableQueries($whereCondition='')
    {
        $select = $this->compileFieldList($this->getIndexedQueryInfoFieldList());
        $local_table = $this->queryVersioningTable;
        $mm_table = $this->queryVersioningMMTable;
        $foreign_table = '';
        $where = $where . ' AND ' . $this->queryVersioningTable . '.query_affectedrows > 0';
        $where = $where . ' AND ' . $this->queryVersioningTable . '.query_error_number = 0';
        $where = $where . ' AND ' . $this->queryVersioningTable . '.issyncscheduled = 1';
        $where = $where . ' AND ' . $this->queryVersioningTable . '.issynced = 0';
        $where = $where . ' AND ' . $this->queryVersioningMMTable . '.tablenames != ' . tx_st9fissync::getInstance()->getSyncDBObject()->fullQuoteStr($this->damMainTable,$this->queryVersioningMMTable);
        $where = $where . ' AND ' . $this->queryVersioningMMTable . '.tablenames != ' . tx_st9fissync::getInstance()->getSyncDBObject()->fullQuoteStr($this->supportDomainModelTable,$this->queryVersioningMMTable);
        $where = $where . $whereCondition;
        $groupBy = '';
        $orderBy = $this->queryVersioningTable . '.uid ASC';

        // /		$query = tx_st9fissync::getInstance()->getSyncDBObject()->SELECT_mm_query($select, $local_table, $mm_table, $foreign_table,  $where, $groupBy, $orderBy);
        $res = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECT_mm_query($select, $local_table, $mm_table, $foreign_table,  $where, $groupBy, $orderBy);

        if (!$res) {
            //$e->getMessage()
        } else {
            $rows = $this->getRowsForResultSet($res);

            return $rows;
        }

        return $res;
    }

    /**
     *
     * @return multitype:NULL |unknown
     */
    public function getReSyncableSupportUploadsQueries($whereCondition = '')
    {
        $select = $this->compileFieldList($this->getIndexedQueryInfoFieldList());
        $local_table = $this->queryVersioningTable;
        $mm_table = $this->queryVersioningMMTable;
        $foreign_table = '';
        $where = $where . ' AND ' . $this->queryVersioningTable . '.query_affectedrows > 0';
        $where = $where . ' AND ' . $this->queryVersioningTable . '.query_error_number = 0';
        $where = $where . ' AND ' . $this->queryVersioningTable . '.issyncscheduled = 1';
        $where = $where . ' AND ' . $this->queryVersioningTable . '.issynced = 0';
        $where = $where . ' AND ' . $this->queryVersioningMMTable . '.tablenames = ' . tx_st9fissync::getInstance()->getSyncDBObject()->fullQuoteStr($this->supportDomainModelTable,$this->queryVersioningMMTable);
        $where = $where . $whereCondition;
        $groupBy = '';
        $orderBy = $this->queryVersioningTable . '.uid ASC';

        //$query = tx_st9fissync::getInstance()->getSyncDBObject()->SELECT_mm_query($select, $local_table, $mm_table, $foreign_table,  $where, $groupBy, $orderBy);
        $res = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECT_mm_query($select, $local_table, $mm_table, $foreign_table,  $where, $groupBy, $orderBy);

        if (!$res) {
            //$e->getMessage()
        } else {
            $rows = $this->getRowsForResultSet($res);

            return $rows;
        }

        return $res;
    }

    /**
     *
     * @param int $supportId
     *
     * @return string $file
     */
    public function getSyncableSupportUploadsFile($supportId = null)
    {
        $file = '';
        if ($supportId) {
            $select_fields = 'files_raw';
            $from_table = $this->supportDomainModelTable;
            $where_clause = $this->supportDomainModelTable . '.uid = ' . intval($supportId);
            $row = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTgetSingleRow($select_fields, $from_table, $where_clause);
            $file = $row['files_raw'];
        }

        return $file;
    }

    /**
     *
     * @param int $uid_local
     * @param int $uid_foreign
     * @param int $counter
     *
     * @return mixed
     */
    public function createSupportUploadsReferences($uid_local, $uid_foreign, $counter=1)
    {
        $refInserted = false;
        try {

            $whereClause = $this->damMMRefTable . '.uid_local = ' . $uid_local;
            $whereClause .= ' AND ' . $this->damMMRefTable . '.uid_foreign = ' . $uid_foreign;
            $whereClause .= ' AND ' . $this->damMMRefTable . '.tablenames = \'' . $this->supportDomainModelTable . '\'';
            $relationCount = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTcountRows('*', $this->damMMRefTable, $whereClause);

            if ($relationCount == 0) {
                $insertMMRef = array(
                        'uid_local' => $uid_local,
                        'uid_foreign' => $uid_foreign,
                        'ident' => 'files',
                        'tablenames' => $this->supportDomainModelTable,
                        'sorting' => '0',
                        'sorting_foreign' => $counter
                );

                tx_st9fissync::getInstance()->getSyncDBObject()->enableSequencer();
                $refInserted = tx_st9fissync::getInstance()->getSyncDBObject()->exec_INSERTquery($this->damMMRefTable, $insertMMRef);
                tx_st9fissync::getInstance()->getSyncDBObject()->disableSequencer();
            } elseif ($relationCount > 0) {

                $refInserted = -1;
            }
        } catch (tx_st9fissync_exception $supportUploadsEx) {
            tx_st9fissync::getInstance()->getSyncDBObject()->disableSequencer();
        }

        return $refInserted;
    }

    /**
     *
     * @param int $indexId
     *
     * @return boolean
     */
    public function doesDAMIndexExists($indexId)
    {
        return tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTcountRows('*',$this->damMainTable,$this->damMainTable . '.uid = ' . intval($indexId));
    }

    /**
     *
     * @param string $sysRefTableName
     * @param int    $sysRefRecUID
     * @param int    $sysRefDAMIndexRefUID
     * @param string $sysRefIdentSoftRefKeyVal
     *
     * @return resource
     */
    public function getDAMSysIndexRefRes($sysRefRefTableName, $sysRefRecUID, $sysRefDAMIndexRefUID, $sysRefIdentSoftRefKeyVal)
    {
        $fields = tx_dam_db::getMetaInfoFieldList();

        $from_table = $sysRefRefTableName . ', ' . $this->getDAMMainTableName() .  ',' . $this->getDAMRecRTERefTableName();

        $where_clause = $this->getDAMMainTableName() . '.uid = ' . $this->getDAMRecRTERefTableName() . '.ref_uid';
        $where_clause .= ' AND ' . $this->getDAMMainTableName() . '.uid = '  . intval($sysRefDAMIndexRefUID);
        $where_clause .= ' AND ' . $sysRefRefTableName . '.uid = ' . $this->getDAMRecRTERefTableName() . '.recuid';
        $where_clause .= ' AND ' . $sysRefRefTableName . '.uid = ' . intval($sysRefRecUID);
        $where_clause .= ' AND ' . $sysRefRefTableName . '.deleted = 0';
        $where_clause .= ' AND ' . $this->getDAMMainTableName() . '.deleted = 0';
        $where_clause .= ' AND ' . $this->getDAMRecRTERefTableName() . '.deleted = 0';
        $where_clause .= ' AND ' . $this->getDAMRecRTERefTableName() . '.tablename = \'' . $sysRefRefTableName . '\'';
        $where_clause .= ' AND ' . $this->getDAMRecRTERefTableName() . '.softref_key = \'' . $sysRefIdentSoftRefKeyVal . '\'';

        //$q = $this->getSyncDBObject()->SELECTquery($fields, $from_table, $where_clause);

        $res = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTquery($fields, $from_table, $where_clause);

        return $res;
    }

    /**
     *
     */
    public function getSysRefDAMRefMediaElements()
    {
        $whereDAMMediaElements = '(' .  $this->getDAMRecRTERefTableName() . '.softref_key	= \'media\'';
        $whereDAMMediaElements .= ' OR ' .  $this->getDAMRecRTERefTableName() . '.softref_key	= \'mediatag\')';
        $whereDAMMediaElements .= ' AND ' .  $this->getDAMRecRTERefTableName() . '.ref_table	= \'tx_dam\'';
        $whereDAMMediaElements .= ' AND ' .  $this->getDAMRecRTERefTableName() . '.deleted	= 0';
        $sysRefDAMRefMediaElements =  tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTgetRows('*', $this->getDAMRecRTERefTableName(), $whereDAMMediaElements);

        return $sysRefDAMRefMediaElements;
    }

    /**
     * DAM index referrer from tx_dam_mm_ref
     *
     * @param int $recId 'uid'
     *
     * @return boolean
     */
    public function doesRecordExistByDAMMMRef($recId)
    {
        $exists = false;

        try {
            $select_fields = '*';
            $from_table = $this->damMMRefTable;
            $where_clause = 'uid_local = ' . intval($recId);

            $mmRefRows = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTgetRows($select_fields,$from_table,$where_clause);

            foreach ($mmRefRows as $mmRefRow) {
                $table = $mmRefRow['tablenames'];
                $where = 'uid = ' . intval($mmRefRow['uid_foreign']);
                $enableFieldsString = tx_dam_db::enableFields($table);
                if ($enableFieldsString != '') {
                    $where .= ' AND ' . $enableFieldsString;
                }

                if (tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTcountRows('*',$table, $where)) {
                    //results returned, check for each  row in respective table, query /and a record that refers exists legit
                    $exists = true;
                    break;
                } else {
                    //results returned, check for each  row in respective table, query /this record does not exist here

                }
            }

        } catch (tx_st9fissync_exception $recordNonExist) {
            //$recordNonExist->getMessage()
        }

        return $exists;
    }

    /**
     * DAM index referrer from sys_refindex
     *
     * @param int $recId 'uid'
     *
     * @return boolean
     */
    public function doesRecordExistByDAMSysIndexRef($recId)
    {
        $exists = false;

        try {
            $select_fields = '*';
            $from_table = $this->sysRefIndexTable;
            $where_clause = 'ref_uid = ' . intval($recId);
            $where_clause .= ' AND ref_table = \'' . $this->damMainTable . '\'';

            $sysIndexRefRows = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTgetRows($select_fields,$from_table,$where_clause);

            foreach ($sysIndexRefRows as $sysIndexRefRow) {
                $table = $sysIndexRefRow['tablename'];
                $where = 'uid = ' . intval($sysIndexRefRow['recuid']);
                $enableFieldsString = tx_dam_db::enableFields($table);
                if ($enableFieldsString != '') {
                    $where .= ' AND ' . $enableFieldsString;
                }

                if (tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTcountRows('*',$table, $where)) {
                    //results returned, check for each  row in respective table, query /and a record that refers exists legit
                    $exists = true;
                    break;
                } else {
                    //results returned, check for each  row in respective table, query /this record does not exist here

                }
            }

        } catch (tx_st9fissync_exception $recordNonExist) {
            //$recordNonExist->getMessage()
        }

        return $exists;
    }

    /**
     * Get Process Id for currently active Sync process
     *
     * @param int|null $currentProcId
     *
     */
    public function getActiveSyncProcesses($currentProcId = null)
    {
        $activeSyncProcesses = array();
        try {
            $select_fields = '*';
            $from_table = $this->syncProcessTable;

            if (!is_null($currentProcId)) {
                $where_clause = ' uid != ' . intval($currentProcId) . ' AND ';
            }

            $where_clause .= ' ( syncproc_stage = ' . tx_st9fissync_sync::STAGE_RUNNING;
            $where_clause .= ' OR syncproc_stage = ' . tx_st9fissync_sync::STAGE_INIT;
            $where_clause .= ')';
            $groupBy = '';
            $orderBy = '';
            $limit = '';
            $uidIndexField = 'uid';

            $activeSyncProcesses = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTgetRows($select_fields,
                    $from_table,
                    $where_clause,
                    $groupBy, $orderBy, $limit,
                    $uidIndexField);

        } catch (Exception $exception) {
            $activeSyncProcesses = FALSE;
        }

        return $activeSyncProcesses;
    }

    /**
     *
     * @param  tx_st9fissync_process $process
     * @return boolean|Ambigous      <boolean, unknown>
     */
    public function addSyncProcessInfo(tx_st9fissync_process $process = NULL)
    {
        $success = FALSE;
        if (is_null($process)) {
            return $success;
        } else {
            //add process Info
            try {
                $addSyncProcInfo =  tx_st9fissync::getInstance()->getSyncDBObject()->exec_INSERTquery($this->syncProcessTable,$process->getArrayCopy(),array('client_ip'));
                $success = $addSyncProcInfo;
            } catch (Exception $exception) {
                $success = FALSE;
            }
        }

        return $success;
    }

    public function updateSyncProcessInfo(tx_st9fissync_process $process = NULL, $whereClause = '')
    {
        $success = FALSE;
        if (is_null($process)) {
            return $success;
        } else {
            try {
                //$updatequery = tx_st9fissync::getInstance()->getSyncDBObject()->UPDATEquery($this->syncProcessTable, $whereClause, $process->getArrayCopy());
                $updateSyncProcessInfo = tx_st9fissync::getInstance()->getSyncDBObject()->exec_UPDATEquery($this->syncProcessTable, $whereClause, $process->getArrayCopy(), array('client_ip'));
                if ($updateSyncProcessInfo) {
                    $success = TRUE;
                } else {
                    throw new Exception ("Could not update data into table `".$this->syncProcessTable."` Error: ".tx_st9fissync::getInstance()->getSyncDBObject()->sql_error());
                }
            } catch (Exception $e) {
                $success = FALSE;
                //$e->getMessage()
            }

        }

        return $success;
    }

    public function getSyncProcessFieldList()
    {
        $syncProcessFieldList = array();
        $syncProcessFieldList['uid'] = 'uid';
        $syncProcessFieldList['pid'] = 'pid';
        $syncProcessFieldList['cruser_id'] = 'cruser_id';
        $syncProcessFieldList['syncproc_src_sysid'] = 'syncproc_src_sysid';
        $syncProcessFieldList['syncproc_dest_sysid'] = 'syncproc_dest_sysid';
        $syncProcessFieldList['syncproc_starttime'] = 'syncproc_starttime';
        $syncProcessFieldList['syncproc_endtime'] = 'syncproc_endtime';
        $syncProcessFieldList['syncproc_stage'] = 'syncproc_stage';
        $syncProcessFieldList['syncproc_status'] = 'syncproc_status';
        $syncProcessFieldList['typo3_mode'] = 'typo3_mode';
        $syncProcessFieldList['request_url'] = 'request_url';
        $syncProcessFieldList['client_ip'] = 'client_ip';
        $syncProcessFieldList['requests'] = 'requests';

        $syncProcessTable = array();
        $syncProcessTable['tableName'] = $this->syncProcessTable;
        $syncProcessTable['fieldList'] = $syncProcessFieldList;

        return $syncProcessTable;

    }

    /**
     *
     * @param  tx_st9fissync_request $requestDetails
     * @throws Exception
     *
     * @return mixed
     */
    public function addSyncRequestDetails(tx_st9fissync_request $requestDetails = NULL)
    {
        $success = FALSE;
        if (is_null($requestDetails)) {
            return $success;
        } else {
            //add process Info
            try {

                $addSyncRequestDetails =  tx_st9fissync::getInstance()->getSyncDBObject()->exec_INSERTquery($this->syncRequestTable,$requestDetails->getArrayCopy());

                try {
                    if ($addSyncRequestDetails) {
                        //fetch all ref record for MM
                        $refIterator = $requestDetails->getVersionedQueryArtifacts();
                        $uid_local = tx_st9fissync::getInstance()->getSyncDBObject()->sql_insert_id();

                        for ($refIterator->rewind(); $refIterator->valid(); $refIterator->next()) {
                            try {
                                $refObject = $refIterator->current();
                                $refObject->set('uid_local',$uid_local);
                                $addSyncRequestDetails =  tx_st9fissync::getInstance()->getSyncDBObject()->exec_INSERTquery($this->syncRequestQueryVersioningMM,$refObject->getArrayCopy());
                                if ($addSyncRequestDetails) {
                                    $success = TRUE;
                                } else {
                                    throw new Exception ("Could not insert data into table `".$this->syncRequestQueryVersioningMM."` Error: ".tx_st9fissync::getInstance()->getSyncDBObject()->sql_error());
                                }
                            } catch (Exception $exception) {
                                $success = FALSE;
                                //logError $exception->getMessage()
                                continue;
                            }
                        }
                    } else {
                        throw new Exception ("Could not insert data into table `".$this->syncRequestTable."` Error: ".tx_st9fissync::getInstance()->getSyncDBObject()->sql_error());
                    }
                } catch (Exception $e) {
                    //logError $e->getMessage()
                }

            } catch (Exception $exception) {
                //logError $exception->getMessage()
            }
        }

        return $success;
    }

    public function getSyncRequestFieldList()
    {
        $syncRequestFieldList = array();
        $syncRequestFieldList['uid'] = 'uid';
        $syncRequestFieldList['pid'] = 'pid';
        $syncRequestFieldList['request_sent_tstamp'] = 'request_sent_tstamp';
        $syncRequestFieldList['request_sent'] = 'request_sent';
        $syncRequestFieldList['response_received_tstamp'] = 'response_received_tstamp';
        $syncRequestFieldList['response_received'] = 'response_received';
        $syncRequestFieldList['procid'] = 'procid';
        $syncProcessFieldList['remote_handle'] = 'remote_handle';
        $syncRequestFieldList['sync_type'] = 'sync_type';
        $syncRequestFieldList['versionedqueries'] = 'versionedqueries';

        $syncRequestTable = array();
        $syncRequestTable['tableName'] = $this->syncRequestTable;
        $syncRequestTable['fieldList'] = $syncRequestFieldList;

        return $syncRequestTable;
    }

    public function getSyncRequestRefQVFieldList()
    {
        $syncRequestRefQVFieldList = array();
        $syncRequestRefQVFieldList['uid_local'] = 'uid_local';
        $syncRequestRefQVFieldList['uid_foreign'] = 'uid_foreign';
        $syncRequestRefQVFieldList['tablenames'] = 'tablenames';
        //$syncRequestRefQVFieldList['sysid'] = 'sysid';
        $syncRequestRefQVFieldList['error_message'] = 'error_message';
        $syncRequestRefQVFieldList['isremote'] = 'isremote';

        $syncRequestRefQVTable = array();
        $syncRequestRefQVTable['tableName'] = $this->syncRequestQueryVersioningMM;
        $syncRequestRefQVTable['fieldList'] = $syncRequestRefQVFieldList;

        return $syncRequestRefQVTable;

    }

    /**
     *
     * @param  tx_st9fissync_request_handler $requestHandlerDetails
     * @throws Exception
     *
     * @return mixed $success
     */
    public function addSyncRequestHandlerArtifact(tx_st9fissync_request_handler $requestHandlerDetails = NULL)
    {
        $success = FALSE;
        if (is_null($requestHandlerDetails)) {
            return $success;
        } else {
            //add sync request handler Info
            try {
                $addSyncRequestHandlerDetails =  tx_st9fissync::getInstance()->getSyncDBObject()->exec_INSERTquery($this->syncRequestHandlerTable,$requestHandlerDetails->getArrayCopy());
                $success = $addSyncRequestHandlerDetails;
            } catch (Exception $exception) {
                //$exception->getMessage()
            }
        }

        return $success;
    }

    public function updateSyncRequestHandlerArtifact(tx_st9fissync_request_handler $requestHandlerDetails = NULL, $whereClause = '')
    {
        $success = FALSE;
        if (is_null($requestHandlerDetails)) {
            return $success;
        } else {
            try {
                $updateSyncRequestHandlerDetails = tx_st9fissync::getInstance()->getSyncDBObject()->exec_UPDATEquery($this->syncRequestHandlerTable, $whereClause, $requestHandlerDetails->getArrayCopy());
                if ($updateSyncRequestHandlerDetails) {
                    $success = $updateSyncRequestHandlerDetails;
                } else {
                    throw new Exception ("Could not update data into table `".$this->syncRequestHandlerTable."` Error: ".tx_st9fissync::getInstance()->getSyncDBObject()->sql_error());
                }
            } catch (Exception $e) {
                $success = FALSE;
                //logError $e->getMessage()
            }

        }

        return $success;
    }

    public function getSyncRequestHandlerFieldList()
    {
        $syncRequestHandlerFieldList = array();
        $syncRequestHandlerFieldList['uid'] = 'uid';
        $syncRequestHandlerFieldList['pid'] = 'pid';
        $syncRequestHandlerFieldList['request_received_tstamp'] = 'request_received_tstamp';
        $syncRequestHandlerFieldList['request_received'] = 'request_received';
        $syncRequestHandlerFieldList['response_sent_tstamp'] = 'response_sent_tstamp';
        $syncRequestHandlerFieldList['response_sent'] = 'response_sent';

        $syncRequestHandlerTable = array();
        $syncRequestHandlerTable['tableName'] = $this->syncRequestHandlerTable;
        $syncRequestHandlerTable['fieldList'] = $syncRequestHandlerFieldList;

        return $syncRequestHandlerTable;
    }

    /**
     *
     * @param array   $tablesFields
     * @param boolean $prependTableName
     * @param boolean $forSelect
     *
     * @return array
     */
    public function compileFieldList($tablesFields, $prependTableName=TRUE, $forSelect = TRUE)
    {
        $fieldList = array();
        $conflictFieldList = array();

        if ($tablesFields) {
            foreach ($tablesFields as $tableField) {
                $tableName = $tableField['tableName'];
                $tableFields = $tableField['fieldList'];
                foreach ($tableFields as $field) {

                    if (!array_key_exists($field, $conflictFieldList) && !in_array($field, $conflictFieldList)) {
                        $conflictFieldList[$field] = $field;
                        $queryAs = '';
                    } else {
                        $queryAs = ' AS ' . $tableName . '_' . $field;
                    }

                    if ($prependTableName) {
                        $fieldList[$tableName.'.'.$field] = $tableName . '.' . $field . ($forSelect? $queryAs : '');
                    } else {
                        $fieldList[$field] = $field . $queryAs;
                    }
                }
            }
        }

        return implode(', ', $fieldList);
    }

    public function getRowsForResultSet($res=NULL)
    {
        if (!tx_st9fissync::getInstance()->getSyncDBObject()->sql_error()) {
            $rows = array();
            while ($rows[] = tx_st9fissync::getInstance()->getSyncDBObject()->sql_fetch_assoc($res)) {
                ;
            }
            array_pop($rows);
            tx_st9fissync::getInstance()->getSyncDBObject()->sql_free_result($res);
        }

        return $rows;
    }

    /**
     * Determine if a particular column exists in a table if column name has been specified
     * If a col name has not been specified returns a list of columns and details for the specified table
     *
     * @param string $tableName
     * @param string $colName
     *
     * @return mixed
     */
    public function isColumnInTable($tableName,$colName = NULL)
    {
        if (!$this->isTableInDB($tableName)) {
            return false;
        }

        $columnsQuery = "SHOW COLUMNS FROM `" .  tx_st9fissync::getInstance()->getSyncDBObject()->quoteStr($tableName,$tableName) . "`";
        $cols =  tx_st9fissync::getInstance()->getSyncDBObject()->sql_query($columnsQuery);

        $fieldnames=array();
        if (tx_st9fissync::getInstance()->getSyncDBObject()->sql_num_rows($cols) > 0) {
            while ($row = tx_st9fissync::getInstance()->getSyncDBObject()->sql_fetch_assoc($cols)) {
                $fieldnames[] = $row['Field'];
            }
        }

        if(is_null($colName))

            return $fieldnames;
        else{
            $columnExists = in_array($colName, $fieldnames);
            try {
                if (!$columnExists) {
                    throw new Exception ("Error: Unknown column ".$tableName.".".$colName);
                }
            } catch (Exception $e) {
                // logError $e->getMessage()
            }

            return $columnExists;
        }
    }

    /**
     * Check to see if current table to processed is in DB or not
     *
     * @param  string  $tableName
     * @return boolean
     */
    public function isTableInDB($tableName)
    {
        $tableQuery = "SHOW TABLES LIKE '" . tx_st9fissync::getInstance()->getSyncDBObject()->quoteStr($tableName,$tableName) . "'";
        $table = tx_st9fissync::getInstance()->getSyncDBObject()->sql_query($tableQuery);
        $tableExists =  tx_st9fissync::getInstance()->getSyncDBObject()->sql_num_rows($table) > 0;

        try {
            if (!$tableExists) {
                throw new Exception ("Error: Table `".$tableName."` does not exists" );
            }
        } catch (Exception $e) {
            //logError $e->getMessage()
        }

        return $tableExists;
    }

    public function getLogTable()
    {
        return $this->logTable;
    }

    /**
     * Get garbage collector process table
     * @return string
     */
    public function getGCProcessTable()
    {
        return $this->gcProcessTable;
    }

    /**
     * Get garbage collector log table
     * @return string
     */
    public function getGCLogTable()
    {
        return $this->gcLogTable;
    }

    public function logToSyncLogTable($message, $priority)
    {
        $logArray = array();
        $logArray['pid'] = tx_st9fissync::getInstance()->getLoggerStoragePid();
        $logArray['sysid'] = tx_st9fissync::getInstance()->getSyncConfigManager()->getLogSystemId();
        $logArray['crdate'] = tx_st9fissync::getInstance()->getMicroTime();
        $logArray['log_message'] = $message;
        $logArray['log_priority'] = $priority;
        $logArray['log_stage'] = tx_st9fissync::getInstance()->getStage();
        $logArray['procid'] = tx_st9fissync::getInstance()->getSyncProcessHandle()->getProcId();

        return tx_st9fissync::getInstance()->getSyncDBObject()->exec_INSERTquery($this->logTable,$logArray);
    }

    public function logToGCProcTable($message, $priority)
    {
        $logArray = array();
        $logArray['pid'] = tx_st9fissync::getInstance()->getLoggerStoragePid();
        $logArray['sysid'] = tx_st9fissync::getInstance()->getSyncConfigManager()->getLogSystemId();
        $logArray['crdate'] = tx_st9fissync::getInstance()->getMicroTime();
        $logArray['log_message'] = $message;
        $logArray['log_priority'] = $priority;
        $logArray['log_stage'] = tx_st9fissync::getInstance()->getStage();
        $logArray['procid'] = tx_st9fissync_gc::getGCInstance()->getGCProcId();

        return tx_st9fissync::getInstance()->getSyncDBObject()->exec_INSERTquery($this->gcLogTable, $logArray);
    }

    /**
     * Store log in DB
     *
     * @param string $message
     * @param int    $priority
     *
     * @return boolean
     */
    public function logtoDB($message, $priority)
    {
        switch (tx_st9fissync::getInstance()->getStage()) {
            case tx_st9fissync::GC:
                return $this->logToGCProcTable($message, $priority);
            case  tx_st9fissync::SYNC:
                return $this->logToSyncLogTable($message, $priority);
            default:
                return false;
        }

        return false;
    }

    /**
     * Function for adding GC process
     * @param  array   $arrData
     * @return boolean
     */
    public function addGCProcess($arrData)
    {
        $success = FALSE;
        try {
            $addGCProcInfo =  tx_st9fissync::getInstance()->getSyncDBObject()->exec_INSERTquery($this->gcProcessTable, $arrData);
            $success = $addGCProcInfo;
        } catch (Exception $exception) {
            $success = FALSE;
        }

        return $success;
    }

    /**
     * Function for updating GC process
     * @param  array   $arrData
     * @param  string  $whereClause
     * @return boolean
     */
    public function updateGCProcess($arrData, $whereClause)
    {
        $success = FALSE;
        try {
            $updateGCProcInfo = tx_st9fissync::getInstance()->getSyncDBObject()->exec_UPDATEquery($this->gcProcessTable, $whereClause, $arrData);
            if ($updateGCProcInfo) {
                $success = TRUE;
            } else {
                throw new Exception ("Could not update data into table `".$this->gcProcessTable."` Error: ".tx_st9fissync::getInstance()->getSyncDBObject()->sql_error());
            }
        } catch (Exception $e) {
            $success = FALSE;
        }

        return $success;
    }

    /**
     * Function for getting the active GC process
     * @return boolean|array
     */
    public function getGCActiveProcesses()
    {
        $activeGCProcesses = array();
        try {
            $select_fields = '*';
            $from_table = $this->gcProcessTable;

            $where_clause .= ' ( gc_stage = ' . tx_st9fissync_gc::GCSTAGE_EXECUTING;
            $where_clause .= ')';
            $groupBy = '';
            $orderBy = '';
            $limit = '';
            $uidIndexField = 'uid';

            $activeGCProcesses = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTgetRows($select_fields,
                    $from_table,
                    $where_clause,
                    $groupBy, $orderBy, $limit,
                    $uidIndexField);

        } catch (Exception $exception) {
            $activeGCProcesses = FALSE;
        }

        return $activeGCProcesses;
    }

    /**
     * Function for getting synced queries by where clause
     * @param $whereclause
     * @return boolean|array
     */
    public function getSyncedQueriesByWhereClause($whereclause)
    {
        $syncedQueries = array();
        try {
            $select_fields = '*';
            $from_table = $this->queryVersioningTable;
            $where_clause .= $whereclause;
            $groupBy = '';
            $orderBy = '';
            $limit = '';
            $uidIndexField = 'uid';

            $syncedQueries = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTgetRows($select_fields,
                    $from_table,
                    $where_clause,
                    $groupBy, $orderBy, $limit,
                    $uidIndexField);
        } catch (Exception $exception) {
            $syncedQueries = FALSE;
        }

        return $syncedQueries;
    }

    /**
     * Function for getting synced uids from request
     * @param $syncedUids
     * @param $isRemote
     * @return boolean|array
     */
    public function getLocalUIDsForSyncedRecordFromRequest($syncedUids, $isRemote)
    {
        $requestMMQueries = array();
        try {
            $select_fields = 'DISTINCT uid_local';
            $from_table = $this->syncRequestQueryVersioningMM;
            $where_clause .= ' ( uid_foreign IN ('.$syncedUids.') AND isremote='.$isRemote.' ) ';
            $groupBy = '';
            $orderBy = '';
            $limit = '';
            $uidIndexField = 'uid_local';

            $requestMMQueries = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTgetRows($select_fields,
                    $from_table,
                    $where_clause,
                    $groupBy, $orderBy, $limit,
                    $uidIndexField);
        } catch (Exception $exception) {
            $requestMMQueries = FALSE;
        }

        return $requestMMQueries;
    }

    /**
     * Function for getting local uids for not synced records
     * @param $syncedUids
     * @param $localUids
     * @param $isRemote
     * @return boolean|array
     */
    public function getLocalUIDsForNotSyncedRecordFromRequest($syncedUids, $localUids, $isRemote)
    {
        $requestMMQueries = array();
        try {
            $select_fields = 'DISTINCT uid_local';
            $from_table = $this->syncRequestQueryVersioningMM;
            $where_clause .= ' ( uid_foreign NOT IN ('.$syncedUids.') AND uid_local IN ('.$localUids.') AND isremote='.$isRemote.' ) ';
            $groupBy = '';
            $orderBy = '';
            $limit = '';
            $uidIndexField = 'uid_local';

            $requestMMQueries = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTgetRows($select_fields,
                    $from_table,
                    $where_clause,
                    $groupBy, $orderBy, $limit,
                    $uidIndexField);
        } catch (Exception $exception) {
            $requestMMQueries = FALSE;
        }

        return $requestMMQueries;
    }

    /**
     * Getting sync request details by uids
     * @param  string        $requestUids
     * @return array|boolean
     */
    public function getRequestDetailsBasedOnUids($requestUids)
    {
        $requestQueries = array();
        try {
            $select_fields = '*';
            $from_table = $this->syncRequestTable;
            $where_clause .= ' ( uid IN ('.$requestUids.') ) ';
            $groupBy = '';
            $orderBy = '';
            $limit = '';
            $uidIndexField = 'uid';

            $requestQueries = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTgetRows($select_fields,
                    $from_table,
                    $where_clause,
                    $groupBy, $orderBy, $limit,
                    $uidIndexField);
        } catch (Exception $exception) {
            $requestQueries = FALSE;
        }

        return $requestQueries;
    }

    /**
     * Function for getting the procids other than request ids
     *
     * @param  string        $strProcIds
     * @param  string        $strRequestUidsCanBeDeleted
     * @return array|boolean
     */
    public function getProcIdsOtherThanRequestUids($strProcIds, $strRequestUidsCanBeDeleted)
    {
        $requestQueries = array();
        try {
            $select_fields = 'DISTINCT procid';
            $from_table = $this->syncRequestTable;
            $where_clause .= ' ( uid NOT IN ('.$strRequestUidsCanBeDeleted.') AND procid IN ('.$strProcIds.') ) ';
            $groupBy = '';
            $orderBy = '';
            $limit = '';
            $uidIndexField = 'uid';

            $requestQueries = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTgetRows($select_fields,
                    $from_table,
                    $where_clause,
                    $groupBy, $orderBy, $limit,
                    $uidIndexField);
        } catch (Exception $exception) {
            $requestQueries = FALSE;
        }

        return $requestQueries;
    }

    /**
     * @return array|boolean
     */
    public function getRequestProcIds()
    {
        $procQueries = array();
        try {
            $select_fields = 'DISTINCT procid';
            $from_table = $this->syncRequestTable;
            #$where_clause .= '';
            $groupBy = '';
            $orderBy = '';
            $limit = '';
            $uidIndexField = 'procid';

            $procQueries = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTgetRows($select_fields,
                    $from_table,
                    $where_clause,
                    $groupBy, $orderBy, $limit,
                    $uidIndexField);
        } catch (Exception $exception) {
            $procQueries = FALSE;
        }

        return $procQueries;
    }

    /**
     * @param  string        $procids
     * @return array|boolean
     */
    public function getProcIdsWhereNoRequestIds($procids, $timestamp)
    {
        $procQueries = array();
        try {
            $select_fields = 'uid';
            $from_table = $this->syncProcessTable;
            $where_clause .= ' ( uid NOT IN ('.$procids.') AND syncproc_starttime < '.$timestamp.') ';
            $groupBy = '';
            $orderBy = '';
            $limit = '';
            $uidIndexField = 'uid';

            $procQueries = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTgetRows($select_fields,
                    $from_table,
                    $where_clause,
                    $groupBy, $orderBy, $limit,
                    $uidIndexField);
        } catch (Exception $exception) {
            $procQueries = FALSE;
        }

        return $procQueries;
    }

    /**
     * Function for getting the GC procids by timestamp
     *
     * @param $timestamp
     * @return array|boolean
     */
    public function getGCProcIdsByTimestamp($timestamp)
    {
        $procQueries = array();
        try {
            $select_fields = 'uid';
            $from_table = $this->gcProcessTable;
            $where_clause .= ' ( crdate < '.$timestamp.' ) ';
            $groupBy = '';
            $orderBy = '';
            $limit = '';
            $uidIndexField = 'uid';

            $procQueries = tx_st9fissync::getInstance()->getSyncDBObject()->exec_SELECTgetRows($select_fields,
                    $from_table,
                    $where_clause,
                    $groupBy, $orderBy, $limit,
                    $uidIndexField);
        } catch (Exception $exception) {
            $procQueries = FALSE;
        }

        return $procQueries;
    }

    /**
     * Function for deleting records
     *
     * @param  string  $tablename
     * @param  string  $whereclause
     * @return boolean
     */
    public function deleteRecordsFromTable($tablename, $whereclause, $optimizeTable=true)
    {
        try {
            $deleteQueries = tx_st9fissync::getInstance()->getSyncDBObject()->exec_DELETEquery($tablename, $whereclause);
            $affectedRows = tx_st9fissync::getInstance()->getSyncDBObject()->sql_affected_rows();
            if ($optimizeTable && $affectedRows > 0) {
                $this->optimizeTable($tablename);
            }
        } catch (Exception $exception) {
            $deleteQueries = FALSE;
        }

        return array('deleteQueries'=>$deleteQueries, 'affectedRows'=>$affectedRows);
    }

}

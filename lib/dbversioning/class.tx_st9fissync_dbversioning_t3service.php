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

* T3 query and DB actions recording service
*
*
* @author <info@studioneun.de>
* @package TYPO3
* @subpackage st9fissync
*
*/

class tx_st9fissync_dbversioning_t3service
{
    /**
     * Typical PID column name used in T3 tables
     *
     * @var string
     */
    private $pidColumn = 'pid';

    /**
     * Typical UID/ID column names used in T3 tables
     *
     * @var string
     */
    private $uidColumn = 'uid';

    /**
     * Crucial array that determines factors such as
     * 1.rootId: root page ID of the branch for which the record is allowed to synced
     * 2.excludeid: root page ID of the branch for which the record has been excluded from the next sync
     * 3.issyncscheduled: is record eligible to be synced
     *
     * @var array
     */
    private $syncFactors = array('rootid' => 0,'excludeid' => 0, 'issyncscheduled' => 0);

    /**
     * Current table for which the recording services have to be rendered
     *
     * @var string
     */
    private $table;

    /**
     * Current table characteristic is uid/pid enabled
     *
     * @var array
     */
    private $tableCharacteristic = array('uid' => true, 'pid' => true);

    /**
     * Extremely important data structure that holds tips for the versioning service
     * to render its core functionality
     *
     * @var mixed
     */
    private $versionTips;

    /**
     *
     * @var t3lib_DB
     */
    private $_temp_TYPO3_DB = null;

    /**
     * @var tx_st9fissync_dbversioning_sqlparser
     */
    private $dbVersioningSQLParser = null;

    /**
     *
     * @var tx_st9fissync
     */
    private $syncAPI = null;

    /**
     *
     * @var tx_st9fissync_db
     */
    private $syncDb = null;

    /**
     * To be added for postprocessing activities
     *
     * @var int
     */
    private $lastAddedMainQueryQVId = 0;

    /**
     * Supported Operations mapping
     *
     */
    const INSERT = 1;
    const MULTIINSERT = 2;
    const UPDATE = 3;
    const DELETE = 4;
    const TRUNCATE = 5;

    /**
     * Initialize recording variables and configuration parameters
     *
     * @param array $conf
     *
     */
    public function __construct()
    {
        $this->versionTips = array();

        $this->dbVersioningSQLParser = new tx_st9fissync_dbversioning_sqlparser();
        $this->syncAPI = tx_st9fissync::getInstance();
        $this->syncDb = $this->syncAPI->getSyncDBOperationsManager();
    }

    /**
     * Classify query type
     * Will be moved to configuration as a command <-> supported operations type mapping
     *
     * @param void
     * @return int
     */
    public function getQueryType()
    {
        switch ($this->versionTips['cmd']) {
            case 'exec_UPDATEquery':
                return tx_st9fissync_dbversioning_t3service::UPDATE;
            case 'exec_INSERTquery':
                return tx_st9fissync_dbversioning_t3service::INSERT;
            case 'exec_INSERTmultipleRows':
                return tx_st9fissync_dbversioning_t3service::MULTIINSERT;
            case 'exec_DELETEquery':
                return tx_st9fissync_dbversioning_t3service::DELETE;
            case 'exec_TRUNCATEquery':
                return tx_st9fissync_dbversioning_t3service::TRUNCATE;
            default:
                //log?? is not an allowed command type for query execution
                break;
        }

        return 0;// undetermined type, implement parsing query later
    }

    /**
     * Number of tables affected/written to due to a write (Update/Insert) query
     * Currently assuming only 1
     *
     * @param void
     * @return int
     */
    public function getNumOfTablesForQuery()
    {
        $numOfTables = 1; // nothing complex as of now, assuming all queries are hitting a single table for writing, needs improvement

        return $numOfTables;
    }

    /**
     * Check if current table is configured to use the versioning
     *
     * @param void
     * @return boolean
     */
    public function isTableVersionEnabled()
    {
        if (in_array($this->table,$this->syncAPI->getSyncConfigManager()->getVersionEnabledTablesList())) {
            return true;
        }

        return false;
    }

    /**
     * Check if a current table is configured to force exclude from versioning
     *
     * @param string
     * @return boolean
     */
    public function isTableVersionExcluded($table)
    {
        if (in_array($table,$this->syncAPI->getSyncConfigManager()->getVersionDisabledTablesList())) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Analyze an T3 INSERT query array to find and return T3 PID column
     *
     * @see analyzeInsertRootVersionEnabled()
     * @param mixed $fieldValues
     *
     * @return mixed
     */
    private function analyzeInsertForPid(array $fieldValues)
    {
        if (array_key_exists($this->pidColumn, $fieldValues)) {
            return $fieldValues[$this->pidColumn][0];
        } elseif ($this->syncDb->isTableInDB($this->table) && array_key_exists($this->pidColumn, tx_st9fissync::getInstance()->getSyncDBObject()->admin_get_fields($this->table))) {

            //this should not be the case, if it is maybe pid is an auto field or something like that? so fetch it anyway from DB
            $recId = 0;
            if (!array_key_exists($this->uidColumn,$fieldValues)) {
                //something wrong as uid is not in insert query
                //Could not find uid in insert query
            } else {
                $recId = $fieldValues[$this->uidColumn][0];
            }

            if ($recId > 0) {
                return $this->syncDb->getPidForTableRecord($recId, $this->table);
            } else {
                //Unable to fetch uid
            }
        }

        return false;
    }

    /**
     * Validate to check if a T3 record is in any of branches for an allowed root PID
     *
     * @see analyzeInsertRootVersionEnabled()
     * @param mixed $item
     *
     * @return boolean
     */
    public function validateObjectInRoot($item)
    {
        return in_array($item['uid'], $this->syncAPI->getSyncConfigManager()->getVersionEnabledRootPidList());
    }

    public function getParentInRootLineSupported($rootLine)
    {
        $parentInRootLineSupported = array();
        foreach ($rootLine as $item) {
            if (in_array($item['uid'], $this->syncAPI->getSyncConfigManager()->getVersionEnabledRootPidList())) {
                $parentInRootLineSupported[] = $item;
            }
        }

        return $parentInRootLineSupported;
    }

    /**
     * Validate to check if a T3 record is in any of branches for an excluded root PID
     *
     * @see analyzeInsertRootVersionEnabled()
     * @param mixed $item
     *
     * @return boolean
     */
    public function invalidateObjectInRoot($item)
    {
        return in_array($item['uid'], $this->syncAPI->getSyncConfigManager()->getVersionExcludePidList());
    }

    public function getParentInRootLineExcluded($rootLine)
    {
        $parentInRootLineExcluded = array();
        foreach ($rootLine as $item) {
            if (in_array($item['uid'], $this->syncAPI->getSyncConfigManager()->getVersionExcludePidList())) {
                $parentInRootLineExcluded[] = $item;
            }
        }

        return $parentInRootLineExcluded;
    }

    /**
     * Analyze an T3 INSERT query to ascertain that the record has to be synced or not.
     * Setup sync factor 'issyncscheduled' and use it for recording a DB INSERT action.
     * Table must contain PID column, relieving that duty for the caller or the context and not checking here
     *
     * @param  int  $insertQueryType
     * @return void
     */
    public function analyzeInsertRootVersionEnabled($insertQueryType)
    {
        $parsedQueryString = $this->dbVersioningSQLParser->parseSQL($this->versionTips['executedQuery']);

        if ($this->dbVersioningSQLParser->parse_error != '') {
            //log Error ?? $this->dbVersioningSQLParser->parse_error
        }

        $pidArray = array();

        if ($insertQueryType == tx_st9fissync_dbversioning_t3service::INSERT) {

            $pid = $this->analyzeInsertForPid($parsedQueryString['FIELDS']);
            if ($pid) {
                $pidArray[] = $pid;
            }
        } elseif ($insertQueryType == tx_st9fissync_dbversioning_t3service::MULTIINSERT) {

            foreach ($parsedQueryString['FIELDS'] as $fieldValues) {
                $pid = $this->analyzeInsertForPid($fieldValues);
                if ($pid) {
                    $pidArray[] = $pid;
                }
            }
        }

        $scheduleSync = false;
        $sys_page = t3lib_div::makeInstance("t3lib_pageSelect");

        foreach ($pidArray as $key => $pidVal) {

            $_currentRootLine = $sys_page->getRootLine($pidVal);

            if ($sys_page->error_getRootLine != '') {
                //log Error?? $sys_page->error_getRootLine." Fail Pid :".$sys_page->error_getRootLine_failPid
            }

            //$_parentInRootLineExcluded = array_values(array_filter($_currentRootLine, array($this,'invalidateObjectInRoot')));
            $_parentInRootLineExcluded = $this->getParentInRootLineExcluded($_currentRootLine);
            //$_parentInRootLineSupported = array_values(array_filter($_currentRootLine, array($this,'validateObjectInRoot')));
            $_parentInRootLineSupported = $this->getParentInRootLineSupported($_currentRootLine);

            if (count($_parentInRootLineExcluded) > 0 && count($_parentInRootLineSupported) > 0) {

                $this->syncFactors['rootid'] = $_parentInRootLineSupported[0]['uid'];
                $this->syncFactors['excludeid'] = $_parentInRootLineExcluded[0]['uid'];

                //evaluate if supported rootline is a child branch of excluded root line, if no overlap is found must set sync schedule to true
                $_excludedRootLine = $sys_page->getRootLine($_parentInRootLineExcluded[0]['uid']);
                $_conflictingRootLine = array();
                foreach ($_excludedRootLine as $excludedItems) {
                    if ($excludedItems['uid'] == $_parentInRootLineSupported[0]['uid']) {
                        $_conflictingRootLine[] = $excludedItems;
                    }
                }
                if (count($_conflictingRootLine) == 0) {
                    //no conflict/overlap between the 2 branches so schedule it for this query
                    $scheduleSync = true;
                    $this->syncFactors['issyncscheduled'] = 1;
                    //No conflict or overlap between two branches. Sync scheduled
                    break;
                } else {
                    //Conflict found in exclude rootline and supported rootline. Sync not scheduled
                }
            } elseif (count($_parentInRootLineSupported) > 0) {
                $this->syncFactors['rootid'] = $_parentInRootLineSupported[0]['uid'];
                $scheduleSync = true;
                $this->syncFactors['issyncscheduled'] = 1;
                break;
            } else {
                //All other cases now continue evaluating for other pids and check for a possible support/exclude scenario
            }
        }

        return $scheduleSync;

    }

    /**
     * Function to set up Sync factors in hard to determine / uncertain contexts such as an UPDATE/DELETE query
     *
     *  @see analyzeUpdateRootVersionEnabled
     *  @see analyzeDeleteRootVersionEnabled
     *  @param void
     *  @return void
     */
    public function setUpIndeterminateSyncFactors()
    {
        $this->syncFactors['rootid'] = 0;// will analyze update/delete query later
        $this->syncFactors['excludeid'] = 0; // will analyze update/delete query later

        //set up update for sync records anyway, it doesn't hurt to sync and execute such query commands
        // as trying to update non-existent records would mean nothing, so no harm.
        //On the other hand do compute if possible for a likely chance where it can be for certain said,
        //"Updates for current record is not required to be synced and executed". We can find this from
        // any tracked/recorded 'ROOT'/ originator query for the record like an "INSERT".
        //If such a root recording is found to be ineligible to synced, then it can be certainly said, subsequent
        //queries against this record UID and hence also marked the same.

        if ($this->versionTips['query_affectedrows'] > 0) {
            $this->syncFactors['issyncscheduled'] = 1; //read above
        }

        $breakFromOuterLoop = false;
        //track back the original INSERT query for records affected by update/delete
        if ($this->syncDb->isColumnInTable($this->table,$this->uidColumn)) {
            foreach ($this->versionTips["recordRevision"] as $recordRow) {

                $trackRecInsert = $this->syncDb->getIndexedInsertQuery($recordRow[$this->uidColumn], $this->table);

                if ($trackRecInsert && is_array($trackRecInsert) && count($trackRecInsert) > 0) {

                    //Previous insert tracked
                    foreach ($trackRecInsert as $recordInsert) {
                        //maybe readjust issyncscheduled before going to the next step, necessary if an update has pid set to
                        // a page not supposed to be synced
                        if ($recordInsert['issyncscheduled']) {
                            //$this->syncFactors['issyncscheduled'] flag is already set, nothing to do just break from here
                            //assuming this update definitely needs and syncschedule flag
                            $breakFromOuterLoop = true;
                            //Sync factor of previous insert is already set, so current sync schedule will be set by logic
                            break;

                        } else {
                            $this->syncFactors['issyncscheduled'] = 0; //read above
                            //keep checking
                            continue;
                        }
                    }
                } else {
                    //no inserts found
                    //No Previous insert tracked
                    continue;

                }
                if ($breakFromOuterLoop) {
                    $this->syncFactors['issyncscheduled'] = 1;
                    break;
                }

            }
        } else {
            //case of no uids in a table
            //Column UID is not found for table  skipping track back
        }

    }

    /**
     * Currently no specific processing to determine if an updated record and query should be part of sync factors
     * Using a generic method to setup up sync factors for an indeterminate situation such as this
     *
     * @see $syncFactors
     * @see setUpSyncFactors()
     */
    public function analyzeUpdateRootVersionEnabled()
    {
        //use this temporarily for updates/deletes

        /**
         * use-case to handle cut-paste operations, not implmeneted as of now (also not in specs)
         */

        $this->setUpIndeterminateSyncFactors();

        return true;
    }

    /**
     * Currently no specific processing to determine if an deleted record and query should be part of sync factors
     * Using a generic method to setup up sync factors for an indeterminate situation such as this
     *
     * @see $syncFactors
     * @see setUpSyncFactors()
     */
    public function analyzeDeleteRootVersionEnabled()
    {
        //use this temporarily for updates/deletes
        $this->setUpIndeterminateSyncFactors();

        return true;
    }

    /**
     * Setup sync factors
     *
     * @see $syncFactors
     */
    public function setUpSyncFactors()
    {
        if (!$this->isTableVersionEnabled()) {
            //if table is not allowed to be in version, skip sync for such queries
            $this->syncFactors['issyncscheduled'] = 0;

            return 0;
        }

        if (!$this->syncDb->isColumnInTable($this->table,$this->pidColumn)) {
            //version records from table with no 'pid' column, schedule sync for them anyway
            $this->syncFactors['issyncscheduled'] = 1;

            return 1;
        }

        $queryType = $this->getQueryType();
        switch ($queryType) {
            case tx_st9fissync_dbversioning_t3service::INSERT:
                if ($this->analyzeInsertRootVersionEnabled(tx_st9fissync_dbversioning_t3service::INSERT)) {
                    return 1;
                }
                break;
            case tx_st9fissync_dbversioning_t3service::MULTIINSERT:
                if ($this->analyzeInsertRootVersionEnabled(tx_st9fissync_dbversioning_t3service::MULTIINSERT)) {
                    return 1;
                }
                break;
            case tx_st9fissync_dbversioning_t3service::UPDATE:
                if ($this->analyzeUpdateRootVersionEnabled()) {
                    return 1;
                }
                break;
            case tx_st9fissync_dbversioning_t3service::DELETE:
                if ($this->analyzeDeleteRootVersionEnabled()) {
                    return 1;
                }
                break;
            case tx_st9fissync_dbversioning_t3service::TRUNCATE:
                if ($this->versionTips['query_affectedrows'] > 0) {
                    //Truncate query effects rows, hence scheduled for sync
                    return 1;
                } else {
                    //Truncate query has no effect, hence not scheduled for sync
                    return 0;
                }
            default:
                //log - unrecognized query type so not to be scheduled but to be reviewed
                break;

        }

        return 0;
    }

    /**
     * Various post-process activities
     *
     */
    public function postProcess()
    {
        //later move this to hook based implementation, postProcessHandlers
        /**
        * Use-case 1: "Insert Records", CType - 'shortcut'
        */
        $this->processInsertRecordsUseCase();

        /**
         * Use-case 2: DAM References for Records "Update" scenario, after inserting relationship into tx_dam_mm_ref
         */
        $this->processDAMReferencesUseCase();

        /**
         * Use-case 3: DAM References for Records "Insert" scenario, before inserting relationship into tx_dam_mm_ref
         */
        $this->processDAMReferencesUseCase2();

        /**
         * Use-case 3: DAM References for Records with RTE linking relationship into sys_refindex
         */
        $this->processDAMReferencesUseCase3();


        // and other such use-cases follow

        /**
         * Last finisher in case of query type single 'INSERT' for TCA based use-case
         * FIX FOR: new content element wizard, TCA form reload, event: onselect of another CE, reports:
         * "Sorry, you didn't have proper permissions to perform this change."
         *
         * P.S. Seems to have found a "real" fix
         *
         */

        /*
         if (tx_st9fissync_dbversioning_t3service::INSERT == $this->getQueryType()) {
        $this->syncDb->handleTCEFormsOnSelect($this->versionTips['recordRevision'][0]['uid']);
        //After this the original record insert LAST_INSERT_ID has been reset for TCE forms handling
        }
        */

    }

    public function resolveDAMdependenciesBySysRefIndexId($sysRefRefTableName, $sysRefRecUID, $sysRefDAMIndexRefUID, $sysRefIdentSoftRefKeyVal)
    {
        global $TCA;
        if ($this->syncDb->isTableInDB($sysRefTableName) && $sysRefRecUID) {

            $damResources = $this->syncAPI->getSysIndexReferencedFiles($sysRefRefTableName, $sysRefRecUID, $sysRefDAMIndexRefUID, $sysRefIdentSoftRefKeyVal);

            foreach ($damResources['files'] as $damRecId => $damResourcePath) {

                /**
                 *
                 * For the current DAM record do 2 things:
                 * 1.Collect and record all indexing of the current DAM record
                 * 1.1 If the first insert query for first indexing of the DAM resouce has been recorded,
                 * 1.1.1 Check to see if this indexing event has been scheduled for sync
                 * 1.1.1.1 If it has already been scheduled for sync, check to see if it has already been synced
                 * if it has already been synced, check to see if it needs to be synced again by checking the resources indexing status
                 * If this test suggests changes, reset the flags to be synced (issyncscheduled) & synced (synced)
                 *
                 * 1.1.2 if it has not been scheduled for sync, reset the flags to be synced (issyncscheduled) & synced (synced)
                 *
                 * 1.1.3 if root insert query has been marked for syncing (resetting of flags), reset these flags for subsequent actions
                 * such as updates/re-updates/deletes
                 *
                 * 1.2 If first insert query has not been recorded at all create an insert query from the current indexing record
                 * i.e. select the dam record row and create/compile an insert query, ignore update/re-update events if not root insert
                 * query has been recorded for the dam record in such a case.
                 *
                 * 2.Also update the tx_dam_mm_ref insert/update event recording to sync scheduled
                 *
                 * 3.Physically handling of the resource can be done on demand basis since we have the DAM indexes updated 'to be synced'
                 *
                 *
                 */

                //First collect, categorize and setup for the later strategies to be deployed - 1
                //find indexed record insert query using $damRecId
                //now check if this record revision exists has already been added to version

                $damRecordRevisions = $this->syncDb->getIndexedDAMInsertQuery($damRecId);

                $rootRevisionExists = FALSE;
                $revisionExists = null;

                if (count($damRecordRevisions) > 0) {

                    //DAM Revisions found.
                    foreach ($damRecordRevisions as $damRecordRevision) {

                        //Group revisions by inserts and then subsequent updates
                        if (!$revisionExists) {
                            $revisionExists = array();
                        }
                        //Root revision is when the record was created i.e. a root revision is only possible by an INSERT query
                        if ($damRecordRevision['query_type'] == tx_st9fissync_dbversioning_t3service::INSERT) {
                            $revisionExists[$damRecordRevision[$this->syncDb->getDAMMainTableName() . '_uid']] = $damRecordRevision;
                            $rootRevisionExists = TRUE;

                        } else {
                            //Subsequent revisions is when the record was updated i.e. an UPDATE query
                            $revisionExists[$damRecordRevision[$this->syncDb->getDAMMainTableName() . '_uid']]['_nextInSequenceRevisions'][] = $damRecordRevision;
                        }

                    }

                }
                //End of step-1

                //if any revision has been found which means the creation event for this record has been 'recorded'
                if ($revisionExists && $rootRevisionExists) {
                    foreach ($revisionExists as $rootRevisionRecIdKey => $rootRevision) {

                        $forceScheduleSyncForDAMIndex = true;
                        $setScheduleSyncForDAMIndex = array();
                        $whereScheduleSyncForDAMIndex = '';

                        if ($rootRevision['issynced']) { //if this dam index has already been synced, check if the file has changed
                            $damResourceStatus = tx_dam::index_check($damResourcePath,$damResources['rows'][$damRecId]['file_hash']);

                            //even if a file index has been 'synced', the file itself might have changed so check for '__status'
                            //which means until we have a re-indexing we still have to sync the physical file if not the index
                            if ($damResourceStatus['__status'] == TXDAM_file_changed) {
                                $forceScheduleSyncForDAMIndex = true;
                            } else {
                                $forceScheduleSyncForDAMIndex = false;
                            }
                        }

                        if ($forceScheduleSyncForDAMIndex) {

                            $forceScheduleSyncForDAMIndexQVId = array();
                            $forceScheduleSyncForDAMIndexQVId[] = $rootRevision['uid'];

                            if ($revisionExists[$rootRevisionRecIdKey]['_nextInSequenceRevisions']) {
                                foreach ($revisionExists[$rootRevisionRecIdKey]['_nextInSequenceRevisions'] as $subsequentRevision) {
                                    $forceScheduleSyncForDAMIndexQVId[] = $subsequentRevision['uid'];
                                }
                            } else {
                                //log that subsequent revisions do not exist
                            }
                            $this->syncDb->setSyncScheduledForIndexedQueries($forceScheduleSyncForDAMIndexQVId);
                        }
                    }
                } else {
                    //compile a root revision  - 1.2
                    $damRecordIndexQToVersion = $this->syncAPI->compileRootRevisionQuery($damRecId, 'tx_dam');
                    if ($damRecordIndexQToVersion) {
                        $damRecordIndexQueryArtifact = t3lib_div::makeInstance('tx_st9fissync_dbversioning_query');
                        $damRecordIndexQueryArtifact->set('query_text',trim($damRecordIndexQToVersion));
                        $damRecordIndexQueryArtifact->set('query_type',tx_st9fissync_dbversioning_t3service::INSERT);
                        $damRecordIndexQueryArtifact->set('query_affectedrows',1);
                        $damRecordIndexQueryArtifact->set('issyncscheduled',1);
                        $damRecordIndexQueryArtifact->set('tables',$this->getNumOfTablesForQuery());

                        $damRecordIndexQueryRefRecordArtifact = t3lib_div::makeInstance('tx_st9fissync_dbversioning_query_mm');
                        $damRecordIndexQueryRefRecordArtifact->set('uid_foreign',$damRecId);
                        $damRecordIndexQueryRefRecordArtifact->set('tablenames',$this->syncDb->getDAMMainTableName());
                        $damRecordIndexQueryArtifact->getTableRows()->append($this->syncAPI->buildQueryRefRecArtifact($damRecordIndexQueryRefRecordArtifact));

                        $this->syncDb->addQueryArtifactToVersion($this->syncAPI->buildQueryArtifact($damRecordIndexQueryArtifact));

                    }

                }

            }

        } else {
            return false;
        }
    }

    public function resolveDAMdependencies($table,$recId)
    {
        global $TCA;
        if ($this->syncDb->isTableInDB($table) && $recId) {

            $this->syncAPI->loadTableTCAForDAMFEMode($table);

            $damResources = tx_dam_db::getReferencedFiles($table, $recId);
            foreach ($damResources['files'] as $damRecId => $damResourcePath) {

                /**
                 *
                 * For the current DAM record do 2 things:
                 * 1.Collect and record all indexing of the current DAM record
                 * 1.1 If the first insert query for first indexing of the DAM resouce has been recorded,
                 * 1.1.1 Check to see if this indexing event has been scheduled for sync
                 * 1.1.1.1 If it has already been scheduled for sync, check to see if it has already been synced
                 * if it has already been synced, check to see if it needs to be synced again by checking the resources indexing status
                 * If this test suggests changes, reset the flags to be synced (issyncscheduled) & synced (synced)
                 *
                 * 1.1.2 if it has not been scheduled for sync, reset the flags to be synced (issyncscheduled) & synced (synced)
                 *
                 * 1.1.3 if root insert query has been marked for syncing (resetting of flags), reset these flags for subsequent actions
                 * such as updates/re-updates/deletes
                 *
                 * 1.2 If first insert query has not been recorded at all create an insert query from the current indexing record
                 * i.e. select the dam record row and create/compile an insert query, ignore update/re-update events if not root insert
                 * query has been recorded for the dam record in such a case.
                 *
                 * 2.Also update the tx_dam_mm_ref insert/update event recording to sync scheduled
                 *
                 * 3.Physically handling of the resource can be done on demand basis since we have the DAM indexes updated 'to be synced'
                 *
                 *
                 */

                //First collect, categorize and setup for the later strategies to be deployed - 1
                //find indexed record insert query using $damRecId
                //now check if this record revision exists has already been added to version
                $damRecordRevisions = $this->syncDb->getIndexedDAMInsertQuery($damRecId);

                $rootRevisionExists = FALSE;
                $revisionExists = null;

                if (count($damRecordRevisions) > 0) {

                    //DAM Revisions found.
                    foreach ($damRecordRevisions as $damRecordRevision) {

                        //Group revisions by inserts and then subsequent updates
                        if (!$revisionExists) {
                            $revisionExists = array();
                        }
                        //Root revision is when the record was created i.e. a root revision is only possible by an INSERT query
                        if ($damRecordRevision['query_type'] == tx_st9fissync_dbversioning_t3service::INSERT) {
                            $revisionExists[$damRecordRevision[$this->syncDb->getDAMMainTableName() . '_uid']] = $damRecordRevision;
                            $rootRevisionExists = TRUE;

                        } else {
                            //Subsequent revisions is when the record was updated i.e. an UPDATE query
                            $revisionExists[$damRecordRevision[$this->syncDb->getDAMMainTableName() . '_uid']]['_nextInSequenceRevisions'][] = $damRecordRevision;
                        }

                    }

                }
                //End of step-1

                //if any revision has been found which means the creation event for this record has been 'recorded'
                if ($revisionExists && $rootRevisionExists) {
                    foreach ($revisionExists as $rootRevisionRecIdKey => $rootRevision) {

                        $forceScheduleSyncForDAMIndex = true;
                        $setScheduleSyncForDAMIndex = array();
                        $whereScheduleSyncForDAMIndex = '';

                        if ($rootRevision['issynced']) { //if this dam index has already been synced, check if the file has changed
                            $damResourceStatus = tx_dam::index_check($damResourcePath,$damResources['rows'][$damRecId]['file_hash']);

                            //even if a file index has been 'synced', the file itself might have changed so check for '__status'
                            //which means until we have a re-indexing we still have to sync the physical file if not the index
                            if ($damResourceStatus['__status'] == TXDAM_file_changed) {
                                $forceScheduleSyncForDAMIndex = true;
                            } else {
                                $forceScheduleSyncForDAMIndex = false;
                            }
                        }

                        if ($forceScheduleSyncForDAMIndex) {

                            $forceScheduleSyncForDAMIndexQVId = array();
                            $forceScheduleSyncForDAMIndexQVId[] = $rootRevision['uid'];

                            if ($revisionExists[$rootRevisionRecIdKey]['_nextInSequenceRevisions']) {
                                foreach ($revisionExists[$rootRevisionRecIdKey]['_nextInSequenceRevisions'] as $subsequentRevision) {
                                    $forceScheduleSyncForDAMIndexQVId[] = $subsequentRevision['uid'];
                                }
                            } else {
                                //log that subsequent revisions do not exist
                            }
                            $this->syncDb->setSyncScheduledForIndexedQueries($forceScheduleSyncForDAMIndexQVId);
                        }
                    }
                } else {
                    //compile a root revision  - 1.2
                    $damRecordIndexQToVersion = $this->syncAPI->compileRootRevisionQuery($damRecId, 'tx_dam');
                    if ($damRecordIndexQToVersion) {
                        $damRecordIndexQueryArtifact = t3lib_div::makeInstance('tx_st9fissync_dbversioning_query');
                        $damRecordIndexQueryArtifact->set('query_text',trim($damRecordIndexQToVersion));
                        $damRecordIndexQueryArtifact->set('query_type',tx_st9fissync_dbversioning_t3service::INSERT);
                        $damRecordIndexQueryArtifact->set('query_affectedrows',1);
                        $damRecordIndexQueryArtifact->set('issyncscheduled',1);
                        $damRecordIndexQueryArtifact->set('tables',$this->getNumOfTablesForQuery());

                        $damRecordIndexQueryRefRecordArtifact = t3lib_div::makeInstance('tx_st9fissync_dbversioning_query_mm');
                        $damRecordIndexQueryRefRecordArtifact->set('uid_foreign',$damRecId);
                        $damRecordIndexQueryRefRecordArtifact->set('tablenames',$this->syncDb->getDAMMainTableName());
                        $damRecordIndexQueryArtifact->getTableRows()->append($this->syncAPI->buildQueryRefRecArtifact($damRecordIndexQueryRefRecordArtifact));

                        $this->syncDb->addQueryArtifactToVersion($this->syncAPI->buildQueryArtifact($damRecordIndexQueryArtifact));

                    }

                }

            }

        } else {
            return false;
        }
    }

    /**
     * sys_refindex references for RTE based linking to DAM indexes
     * @param void
     *
     * @return mixed
     */
    public function processDAMReferencesUseCase3()
    {
        if (t3lib_extMgm::isLoaded('dam') && $this->table == $this->syncDb->getDAMRecRTERefTableName()) {

            if (tx_st9fissync_dbversioning_t3service::INSERT == $this->getQueryType()) {
                //extract uid_foreign,tablenames
                $extractForeignRecDetails = $this->dbVersioningSQLParser->parseSQL($this->versionTips['executedQuery']);

                if(($extractForeignRecDetails['FIELDS']['softref_key'][0] == 'mediatag' ||
                        $extractForeignRecDetails['FIELDS']['softref_key'][0] == 'media') &&
                        $extractForeignRecDetails['FIELDS']['ref_table'][0] == $this->syncDb->getDAMMainTableName()) {

                    //determine if this record for which dam dependencies are supposed to be sync resolved is itself scheduled to be synced
                    $additionalWhereClause = ' AND ' . $this->syncDb->getQueryVersioningTable() . '.issyncscheduled = 1'; //chief player here

                    //Eligibility 1
                    $damDependencyEligiblity = $this->syncDb->getIndexedInsertQuery($extractForeignRecDetails['FIELDS']['recuid'][0], $extractForeignRecDetails['FIELDS']['tablename'][0],$additionalWhereClause);

                    //Eligibility 2
                    $damDependencyEligiblityByReffererRecPid = false;
                    $sys_page = t3lib_div::makeInstance("t3lib_pageSelect");
                    $recPidVal = $this->syncDb->getPidForTableRecord($extractForeignRecDetails['FIELDS']['recuid'][0], $extractForeignRecDetails['FIELDS']['tablename'][0]);

                    $_currentRootLine = $sys_page->getRootLine($recPidVal['pid']);
                    $_parentInRootLineExcluded = $this->getParentInRootLineExcluded($_currentRootLine);
                    $_parentInRootLineSupported = $this->getParentInRootLineSupported($_currentRootLine);

                    if (count($_parentInRootLineExcluded) > 0 && count($_parentInRootLineSupported) > 0) {
                        //evaluate if supported rootline is a child branch of excluded root line, if no overlap is found must set sync schedule to true
                        $_excludedRootLine = $sys_page->getRootLine($_parentInRootLineExcluded[0]['uid']);
                        $_conflictingRootLine = array();
                        foreach ($_excludedRootLine as $excludedItems) {
                            if ($excludedItems['uid'] == $_parentInRootLineSupported[0]['uid']) {
                                $_conflictingRootLine[] = $excludedItems;
                            }
                        }
                        if (count($_conflictingRootLine) == 0) {
                            //no conflict/overlap between the 2 branches so schedule it for this query
                            $damDependencyEligiblityByReffererRecPid = true;
                            //No conflict or overlap between two branches. Sync scheduled
                        } else {
                            //Conflict found in exclude rootline and supported rootline. Sync not scheduled
                        }
                    } elseif (count($_parentInRootLineSupported) > 0) {
                        $damDependencyEligiblityByReffererRecPid = true;
                    } else {
                        //All other cases
                    }

                    if (count($damDependencyEligiblity) > 0 || $damDependencyEligiblityByReffererRecPid) {
                        $this->syncDb->setSyncScheduledForIndexedQueries(array($this->lastAddedMainQueryQVId));

                        return $this->resolveDAMdependenciesBySysRefIndexId($extractForeignRecDetails['FIELDS']['tablename'][0], $extractForeignRecDetails['FIELDS']['recuid'][0], $extractForeignRecDetails['FIELDS']['ref_uid'][0], $extractForeignRecDetails['FIELDS']['softref_key'][0]);
                    } else {
                        //delete, reason: not eligible by PID
                        $this->syncDb->deleteFromVersioningByUid($this->lastAddedMainQueryQVId);
                    }
                } else {
                    //some other sys_refindex entry  not related to use-case of RTE references to DAM indexes
                    //delete, reason: some sys_refIndex insert not related to DAM
                    $this->syncDb->deleteFromVersioningByUid($this->lastAddedMainQueryQVId);
                }
            } elseif (tx_st9fissync_dbversioning_t3service::DELETE == $this->getQueryType()) {
                //$this->versionTips["recordRevision"]

                $deleteThisDeleteQTrackingRec = true;
                foreach ($this->versionTips["recordRevision"] as $recordRow) {
                    if(($recordRow['softref_key'] == 'mediatag' ||
                            $recordRow['softref_key'] == 'media') &&
                            $recordRow['ref_table'] == $this->syncDb->getDAMMainTableName()) {

                        $deleteThisDeleteQTrackingRec = false;
                        break;
                    }
                }

                if ($deleteThisDeleteQTrackingRec) {
                    //delete,reason: as this delete is not about a DAM
                    $this->syncDb->deleteFromVersioningByUid($this->lastAddedMainQueryQVId);
                } else {
                    $this->syncDb->setSyncScheduledForIndexedQueries(array($this->lastAddedMainQueryQVId));
                }


            } else {
                //delete,reason: as all other we do not cover for sys_refIndex
                $this->syncDb->deleteFromVersioningByUid($this->lastAddedMainQueryQVId);
            }
        }

        return false;
    }

    public function processDAMReferencesUseCase2()
    {
        if (t3lib_extMgm::isLoaded('dam') && $this->table == $this->syncDb->getDAMRecRefTableName() && tx_st9fissync_dbversioning_t3service::INSERT == $this->getQueryType()) {

            //extract uid_foreign,tablenames
            $extractForeignRecDetails = $this->dbVersioningSQLParser->parseSQL($this->versionTips['executedQuery']);

            if ($this->dbVersioningSQLParser->parse_error != '') {
                //log Error $this->dbVersioningSQLParser->parse_error
            }

            //determine if this record for which dam dependencies are supposed to be sync resolved is itself scheduled to be synced
            $additionalWhereClause = ' AND ' . $this->syncDb->getQueryVersioningTable() . '.issyncscheduled = 1'; //chief player here
            $damDependencyEligiblity = $this->syncDb->getIndexedInsertQuery($extractForeignRecDetails['FIELDS']['uid_foreign'][0], $extractForeignRecDetails['FIELDS']['tablenames'][0],$additionalWhereClause);

            if (count($damDependencyEligiblity) > 0) {
                return $this->resolveDAMdependencies($extractForeignRecDetails['FIELDS']['tablenames'][0], $extractForeignRecDetails['FIELDS']['uid_foreign'][0]);
            } else {
                //log - does not require to check and set dam record recordings
                return false;
            }
        } else {
            return false;
        }
    }

    public function processDAMReferencesUseCase()
    {
        try {
            if (!t3lib_extMgm::isLoaded('dam')) {
                throw new Exception ("DAM is not loaded, skipping DAM post processing in tracking/recording process");
            } else {

                if (!$this->syncFactors['issyncscheduled']) {
                    //Current object is not set for sync, so skipping further DAM checking eligibility
                    // not to be scheduled, so not eligible further for this use case check
                    return false;
                }

                foreach ($this->versionTips["recordRevision"] as $recordRow) {
                    $recId = $this->syncDb->isColumnInTable($this->table,$this->uidColumn)? $recordRow[$this->uidColumn]:0;
                    $this->resolveDAMdependencies($this->table, $recId);
                }

            }
        } catch (Exception $e) {
            //log Error($e->getMessage());
        }

    }

    /**
     * One post process use case.
     * Detection and handling of „Datensatz einfügen“ to determine eligibility for sync,
     * even if a record is not in an eligible branch for an disallowed/excluded root PID
     *
     * @param void
     * @return mixed
     */
    public function processInsertRecordsUseCase()
    {
        $useCaseTbl = 'tt_content';
        $insertRecordsCType = 'records';

        //detect valid use case
        if ($this->table != $useCaseTbl) {
            return false;
        }

        if (count($this->versionTips['query_affectedrows']) == 0) {
            return false;
        }

        /**
         *
         * This cannot be ensured as insert records use case can also happen for an Update for which
         * we have anyway assumed to be synced all the time.
         * Once an Update is analyzed for such a case then we can use this condition to exit
         * Need to review this situation
         *
         * ^^ Has historical significance
         * @see setUpIndeterminateSyncFactors()
         */
        if (!$this->syncFactors['issyncscheduled']) {
            // not to be scheduled, so not eligible further for this use case check
            return false;
        }

        $parsedQueryString = $this->dbVersioningSQLParser->parseSQL($this->versionTips['executedQuery']);

        if ($this->dbVersioningSQLParser->parse_error != '') {
            // log Error($this->dbVersioningSQLParser->parse_error, $this->versionTips['table']);
        }

        //expecting a standard BE single insert here, need to adapt
        if (array_key_exists($insertRecordsCType, $parsedQueryString['FIELDS']) && isset($parsedQueryString['FIELDS'][$insertRecordsCType][0]) && !empty($parsedQueryString['FIELDS'][$insertRecordsCType][0])) {

            $refRecordsList = explode(',',$parsedQueryString['FIELDS']['records']['0']);

            foreach ($refRecordsList as $refRecord) {

                if (isset($refRecord) && !empty($refRecord)) {
                    // split a record such as tt_news_16,tt_content_8
                    $refRecordSplit = explode('_', $refRecord);
                    //reference record uid
                    $refRecId = array_pop($refRecordSplit);
                    // reference record Table
                    $refTable = implode('_',$refRecordSplit);

                    //now check if this record revision exists has already been added to version
                    $recordRevisions = $this->syncDb->getIndexedQueries($refRecId, $refTable);

                    $rootRevisionFound = FALSE;

                    foreach ($recordRevisions as $revision) {
                        if ($revision['query_type'] == tx_st9fissync_dbversioning_t3service::INSERT) {
                            //  recordRevisions found, also root revision found
                            $rootRevisionFound = TRUE;
                            break;
                        } else {
                            // recordRevisions found, root revision still not found
                            continue;
                        }
                    }

                    if (count($recordRevisions) > 0 && $rootRevisionFound) {

                        // recordRevisions found & root revision found
                        foreach ($recordRevisions as $revision) {
                            if (!$revision['issyncscheduled']) {
                                // revision not scheduled to be synced
                                //update issyncscheduled flag to set it for all tracked records
                                $whereRefRecId = 'uid  = ' . $revision['uid'];
                                //what about subsequent queries here
                                $updateArtifact = t3lib_div::makeInstance('tx_st9fissync_dbversioning_query');
                                $updateArtifact->set('issyncscheduled',1);
                                $updateArtifact->set('issynced',0);
                                $this->syncDb->updateQueryArtifactToVersion($this->syncAPI->updateQueryArtifact($updateArtifact),$whereRefRecId);
                            } else {
                                //nothing to do
                                // revision already scheduled to be synced
                            }
                        }
                    } else {
                        //if no revision exists in mm table or the creation/update query which means at any point this record was never tracked/recorded or has been deleted
                        //2 possibilities:
                        //a. create an insert statement and record this.
                        //b. just log an alert for editor's review

                        //compile revision
                        // as no revision exists

                        $refRecordIndexQToVersion = $this->syncAPI->compileRootRevisionQuery($refRecId, $refTable);

                        if ($refRecordIndexQToVersion) {
                            $refRecordIndexQueryArtifact = t3lib_div::makeInstance('tx_st9fissync_dbversioning_query');
                            $refRecordIndexQueryArtifact->set('query_text',trim($refRecordIndexQToVersion));
                            $refRecordIndexQueryArtifact->set('query_type',tx_st9fissync_dbversioning_t3service::INSERT);
                            $refRecordIndexQueryArtifact->set('query_affectedrows',1);
                            $refRecordIndexQueryArtifact->set('issyncscheduled',1);
                            $refRecordIndexQueryArtifact->set('tables',$this->getNumOfTablesForQuery());

                            $refRecordIndexQueryRefRecordArtifact = t3lib_div::makeInstance('tx_st9fissync_dbversioning_query_mm');
                            $refRecordIndexQueryRefRecordArtifact->set('uid_foreign',$refRecId);
                            $refRecordIndexQueryRefRecordArtifact->set('tablenames',$refTable);
                            $refRecordIndexQueryArtifact->getTableRows()->append($this->syncAPI->buildQueryRefRecArtifact($refRecordIndexQueryRefRecordArtifact));

                            $this->syncDb->addQueryArtifactToVersion($this->syncAPI->buildQueryArtifact($refRecordIndexQueryArtifact));

                            // no revision exists, added query artifact

                        }

                    }

                }

            }

        } else {
            //not as complicated as thought, simply another reason to return from this process, this is not $insertRecordsCTypeF
            //this may be a CType - shortcut but does not necessarily mean it is handling any of the reference records
            return false;
        }
    }

    public function doesRuleApply($versionTips = NULL)
    {
        if (!is_null($versionTips) && is_array($versionTips)) {
            if (!$this->isTableVersionExcluded($versionTips['table']) && $this->syncDb->isTableInDB($versionTips['table'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Main function to apply versioning and set up recorded actions for next sync
     *
     *
     * @param array $versionTips
     */
    public function applyVersioning($versionTips = NULL)
    {
        //apply versioning, also keep in mind 2 SQLs cannot be executed in the same statement !!
        //also atleast!!! all queries that do change data state for a row/rows definitely goes into the version,
        //whether its eligible to synced or not is a different question and is next in line to be evaluated


        //For all 'sync' purposes the db operations API chooses to use a distinct t3lib_DB object,
        //in all other cases such as DAM db operations etc. one has to go back to $GLOBALS['TYPO3_DB'] object
        //so an elaborate and critical step to save the globals object, overwrite with the 'sync' compatible DB object
        // and then restore back to original globals var, see below

        //reset critical factors
        $this->syncFactors = array('rootid' => 0,'excludeid' => 0, 'issyncscheduled' => 0);

        //save
        $this->_temp_TYPO3_DB = $GLOBALS['TYPO3_DB'];

        try {
            //overwrite
            $GLOBALS['TYPO3_DB'] = tx_st9fissync::getInstance()->getSyncDBObject();

            if (!is_null($versionTips)) {
                //continue
                //log ?? Applying versioning ...
            } else {
                // add logger
                return false;
            }

            $this->table = $versionTips['table'];
            $this->versionTips = $versionTips;

            //pre-processing for the action
            $this->setUpSyncFactors();
            $indexedQueryArtifact = t3lib_div::makeInstance('tx_st9fissync_dbversioning_query');
            $indexedQueryArtifact->set('query_text',trim($this->versionTips['executedQuery']));
            $indexedQueryArtifact->set('query_type',$this->getQueryType());
            $indexedQueryArtifact->set('query_affectedrows',$this->versionTips['query_affectedrows']);
            $indexedQueryArtifact->set('query_info',serialize($this->versionTips['query_info']));
            $indexedQueryArtifact->set('query_exectime',$this->versionTips['executionTime']);
            $indexedQueryArtifact->set('query_error_number',$this->versionTips['query_error_number']);
            $indexedQueryArtifact->set('query_error_message',$this->versionTips['query_error_message']);
            $indexedQueryArtifact->set('tables',$this->getNumOfTablesForQuery());
            $indexedQueryArtifact->set('issyncscheduled',$this->syncFactors['issyncscheduled']);

            // Special case : TRUNCATE QUERY
            if ($this->getQueryType() == tx_st9fissync_dbversioning_t3service::TRUNCATE && $this->versionTips['query_affectedrows'] > 0) {
                // Force overwrite
                $indexedQueryArtifact->set('rootid', 0);
                $indexedQueryArtifact->set('excludeid', 0);

                $indexedQueryRefRecordArtifact = t3lib_div::makeInstance('tx_st9fissync_dbversioning_query_mm');
                $indexedQueryRefRecordArtifact->set('uid_foreign', 0);
                $indexedQueryRefRecordArtifact->set('recordRevision', '');
                $indexedQueryRefRecordArtifact->set('tablenames',$this->table);
                $indexedQueryArtifact->getTableRows()->append($this->syncAPI->buildQueryRefRecArtifact($indexedQueryRefRecordArtifact));

            } else {

                $indexedQueryArtifact->set('rootid', $this->syncFactors['rootid']);
                $indexedQueryArtifact->set('excludeid',$this->syncFactors['excludeid']);

                foreach ($this->versionTips["recordRevision"] as $recordRow) {

                    $indexedQueryRefRecordArtifact = t3lib_div::makeInstance('tx_st9fissync_dbversioning_query_mm');
                    $indexedQueryRefRecordArtifact->set('uid_foreign',$this->syncDb->isColumnInTable($this->table,$this->uidColumn)? $recordRow[$this->uidColumn]:0);
                    $indexedQueryRefRecordArtifact->set('recordRevision',serialize($recordRow));

                    /**
                     * currently only single table query supported for write mode, this I think is also a Typo3 native DBAL limitation
                     *
                     */
                    $indexedQueryRefRecordArtifact->set('tablenames',$this->table);
                    $indexedQueryArtifact->getTableRows()->append($this->syncAPI->buildQueryRefRecArtifact($indexedQueryRefRecordArtifact));

                }
            }

            $this->lastAddedMainQueryQVId = $this->syncDb->addQueryArtifactToVersion($this->syncAPI->buildQueryArtifact($indexedQueryArtifact));

            //all kinds of postprocess activities
            $this->postProcess();

            //restore original TYPO3_DB globals
            $GLOBALS['TYPO3_DB']= $this->_temp_TYPO3_DB ;
        } catch (tx_st9fissync_exception $ex) {

            if ($this->_temp_TYPO3_DB != null && is_object($this->_temp_TYPO3_DB)) {
                $GLOBALS['TYPO3_DB'] = $this->_temp_TYPO3_DB;
            }

            //log error
        }

    }

}

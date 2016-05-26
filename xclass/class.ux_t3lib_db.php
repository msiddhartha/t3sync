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

*
* XCLASS(ing) T3 native DB API class and functions
* Serves as a hooking mechanism to
* 1.record DB actions
* 2.sequence DB records to create an UID space for a T3 instance
* 3.Other accessory functions
*
* @see tx_st9fissync_dbversioning_t3service
* @see tx_st9fissync_dbsequencer_t3service
* @see tx_st9fissync_db
* @see tx_st9fissync
*
* @author <info@studioneun.de>
* @package TYPO3
* @subpackage st9fissync
*
*/

class ux_t3lib_db extends t3lib_DB
{
    /**
     *
     * @var boolean
     */
    private $isSequencerEnabled = TRUE;

    /**
     *
     * @var boolean
     */
    private $isVersioningEnabled = TRUE;

    /**
     *
     * @var int
     */
    private $_sql_error_no = NULL;

    /**
     *
     * @var string
     */
    private $_sql_error_message = NULL;

    /**
     *
     * @var int
     */
    private $_sql_insert_id = NULL;

    /**
     *
     * @var int
     */
    private $_sql_affected_rows = NULL;

    /**
     *
     * __construct()
     * @param void
     *
     * @return void
     */
    public function __construct()
    {
        $this->syncAPI = tx_st9fissync::getInstance();
        $this->_sql_error_no = 0;
        $this->_sql_error_message = '';
        $this->_sql_insert_id = 0;
        $this->_sql_affected_rows = 0;
    }

    /************************************
     *
    * Query execution
    *
    * These functions are the RECOMMENDED DBAL functions for use in your applications
    * Using these functions will allow the DBAL to use alternative ways of accessing data (contrary to if a query is returned!)
    * They compile a query AND execute it immediately and then return the result
    * This principle heightens our ability to create various forms of DBAL of the functions.
    * Generally: We want to return a result pointer/object, never queries.
    * Also, having the table name together with the actual query execution allows us to direct the request to other databases.
    *
    **************************************/

    /**
     *
     * @see t3lib_DB::exec_SELECTquery()
     *
     */
    public function exec_SELECTquery($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '')
    {
        $this->syncAPI->setSyncRuntimeMaxExecTime();

        $query = $this->SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit);

        $res = mysql_query($query, $this->link);

        $this->setAllSQLVars();

        if ($this->debugOutput) {
            $this->debug('exec_SELECTquery');
        }
        if ($this->explainOutput) {
            $this->explain($query, $from_table, $this->sql_num_rows($res));
        }

        $this->syncAPI->resetToOrigRuntimeMaxExecTime();

        return $res;
    }

    /**
     *
     * @see t3lib_DB::exec_INSERTquery()
     *
     */
    public function exec_INSERTquery($table, $fields_values, $no_quote_fields = FALSE)
    {
        $this->syncAPI->setSyncRuntimeMaxExecTime();

        $start = t3lib_div::milliseconds();

        $queryToBeExec = $this->INSERTquery($table, $fields_values, $no_quote_fields);
        $res = mysql_query($queryToBeExec, $this->link);

        $this->setAllSQLVars();

        $stop = t3lib_div::milliseconds();

        if ($this->debugOutput) {
            $this->debug('exec_INSERTquery');
        }

        if ($this->isVersioningEnabled) {
            $recordRowUid = array('NEW_REC' =>  $fields_values);
            if (array_key_exists('uid', $this->syncAPI->getSyncDBObject()->admin_get_fields($table))) {
                $recordRowUid = array(array('uid' =>  $this->_sql_insert_id));
            }
            //apply versioning rules if enabled
            $this->syncAPI->runVersioningRulesEngine($this->syncAPI->buildVersionTipsArray($table, 'exec_INSERTquery', $queryToBeExec, $stop - $start, $res, $recordRowUid));
        }

        $this->syncAPI->resetToOrigRuntimeMaxExecTime();

        return $res;
    }

    /**
     *
     * @see t3lib_DB::exec_INSERTmultipleRows()
     *
     */
    public function exec_INSERTmultipleRows($table, array $fields, array $rows, $no_quote_fields = FALSE)
    {
        $this->syncAPI->setSyncRuntimeMaxExecTime();

        //Init bucket for sequencer manipulated UIDs
        $recordRowUids = NULL;
        if (array_key_exists('uid', $this->syncAPI->getSyncDBObject()->admin_get_fields($table))) {
            //init bucket only if table has UID col, imagine MM tables as exception for instance
            $recordRowUids = array();
        }

        $start = t3lib_div::milliseconds();

        $queryToBeExec = $this->INSERTmultipleRows($table, $fields, $rows, $no_quote_fields, $recordRowUids);
        $res = mysql_query($queryToBeExec, $this->link);

        $this->setAllSQLVars();

        $stop = t3lib_div::milliseconds();

        if ($this->debugOutput) {
            $this->debug('exec_INSERTmultipleRows');
        }

        if (is_null($recordRowUids)) {
            $recordRowUids = array('NEW_REC' =>  $rows);
        }

        if ($this->isVersioningEnabled) {
            //apply versioning rules if enabled
            $this->syncAPI->runVersioningRulesEngine($this->syncAPI->buildVersionTipsArray($table, 'exec_INSERTmultipleRows', $queryToBeExec, $stop - $start, $res, $recordRowUids));

        }

        $this->syncAPI->resetToOrigRuntimeMaxExecTime();

        return $res;
    }

    /**
     *
     * @see t3lib_DB::exec_UPDATEquery()
     *
     */
    public function exec_UPDATEquery($table, $where, $fields_values, $no_quote_fields = FALSE)
    {
        $this->syncAPI->setSyncRuntimeMaxExecTime();

        $start = t3lib_div::milliseconds();

        if ($this->isVersioningEnabled) {
            //does not matter which values are changing just save the full row, we can do the trimming later
            $savedRowData = $this->exec_SELECTgetRows('*',$table,$where);
        }

        $queryToBeExec = $this->UPDATEquery($table, $where, $fields_values, $no_quote_fields);
        $res = mysql_query($queryToBeExec, $this->link);

        $this->setAllSQLVars();

        $stop = t3lib_div::milliseconds();

        if ($this->debugOutput) {
            $this->debug('exec_UPDATEquery');
        }

        if ($this->isVersioningEnabled) {
            //apply versioning rules if enabled
            $this->syncAPI->runVersioningRulesEngine($this->syncAPI->buildVersionTipsArray($table, 'exec_UPDATEquery', $queryToBeExec, $stop - $start, $res, $savedRowData));

        }

        $this->syncAPI->resetToOrigRuntimeMaxExecTime();

        return $res;
    }

    /**
     *
     * @see t3lib_DB::exec_DELETEquery()
     *
     */
    public function exec_DELETEquery($table, $where)
    {
        $this->syncAPI->setSyncRuntimeMaxExecTime();

        $start = t3lib_div::milliseconds();

        if ($this->isVersioningEnabled) {
            //does not matter which values are changing just save the full row, we can do the trimming later
            $savedRowData = $this->exec_SELECTgetRows('*',$table,$where);
        }

        $queryToBeExec = $this->DELETEquery($table, $where);
        $res = mysql_query($queryToBeExec, $this->link);

        $this->setAllSQLVars();

        $stop = t3lib_div::milliseconds();

        if ($this->debugOutput) {
            $this->debug('exec_DELETEquery');
        }

        if ($this->isVersioningEnabled) {
            //apply versioning rules if enabled
            $this->syncAPI->runVersioningRulesEngine($this->syncAPI->buildVersionTipsArray($table, 'exec_DELETEquery', $queryToBeExec, $stop - $start, $res, $savedRowData));
        }

        $this->syncAPI->resetToOrigRuntimeMaxExecTime();

        return $res;
    }

    /**
     *
     * @see t3lib_DB::exec_TRUNCATEquery()
     *
     */
    public function exec_TRUNCATEquery($table)
    {
        $this->syncAPI->setSyncRuntimeMaxExecTime();

        $start = t3lib_div::milliseconds();

        if ($this->isVersioningEnabled) {
            $count = $this->exec_SELECTcountRows('*',$table);
        }

        $queryToBeExec = $this->TRUNCATEquery($table);

        $res = mysql_query($queryToBeExec, $this->link);

        $this->setAllSQLVars();

        $stop = t3lib_div::milliseconds();

        if ($this->debugOutput) {
            $this->debug('exec_TRUNCATEquery');
        }

        if ($this->isVersioningEnabled) {
            $this->syncAPI->runVersioningRulesEngine($this->syncAPI->buildVersionTipsArray($table, 'exec_TRUNCATEquery', $queryToBeExec, $stop - $start, $res, array(), $count));
        }

        $this->syncAPI->resetToOrigRuntimeMaxExecTime();

        return $res;
    }

    /**************************************
     *
    * Prepared Query Support
    *
    **************************************/

    /**
     *
     * @see t3lib_DB::exec_PREPAREDquery()
     *
     */
    public function exec_PREPAREDquery($query, array $queryComponents)
    {
        $this->syncAPI->setSyncRuntimeMaxExecTime();

        $res = mysql_query($query, $this->link);

        $this->setAllSQLVars();

        if ($this->debugOutput) {
            $this->debug('stmt_execute', $query);
        }

        $this->syncAPI->resetToOrigRuntimeMaxExecTime();

        return $res;
    }

    /**************************************
     *
    * Query building
    *
    **************************************/

    /**
     *
     * @see t3lib_DB::INSERTquery()
     *
     */
    public function INSERTquery($table, &$fields_values, $no_quote_fields = FALSE)
    {
        if ($this->isSequencerEnabled) {
            //apply sequencer if enabled
            $this->syncAPI->getSyncSequencerService()->modifyInsertFields($table, $fields_values);
        }

        return parent::INSERTquery($table, $fields_values, $no_quote_fields);
    }

    /**
     *
     * @see t3lib_DB::INSERTmultipleRows()
     *
     */
    public function INSERTmultipleRows($table, array $fields, array $rows, $no_quote_fields = FALSE, array &$recordRowUidBucket = NULL)
    {
        if ($this->isSequencerEnabled) {
            //apply sequencer if enabled
            foreach ($rows as &$row) {
                $this->syncAPI->getSyncSequencerService()->modifyInsertFields($table, $row, $fields);
                //add all manipulated UID's to the bucket, that has been passed
                if(!is_null($recordRowUidBucket))
                    $recordRowUidBucket[]['uid'] = $row['uid'];
            }
        }

        return parent::INSERTmultipleRows($table, $fields, $rows, $no_quote_fields);
    }

    /**
     *
     * @see t3lib_DB::UPDATEquery()
     *
     */
    public function UPDATEquery($table, $where, $fields_values, $no_quote_fields = FALSE)
    {
        if (isset($fields_values['uid'])) {
            throw new InvalidArgumentException('no uid allowed in update statement!');
        }

        return parent::UPDATEquery($table, $where, $fields_values, $no_quote_fields);
    }


    /**************************************
     *
    * MySQL wrapper functions
    * (For use in your applications)
    *
    **************************************/

    /**
     *
     * @see t3lib_DB::sql()
     *
     */
    public function sql($db, $query)
    {
        t3lib_div::logDeprecatedFunction();

        $res = mysql_query($query, $this->link);

        $this->setAllSQLVars();

        if ($this->debugOutput) {
            $this->debug('sql', $query);
        }

        return $res;
    }

    /**
     *
     * @see t3lib_DB::sql_query()
     *
     */
    public function sql_query($query)
    {
        $this->syncAPI->setSyncRuntimeMaxExecTime();

        $res = mysql_query($query, $this->link);

        $this->setAllSQLVars();

        if ($this->debugOutput) {
            $this->debug('sql_query', $query);
        }

        $this->syncAPI->resetToOrigRuntimeMaxExecTime();

        return $res;
    }

    /**
     *
     * @see $this->setAllSQLVars()
     * @param void
     * @return string $this->_sql_error_message
     */
    public function sql_error()
    {
        return $this->_sql_error_message;
    }

    /**
     * Returns the error number on the last sql() execution
     * mysql_errno() wrapper function
     *
     * @see $this->setAllSQLVars()
     * @return int MySQL error number.
     */
    public function sql_errno()
    {
        return $this->_sql_error_no;
    }

    /**
     *
     * @see t3lib_DB::sql_free_result()
     *
     */
    public function sql_free_result($res)
    {
        $this->syncAPI->setSyncRuntimeMaxExecTime();

        if ($this->debug_check_recordset($res)) {
            $return = mysql_free_result($res);

            $this->setAllSQLVars();

            $this->syncAPI->resetToOrigRuntimeMaxExecTime();

            return $return;

        } else {

            $this->syncAPI->resetToOrigRuntimeMaxExecTime();

            return FALSE;
        }
    }

    /**
     *
     * @see $this->setAllSQLVars()
     * @param void
     * @return int $this->_sql_insert_id
     */
    public function sql_insert_id()
    {
        return $this->_sql_insert_id;
    }

    /**
     *
     * @see $this->setAllSQLVars()
     * @param void
     * @return int $this->_sql_affected_rows
     */
    public function sql_affected_rows()
    {
        return $this->_sql_affected_rows;
    }


    /**************************************
     *
    * SQL admin functions
    * (For use in the Install Tool and Extension Manager)
    *
    **************************************/

    /**
     *
     * @see t3lib_DB::admin_get_tables()
     */
    public function admin_get_tables()
    {
        $whichTables = array();

        $tables_result = mysql_query('SHOW TABLE STATUS FROM `' . TYPO3_db . '`', $this->link);

        $this->setAllSQLVars();

        if (!mysql_error()) {
            while ($theTable = mysql_fetch_assoc($tables_result)) {
                $whichTables[$theTable['Name']] = $theTable;
            }

            $this->sql_free_result($tables_result);
        }

        return $whichTables;
    }

    /**
     *
     * @see t3lib_DB::admin_get_fields()
     *
     */
    public function admin_get_fields($tableName)
    {
        $output = array();

        $columns_res = mysql_query('SHOW COLUMNS FROM `' . $tableName . '`', $this->link);

        $this->setAllSQLVars();

        if ($columns_res) {
            while ($fieldRow = mysql_fetch_assoc($columns_res)) {
                $output[$fieldRow['Field']] = $fieldRow;
            }
        }
        $this->sql_free_result($columns_res);

        return $output;

    }

    /**
     *
     * @see t3lib_DB::admin_get_keys()
     *
     */
    public function admin_get_keys($tableName)
    {
        $output = array();

        $keyRes = mysql_query('SHOW KEYS FROM `' . $tableName . '`', $this->link);

        $this->setAllSQLVars();

        while ($keyRow = mysql_fetch_assoc($keyRes)) {
            $output[] = $keyRow;
        }

        $this->sql_free_result($keyRes);

        return $output;
    }

    /**
     *
     * @see t3lib_DB::admin_get_charsets()
     *
     */
    public function admin_get_charsets()
    {
        $output = array();

        $columns_res = mysql_query('SHOW CHARACTER SET', $this->link);

        $this->setAllSQLVars();

        if ($columns_res) {
            while (($row = mysql_fetch_assoc($columns_res))) {
                $output[$row['Charset']] = $row;
            }

            $this->sql_free_result($columns_res);
        }

        return $output;
    }

    /**
     *
     * @see t3lib_DB::admin_query()
     *
     */
    public function admin_query($query)
    {
        $this->syncAPI->setSyncRuntimeMaxExecTime();

        $res = mysql_query($query, $this->link);

        $this->setAllSQLVars();

        if ($this->debugOutput) {
            $this->debug('admin_query', $query);
        }

        $this->syncAPI->resetToOrigRuntimeMaxExecTime();

        return $res;
    }


    /**************************************
     *
    * SQL custom functions
    *
    *
    **************************************/

    /**
     *
     * Enables the sequencer.
     *
     * @return void
     */
    public function enableSequencer()
    {
        $this->isSequencerEnabled = TRUE;
    }

    /**
     *
     * Disables the sequencer.
     *
     * @return void
     */
    public function disableSequencer()
    {
        $this->isSequencerEnabled = FALSE;
    }

    /**
     *
     * Enables versioning.
     *
     * @return void
     */
    public function enableVersioning()
    {
        $this->isVersioningEnabled = TRUE;
    }

    /**
     *
     * Disables versioning.
     *
     * @return void
     */
    public function disableVersioning()
    {
        $this->isVersioningEnabled = FALSE;
    }

    /**
     *
     * @param boolean $returnArray
     * @param boolean $suppressWarnings
     *
     * @return mixed
     */
    public function sql_info($returnArray = TRUE,$suppressWarnings = FALSE)
    {
        if($suppressWarnings)
            $strInfo = @mysql_info($this->link);
        else
            $strInfo = mysql_info($this->link);

        if ($this->debugOutput) {
            $this->debug('sql_info',$info);
        }

        if ($returnArray) {
            $infoArray = array();
            preg_match("/Records: ([0-9]*)/", $strInfo, $records);
            preg_match("/Duplicates: ([0-9]*)/", $strInfo, $dupes);
            preg_match("/Warnings: ([0-9]*)/", $strInfo, $warnings);
            preg_match("/Deleted: ([0-9]*)/", $strInfo, $deleted);
            preg_match("/Skipped: ([0-9]*)/", $strInfo, $skipped);
            preg_match("/Rows matched: ([0-9]*)/", $strInfo, $rows_matched);
            preg_match("/Changed: ([0-9]*)/", $strInfo, $changed);

            $infoArray['records'] = $records[1];
            $infoArray['duplicates'] = $dupes[1];
            $infoArray['warnings'] = $warnings[1];
            $infoArray['deleted'] = $deleted[1];
            $infoArray['skipped'] = $skipped[1];
            $infoArray['rows_matched'] = $rows_matched[1];
            $infoArray['changed'] = $changed[1];

            return $infoArray;
        } else {
            return $strInfo;
        }
    }

    /**
     * Creates a SELECT query, selecting fields ($select) from two/three tables joined
     * Use $mm_table together with $local_table or $foreign_table to select over two tables. Or use all three tables to select the full MM-relation.
     * The JOIN is done with [$local_table].uid <--> [$mm_table].uid_local  / [$mm_table].uid_foreign <--> [$foreign_table].uid
     * The function is very useful for selecting MM-relations between tables adhering to the MM-format used by TCE (TYPO3 Core Engine). See the section on $TCA in Inside TYPO3 for more details.
     *
     * Usage: 12 (spec. ext. sys_action, sys_messages, sys_todos)
     *
     * @param	string		Field list for SELECT
     * @param	string		Tablename, local table
     * @param	string		Tablename, relation table
     * @param	string		Tablename, foreign table
     * @param string		Optional additional WHERE clauses put in the end of the query. NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself! DO NOT PUT IN GROUP BY, ORDER BY or LIMIT! You have to prepend 'AND ' to this parameter yourself!
     * @param	string		Optional GROUP BY field(s), if none, supply blank string.
     * @param	string		Optional ORDER BY field(s), if none, supply blank string.
     * @param	string		Optional LIMIT value ([begin,]max), if none, supply blank string.
     * @return pointer MySQL result pointer / DBAL object
     * @see SELECTquery()
     */
    public function SELECT_mm_query($select, $local_table, $mm_table, $foreign_table, $whereClause = '', $groupBy = '', $orderBy = '', $limit = '')
    {
        if ($foreign_table == $local_table) {
            $foreign_table_as = $foreign_table . uniqid('_join');
        }

        $mmWhere = $local_table ? $local_table . '.uid=' . $mm_table . '.uid_local' : '';
        $mmWhere .= ($local_table AND $foreign_table) ? ' AND ' : '';

        $tables = ($local_table ? $local_table . ',' : '') . $mm_table;

        if ($foreign_table) {
            $mmWhere .= ($foreign_table_as ? $foreign_table_as : $foreign_table) . '.uid=' . $mm_table . '.uid_foreign';
            $tables .= ',' . $foreign_table . ($foreign_table_as ? ' AS ' . $foreign_table_as : '');
        }

        return $this->SELECTquery(
                $select,
                $tables,
                // whereClauseMightContainGroupOrderBy
                $mmWhere . ' ' . $whereClause,
                $groupBy,
                $orderBy,
                $limit
        );
    }

    /**
     * Used to set SQL vars after each mysql_query()
     */
    private function setAllSQLVars()
    {
        $this->setSQLInsertId();
        $this->setSQLAffectedRows();
        $this->setSQLErrorNo();
        $this->setSQLErrorMessage();
    }

    /**
     * Used to set SQL error vars after each mysql_query() error
     */
    private function setAllSQLErrorVars()
    {
        $this->setSQLErrorNo();
        $this->setSQLErrorMessage();
    }

    /**
     * Set these variables individually
     */
    private function setSQLErrorMessage()
    {
        $this->_sql_error_message = mysql_error($this->link);
    }

    private function setSQLErrorNo()
    {
        $this->_sql_error_no = mysql_errno($this->link);
    }

    private function setSQLAffectedRows()
    {
        $this->_sql_affected_rows = mysql_affected_rows($this->link);
    }

    private function setSQLInsertId()
    {
        $this->_sql_insert_id = mysql_insert_id($this->link);
    }

}

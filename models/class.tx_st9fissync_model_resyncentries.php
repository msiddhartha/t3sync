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
 * Class that implements the model 'resyncentries' for tx_st9fissync.
*
*
* @author	André Spindler <info@studioneun.de>
* @package	TYPO3
* @subpackage	tx_st9fissync
*/

/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
*
*
*
*
* TOTAL FUNCTIONS: 7
* (This index is automatically created/updated by the extension "extdeveval")
*
*/

define('RESYNC_ACTION_FINISHED',	0);		//	nothing to do
define('RESYNC_ACTION_RUNNING',		1);		//	currently processed
define('RESYNC_ACTION_NEW',			2);		//	new record
define('RESYNC_ACTION_UPDATE',		3);		//	updated record

class tx_st9fissync_model_resyncentries extends tx_lib_object
{
    /**
     *
     * Table name
     * @var	string
     */
    protected $table = 'tx_st9fissync_resync_entries';

    /**
     *
     * filters for queries
     * @var	array
     */
    protected $filters = false;

    public function __construct($parameter1 = null, $parameter2 = null)
    {
        parent::tx_lib_object($parameter1, $parameter2);
        $this->unsetFilter();
    }

    /**
     *
     * return name of table
     */
    public function getTableName()
    {
        return $this->table;
    }

    /**
     *
     * Clear all filters
     *
     */
    public function unsetFilter()
    {
        $this->filters = array();
    }

    /**
     *
     * Set table of record
     * @param string $table
     */
    public function setRecordTable($table)
    {
        $this->set('record_table', strval($table));
    }

    /**
     *
     * get table of record
     * @return string
     */
    public function getRecordTable()
    {
        return $this->get('record_table');
    }

    /**
     *
     * Set uid of record
     * @param integer $uid
     */
    public function setRecordUid($uid)
    {
        $this->set('record_uid', intval($uid));
    }

    /**
     *
     * get uid of record
     * @return integer
     */
    public function getRecordUid()
    {
        return $this->get('record_uid');
    }

    /**
     *
     * Set action of record
     * @param integer $action
     */
    public function setRecordAction($action)
    {
        $this->set('record_action', intval($action));
    }

    /**
     *
     * get action of record
     * @return integer
     */
    public function getRecordAction()
    {
        return $this->get('record_action');
    }

    /**
     *
     * Set timestamp of record
     * @param integer $tstamp
     */
    public function setRecordTstamp($tstamp)
    {
        $this->set('record_tstamp', intval($tstamp));
    }

    /**
     *
     * get timestamp of record
     * @return integer
     */
    public function getRecordTstamp()
    {
        return $this->get('record_tstamp');
    }

    /**
     *
     * load all records
     *
     * @return boolean success
     */
    public function load()
    {
        $this->clear();

        $fields = '*';
        $tables = $this->table;

        $where = '(1=1)';
        $filters = $this->filters;
        if (count($filters))
            $where = implode(' AND ', $filters);

        $groupBy = '';
        $sorting = $this->sorting;
        $limit = '';

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $tables, $where, $groupBy, $sorting, $limit);
        if (!$res)
            return false;

        while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {

            if (!is_array($row))
                continue;

            $entry = t3lib_div::makeInstance(get_class($this), $row, $this->controller);
            $this->append($entry);
        }
        $GLOBALS['TYPO3_DB']->sql_free_result($res);

        return true;
    }

    /**
     * load single record
     *
     * @param mixed  $value
     * @param string $field
     * @param string $addWhere
     */
    public function loadSingle($value, $field = 'uid', $addWhere = '')
    {
        $field = strval($field);
        if ($field === 'uid') {
            $value = intval($value);
            if (!$value)
                return false;
        }

        $fields = '*';
        $tables = $this->table;

        $where = $this->table . '.' . $field . ' = "' . $value . '"';
        if ($addWhere !== '')
            $where .= ' ' . $addWhere;

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $tables, $where);
        if (!$res)
            return false;

        $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
        if (!is_array($row))
            return false;

        if (!is_array($row))
            return false;

        $GLOBALS['TYPO3_DB']->sql_free_result($res);

        $this->exchangeArray($row);

        return true;
    }

    /**
     * create record
     *
     * @return boolean result
     */
    public function create()
    {
        $this->set('pid', 0);
        $this->set('crdate', time());
        $this->set('cruser_id', $GLOBALS['BE_USER']->user['uid']);

        return $this->write();
    }

    /**
     * write current record to db
     *
     * @return boolean result
     */
    public function write()
    {
        /*

        $this->set('tstamp', time());
        $record = array();
        $fieldList = $GLOBALS['TYPO3_DB']->admin_get_fields($this->table);

        foreach ($this as $key => $value) {
        if (array_key_exists($key,$fieldList)) {
        $record[$key] = $value;
        }
        }

        if ($this->get('uid')) {
        $where = 'uid = ' . $this->get('uid');
        $res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->table, $where, $record);
        } else {
        $res = $GLOBALS['TYPO3_DB']->exec_INSERTquery($this->table, $record);
        $this->set('uid', $GLOBALS['TYPO3_DB']->sql_insert_id());
        }

        return (bool) $res;
     */
        /**
         * Switching off re-sync recording as generic recording should take care of all scenarios
         * @see st9fissync/xclass/class.ux_t3lib_db.php
         */

        return true;
    }

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/st9fissync/models/class.tx_st9fissync_model_resyncentries.php']) {
    include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/st9fissync/models/class.tx_st9fissync_model_resyncentries.php']);
}

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
 * Class that implements the queue library for tx_st9fissync.
 *
 *
 * @author	André Spindler <info@studioneun.de>
 * @package	TYPO3
 * @subpackage	tx_st9fissyn
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

class tx_st9fissync_lib_queue
{
    /**
     *
     * class name of resync entries model
     * @var	string
     */
    protected $resyncEntriesModelClassName = 'tx_st9fissync_model_resyncentries';

    /**
     *
     * model to handle resync entries in queue
     * @var	tx_st9fissync_model_resyncentries
     */
    protected $resyncEntries;

    /**
     *
     * constructor
     */
    public function __construct()
    {
        $this->resyncEntries = t3lib_div::makeInstance($this->resyncEntriesModelClassName);
    }

    /**
     *
     * destructor
     */
    public function __destruct()
    {
        unset($this->resyncEntries);
    }

    /**
     *
     * add resync entry to queue - is called by different extensions
     * @param  string  $table
     * @param  integer $uid
     * @param  integer $action; only RESYNC_ACTION_NEW and RESYNC_ACTION_UPDATE are allowed
     * @param  integer $tstamp
     * @return boolean success
     */
    public function addResyncEntry($table, $uid, $action, $tstamp)
    {
        $table = trim($table);
        if (!strlen($table))
            return false;

        $uid = intval($uid);
        if (!$uid)
            return false;

        if (($action !== RESYNC_ACTION_NEW) && ($action != RESYNC_ACTION_UPDATE))
            return false;

        $tstamp = intval($tstamp);
        if (!$tstamp)
            return false;

        $this->resyncEntries->clear();
        $this->resyncEntries->setRecordTable($table);
        $this->resyncEntries->setRecordUid($uid);
        $this->resyncEntries->setRecordAction($action);
        $this->resyncEntries->setRecordTstamp($tstamp);

        return $this->resyncEntries->create();
    }

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/st9fissync/lib/class.tx_st9fissync_lib_queue.php']) {
    include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/st9fissync/lib/class.tx_stfissync_lib_queue.php']);
}

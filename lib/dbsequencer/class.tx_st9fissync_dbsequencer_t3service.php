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

* Sequencer service agent
*
* @author <info@studioneun.de>
* @package TYPO3
* @subpackage st9fissync
*
*/

class tx_st9fissync_dbsequencer_t3service
{
    /**
     *
     * @var tx_st9fissync_dbsequencer_sequencer
     */
    private $sequencer;

    /**
     * array of configured tables that should call the sequencer
     *
     * @var array
     */
    private $supportedTables;

    /**
     *
     * @param tx_st9fissync_dbsequencer_sequencer $sequencer
     */
    public function __construct(tx_st9fissync_dbsequencer_sequencer $sequencer)
    {
        $this->sequencer = $sequencer;
        $this->syncAPI = tx_st9fissync::getInstance();

        $this->supportedTables = $this->syncAPI->getSyncConfigManager()->getSequencerEnabledTablesList();

        $this->sequencer->setDefaultOffset($this->syncAPI->getSyncConfigManager()->getSequencerOffSet());
        $this->sequencer->setDefaultStart($this->syncAPI->getSyncConfigManager()->getSequencerSystemId()-1);

    }

    /**
     * Manipulate a TYPO3 insert array (key -> value),
     * adds the uid that should be forced during INSERT
     *
     * @param string $tableName
     * @param array  $fields_values
     */
    public function modifyInsertFields($tableName, array &$fields_values, array &$fields = NULL)
    {
        if ($this->isSequencerSupported($tableName) && array_key_exists('uid', tx_st9fissync::getInstance()->getSyncDBObject()->admin_get_fields($tableName))) {
            if (isset($fields_values['uid'])) {
                //use for logger 'UID is already set for table "' . $tableName . '"', 'st9fissync_dbsequencer'/ $fields;
            } else {
                //modify field values
                $fields_values['uid'] = $this->sequencer->getNextIdForTable($tableName);

                //modify fields
                if (!is_null($fields) && !in_array('uid', $fields)) {
                    $fields[] = 'uid';
                }

                return true;
            }
        }

        return false;
        //return $fields_values;
    }

    /**
     * Check if a table is configured to use the sequencer
     *
     * @param  string  $tableName
     * @return boolean
     */
    public function isSequencerSupported($tableName)
    {
        if (in_array($tableName,$this->supportedTables)) {
            return true;
        }

        return false;
    }

}

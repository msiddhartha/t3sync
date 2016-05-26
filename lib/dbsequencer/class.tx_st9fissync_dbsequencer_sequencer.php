<?php
/***************************************************************
 *  Copyright notice
*
*  (c) 2012 Netzrezepte <info@netzrezepte.de>
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

* Sequencer is used to generate system wide independent UID(s)
* creating an system UID space
*
* @author <info@studioneun.de>
* @package TYPO3
* @subpackage st9fissync
*
*/

class tx_st9fissync_dbsequencer_sequencer
{
    /**
     * @var integer
     */
    private $defaultStart = 0;
    /**
     * @var integer
     */
    private $defaultOffset = 1;
    /**
     * @var integer
     */
    private $checkInterval = 0;

    /**
     * @param $defaultStart the $defaultStart to set
     */
    public function setDefaultStart($defaultStart)
    {
        $this->defaultStart = $defaultStart;
    }

    /**
     * @param $defaultOffset the $defaultOffset to set
     */
    public function setDefaultOffset($defaultOffset)
    {
        $this->defaultOffset = $defaultOffset;
    }

    /**
     * returns next free id in the sequence for the table
     *
     * @param unknown_type $table
     * @param unknown_type $depth
     */
    public function getNextIdForTable($table, $depth = 0)
    {
        if ($depth > 99) {
            throw new Exception ( 'The sequencer cannot return IDs for this table -' . $table . ' Too many recursions - maybe to much load?' );
        }

        $row = tx_st9fissync::getInstance()->getSyncDBOperationsManager()->getSequencerDetailsForTable($table);

        $complexCondition = false;

        if (! isset ( $row ['current'] )) {
            $this->initSequencerForTable ( $table );

            return $this->getNextIdForTable ( $table, ++ $depth );
            //throw new Exception('The sequenzer cannot return IDs for this table -'.$table.'- its not configured!');
        } elseif ($row ['timestamp'] + $this->checkInterval < $GLOBALS ['EXEC_TIME']) {
            $defaultStartValue = $this->getDefaultStartValue ( $table );
            $isValueOutdated = ($row ['current'] < $defaultStartValue);
            $isOffsetChanged = ($row ['offset'] != $this->defaultOffset);
            $isStartChanged = ($row ['current'] % $this->defaultOffset != $this->defaultStart);
            if ($isValueOutdated || $isOffsetChanged || $isStartChanged) {
                $row ['current'] = $defaultStartValue;
                $complexCondition = true;
            }
        }

        if ($complexCondition) {
            $new = $row ['current'];
        } else {
            $new = $row ['current'] + $row ['offset'];
        }

        $res2 = tx_st9fissync::getInstance()->getSyncDBOperationsManager()->updateCurrentSequencerFootprint($table, $new, $row ['timestamp']);
        if ($res2 && tx_st9fissync::getInstance()->getSyncDBObject()->sql_affected_rows() > 0) {
            return $new;
        } else {
            return $this->getNextIdForTable ( $table, ++ $depth );
        }
    }

    /**
     * Gets the default start value for a given table.
     *
     * @param string $table
     * @param integer
     */
    private function getDefaultStartValue($table)
    {
        $nextAutoIndex =  tx_st9fissync::getInstance()->getSyncDBOperationsManager()->getNextAutoIndexIdForTable($table);
        $start = $this->defaultStart + ($this->defaultOffset * ceil ( $nextAutoIndex / $this->defaultOffset ));

        return $start;
    }

    /**
     * if no sequence entry for the table yet exists, this method initialises the sequencer
     * to fit offest and start and current max value in the table
     *
     * @param string $table
     */
    private function initSequencerForTable($table)
    {
        $start = $this->getDefaultStartValue ( $table );
        tx_st9fissync::getInstance()->getSyncDBOperationsManager()->registerTableForSequencing($table, $start, $this->defaultOffset);
    }

}

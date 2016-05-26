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
 * Mapper, Assembler, DTO generator
 * for DAM processing
* @author <info@studioneun.de>
* @package TYPO3
* @subpackage st9fissync
*
*/

class tx_st9fissync_dam_dto extends tx_lib_object
{
    public function loadSyncableDAMQueryRes()
    {
        //only the fields that will be used as arguments
        $syncDAMArgsQueue = t3lib_div::makeInstance('tx_lib_object');

        //all the fields for post response evaluation
        $syncDAMItemsQueue = t3lib_div::makeInstance('tx_lib_object');

        //fetch all syncable items unfiltered
        $syncableDAMItems = tx_st9fissync::getInstance()->getSyncDBOperationsManager()->getSyncableDAMIndexQueries();

        //transform these rows for the sync/re-sync queue
        foreach ($syncableDAMItems as $syncableDAMItem) {

            $syncableDAMItem['query_text'] = tx_st9fissync::getInstance()->cureControlCharsInString($syncableDAMItem['query_text']);

            //set & save the unfiltered version
            $syncDAMItemsQueue->set("".$syncableDAMItem['uid'], $syncableDAMItem);

            foreach ($syncableDAMItem as $syncableDAMItemField => $syncableDAMItemVal) {
                if (!in_array($syncableDAMItemField, tx_st9fissync::getInstance()->getAllowTransferFieldsSyncDAMItems()) && $syncableDAMItemField != 'uid') {
                    //unset fields not allowed for transfer
                    unset($syncableDAMItem[$syncableDAMItemField]);
                }
            }
            //filtered version of syncable items used as arguments for transfer objects
            $syncDAMArgsQueue->set("".$syncableDAMItem['uid'], $syncableDAMItem);
        }

        $this->clear();
        $this->set('syncDAMRelQueue', $syncDAMItemsQueue->getArrayCopy());
        $this->set('syncDAMRelDTOQueue', $syncDAMArgsQueue->getArrayCopy());
    }

    /**
     * @param  boolean $refresh
     * @return array   $syncDAMRelQueue
     */
    public function getSyncableDAMQueryResRaw($refresh = false)
    {
        if($refresh)
            $this->loadSyncableDAMQueryRes();

        return $this->get('syncDAMRelQueue');
    }

    /**
     * @param  boolean $refresh
     * @return array   $syncDAMRelDTOQueue
     */
    public function getSyncableDAMQueryResDTOs($refresh = false)
    {
        if($refresh)
            $this->loadSyncableDAMQueryRes();

        return $this->get('syncDAMRelDTOQueue');
    }

}

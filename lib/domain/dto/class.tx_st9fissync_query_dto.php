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
 *
 * @author <info@studioneun.de>
* @package TYPO3
* @subpackage st9fissync
*
*/

class tx_st9fissync_query_dto extends tx_lib_object
{
    //set syncItemsQueue

    //set syncResultsQueue

    // set syncErrorsQueue

    /**
     *
     */
    public function loadSyncableQueries()
    {
        //only the fields that will be used as arguments
        $syncArgsQueue = t3lib_div::makeInstance('tx_lib_object');

        //all the fields for post response evaluation
        $syncItemsQueue = t3lib_div::makeInstance('tx_lib_object');

        $syncableItems = array();
        //fetch all syncable items unfiltered
        if (tx_st9fissync::getInstance()->getSyncMode() == tx_st9fissync::SYNC_MODE_SYNC) {
            $syncableItems = tx_st9fissync::getInstance()->getSyncDBOperationsManager()->getSyncableQueries();
        } elseif (tx_st9fissync::getInstance()->getSyncMode() == tx_st9fissync::SYNC_MODE_RESYNC) {
            $syncableItems = tx_st9fissync::getInstance()->getSyncDBOperationsManager()->getReSyncableQueries();
        }
        //transform these rows for the sync/re-sync queue
        foreach ($syncableItems as $syncableItem) {

            $syncableItem['query_text'] = tx_st9fissync::getInstance()->cureControlCharsInString($syncableItem['query_text']);

            //set & save the unfiltered version
            $syncItemsQueue->set("".$syncableItem['uid'], $syncableItem);

            foreach ($syncableItem as $syncableItemField => $syncableItemVal) {
                if (!in_array($syncableItemField, tx_st9fissync::getInstance()->getAllowTransferFieldsSyncItems()) && $syncableItemField != 'uid') {
                    //unset fields not allowed for transfer
                    unset($syncableItem[$syncableItemField]);
                }
            }
            //filtered version of syncable items used as arguments for transfer objects
            $syncArgsQueue->set("".$syncableItem['uid'], $syncableItem);
        }

        $this->clear();
        $this->set('syncQueryQueue', $syncItemsQueue->getArrayCopy());
        $this->set('syncQueryDTOQueue', $syncArgsQueue->getArrayCopy());

    }

    /**
     * @param  boolean $refresh
     * @return array   $syncArgsQueue
     */
    public function getSyncableQueriesRaw($refresh = false)
    {
        if($refresh)
            $this->loadSyncableQueries();

        return $this->get('syncQueryQueue');
    }

    /**
     * @param  boolean $refresh
     * @return array   $syncArgsQueue
     */
    public function getSyncableQueryDTOs($refresh = false)
    {
        if($refresh)
            $this->loadSyncableQueries();

        return $this->get('syncQueryDTOQueue');
    }

}

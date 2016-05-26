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
 * for Support Module Uploads
* @author <info@studioneun.de>
* @package TYPO3
* @subpackage st9fissync
*
*/

class tx_st9fissync_st9fissupport_dto extends tx_lib_object
{
    /**
     * @param void
     *
     * @return void
     */
    public function loadSyncableSupportUploadsRes()
    {
        $syncSupportUploadsFileInfoQueue = t3lib_div::makeInstance('tx_lib_object');

        //only the fields that will be used as arguments
        $syncSupportUploadsDTOQueue = t3lib_div::makeInstance('tx_lib_object');

        //all the fields for post response evaluation
        $syncSupportUploadsQueue = t3lib_div::makeInstance('tx_lib_object');

        //fetch all syncable items unfiltered
        $syncableSupportUploadsItems = tx_st9fissync::getInstance()->getSyncDBOperationsManager()->getReSyncableSupportUploadsQueries();

        //transform these rows for the sync/re-sync queue
        foreach ($syncableSupportUploadsItems as $syncableSupportUploadsItem) {

            $syncableSupportUploadsItem['query_text'] = tx_st9fissync::getInstance()->cureControlCharsInString($syncableSupportUploadsItem['query_text']);

            $filePath = tx_st9fissync::getInstance()->getSyncDBOperationsManager()->getSyncableSupportUploadsFile($syncableSupportUploadsItem['uid_foreign']);

            $fileInfo = tx_dam::file_compileInfo($filePath);

            if (!$fileInfo) {

                $fileInfo = array();

                $filePath = trim($filePath);

                if (empty($filePath)) {
                    //some support file is not  supposed to exist, so it does not (distinct flag set to -1)
                    $fileInfo['__exists'] = -1;
                } else {
                    //some file should exist, yet it does not (flag set to 0)
                    $fileInfo['__exists'] = 0;
                }
            }

            if (!$syncSupportUploadsFileInfoQueue->offsetExists($filePath)) {
                unset($fileInfo['file_path_absolute']);
                $fileInfo['qvIds'][] = $syncableSupportUploadsItem['uid'];
                $syncSupportUploadsFileInfoQueue->set($filePath,$fileInfo);
            } else {
                $fileInfo = $syncSupportUploadsFileInfoQueue->get($filePath);
                $fileInfo['qvIds'][] = $syncableSupportUploadsItem['uid'];
                $syncSupportUploadsFileInfoQueue->set($filePath,$fileInfo);
            }

            //set & save the unfiltered version
            $syncSupportUploadsQueue->set("".$syncableSupportUploadsItem['uid'], $syncableSupportUploadsItem);

            foreach ($syncableSupportUploadsItem as $syncableSupportUploadsItemField => $syncableSupportUploadsItemVal) {
                if (!in_array($syncableSupportUploadsItemField, tx_st9fissync::getInstance()->getAllowTransferFieldsSyncSupportUploadsItems()) && $syncableSupportUploadsItemField != 'uid') {
                    //unset fields not allowed for transfer
                    unset($syncableSupportUploadsItem[$syncableSupportUploadsItemField]);
                }
            }
            //filtered version of syncable items used as arguments for transfer objects
            $syncSupportUploadsDTOQueue->set("".$syncableSupportUploadsItem['uid'], $syncableSupportUploadsItem);
        }

        $this->clear();
        $supportUploadsRes = array();
        $supportUploadsRes['supportUploadFileInfo'] = $syncSupportUploadsFileInfoQueue->getArrayCopy();

        $supportUploadsRes['supportUploadQueries'] = $syncSupportUploadsQueue->getArrayCopy();
        $this->set('syncSupportUploadsQueue', $supportUploadsRes);

        $supportUploadsRes['supportUploadQueries'] = $syncSupportUploadsDTOQueue->getArrayCopy();
        $this->set('syncSupportUploadsDTOQueue', $supportUploadsRes);
    }

    /**
     * @param  boolean $refresh
     * @return array   $syncSupportUploadsQueue
     */
    public function getSyncableSupportUploadsRaw($refresh = false)
    {
        if($refresh)
            $this->loadSyncableSupportUploadsRes();

        return $this->get('syncSupportUploadsQueue');
    }

    /**
     * @param  boolean $refresh
     * @return array   $syncSupportUploadsDTOQueue
     */
    public function getSyncableSupportUploadsDTOs($refresh = false)
    {
        if($refresh)
            $this->loadSyncableSupportUploadsRes();

        return $this->get('syncSupportUploadsDTOQueue');
    }

}

<?php

class tx_st9fissync_functests
{
    public function testMakeFolderPathForTimeStamp($folderPathRoot , $timestamp = null)
    {
        tx_st9fissync::getInstance()->makeFolderPathForTimeStamp($folderPathRoot,$timestamp);
    }

    public function testDoTableRowBackUp()
    {
        $timeStamp = time();
        $tableName = 'tx_st9fissync_dbversioning_query';
        $filePath = tx_st9fissync::getInstance()->makeFolderPathForTimeStamp(
                tx_st9fissync::getInstance()->getSyncConfigManager()->getGCBackupFolderRoot(), $timeStamp);
        if ($filePath) {
            $filePath = tx_st9fissync::getInstance()->makeFullFilePath($filePath, 'gc1-' . $timeStamp . '-' . $tableName . '.sql');
        }
        if ($filePath) {
            $whereClause = 'UID > 20';
            tx_st9fissync::getInstance()->doTableRowBackUp($tableName, $whereClause, $filePath);
        }
    }

    public function initFuncTests()
    {
        //$this->testMakeFolderPathForTimeStamp(tx_st9fissync::getInstance()->getSyncConfigManager()->getGCBackupFolderRoot());
        $this->testDoTableRowBackUp();
    }

}

if (defined ('TYPO3_MODE') && TYPO3_MODE === 'BE') {

    class tx_st9fissync_tests extends tx_scheduler_Task
    {
        public function execute()
        {
            $funcTests = t3lib_div::makeInstance('tx_st9fissync_functests');
            $funcTests->initFuncTests();

            return true;
        }

    }

}

<?php

if (!defined ('PATH_typo3conf')) die ('Could not access this script directly!');

class ajaxClass
{
    /**
     *
     * initialize ajax environment
     */
    public function init()
    {
        tslib_eidtools::connectDB();
    }

    /**
     *
     * main function, handle request
     */
    public function main()
    {
        $cmd = strip_tags($_GET['cmd']);
        switch ($cmd) {
        case 'addresync':
            return $this->addResyncEntry($_GET['table'], $_GET['uid'], $_GET['action'], $_GET['tstamp']);
            break;
        default:
            return 'UNKNOWN COMMAND';
        }
    }

    /**
     *
     * add entry to resync queuein db
     * @param  string  $table
     * @param  integer $uid
     * @param  string  $action (only 'new' and 'update' are allowed)
     * @param  integer $tstamp
     * @return string  result message
     */
    private function addResyncEntry($table, $uid, $action, $tstamp)
    {
        $res = 'failed!';

        include_once PATH_typo3 . 'contrib/RemoveXSS/RemoveXSS.php';
        $xss = t3lib_div::makeInstance('RemoveXSS', 'dummy');

        include_once t3lib_extMgm::extPath('st9fissync') . 'models/class.tx_st9fissync_model_resyncentries.php';
        include_once t3lib_extMgm::extPath('st9fissync') . 'lib/class.tx_st9fissync_lib_queue.php';

        $record_table = $xss->RemoveXSS($table);
        unset($xss);
        $record_uid = intval($uid);
        switch ($action) {
        case 'new':
            $record_action = RESYNC_ACTION_NEW;
            break;
        case 'update':
            $record_action = RESYNC_ACTION_UPDATE;
            break;
        default:
            return $res;
        }
        $record_tstamp = intval($tstamp);

        $queue = t3lib_div::makeInstance('tx_st9fissync_lib_queue');
        $added = $queue->addResyncEntry($record_table, $record_uid, $record_action, $record_tstamp);

        unset($queue);

        if ($added)
            $res = 'done.';

        return $res;
    }

    /**
     *
     * create log entry for file
     * @param integer $fileID
     */
    private function trackFile($fileID)
    {
        $fileID = (int) $fileID;
        if (!$fileID)
            return;

        $data = array(
            'pid'				=> $this->damFolder,
            'tstamp'			=> time(),
            'crdate'			=> time(),
            'cruser_id'			=> 0,

            'label'				=> $this->getLabel(),
            'file'				=> $fileID,
            'feuser'			=> (int) $this->user->user['uid'],
            'tstamp_download'	=> time(),
            'tstamp_ip'			=> t3lib_div::getIndpEnv('REMOTE_ADDR'),
        );

        $table = 'tx_st9fisdownloads_protocol';

        $res = $GLOBALS['TYPO3_DB']->exec_INSERTquery($table, $data);
        if (!$res)
            return false;

        $fields = 'COUNT(*) AS num';
        $where = 'file = ' . $fileID;
        $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where);
        if (!$res)
            return false;

        $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
        if (!is_array($row))
            return false;

        $num = $row['num'];
        $table = 'tx_dam';
        $where = 'uid = ' . $fileID;
        $data = array(
            'tx_st9fisdownloads_protocol' => $num,
        );
        $res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($table, $where, $data);

        return $res;
    }

}

$ajaxRequest = new ajaxClass;
$ajaxRequest->init();
echo $ajaxRequest->main();

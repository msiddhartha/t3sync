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
 * Base API for tx_st9fissync.
 *
 *
 * @author	André Spindler <info@studioneun.de>
* @package	TYPO3
* @subpackage	tx_st9fissync
*/

require_once(t3lib_extMgm::extPath('caretaker_instance', 'classes/class.tx_caretakerinstance_ServiceFactory.php'));

class tx_st9fissync
{
    /**
     *
     * @var int
     */
    private $stage;
    const RECORDING 	= 1;
    const GC		 	= 3;
    const SYNC  		= 2; //check sync sub mode

    /**
     *
     * @var int
     */
    private $syncMode;
    const SYNC_MODE_SYNC = 1;
    const SYNC_MODE_RESYNC = 2;

    /**
     *
     * @var tx_st9fissync
     */
    protected static $instance;

    /**
     *
     * @var int
     */
    private $origEnvironMaxExecTime;

    /**
     *
     * @var int
     */
    private $origEnvironMemLimit;

    /**
     *
     * @var int
     */
    private $origSOAPWSDLCacheEnable;

    /**
     *
     * @var int
     */
    private $origPCREBackTrackLimit;

    const DAM_INDEX_TRANSFER_INELIGIBLE = 0;
    const DAM_INDEX_TRANSFER_ELIGIBLE = 1;
    const DAM_FILE_TRANSFER_ELIGIBLE = 2;

    /**
     * Instantiate API
     * @static
     * @return tx_st9fissync
     */
    public static function getInstance()
    {
        if (tx_st9fissync::$instance == null) {
            tx_st9fissync::$instance = t3lib_div::makeInstance('tx_st9fissync');
        }

        return tx_st9fissync::$instance;
    }

    public function setSyncRuntimeMaxExecTime()
    {
        $this->origEnvironMaxExecTime = ini_get('max_execution_time');
        $syncRunTimeMaxExecTime = $this->getSyncConfigManager()->getSyncMaxExecTime();
        if ($syncRunTimeMaxExecTime != null) {
            ini_set('max_execution_time', $syncRunTimeMaxExecTime);
        }

        return $this->origEnvironMaxExecTime;
    }

    public function resetToOrigRuntimeMaxExecTime()
    {
        if ($this->origEnvironMaxExecTime != null) {
            ini_set('max_execution_time', $this->origEnvironMaxExecTime);
        }

        return $this->origEnvironMaxExecTime;
    }

    public function setSyncRuntimeMemoryLimit()
    {
        $this->origEnvironMemLimit = ini_get('memory_limit');
        $syncRunTimeMemLimit = $this->getSyncConfigManager()->getSyncMemoryLimit();

        if ($syncRunTimeMemLimit != null) {

            ini_set('memory_limit', $syncRunTimeMemLimit);

        }

        return $this->origEnvironMemLimit;
    }

    public function resetToOrigRuntimeMemoryLimit()
    {
        if ($this->origEnvironMemLimit != null) {
            ini_set('memory_limit', $this->origEnvironMemLimit);
        }

        return $this->origEnvironMemLimit;
    }

    public function setSyncT3SOAPWSDLCacheEnable()
    {
        $this->origSOAPWSDLCacheEnable = ini_get('soap.wsdl_cache_enabled');
        $syncT3SOAPWSDLCacheStatus = $this->getSyncConfigManager()->getSyncT3SoapWSDLCacheEnabled();
        if ($syncT3SOAPWSDLCacheStatus != null) {
            ini_set('soap.wsdl_cache_enabled', $syncT3SOAPWSDLCacheStatus);
        }

        return $this->origSOAPWSDLCacheEnable;
    }

    public function resetToOrigSOAPWSDLCacheEnable()
    {
        if ($this->origSOAPWSDLCacheEnable != null) {
            ini_set('soap.wsdl_cache_enabled', $this->origSOAPWSDLCacheEnable);
        }

        return $this->origSOAPWSDLCacheEnable;
    }

    /**
     * Define stage as RECORDING
     *
     */
    public function setStageRecording()
    {
        $this->setStage(tx_st9fissync::RECORDING);
    }

    /**
     * Define stage as SYNC
     *
     */
    public function setStageSync()
    {
        $this->setStage(tx_st9fissync::SYNC);
    }

    /**
     * Define stage as GC
     *
     */
    public function setStageGC()
    {
        $this->setStage(tx_st9fissync::GC);
    }

    /**
     * Set Stage (Setter)
     *
     * @param int $stage
     */
    public function setStage($stage)
    {
        $this->stage = $stage;
    }

    /**
     * Get Stage (Getter)
     *
     * @return int
     */
    public function getStage()
    {
        return $this->stage;
    }

    /**
     * $GLOBALS['TYPO3_DB'] like object used for Sync write
     */
    public function getSyncDBObject()
    {
        if ($this->syncDBObject == null) {
            require_once(PATH_t3lib.'config_default.php');

            try {
                if (!defined ('TYPO3_db')) {
                    throw new Exception ("The configuration file was not included.");
                } else {
                    require_once(PATH_t3lib.'class.t3lib_db.php');
                    $this->syncDBObject = t3lib_div::makeInstance('t3lib_DB');
                    $this->syncDBObject->sql_pconnect(TYPO3_db_host, TYPO3_db_username, TYPO3_db_password);
                    $this->syncDBObject->sql_select_db(TYPO3_db);
                    $this->syncDBObject->disableSequencer();
                    $this->syncDBObject->disableVersioning();
                }
            } catch (Exception $e) {
                //logFatal $e->getMessage()
            }

        }

        return $this->syncDBObject;
    }

    /**
     *
     * @return tx_st9fissync_dbsequencer_t3service
     */
    public function getSyncSequencerService()
    {
        if ($this->syncSequencerService == null) {
            $this->syncSequencerService = t3lib_div::makeInstance('tx_st9fissync_dbsequencer_t3service',$this->getSequencer());
        }

        return $this->syncSequencerService;
    }

    /**
     *
     * @return tx_st9fissync_dbsequencer_sequencer
     */
    public function getSequencer()
    {
        if ($this->syncSequencer == null) {
            $this->syncSequencer = t3lib_div::makeInstance('tx_st9fissync_dbsequencer_sequencer');
        }

        return $this->syncSequencer;
    }

    /**
     *
     * @return tx_st9fissync_dbversioning_t3service
     */
    public function getSyncT3VersioningService($versioningRulesEngineClassName = 'tx_st9fissync_dbversioning_t3service')
    {
        if ($this->syncT3VersioningService == null) {
            $this->syncT3VersioningService = t3lib_div::makeInstance($versioningRulesEngineClassName);
        }

        return $this->syncT3VersioningService;
    }

    /**
     *
     * @return tx_st9fissync_db
     */
    public function getSyncDBOperationsManager()
    {
        if ($this->syncDBOperationsManager == null) {
            $this->syncDBOperationsManager = t3lib_div::makeInstance('tx_st9fissync_db');
        }

        return $this->syncDBOperationsManager;
    }

    /**
     *
     * @return tx_st9fissync_config
     */
    public function getSyncConfigManager($conf = NULL)
    {
        if ($this->syncConfigManager == null) {
            if (is_null($conf)) {
                $conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][ST9FISSYNC_EXTKEY]);
            }
            $this->syncConfigManager = t3lib_div::makeInstance('tx_st9fissync_config',$conf);

            $status = $this->getSyncDBOperationsManager()->checkUpdateConfigHistory($this->syncConfigManager);
        }

        return $this->syncConfigManager;
    }

    /**
     *
     * @return tx_st9fissync_sync
     */
    public function getSyncProcessHandle()
    {
        if ($this->syncProcessHandle == null) {
            $this->syncProcessHandle = t3lib_div::makeInstance('tx_st9fissync_sync');
        }

        return $this->syncProcessHandle;
    }

    /**
     *
     * @param int|void
     *
     * @return tx_st9fissync_logger
     */
    public function getSyncLogger($priority = null)
    {
        if ($this->syncLogger == null) {
            if ($priority==null) {
                $priority = $this->getSyncConfigManager()->getSyncLoggerPriority();
            }
            $this->syncLogger = t3lib_div::makeInstance('tx_st9fissync_logger',$priority);
        }

        return $this->syncLogger;
    }

    /**
     *
     * @return tx_st9fissync_service_handler $syncServiceHandler
     */
    public function getSyncServiceHandler()
    {
        if ($this->syncServiceHandler == null) {
            $this->syncServiceHandler = t3lib_div::makeInstance('tx_st9fissync_service_handler');
        }

        return $this->syncServiceHandler;
    }

    /**
     *
     * @param boolean $refresh
     *
     * @return tx_st9fissync_query_dto
     */
    public function getSyncDataTransferObject($refresh = false)
    {
        if ($this->syncDTO == null || $refresh) {
            $this->syncDTO = t3lib_div::makeInstance('tx_st9fissync_query_dto');
        }

        return $this->syncDTO;
    }

    /**
     *
     * @param boolean $refresh
     *
     * @return tx_st9fissync_dam_dto
     */
    public function getSyncDAMDataTransferObject($refresh = false)
    {
        if ($this->syncDTO == null || $refresh) {
            $this->syncDTO = t3lib_div::makeInstance('tx_st9fissync_dam_dto');
        }

        return $this->syncDTO;
    }

    /**
     *
     * @param boolean $refresh
     *
     * @return tx_st9fissync_st9fissupport_dto
     */
    public function getSyncSupportUploadsDTO($refresh = false)
    {
        if ($this->syncSupportUploadsDTO == null || $refresh) {
            $this->syncSupportUploadsDTO = t3lib_div::makeInstance('tx_st9fissync_st9fissupport_dto');
        }

        return $this->syncSupportUploadsDTO;
    }

    /**
     *
     * @param boolean $refresh
     *
     * @return tx_st9fissync_resultresponse_dto
     */
    public function getSyncResultResponseDTO($refresh = false)
    {
        if ($this->syncResDTO == null || $refresh) {
            $this->syncResDTO = t3lib_div::makeInstance('tx_st9fissync_resultresponse_dto');
        }

        return $this->syncResDTO;
    }

    /**
     * Sync Mode:  TYPE_SYNC = 0 / TYPE_RESYNC = 1
     *
     * @param int $syncMode
     *
     */
    public function setSyncMode($syncMode)
    {
        if (!in_array($syncMode, array(tx_st9fissync::SYNC_MODE_SYNC, tx_st9fissync::SYNC_MODE_RESYNC, null))) {
            throw new tx_st9fissync_exception('Invalid sync mode specified. Use tx_st9fissync::SYNC_MODE_SYNC or tx_st9fissync::SYNC_MODE_RESYNC constants.');
        }
        $this->syncMode = $syncMode;
    }

    /**
     * Sync Mode:  TYPE_SYNC = 0 / TYPE_RESYNC = 1
     *
     * @return int
     */
    public function getSyncMode()
    {
        return $this->syncMode;
    }

    /**
     * return Mode as string labels for use in various notification/logging scenarios
     * will be later made part of LL XMLs
     *
     * @return string
     */
    public function getSyncModeAsLabel()
    {
        if ($this->syncMode == tx_st9fissync::SYNC_MODE_SYNC) {
            return 'Mode - [SYNC]: ';
        } elseif ($this->syncMode == tx_st9fissync::SYNC_MODE_RESYNC) {
            return 'Mode - [RE-SYNC]: ';
        } else {
            return "";
        }
    }

    /**
     * Get Sync labels
     * @param string $key
     *
     * @return string
     */
    public function getSyncLabels($key)
    {
        if (!is_object($GLOBALS['LANG'])) {
            $GLOBALS['LANG'] = t3lib_div::makeInstance('language');
            $GLOBALS['LANG']->init('default');
        } else {
            $GLOBALS['LANG']->includeLLFile(t3lib_extMgm::extPath(ST9FISSYNC_EXTKEY).'language/locallang_app_core.xml');

            return  $GLOBALS['LANG']->getLL($key, true);
        }

        return '';
    }

    /**
     * allowed fields to be part of the transfer object, depends on the Sync mode
     *
     * make it part of DTO s!!!!
     *
     * @return array
     */
    public function getAllowTransferFieldsSyncItems()
    {
        return array('query_text','query_type','uid_foreign','tablenames');
    }

    public function getAllowTransferFieldsSyncDAMItems()
    {
        return array('query_text','query_type','uid_foreign','tablenames');
    }

    public function getAllowTransferFieldsSyncSupportUploadsItems()
    {
        return array('query_text','query_type','uid_foreign','tablenames');
    }

    /**
     * First check if index already exists
     * if exists check if physical file exists
     * if exists check if it has really changed (send some more metadata to identify)
     *
     * if index does not exist, check usage from mm ref and foreign table record
     * any usage found must indicate as eligible to sync
     *
     * @param array $damMap
     *
     * @return boolean
     */
    public function isSyncEligibleByDAMTransferRules($damMap)
    {
        try {

            foreach ($damMap as $damIndex => $damMetadata) {

                $eligible = tx_st9fissync::DAM_INDEX_TRANSFER_INELIGIBLE;

                if ($this->getSyncDBOperationsManager()->doesDAMIndexExists($damIndex)) {

                    if ($this->doesRecordExistByDAMProtocol($damIndex)) {

                        $otherDAMMETA = tx_dam::meta_getDataByUid($damIndex);

                        if ($damMetadata['metadata'] != $otherDAMMETA) {
                            //meta data not equal so eligible
                            $eligible = tx_st9fissync::DAM_FILE_TRANSFER_ELIGIBLE;

                        } else {
                            //meta data equal so not eligible to be synced

                        }
                    } else {
                        // index exists but no reference so not eligible

                    }

                } else {
                    //index does not exists, so check if ref in mm table and if all such references have legitimate reference objects
                    if ($this->doesRecordExistByDAMProtocol($damIndex)) {
                        //but referred by a legitimate table record so eligible
                        $eligible = tx_st9fissync::DAM_INDEX_TRANSFER_ELIGIBLE;

                    } else {
                        //not referred by a legitimate table record so not eligible

                    }
                }

            }

        } catch (tx_st9fissync_exception $damTransferIneligible) {
            //$damTransferIneligible->getMessage()
        }

        return $eligible;
    }

    /**
     * Checks if a particular DAM index UID is referred to by a legitimate record cObj
     *
     * @param int $recId DAM index UID
     */
    public function doesRecordExistByDAMProtocol($recId)
    {
        return $this->getSyncDBOperationsManager()->doesRecordExistByDAMMMRef($recId) ||
        $this->getSyncDBOperationsManager()->doesRecordExistByDAMSysIndexRef($recId);

    }

    /**
     *
     * @return tx_st9fissync_errorqueue $this->syncErrorQueue
     */
    public function getSyncErrorQueue()
    {
        if ($this->syncErrorQueue == null) {
            $this->syncErrorQueue = t3lib_div::makeInstance('tx_st9fissync_errorqueue');
        }

        return $this->syncErrorQueue;
    }

    /**
     * Wrap a value in CDATA tags
     * typically for SOAP XML requests
     *
     * @param string @value
     *	The string to wrap in CDATA
     *
     * @return string
     *	The wrapped string
     */
    public static function wrapInCDATA($value)
    {
        return '<![CDATA[' . $value . ']]>';
    }

    /**
     *
     *  un-cdata the string
     *  typically for queries that are SOAP trasnferred and hence part of SOAP request XML
     * @var string
     */
    public static function unwrapCDATAString($cdataWrappedString)
    {
        $cdataUnWrappedString = str_replace('<![CDATA[', '', $cdataWrappedString);
        $cdataUnWrappedString = str_replace(']]>',       '', $cdataUnWrappedString);

        return $cdataUnWrappedString;
    }

    /**
     *
     * @param string $xmlString
     *
     * @return string
     */
    public static function cureCDATAInXML($xmlString)
    {
        $curedCDATAXMLString = str_replace('&lt;![CDATA[', '<![CDATA[', $xmlString);
        $curedCDATAXMLString = str_replace(']]&gt;', ']]>', $curedCDATAXMLString);

        return $curedCDATAXMLString;
    }

    /**
     *
     * Use to cure strings transferred as part of SOAP XML payloads
     *
     * Replace control chars with their representable versions
     *
     * @param  string $string
     * @return string
     */
    public static function cureControlCharsInString($string)
    {
        //return preg_replace('/[[:cntrl:]]/', '', $string);

        $string = preg_replace(
                array(
                        '/\x00/', '/\x01/', '/\x02/', '/\x03/', '/\x04/',
                        '/\x05/', '/\x06/', '/\x07/', '/\x08/', '/\x09/', '/\x0A/',
                        '/\x0B/','/\x0C/','/\x0D/', '/\x0E/', '/\x0F/', '/\x10/', '/\x11/',
                        '/\x12/','/\x13/','/\x14/','/\x15/', '/\x16/', '/\x17/', '/\x18/',
                        '/\x19/','/\x1A/','/\x1B/','/\x1C/','/\x1D/', '/\x1E/', '/\x1F/'
                ),
                array(
                        "\u0000", "\u0001", "\u0002", "\u0003", "\u0004",
                        "\u0005", "\u0006", "\u0007", "\u0008", "\u0009", "\u000A",
                        "\u000B", "\u000C", "\u000D", "\u000E", "\u000F", "\u0010", "\u0011",
                        "\u0012", "\u0013", "\u0014", "\u0015", "\u0016", "\u0017", "\u0018",
                        "\u0019", "\u001A", "\u001B", "\u001C", "\u001D", "\u001E", "\u001F"
                ),
                $string
        );

        return $string;
    }

    /**
     * This method is used to add a message to the internal queue
     *
     * @param	string	the message itself
     * @param	integer	message level (-1 = success (default), 0 = info, 1 = notice, 2 = warning, 3 = error)
     * @return string $appFlashMsg->render() if $renderInline is TRUE or just add to the queue
     */
    public function addAppMessage($message, $title = '', $severity = tx_st9fissync_message::OK, $storeInSession = FALSE, $renderInline = FALSE)
    {
        $appMsg= t3lib_div::makeInstance(
                'tx_st9fissync_message',
                $message,
                $title,
                $severity,
                $storeInSession
        );

        if ($renderInline) {
            return $appMsg->render();
        }

        tx_st9fissync_messagequeue::addMessage($appMsg);
    }

    public function addAppLog($message, $logLevel, $relatedTable = '')
    {
        // Force exclude from logging
        if (in_array($relatedTable, $this->getSyncConfigManager()->getLoggerDisabledTablesList()) || $relatedTable == $this->getSyncDBOperationsManager()->getLogTable()) {
            return false;
        }

        $logLevel = intval($logLevel);
        $message = $this->br2nl($message);

        return $this->getSyncLogger()->log($message, $logLevel);

    }

    /**
     *
     * Add messages START
     *
     * 1. log error to DB
     * 2. add error to app error message queue
     *
     * @param string  $message
     * @param string  $title
     * @param boolean $addToAppLog
     * @param boolean $addToAppMessage
     *
     * @return void
     */

    public function addDebugMessage($message, $newLine = true, $title = '', $addToAppLog = true, $addToAppMessage = true)
    {
        if($newLine)
            $message .= '<br>';

        if($addToAppLog)
            $this->addAppLog($message, tx_st9fissync_logger::DEBUG);
        if($addToAppMessage)
            $this->addAppMessage($message, $title, tx_st9fissync_message::INFO);
    }

    public function addInfoMessage($message, $newLine = true, $title = '', $addToAppLog = true, $addToAppMessage = true)
    {
        if($newLine)
            $message .= '<br>';

        if($addToAppLog)
            $this->addAppLog($message, tx_st9fissync_logger::INFO);
        if($addToAppMessage)
            $this->addAppMessage($message, $title, tx_st9fissync_message::INFO);
    }

    public function addWarningMessage($message, $newLine = true, $title = '', $addToAppLog = true, $addToAppMessage = true)
    {
        if($newLine)
            $message .= '<br>';

        if($addToAppLog)
            $this->addAppLog($message, tx_st9fissync_logger::WARN);
        if($addToAppMessage)
            $this->addAppMessage($message, $title, tx_st9fissync_message::WARNING);
    }

    public function addErrorMessage($message, $newLine = true, $title = '', $addToAppLog = true, $addToAppMessage = true)
    {
        if($newLine)
            $message .= '<br>';

        if($addToAppLog)
            $this->addAppLog($message, tx_st9fissync_logger::ERROR);
        if($addToAppMessage)
            $this->addAppMessage($message, $title, tx_st9fissync_message::ERROR);
    }

    public function addFatalMessage($message, $newLine = true, $title = '', $addToAppLog = true, $addToAppMessage = true)
    {
        if($newLine)
            $message .= '<br>';

        if($addToAppLog)
            $this->addAppLog($message, tx_st9fissync_logger::ERROR);
        if($addToAppMessage)
            $this->addAppMessage($message, $title, tx_st9fissync_message::ERROR);
    }

    public function addNoticeMessage($message, $newLine = true, $title = '', $addToAppLog = true, $addToAppMessage = true)
    {
        if($newLine)
            $message .= '<br>';

        if($addToAppLog)
            $this->addAppLog($message, tx_st9fissync_logger::INFO);
        if($addToAppMessage)
            $this->addAppMessage($message, $title, tx_st9fissync_message::NOTICE);
    }

    public function addOkMessage($message, $newLine = true, $title = '', $addToAppLog = true, $addToAppMessage = true)
    {
        if($newLine)
            $message .= '<br>';

        if($addToAppLog)
            $this->addAppLog($message, tx_st9fissync_logger::INFO);
        if($addToAppMessage)
            $this->addAppMessage($message, $title, tx_st9fissync_message::OK);

    }

    /**
     *
     * Add messages END
     *
     */

    /**
     * Transforms one kind of message to another
     *
     * @param tx_st9fissync_message $syncMessage
     *
     * @return t3lib_FlashMessage
     */
    public function buildSyncFlashMessage(tx_st9fissync_message $syncMessage)
    {
        $flashMsg= t3lib_div::makeInstance(
                't3lib_FlashMessage',
                $syncMessage->getMessage(),
                $syncMessage->getTitle(),
                $syncMessage->getSeverity(),
                $syncMessage->isSessionMessage()
        );

        return $flashMsg;
    }

    /**
     *
     * Entry point to the Versioning/recording rules engine
     *
     * @param array $versionTips
     *
     * @return mixed
     */
    public function runVersioningRulesEngine($versionTips)
    {
        if ($this->getSyncT3VersioningService()->doesRuleApply($versionTips)) {
            $this->setStageRecording();
            $status = $this->getSyncT3VersioningService()->applyVersioning($versionTips);

            return $status;
        }

        return false;
    }

    /**
     * A centrally maintained registry for all class mapping used for Sync SOAP transactions
     * This has been tested to absolutely make no difference at all
     *
     *
     * @return array
     */
    public function getClassMapforSOAP()
    {
        return array(
                'tx_st9fissync_process' => 'tx_st9fissync_process',
                'tx_lib_object' => 'tx_lib_object',
                'tx_lib_spl_arrayIterator' => 'tx_lib_spl_arrayIterator',
                'tx_lib_spl_arrayObject' => 'tx_lib_spl_arrayObject',
                'tx_lib_objectBase' => 'tx_lib_objectBase',
                'tx_lib_selfAwareness' => 'tx_lib_selfAwareness',
                //'tx_st9fissync_query_dto' => 'tx_st9fissync_query_dto',

        );
    }

    /**
     * Get a SOAP Client API object
     * You might want to fetch a new one each time if you had like to
     * Just set refresh to true
     *
     * @param string  $wsdl
     * @param array   $options
     * @param boolean $refresh
     *
     * @return tx_st9fissync_t3soap_client
     */
    public function getSOAPClient($wsdl,$options,$refresh=false)
    {
        if ($this->SOAPClient == null || $refresh) {
            $this->SOAPClient = t3lib_div::makeInstance('tx_st9fissync_t3soap_client',$wsdl,$options);
        }

        return $this->SOAPClient;
    }

    /**
     * Get Remote Sync Service URL Location/ WSDL URL
     *
     * @param boolean
     *
     * @return string $serviceURL
     */
    public function getSyncServiceURL($wsdl = false)
    {
        $serviceURL = $this->getSyncConfigManager()->getRemoteURL() . 'typo3conf/ext/' . ST9FISSYNC_EXTKEY . '/mod_sync/index.php' . ($wsdl ? '?wsdl':'');

        return $serviceURL;
    }

    /**
     * A getter for 'st9fissync' T3 Sync API services
     * To get a secured channel set $secured = true
     * @param boolean $secured
     *
     * @return tx_st9fissync_t3soap_client|null $SOAPClient
     */
    public function getSyncSOAPClient($secured=false)
    {
        // initialize SOAP client, secured or not
        //$wsdlURI = null;
        $SOAPClient = null;

        $wsdlURI = $this->getSyncServiceURL(true);
        if ($secured) {
            $wsdlURI .= '&secured';
        }

        $options = 		 array(
                'location' =>  $this->getSyncServiceURL() . ($secured?'?secured':''),
                //	'uri'      =>  $this->getSyncServiceURL() . ($secured?'?secured':''),
                'login'    =>  $this->getSyncConfigManager()->getRemoteHttpLogin(),
                'password' =>  $this->getSyncConfigManager()->getRemoteHttpPassword(),
                'classmap' => $this->getClassMapforSOAP(),
                'cache_wsdl' => WSDL_CACHE_NONE
        );

        try {
            //in case of new SOAP client set refresh parameter to true,
            //in this case it is refreshed when one wants a secured SOAP client
            $SOAPClient = $this->getSOAPClient($wsdlURI,$options,$secured);
            if ($secured) {
                $SOAPClient->setSOAPChannelSecurity();
            }
        } catch (SoapFault $s) {
            // logError('Initialize SOAP Client, ERROR: [' . $s->faultcode . '] ' . $s->faultstring);
        } catch (Exception $e) {
            // logError('Initialize SOAP Client, ERROR: ' . $e->getMessage());
        }

        return $SOAPClient;
    }

    /**
     *
     * Matches the remote system's publickey and checks if it is already registered to the
     * caretaker instance at this T3 system.
     *
     * If no client public key is passed then it simply returns this T3 system's public key
     *
     * @param string
     *
     * @return boolean|string
     */
    public function isSOAPSecured($remotePublicKey=NULL)
    {
        if (t3lib_extMgm::isLoaded('caretaker_instance')) {
            if (!is_null($remotePublicKey)) {
                //if remote Client Pub Key is passed
                $clientPubKey = $this->getCaretakerSecurityManagerObject()->getClientPublicKey();
                $resultbool = !empty($clientPubKey) && $clientPubKey == $remotePublicKey;

                return $resultbool;
            } else {
                //if remote Client Pub Key is not passed just return this instance's Pub Key
                return $this->getPublicKey();
            }
        }

        return false;
    }

    /**
     * Get this Sync instance's public key
     *
     * @return string|NULL
     */
    public function getPublicKey()
    {
        if (t3lib_extMgm::isLoaded('caretaker_instance')) {
            return $this->getCaretakerSecurityManagerObject()->getPublicKey();
        }

        return null;
    }

    /**
     * Encode raw data to an encrypted one used for Sync's secure communication purposes
     *
     * @param mixed $rawData
     *
     * @return mixed
     */
    public function getEncrypted($rawData)
    {
        if (t3lib_extMgm::isLoaded('caretaker_instance')) {
            return $this->getCaretakerSecurityManagerObject()->encodeResult(serialize($rawData));
        }

        return $rawData;
    }

    /**
     * Decode encrypted data to an original format used for Sync's secure communication purposes
     *
     * @param string $encryptedData
     *
     * @return mixed
     */
    public function getDecrypted($encryptedData)
    {
        if (t3lib_extMgm::isLoaded('caretaker_instance')) {
            return unserialize($this->getCaretakerSecurityManagerObject()->decodeResult($encryptedData));
        }

        return $encryptedData;
    }

    /**
     * Function for setting Sync public/private keys and
     * return caretaker security manage object
     *
     * @return tx_caretakerinstance_SecurityManager|null $objCaretakerSecurityManager
     */
    public function getCaretakerSecurityManagerObject()
    {
        if (t3lib_extMgm::isLoaded('caretaker_instance')) {
            $objCaretakerSecurityManager = tx_caretakerinstance_ServiceFactory::getInstance()->getSecurityManager();

            $objCaretakerSecurityManager->setPublicKey($this->getSyncConfigManager()->getCryptoPublicKey());
            $objCaretakerSecurityManager->setPrivateKey($this->getSyncConfigManager()->getCryptoPrivateKey());
            $objCaretakerSecurityManager->setClientPublicKey($this->getSyncConfigManager()->getCryptoClientPublicKey());
            $objCaretakerSecurityManager->setClientHostAddressRestriction('');

            return $objCaretakerSecurityManager;
        }

        return null;
    }

    /**
     * ----- BEGIN: Sync Security Manager, extending Caretaker Instance functionality (mostly for error handling) -----
     *
     */

    /**
     *
     public function isSOAPSecured($remotePublicKey=NULL)
     {
     if (t3lib_extMgm::isLoaded('caretaker_instance')) {
     if (!is_null($remotePublicKey)) {
     //if remote Client Pub Key is passed
     $clientPubKey = tx_st9fissync_caretakerinstance_servicefactory::getInstance()->getSyncSecurityManager()->getClientPublicKey();
     $resultbool = !empty($clientPubKey) && $clientPubKey == $remotePublicKey;

     return $resultbool;
     } else {
     //if remote Client Pub Key is not passed just return this instance's Pub Key
     return $this->getPublicKey();
     }
     }

     return false;
     }

     public function getPublicKey()
     {
     if (t3lib_extMgm::isLoaded('caretaker_instance')) {
     return tx_st9fissync_caretakerinstance_servicefactory::getInstance()->getSyncSecurityManager()->getPublicKey();
     }

     return null;
     }

     public function validateReqResDataIntegrity($dataToValidate, $signature)
     {
     if (t3lib_extMgm::isLoaded('caretaker_instance')) {
     return tx_st9fissync_caretakerinstance_servicefactory::getInstance()->getSyncSecurityManager()->validateDataIntegrity($dataToValidate,$signature);
     }

     return false;
     }

     public function getReqResDataSignature($dataToSign)
     {
     if (t3lib_extMgm::isLoaded('caretaker_instance')) {
     return tx_st9fissync_caretakerinstance_servicefactory::getInstance()->getSyncSecurityManager()->createDataSignature($dataToSign);
     }

     return null;
     }

     public function getEncrypted($rawData)
     {
     if (t3lib_extMgm::isLoaded('caretaker_instance')) {
     return tx_st9fissync_caretakerinstance_servicefactory::getInstance()->getSyncSecurityManager()->encodeResult(serialize($rawData));
     }

     return $rawData;
     }

     public function getDecrypted($encryptedData)
     {
     if (t3lib_extMgm::isLoaded('caretaker_instance')) {
     return unserialize(tx_st9fissync_caretakerinstance_servicefactory::getInstance()->getSyncSecurityManager()->decodeResult($encryptedData));
     }

     return $encryptedData;
     }

     */

    /**
     * ----- END: Sync Security Manager, extending Caretaker Instance functionality (mostly for error handling) -----
     *
     */

    /**
     * Not used anymore
     *
     * @return tx_st9fissync_curlrequesthandler
     */
    public function getCurlRequestHandler()
    {
        throw new Exception('Not to be used by the sync!!');

        if ($this->curlRequestHandler == null) {
            $this->curlRequestHandler = t3lib_div::makeInstance('tx_st9fissync_curlrequesthandler');
        }

        return $this->curlRequestHandler;
    }

    /**
     * Not used anymore
     *
     * @param  unknown_type $postData
     * @throws Exception
     * @return mixed
     */
    public function makeCurlRequest($postData)
    {
        throw new Exception('Not to be used by the sync!!');

        try {
            if (!is_array($postData)) {
                throw new Exception ("Argument of function makeCurlRequest() is not an array");
            }
        } catch (Exception $e) {
            //
        }

        try {

            $baseURL = $this->getSyncConfigManager()->getCurlBaseURL();
            $postDataFields = http_build_query($postData);

            $curl = curl_init();
            curl_setopt ($curl, CURLOPT_URL, $baseURL.'?curlRequest=1');
            curl_setopt($curl, CURLOPT_NOBODY, true);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postDataFields);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec ($curl);

            if ($result === false) {
                throw new Exception ('Curl error: ' . curl_error($curl));
            } else {
                //
            }

            curl_close ($curl);

        } catch (Exception $e) {
            tx_st9fissync_exception::exceptionPostCatchHandler($e);
        }

        return $result;
    }

    /**
     * Serves as a debugger if configuration has been changed since last version recording
     * Also nice for monitoring and analysis purposes
     *
     * @param void
     * @return void
     */
    public function checkUpdateConfigHistory()
    {
        return $this->getSyncDBOperationsManager()->checkUpdateConfigHistory($this->getSyncConfigManager());

    }

    /**
     * Not sure if this is the optimal method to ascertain the user from which this commit of write query request initiated
     */
    public function getUserForT3Mode()
    {
        if (defined('TYPO3_PROCEED_IF_NO_USER')) {
            return 0;
        } elseif (TYPO3_MODE == 'FE') {
            return $GLOBALS["TSFE"]->fe_user->user['uid'];
        } else {
            return $GLOBALS['BE_USER']->user['uid'];
        }
    }

    /**
     *
     * A getter for the current WS
     *
     * @return mixed
     */
    public function getCurrentWorkSpace()
    {
        if (TYPO3_MODE == 'FE' || defined('TYPO3_PROCEED_IF_NO_USER')) {
            return NULL;
        }

        return $GLOBALS['BE_USER']->workspace;
    }

    /**
     * The precise request type
     * @see t3lib/config_default.php
     *
     * @return string
     */
    public function getT3Mode()
    {
        return TYPO3_REQUESTTYPE;
    }

    /**
     * Storage PID for recorded entities
     *
     * @param void
     * @return int
     */
    public function getVersioningStoragePid()
    {
        $pid = 0;
        // In the FE context, this is obviously the current page
        if (isset($GLOBALS['TSFE'])) {
            $pid = $GLOBALS['TSFE']->id;

            // In other contexts, a global variable may be set with a relevant pid
        } else {
            $pid = $this->getSyncConfigManager()->getStoragePID();
        }

        return $pid;
    }

    /**
     * Get Storage Pid for Log
     * @see getVersioningStoragePid()
     */
    public function getLoggerStoragePid()
    {
        return $this->getVersioningStoragePid();
    }

    /**
     * Get Storage Pid for Sync
     * @see getVersioningStoragePid()
     */
    public function getSyncProcessStoragePid()
    {
        return $this->getVersioningStoragePid();
    }

    /**
     * Recording request origin
     *
     * @param void
     * @return string
     */
    public function getClientIp()
    {
        // Find the IP address of the user
        $ipaddress = t3lib_div::getIndpEnv('REMOTE_ADDR');

        // If the user is connected through a proxy server, the real ip address of the user can usually be found in the HTTP_X_FORWARDED_FOR parameter.
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // This parameter is comma-seperated when multiple proxy servers are used, the first ip address in the list should be the address of the user.
            $ips = t3lib_div::trimExplode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ipaddress = $ips[0];
        }
        // Convert the IP address to an unsiged integer before inserting it in the database.
        //return 'INET_ATON("' . mysql_real_escape_string($ipaddress) . '")';
        return 'INET_ATON("' . $this->getSyncDBObject()->quoteStr($ipaddress,$this->getSyncDBOperationsManager()->getQueryVersioningTable()) . '")';
    }

    /**
     *
     * Get time with high degree of precision,
     * typically used to distinguish between 2 different actions
     * @param boolean $precision
     *
     * @return int
     */
    public function getMicroTime($precision=false)
    {
        $parts = explode(' ', microtime());
        // Timestamp with microseconds to make sure 2 versioning runs for DB actions can always be distinguished
        // even when happening very close to one another
        if ($precision) {
            $mstamp = (string) $parts[1] . (string) intval((float) $parts[0] * 10000.0);

            return $mstamp;
        } else {
            // Normal timestamp
            $tstamp = $parts[1];

            return $tstamp;
        }
    }

    /**
     * Fetches a INSERT / creation query for the current state of a particular record (uid) from a table
     * Artificially creating an INSERT query
     *
     * @see tx_st9fissync_dbversioning_t3service
     *
     * @param int         $recUid
     * @param string      $tableName
     * @param string|void $uidColName
     *
     * @return string $rootRevisionQuery
     */
    public function compileRootRevisionQuery($recUid, $tableName, $uidColName='uid')
    {
        $rootRevisionQuery = NULL;
        if ($rows = $this->getSyncDBObject()->exec_SELECTgetRows('*', $tableName, $uidColName . ' ='.intval($recUid), '', '', 1, $uidColName)) {
            $row = $rows[$recUid];
            $rootRevisionQuery = $this->getSyncDBObject()->INSERTquery($tableName, $row,true);
        }

        return $rootRevisionQuery;
    }

    /**
     * Compile an INSERT query for a DB table row
     *
     * @param array  $row
     * @param string $tableName
     */
    public function compileRootRevisionQueryForATableRow($row, $tableName)
    {
        return $this->getSyncDBObject()->INSERTquery($tableName, $row,true);

    }

    /**
     *
     * @param tx_st9fissync_dbversioning_query $queryArtifact
     * @param array                            $versionTips
     *
     * @return tx_st9fissync_dbversioning_query
     */
    public function buildQueryArtifact(tx_st9fissync_dbversioning_query $queryArtifact = NULL, $versionTips = NULL)
    {
        if (is_null($queryArtifact)) {
            $queryArtifact = t3lib_div::makeInstance('tx_st9fissync_dbversioning_query');
        }

        $queryVersioningFieldList = $this->getSyncDBOperationsManager()->getQueryVersioningFieldList();

        foreach ($queryVersioningFieldList['fieldList'] as $queryVersioningField) {
            if ($queryArtifact->offsetExists($queryVersioningField)) {
                continue;
            } else {
                switch ($queryVersioningField) {
                    case 'pid':
                        $queryArtifact->set($queryVersioningField, $this->getVersioningStoragePid());
                        break;
                    case 'sysid':
                        $queryArtifact->set($queryVersioningField, $this->getSyncConfigManager()->getSyncSystemId());
                        break;
                    case 'crdate':
                        $queryArtifact->set($queryVersioningField, $this->getMicroTime());
                        break;
                    case 'crmsec':
                        $queryArtifact->set($queryVersioningField, $this->getMicroTime(true));
                        break;
                    case 'timestamp':
                        $queryArtifact->set($queryVersioningField, $this->getMicroTime());
                        break;
                    case 'cruser_id':
                        $queryArtifact->set($queryVersioningField, $this->getUserForT3Mode());
                        break;
                    case 'updtuser_id':
                        $queryArtifact->set($queryVersioningField,  $this->getUserForT3Mode());
                        break;
                    case 'updt_typo3_mode':
                    case 'typo3_mode':
                        $queryArtifact->set($queryVersioningField, $this->getT3Mode());
                        break;
                    case 'request_url':
                        $queryArtifact->set($queryVersioningField, t3lib_div::getIndpEnv('TYPO3_REQUEST_URL'));
                        break;
                    case 'client_ip':
                        $queryArtifact->set($queryVersioningField,  $this->getClientIp());
                        break;
                    case 'workspace':
                        $currentWS = $this->getCurrentWorkSpace();
                        if (!is_null($currentWS)) {
                            $queryArtifact->set($queryVersioningField,  $currentWS);
                        }
                        break;
                }
            }
        }

        return $queryArtifact;
    }

    /**
     * Builds DB versioning query MM
     *
     * @param  tx_st9fissync_dbversioning_query_mm $queryRefRecArtifact
     * @param  array|null                          $versionTips
     * @return tx_st9fissync_dbversioning_query_mm
     */
    public function buildQueryRefRecArtifact(tx_st9fissync_dbversioning_query_mm $queryRefRecArtifact = NULL, $versionTips = NULL)
    {
        if (is_null($queryRefRecArtifact)) {
            $queryRefRecArtifact = t3lib_div::makeInstance('tx_st9fissync_dbversioning_query_mm');
        }

        $queryVersioningRefRecordFieldList = $this->getSyncDBOperationsManager()->getQueryVersioningRefRecordFieldList();

        foreach ($queryVersioningRefRecordFieldList['fieldList'] as $queryRefRecVersioningField) {

            if ($queryRefRecArtifact->offsetExists($queryRefRecVersioningField)) {
                continue;
            } else {
                switch ($queryRefRecVersioningField) {
                    //nothng that can be done from here
                }
            }
        }

        return $queryRefRecArtifact;
    }

    /**
     * Builds an query artifact for purposes of update
     *
     * @param tx_st9fissync_dbversioning_query $queryArtifact
     */
    public function updateQueryArtifact(tx_st9fissync_dbversioning_query $queryArtifact = NULL)
    {
        if (is_null($queryArtifact)) {
            $queryArtifact = t3lib_div::makeInstance('tx_st9fissync_dbversioning_query');
        }

        $queryArtifact->set('updtuser_id', $this->getUserForT3Mode());
        $queryArtifact->set('timestamp', $this->getMicroTime());
        $queryArtifact->set('updt_typo3_mode', $this->getT3Mode());

        return $queryArtifact;
    }

    /**
     * Builds versioning tips, for user queries of course
     *
     * @return array
     */
    public function buildVersionTipsArray($table, $cmd, $executedQuery, $executionTime, $resultResource, $recordRevision, $affectedRows = 0)
    {
        if ($cmd == 'exec_TRUNCATEquery') {
            //special case handling for exec_TRUNCATEquery calls
            // Retain the rows count before truncating
            $queryAffectedRows = $affectedRows;
        } else {
            $queryAffectedRows = $GLOBALS['TYPO3_DB']->sql_affected_rows();
        }

        $versionTips = array(
                'table' => $table,
                'cmd' => $cmd,
                'executedQuery' => 	$executedQuery,
                'executionTime' => $executionTime,
                'resultResource' => $resultResource,
                'recordRevision' => $recordRevision,
                'query_affectedrows' =>  $queryAffectedRows,
                'query_info' =>  $GLOBALS['TYPO3_DB']->sql_info(true,true),
                'query_error_number'  =>  $GLOBALS['TYPO3_DB']->sql_errno(),
                'query_error_message'  =>  $GLOBALS['TYPO3_DB']->sql_error(),
        );

        return $versionTips;
    }

    /**
     *
     * Builds a versioning hint/tips object,
     * this is useful for various use-case based analysis and evaluation
     * during the tracking/versioning of query objects process
     *
     * @param tx_st9fissync_versioningtips $versioningtips
     *
     * @return tx_st9fissync_versioningtips
     */
    public function buildVersionTipsObject(tx_st9fissync_versioningtips $versioningTips = NULL)
    {
        if (is_null($versioningTips)) {
            $versioningTips = t3lib_div::makeInstance('tx_st9fissync_versioningtips');
        }

        foreach ($versioningTips->getFieldList()->getArrayCopy() as $versionTipField) {
            if ($versioningTips->offsetExists($versionTipField)) {
                continue;
            } else {
                switch ($versionTipField) {
                    case 'query_affectedrows':
                        $versioningTips->set($versionTipField, $GLOBALS['TYPO3_DB']->sql_affected_rows());
                        break;
                    case 'query_info':
                        $versioningTips->set($versionTipField, $GLOBALS['TYPO3_DB']->sql_info(true,true));
                        break;
                    case 'query_error_number':
                        $versioningTips->set($versionTipField,  $GLOBALS['TYPO3_DB']->sql_errno());
                        break;
                    case 'query_error_message':
                        $versioningTips->set($versionTipField, $GLOBALS['TYPO3_DB']->sql_error());
                        break;
                }
            }
        }

        return $versioningTips;
    }

    /**
     * Builds a Sync process artifact
     *
     * @param tx_st9fissync_process $syncProcessArtifact
     *
     * @return tx_st9fissync_process
     */
    public function buildSyncProcessArtifact(tx_st9fissync_process $syncProcessArtifact = NULL, $unsetExistingOffset = FALSE)
    {
        if (is_null($syncProcessArtifact)) {
            $syncProcessArtifact = t3lib_div::makeInstance('tx_st9fissync_process');
        }

        $syncProcessFieldList = $this->getSyncDBOperationsManager()->getSyncProcessFieldList();

        foreach ($syncProcessFieldList['fieldList'] as $syncProcessField) {
            if ($syncProcessArtifact->offsetExists($syncProcessField)) {
                if ($unsetExistingOffset) {
                    $syncProcessArtifact->offsetUnset($syncProcessField);
                }
                continue;
            } else {
                switch ($syncProcessField) {
                    case 'pid':
                        $syncProcessArtifact->set($syncProcessField, $this->getVersioningStoragePid());
                        break;
                    case 'syncproc_src_sysid':
                        $syncProcessArtifact->set($syncProcessField, $this->getSyncConfigManager()->getSyncSystemId());
                        break;
                        /* case 'syncproc_dest_sysid':
                         $syncProcessArtifact->set($syncProcessField, 0); //default
                        break;
                        */
                    case 'syncproc_starttime':
                        $syncProcessArtifact->set($syncProcessField, $this->getMicroTime());
                        break;
                    case 'cruser_id':
                        $syncProcessArtifact->set($syncProcessField, $this->getUserForT3Mode());
                        break;
                    case 'typo3_mode':
                        $syncProcessArtifact->set($syncProcessField, $this->getT3Mode());
                        break;
                    case 'request_url':
                        $syncProcessArtifact->set($syncProcessField, t3lib_div::getIndpEnv('TYPO3_REQUEST_URL'));
                        break;
                    case 'client_ip':
                        $syncProcessArtifact->set($syncProcessField,  $this->getClientIp());
                        break;
                }
            }
        }

        return $syncProcessArtifact;
    }

    /**
     * Builds a Sync request details artifact
     *
     * @param tx_st9fissync_request|null $requestDetailsArtifact
     * @param boolean                    $unsetExistingOffset
     *
     * @return tx_st9fissync_request
     */
    public function buildRequestDetailsArtifact(tx_st9fissync_request $requestDetailsArtifact = NULL, $unsetExistingOffset = FALSE)
    {
        if (is_null($requestDetailsArtifact)) {
            $requestDetailsArtifact = t3lib_div::makeInstance('tx_st9fissync_request');
        }

        $syncRequestDetailsFieldList = $this->getSyncDBOperationsManager()->getSyncRequestFieldList();

        foreach ($syncRequestDetailsFieldList['fieldList'] as $syncRequestDetailsField) {
            if ($requestDetailsArtifact->offsetExists($syncRequestDetailsField)) {
                if ($unsetExistingOffset) {
                    $requestDetailsArtifact->offsetUnset($syncRequestDetailsField);
                }
                continue;
            } else {
                switch ($syncRequestDetailsField) {
                    case 'pid':
                        $requestDetailsArtifact->set($syncRequestDetailsField, $this->getVersioningStoragePid());
                        break;
                    case 'request_sent_tstamp':
                        $requestDetailsArtifact->set($syncRequestDetailsField, $this->getMicroTime());
                        break;
                    case 'sync_type':
                        $requestDetailsArtifact->set($syncRequestDetailsField, $this->syncMode);
                        break;
                    case 'request_sent':
                    case 'response_received_tstamp':
                    case 'response_received':
                    case 'procid':
                    case 'remote_handle':
                    case 'versionedqueries':
                        break;
                }
            }
        }

        return $requestDetailsArtifact;
    }

    /**
     * Builds a Sync request MM ref object
     *
     * @param tx_st9fissync_request_dbversioning_query_mm|null $requestRefQueryRecArtifact
     *
     * @return tx_st9fissync_request_dbversioning_query_mm
     */
    public function buildRequestRefQueryRecArtifact(tx_st9fissync_request_dbversioning_query_mm $requestRefQueryRecArtifact = NULL)
    {
        if (is_null($requestRefQueryRecArtifact)) {
            $requestRefQueryRecArtifact = t3lib_div::makeInstance('tx_st9fissync_request_dbversioning_query_mm');
        }

        $requestRefQueryRecFieldList = $this->getSyncDBOperationsManager()->getSyncRequestRefQVFieldList();

        foreach ($requestRefQueryRecFieldList['fieldList'] as $requestRefQueryRecField) {
            if ($requestRefQueryRecArtifact->offsetExists($requestRefQueryRecField)) {
                continue;
            } else {
                switch ($requestRefQueryRecField) {
                    case 'tablenames':
                        $requestRefQueryRecArtifact->set($requestRefQueryRecField, $this->getSyncDBOperationsManager()->getQueryVersioningTable());
                        break;
                }
            }
        }

        return $requestRefQueryRecArtifact;
    }

    /**
     * Builds a Sync request handler artifact
     *
     * @param tx_st9fissync_request_handler|null $syncProcessArtifact
     * @param boolean                            $unsetExistingOffset
     *
     * @return tx_st9fissync_request_handler
     */
    public function buildSyncRequestHandlerArtifact(tx_st9fissync_request_handler $syncRequestHandlerArtifact = NULL, $unsetExistingOffset = FALSE)
    {
        if (is_null($syncRequestHandlerArtifact)) {
            $syncRequestHandlerArtifact = t3lib_div::makeInstance('tx_st9fissync_request_handler');
        }

        $syncRequestHandlerFieldList = $this->getSyncDBOperationsManager()->getSyncRequestHandlerFieldList();

        foreach ($syncRequestHandlerFieldList['fieldList'] as $syncRequestHandlerField) {
            if ($syncRequestHandlerArtifact->offsetExists($syncRequestHandlerField)) {
                if ($unsetExistingOffset) {
                    $syncRequestHandlerArtifact->offsetUnset($syncRequestHandlerField);
                }
                continue;
            } else {
                switch ($syncRequestHandlerField) {
                    case 'pid':
                        $syncRequestHandlerArtifact->set($syncRequestHandlerField, $this->getVersioningStoragePid());
                        break;
                    case 'request_received_tstamp':
                        $syncRequestHandlerArtifact->set($syncRequestHandlerField, $this->getMicroTime());
                        break;
                }
            }
        }

        return $syncRequestHandlerArtifact;
    }

    /**
     * Removes specifically values for SOAP XAML data keys
     *
     * @param  string                  $xml
     * @throws tx_st9fissync_exception
     *
     * @return string $croppedXML
     */
    public function truncateXMLTagDataContent(&$xml)
    {
        $croppedXML = '';
        $len = strlen($xml);
        if ($len > 0) {
            $pattern = "%<key xsi:type=\"xsd:string\">data</key><value xsi:type=\"xsd:string\">.*?</value>%i";
            $replacement = "<key xsi:type=\"xsd:string\">data</key><value xsi:type=\"xsd:string\">......</value>";

            $syncPCREBackTrackLimit = $this->getSyncConfigManager()->getSyncPCREBackTrackLimit();

            if ($syncPCREBackTrackLimit) {
                //save original
                $this->origPCREBackTrackLimit = ini_get('pcre.backtrack_limit');
                //set
                ini_set('pcre.backtrack_limit', $syncPCREBackTrackLimit);
            }

            //preg replace
            $croppedXML = preg_replace($pattern, $replacement, $xml);

            if ($this->origPCREBackTrackLimit) {
                //reset
                ini_set('pcre.backtrack_limit', $this->origPCREBackTrackLimit);
            }
        } else {
            throw new tx_st9fissync_exception('Invalid XML! Nothing to truncate');
        }

        return $croppedXML;
    }

    /**
     * DAM index files
     *
     * @param array $fileList
     *
     * @return mixed $indexedMap
     */
    public function indexFiles($fileList)
    {
        global $BACK_PATH, $LANG, $TYPO3_CONF_VARS;
        $indexedMap = null;
        $_temp_TYPO3_DB = $GLOBALS['TYPO3_DB'];
        try {
            $this->getSyncDBObject()->enableSequencer();
            $GLOBALS['TYPO3_DB'] = $this->getSyncDBObject();

            require_once(PATH_txdam.'lib/class.tx_dam_indexing.php');
            $index = t3lib_div::makeInstance('tx_dam_indexing');
            $index->init();
            $index->enableReindexing(2);
            $index->initEnabledRules();
            $index->setRunType('auto');
            $indexedMap =  $index->indexFiles($fileList, tx_dam_db::getPid());

            $this->getSyncDBObject()->disableSequencer();

            //restore original TYPO3_DB globals
            $GLOBALS['TYPO3_DB']= $_temp_TYPO3_DB ;
        } catch (tx_st9fissync_exception $indexException) {
            $this->getSyncDBObject()->disableSequencer();
            if ($_temp_TYPO3_DB != null && is_object($_temp_TYPO3_DB)) {
                $GLOBALS['TYPO3_DB'] = $_temp_TYPO3_DB;
            }
        }

        return $indexedMap;
    }

    /**
     * Patch fix for:
     *  PHP Fatal error:  Call to a member function enableFields() on a non-object in
     *  /var/www/typo3/iris/typo3conf/ext/dam/lib/class.tx_dam_db.php on line 1650, referer:...
     *
     *  Use-case: when fe logging in process is involved, as fe_users will surely trigger a DAM usage check
     *  even before TSFE object is fully created (objects such as $GLOBALS['TSFE']->sys_page) or put to use.
     *
     */
    public function loadTableTCAForDAMFEMode($tableName)
    {
        if (TYPO3_MODE === 'FE' && is_object($GLOBALS['TSFE'])) {
            if (!is_object($GLOBALS['TSFE']->sys_page)) {
                $GLOBALS['TSFE']->sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
            }
            if (!is_array($TCA)) {
                $GLOBALS['TSFE']->includeTCA();
            }
            t3lib_div::loadTCA($this->getSyncDBOperationsManager()->getDAMMainTableName());
            t3lib_div::loadTCA($tableName);
        }
    }

    /**
     * Get DAM array for sys_refindex file ref
     *
     * @param string $sysRefTableName
     * @param int    $sysRefRecUID
     * @param int    $sysRefDAMIndexRefUID
     * @param string $sysRefIdentSoftRefKeyVal
     *
     * @return array
     */
    public function getSysIndexReferencedFiles($sysRefRefTableName, $sysRefRecUID, $sysRefDAMIndexRefUID, $sysRefIdentSoftRefKeyVal)
    {
        $this->loadTableTCAForDAMFEMode($sysRefRefTableName);

        $files = array();
        $rows  = array();

        $res = $this->getSyncDBOperationsManager()->getDAMSysIndexRefRes($sysRefRefTableName,
                $sysRefRecUID,
                $sysRefDAMIndexRefUID,
                $sysRefIdentSoftRefKeyVal);

        if ($res) {
            while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
                $files[$row['uid']] = $row['file_path'].$row['file_name'];
                $rows[$row['uid']] = $row;
            }
        }

        return array('files' => $files, 'rows' => $rows);

    }

    /**
     * Cast an object to another class, keeping the properties, but changing the methods
     *
     * @param  string $class  Class name
     * @param  object $object
     * @return object
     */
    public function castToClass($class, $object)
    {
        return unserialize(preg_replace('/^O:\d+:"[^"]++"/', 'O:' . strlen($class) . ':"' . $class . '"', serialize($object)));
    }

    /**
     * Replace <br> tags in string with new line for textual
     *
     * @param string $string
     */
    public function br2nl($string)
    {
        return preg_replace('#<br\s*?/?>#i', "\n", $string);
    }

    /**
     * Replace &lt; tags in string with < for textual
     *
     * @param string $string
     */
    public function lt2lt($string)
    {
        return preg_replace('#&lt;\s*#i', "<", $string);
    }

    /**
     * Replace &gt; tags in string with > for textual
     *
     * @param string $string
     */
    public function gt2gt($string)
    {
        return preg_replace('#\s*&gt;#i', ">", $string);
    }

    /**
     * Clears RealURL cache tables
     */
    public function clearRealURLCache()
    {
        $this->getSyncDBObject()->exec_TRUNCATEquery('tx_realurl_chashcache');
        $this->getSyncDBObject()->exec_TRUNCATEquery('tx_realurl_pathcache');
        $this->getSyncDBObject()->exec_TRUNCATEquery('tx_realurl_uniqalias');
        $this->getSyncDBObject()->exec_TRUNCATEquery('tx_realurl_urldecodecache');
        $this->getSyncDBObject()->exec_TRUNCATEquery('tx_realurl_urlencodecache');
    }

    /**
     * Clears t3 caches
     *
     * @param string $cacheCmd
     */
    public function clearT3Cache($cacheCmd)
    {
        try {
            $_temp_TYPO3_DB = $GLOBALS['TYPO3_DB'];
            $GLOBALS['TYPO3_DB'] = $this->getSyncDBObject();
            $TceMain = t3lib_div::makeInstance('t3lib_TCEmain');
            $TceMain->stripslashes_values = 0;
            $TceMain->start(Array(),Array());
            $TceMain->clear_cacheCmd($cacheCmd);
            //restore original TYPO3_DB globals
            $GLOBALS['TYPO3_DB']= $_temp_TYPO3_DB ;
            unset($TceMain);
        } catch (Exception $ex) {
            if ($_temp_TYPO3_DB != null && is_object($_temp_TYPO3_DB)) {
                $GLOBALS['TYPO3_DB'] = $_temp_TYPO3_DB;
            }
        }
    }

    /**
     * Purges caches from all T3 + RealURL tables
     *
     * @param array $cmdArray
     */
    public function purgeVariousCaches($cmdArray = array())
    {
        $results = array();
        try {
            switch ($cmdArray['subject']) {
                case 't3':
                    switch ($cmdArray['subject']['cmd']) {
                        case 'all':
                        case 'pages':
                            $this->clearT3Cache($cmdArray['subject']['cmd']);
                            break;
                        default:
                            $this->clearT3Cache('all');
                            break;
                    }
                    break;
                case 'realurl':
                    $this->clearRealURLCache();
                    break;
                default:
                    $cmdArray['subject'] = array('t3' => array('cmd' => 'all'), 'realurl' => true);
                    $this->clearT3Cache('all');
                    $this->clearRealURLCache();
                    break;
            }
        } catch (Exception $syncex) {
            //
        }

        return $cmdArray;
    }

    /**
     * Notification: message body composed out of flash message queue
     *
     */
    public function notify()
    {
        $messages = tx_st9fissync_messagequeue::renderFlashMessages($this->getSyncConfigManager()->getSyncEmailMessageSeverity());
        $messages = trim($messages);
        if (strlen($messages) > 0 ) {
            $messageBody = $this->gt2gt($this->lt2lt($this->br2nl($messages)));

            // Process email template
            $templateFileContent = $this->getEmailTemplateFileContent('sync_email.html');
            if ($templateFileContent != '') {
                $message = $this->processEmailTemplateContent($templateFileContent, array('###SYNCHRONIZATION_PROCESS_MESSAGES###'=>$messageBody));
            } else {
                $message = $messageBody;
            }

            $subject = $this->getSyncConfigManager()->getSyncNotificationEMailSubject();
            $recipients = $this->getSyncConfigManager()->getSyncNotificationEMail();
            $senderEmail = $this->getSyncConfigManager()->getSyncSenderEMail();
            foreach ($recipients as $recipient) {
                tx_st9fisutility_base::sendNotificationEmail($recipient, $subject, $message, $senderEmail);
            }
        }
    }

    /**
     * Makes or ascertains if a folder of the format -- {$folderPathRoot}/{year}/{month}/{day}
     * based on the following arguments exists or if possible can/should be created
     *
     * @param string    $folderPathRoot
     * @param long|null $timestamp
     *
     * @return string|boolean $fullFolderPathExists
     * returns false if the desired folder could not be created else returns the full directory path
     */
    public function makeFolderPathForTimeStamp($folderPathRoot , $timestamp = null)
    {
        $fullFolderPathExists = false;

        if (!t3lib_div::isAbsPath($folderPathRoot)) {
            $folderPathRoot = PATH_site . $folderPathRoot;
        }

        if ($timestamp) {
            $dateSpecificFolderPath = getdate($timestamp);
        } else {
            $dateSpecificFolderPath = getdate();
        }
        $fullFolderPath =   tx_dam::path_makeClean($folderPathRoot . DIRECTORY_SEPARATOR .
                $dateSpecificFolderPath['year'] . DIRECTORY_SEPARATOR .
                $dateSpecificFolderPath['mon'] . DIRECTORY_SEPARATOR .
                $dateSpecificFolderPath['mday'] . DIRECTORY_SEPARATOR);

        if (!is_dir($fullFolderPath)) {
            if (mkdir($fullFolderPath, octdec($GLOBALS['TYPO3_CONF_VARS']['BE']['folderCreateMask']), true)) {
                t3lib_div::fixPermissions($fullFolderPath);
                $fullFolderPathExists = $fullFolderPath;
            }
        } else {
            $fullFolderPathExists = $fullFolderPath;
        }

        return $fullFolderPathExists;
    }

    /**
     * Checks if a path exists and if yes make a full file path for a $filename in the $folderPath
     *
     * @param string $folderPath
     * @param string $fileName
     */
    public function makeFullFilePath($folderPath, $fileName)
    {
        $fullPath = false;
        if (is_dir($folderPath)) {
            $fullPath =  tx_dam::path_makeClean($folderPath) . $fileName;
        }

        return $fullPath;
    }

    public function doTableRowBackUp($tableName, $whereClause, $filePath)
    {
        /**
         * sample mysqldump command -
         * mysqldump --skip-add-drop-table -c -t  -w"UID IN (1,2)"
         * -usomemysqluser -psomemysqlusrpwd -hsomemysqlhost somedbname sometablename > somesql3.sql
         */
        $backUpCmd = $this->getSyncConfigManager()->getGCBackupCommand();
        $backUpCmd .= ' --skip-add-drop-table';
        $backUpCmd .= ' -c';
        //	$backUpCmd .= ' -v';
        $backUpCmd .= ' -t';
        $backUpCmd .= ' -w"' . stripslashes($whereClause) .'"';
        $backUpCmd .= ' -u' . TYPO3_db_username;
        $backUpCmd .= ' -p' . TYPO3_db_password;
        $backUpCmd .= ' -h' . TYPO3_db_host;
        $backUpCmd .= ' ' . TYPO3_db;
        $backUpCmd .= ' ' . $tableName;
        $backUpCmd .= ' > ' . $filePath;

        t3lib_utility_Command::exec($backUpCmd, $output, $returnValue);

        return  array('cmd' => $backUpCmd, 'status' => $returnValue == 0 ? true : false);;

    }

    /**
     * Method initalizes the error context that the SOAPServer enviroment will run in.
     *
     * @var Object $handlerSvObj
     * @var string $syncErrorHandler
     * @var string $syncFatalErrorHandler
     *
     * @return boolean display_errors original value
     */
    public function initializeSyncErrorContext($handlerSvObj, $syncErrorHandler, $syncFatalErrorHandler)
    {
        $this->displayErrorsOriginalState = ini_get('display_errors');
        ini_set('display_errors', false);

        if (is_object($handlerSvObj)) {

            if (method_exists($handlerSvObj, $syncErrorHandler)) {
                set_error_handler(array($handlerSvObj, $syncErrorHandler), E_USER_ERROR);
            }
            if (method_exists($handlerSvObj, $syncFatalErrorHandler)) {
                register_shutdown_function(array($handlerSvObj, $syncFatalErrorHandler));
            }
        }

        return $this->displayErrorsOriginalState;
    }

    /**
     * Generate a Sync fault
     *
     * If an exception is passed as the first argument, its message and code
     * will be used to create the fault object
     *
     * @param string|Exception $fault
     * @param string           $code  Sync Fault Codes
     *
     * @return tx_st9fissync_exception
     */
    public function syncFault($fault = null, $code = null)
    {
        if ($fault instanceof Exception) {
            $class = get_class($fault);
            $message = $fault->getMessage();
            $eCode   = $fault->getCode();
            $code    = empty($eCode) ? $code : $eCode;

        } elseif (is_string($fault)) {
            $message = $fault;
        } else {
            $message = 'Unknown Sync Error';
        }

        return new tx_st9fissync_exception($message, $code);
    }

    /**
     * Throw PHP errors for various workflows in Sync
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param int    $errline
     * @param array  $errcontext
     *
     * @return tx_st9fissync_exception
     */
    public function handlePhpErrors($errno, $errstr, $errfile = null, $errline = null, array $errcontext = null)
    {
        if($errfile)
            $errstr .= ' / in: ' . $errfile;

        if($errline)
            $errstr .= ' / on line #: ' . $errline;

        return $this->syncFault($errstr);
    }

    /**
     * on every shutdown called to detect any fatal error termination
     *
     * @return tx_st9fissync_exception
     *
     */
    public function handleFatalError()
    {
        $last_error = error_get_last();
        if ($last_error['type'] === E_ERROR) {
            // fatal error
            return $this->handlePhpErrors(E_ERROR, $last_error['message'], $last_error['file'], $last_error['line']);
        }
    }

    /**
     * Reset Error content
     *
     * @param void
     * @return void
     */
    public function resetErrorContext()
    {
        // Restore original error handler
        if ($this->displayErrorsOriginalState != null) {
            restore_error_handler();
            ini_set('display_errors', $this->displayErrorsOriginalState);
        }
    }

    /**
     * Makes a printable array
     *
     * @param array  $array
     * @param string $delimiter
     */
    public function printArrayElementsByDelimiter(array $array , $delimiter = ',')
    {
        return preg_replace('/\n/', $delimiter . ' ', trim(preg_replace('/\)$/', '', preg_replace('/^\(/' ,'',preg_replace('/Array[\r\n]/','', print_r($array, TRUE))))));
    }

    /**
     * Function for getting templates file content
     *
     * @param  string $templateFileName
     * @param  string $templatePath
     * @return string
     */
    public function getEmailTemplateFileContent($templateFileName, $templatePath="")
    {
        $fileContent = "";

        if (trim($templateFileName) != '') {
            if (trim($templatePath) == "") {
                $templatePath = t3lib_extMgm::extPath(ST9FISSYNC_EXTKEY) . "res/templates/";
            }

            // File path
            $filePath = rtrim($templatePath, "/") ."/". $templateFileName;

            // Checking file exists/read file content
            if (file_exists($filePath)) {
                $handle = fopen($filePath, "r");
                $fileContent = fread($handle, filesize($filePath));
                fclose($handle);
            }
        }

        return $fileContent;
    }

    /**
     * Function for processing the email template content
     *
     * @param  string $templateFileContent
     * @param  array  $arrContent
     * @return string
     */
    public function processEmailTemplateContent($templateFileContent, $arrContent=array())
    {
        if ($arrContent && is_array($arrContent) && count($arrContent) > 0) {
            foreach ($arrContent as $key=>$val) {
                $templateFileContent = str_replace($key, $val, $templateFileContent);
            }
        }

        return $templateFileContent;
    }

    /**
     *
     * @return tx_st9fissync_gc
     */
    public function getGCProcessHandle()
    {
        if ($this->gcProcessHandle == null) {
            $this->gcProcessHandle = t3lib_div::makeInstance('tx_st9fissync_gc');
        }

        return $this->gcProcessHandle;
    }

    /**
     * Function for checking table for backup
     * @param $tablename
     * @return boolean
     */
    public function checkTableForBackup($tablename)
    {
        $arrBackupTableNames = $this->getSyncConfigManager()->getGCBackupTableList();
        if ($arrBackupTableNames && is_array($arrBackupTableNames) && count($arrBackupTableNames) > 0 && trim($tablename) != '') {
            if (in_array($tablename,$arrBackupTableNames)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Function for getting table backup
     * @param $arrparam [tablename, gcprocid, backuptime, whereclause, filenumber]
     * @return boolean|array
     */
    public function doTableBackup($arrparam)
    {
        $arrTableBackupResult = false;
        if ($this->checkTableForBackup(trim($arrparam['tablename']))) {
            $folderPath = $this->makeFolderPathForTimeStamp($this->getSyncConfigManager()->getGCBackupFolderRoot(), $arrparam['backuptime']);
            if ($folderPath) {
                $filePath = $this->makeFullFilePath($folderPath, 'gc' . $arrparam['gcprocid'] .'-'. $arrparam['backuptime'] . '-' . $arrparam['tablename'] . '-' . $arrparam['filenumber'] . '.sql');
            }
            if ($filePath) {
                $arrTableBackupResult = $this->doTableRowBackUp(trim($arrparam['tablename']), trim($arrparam['whereclause']), $filePath);
                $arrTableBackupResult['backupFolderPath'] = $folderPath;
            }
        } else {
            $arrTableBackupResult = true; // table not in the backup list
        }

        return $arrTableBackupResult;
    }

    /**
     * Function for deleting the sync records based whereclause
     * @param $tablename
     * @param $whereclause
     * @return array
     */
    public function deleteRecordsFromTable($tablename, $whereclause)
    {
        $deleteRecords = false;
        $tablename = trim($tablename);
        $whereclause = trim($whereclause);

        if ($tablename != '' && $whereclause != '') {
            $deleteRecords = $this->getSyncDBOperationsManager()->deleteRecordsFromTable($tablename, $whereclause);
        }

        return $deleteRecords;
    }

    /**
     * Function for checking table size for Garbage collector
     *
     * @return boolean
     */
    public function checkGCTableSize()
    {
        // Getting the max table size
        $maxSize = trim($this->getSyncConfigManager()->getGCDesirableMaxSize());

        if ($maxSize != '' && $maxSize > 0) {
            // Get versioning table size
            $tableSize = $this->getTableSize($this->getSyncDBOperationsManager()->getQueryVersioningTable());

            // Checking if max allowed table size is less then table size
            if ($tableSize < $maxSize) {
                return false;
            }
        }

        return true;
    }

    /**
     * Function for getting table size
     * @param  string $tablename
     * @return int    $tablesize
     */
    public function getTableSize($tablename)
    {
        $tableSize = 0;
        if (trim($tablename) != '') {
            $tableSize = $this->getSyncDBOperationsManager()->getSpaceUsageInBytesForTable($tablename);
        }

        return $tableSize;
    }

    /**
     * Function for sending email
     * @param $arrData [message, subject, recipients, senderEmail, templatefile (optional), templatefilepath (optional)]
     */
    public function sendEmail($arrData)
    {
        if (trim($arrData['message']) != '' && trim($arrData['subject']) != '' && trim($arrData['senderEmail']) != '' && is_array($arrData['recipients']) && count($arrData['recipients']) > 0) {
            $messageBody = $this->gt2gt($this->lt2lt($this->br2nl($arrData['message'])));

            // Process email template
            $templateFileContent = '';
            if (trim($arrData['templatefile']) != '') {
                $templateFileContent = $this->getEmailTemplateFileContent(trim($arrData['templatefile']), trim($arrData['templatefilepath']));
            }

            if ($templateFileContent != '') {
                $message = $this->processEmailTemplateContent($templateFileContent, array('###MESSAGE_BODY###'=>$messageBody));
            } else {
                $message = $messageBody;
            }

            $recipients = $arrData['recipients'];
            foreach ($recipients as $recipient) {
                tx_st9fisutility_base::sendNotificationEmail($recipient, trim($arrData['subject']), $message, trim($arrData['senderEmail']));
            }
        }
    }

    /**
     * Used for debugging to files
     *
     * @param  mixed  $message
     * @return number
     */
    public function debugToFile($message)
    {
        $fileLog = ST9FISSYNC_LOGSFOLDER . 'sync.log';

        return file_put_contents($fileLog,
                "\n---BEGIN @" . date($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] . ' ' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'] . ': ')
                . "---\n" . print_r($message, true) . "\n--END--\n",
                FILE_APPEND);
    }

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/st9fissync/lib/class.tx_st9fissync.php']) {
    include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/st9fissync/lib/class.tx_stfissync.php']);
}

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
 * Config manager tx_st9fissync.
 *
 *
 * @author	André Spindler <info@studioneun.de>
* @package	TYPO3
* @subpackage	tx_st9fissync
*/

class tx_st9fissync_config extends tx_lib_object
{
    /**
     * Tables at least considered to be evaluated by verioning/recording system
     * the postive list
     *
     * All tables not in the positive or the negative list
     * are recorded but not evaluated for sync scheduling
     *
     * @param boolean $asArray
     *
     * @return Ambigous <mixed, multitype:>
     */
    public function getVersionEnabledTablesList($asArray=TRUE)
    {
        if($asArray)

            return t3lib_div::trimExplode(',',$this->get('dbversioning_tables'));
        return $this->get('dbversioning_tables');
    }

    /**
     *
     * Tables not at all considered to be evaluated by verioning/recording system
     * the  negative list
     * @param boolean $asArray
     *
     * @return Ambigous <mixed, multitype:>
     */
    public function getVersionDisabledTablesList($asArray=TRUE)
    {
        if($asArray)

            return t3lib_div::trimExplode(',',$this->get('dbversioning_ignore_tables'));
        return $this->get('dbversioning_ignore_tables');
    }

    /**
     * UID of the branch roots to be included for sync
     * negative list
     *
     * @param  boolean  $asArray
     * @return Ambigous <mixed, multitype:>
     */
    public function getVersionEnabledRootPidList($asArray=TRUE)
    {
        if($asArray)

            return t3lib_div::trimExplode(',',$this->get('dbversioning_root'));
        return $this->get('dbversioning_root');
    }

    /**
     * UID of the branch roots to be excluded from sync scheduling
     * negative list
     *
     * @param  boolean  $asArray
     * @return Ambigous <mixed, multitype:>
     */
    public function getVersionExcludePidList($asArray=TRUE)
    {
        if($asArray)

            return t3lib_div::trimExplode(',',$this->get('dbversioning_excludePids'));
        return $this->get('dbversioning_excludePids');
    }

    /**
     * Sequencer enabled tables list
     *
     * @param boolean $asArray
     *
     * @return Ambigous <mixed, multitype:>
     */
    public function getSequencerEnabledTablesList($asArray=TRUE)
    {
        if($asArray)

            return t3lib_div::trimExplode(',',$this->get('dbsequencer_tables'));
        return $this->get('dbsequencer_tables');
    }

    /**
     * Number of systems enabled for sync versioning/sequencing
     *
     * @return number
     */
    public function getSequencerOffSet()
    {
        return intval($this->get('dbsequencer_offset'));
    }

    /**
     * @see getSystemId()
     */
    public function getSequencerSystemId()
    {
        return intval($this->get('dbsequencer_system'));
    }

    /**
     * @see getSystemId()
     */
    public function getSyncSystemId()
    {
        return intval($this->get('dbsequencer_system'));
    }

    /**
     * @see getSystemId()
     */
    public function getLogSystemId()
    {
        return intval($this->get('dbsequencer_system'));
    }

    /**
     *
     * Identifier of the T3 instance w.r.t sync versioning/recording/sequencing
     *
     *  @return int
     */
    public function getSystemId()
    {
        return intval($this->get('dbsequencer_system'));
    }

    /**
     * If a TCA representation is built for all the query types and entities
     *
     * @return number
     */
    public function getStoragePID()
    {
        return intval($this->get('dbversioning_storagepid'));
    }

    /**
     * Tables list which do not need to be logged for
     *
     * @param  boolean  $asArray
     * @return Ambigous <mixed, multitype:>
     */
    public function getLoggerDisabledTablesList($asArray=TRUE)
    {
        if($asArray)

            return t3lib_div::trimExplode(',',$this->get('dblogger_ignore_tables'));
        return $this->get('dblogger_ignore_tables');
    }

    /**
     * Global logger priority settings for the synchronization module
     * Set appropriately as per the environment
     *
     * @return int
     */
    public function getSyncLoggerPriority()
    {
        return intval($this->get('dblogger_log_priority'));
    }

    /**
     * Get 'curl' services URL
     * Not used anymore
     *
     * @return string
     */
    public function getCurlBaseURL()
    {
        throw new Exception('Not to be used by the sync!!');

        return "http://typo3.local/iris/typo3conf/ext/st9fissync/mod_sync/";
    }

    /**
     * URL for the second T3 instance
     * @return string
     */
    public function getRemoteURL()
    {
        return $this->get('sync_remote_url');
    }

    /**
     * BE user details for a designated sync user on the remote server
     * @return string
     */
    public function getRemoteSyncBEUser()
    {
        return $this->get('sync_remote_beuser');
    }

    /**
     * BE password details for the designated sync user on the remote server
     * @return string
     */
    public function getRemoteSyncBEPassword()
    {
        return $this->get('sync_remote_bepwd');
    }

    /**
     * Remote HTTP Login
     * @return string
     */
    public function getRemoteHttpLogin()
    {
        return $this->get('sync_remote_httplogin');
    }

    /**
     * Remote HTTP Password
     * @return string
     */
    public function getRemoteHttpPassword()
    {
        return $this->get('sync_remote_httppassword');
    }

    /**
     * Batch size for the number of queries to be sent for execution
     * per SOAP request to the remote instance
     * Set more for greater speeds of sync
     *
     * @return int
     */
    public function getSyncQuerySetBatchSize()
    {
        return intval($this->get('sync_query_batchsize'));
    }

    /**
     * Total payload size (in bytes) for file transfer
     * per SOAP request to the remote instance
     * Set more for greater speeds of sync,
     * but be carefull of memory limits!!
     *
     * @return int
     */
    public function getSyncFileSetMaxSize()
    {
        //return 1048576;
        return intval($this->get('sync_fileset_maxsize'));
    }

    /**
     * Sync Notification E-Mail subject
     *
     * @return string
     */
    public function getSyncNotificationEMailSubject()
    {
        return $this->get('sync_notification_email_subject');
    }

    /**
     * Sync notification E-Mail subject
     *
     * @return string
     */
    public function getSyncNotificationEMail()
    {
        return explode(',', $this->get('sync_notification_email'));
    }

    /**
     * Sync Sender E-Mail
     *
     * @return string
     */
    public function getSyncSenderEMail()
    {
        return $this->get('sync_notification_sender_email');
    }

    /**
     * Synchronization process E-Mail message level
     *
     * @return int | null
     */
    public function getSyncEmailMessageSeverity()
    {
        return $this->getSyncConfigValIntOrNull('sync_notification_email_message_level');
    }

    /**
     * Sync test email address
     * @return array
     */
    public function getSyncTestEMail()
    {
        return explode(',', $this->get('sync_test_email'));
    }

    /**
     * Sync test email sender email address
     * @return string
     */
    public function getSyncTestEmailSenderEMail()
    {
        return $this->get('sync_test_email_sender_email');
    }

    /**
     * Sync test email subject
     * @return string
     */
    public function getSyncTestEmailSubject()
    {
        return $this->get('sync_test_email_subject');
    }

    /**
     * Sync socket time out
     * If defined, overrides PHP runtime configuration directive 'default_socket_timeout'
     *
     * @return int|null
     */
    public function getSyncSocketTimeOut()
    {
        return $this->getSyncConfigValIntOrNull('default_socket_timeout');
    }

    /**
     *
     * If defined, overrides PHP runtime configuration directive 'max_execution_time'
     *
     * @return int|null
     */
    public function getSyncMaxExecTime()
    {
        return $this->getSyncConfigValIntOrNull('max_execution_time');
    }

    /**
     *
     * If defined, overrides PHP runtime configuration directive 'memory_limit'
     *
     * @return int|null
     */
    public function getSyncMemoryLimit()
    {
        return $this->getSyncConfigValIntOrNull('memory_limit');
    }

    /**
     *
     * If defined, overrides PHP runtime configuration directive 'soap.wsdl_cache_enabled'
     *
     * @return int|null
     */
    public function getSyncT3SoapWSDLCacheEnabled()
    {
        return $this->getSyncConfigValIntOrNull('soap_wsdl_cache_enabled');
    }

    /**
     *
     * If defined, overrides PHP runtime configuration directive 'soap.wsdl_cache_ttl'
     *
     * @return int|null
     */
    public function getSyncT3SoapWSDLCacheTTL()
    {
        return $this->getSyncConfigValIntOrNull('soap_wsdl_cache_ttl');
    }

    /**
     *
     * If defined, overrides PHP runtime configuration directive 'soap.wsdl_cache'
     *
     * @return int|null
     */
    public function getSyncT3SoapWSDLCacheType()
    {
        return $this->getSyncConfigValIntOrNull('soap_wsdl_cache');
    }

    /**
     *
     * If defined, overrides PHP runtime configuration directive 'soap.wsdl_cache_limit'
     *
     * @return int|null
     */
    public function getSyncT3SoapWSDLCacheLimit()
    {
        return $this->getSyncConfigValIntOrNull('soap_wsdl_cache_limit');
    }

    /**
     *
     * PCRE's backtracking limit. Defaults to 100000 for PHP < 5.3.7.
     *
     * @link http://php.net/manual/en/pcre.configuration.php
     *
     * @return int|null
     */
    public function getSyncPCREBackTrackLimit()
    {
        return $this->getSyncConfigValIntOrNull('pcre_backtrack_limit');
    }

    /**
     * Generic utility to handle empty or set config var value
     *
     * @param string $configVarKey
     *
     * @return int|null
     */
    private function getSyncConfigValIntOrNull($configVarKey)
    {
        $configVarVal = $this->get($configVarKey);
        if ($configVarVal === null || $configVarVal == '') {
            return null;
        }

        return intval($configVarVal);
    }

    /**
     * Sync crypto public key for this instance
     * @return string
     */
    public function getCryptoPublicKey()
    {
        return $this->get('crypto_instance_publicKey');
    }

    /**
     * Sync crypto private key for this instance
     * @return string
     */
    public function getCryptoPrivateKey()
    {
        return $this->get('crypto_instance_privateKey');
    }

    /**
     * Sync crypto client public key
     * @return string
     */
    public function getCryptoClientPublicKey()
    {
        return $this->get('crypto_client_publicKey');
    }

    /**
     * Get GC desirable max size
     * @return int|null
     */
    public function getGCDesirableMaxSize()
    {
        return $this->get('gc_desirable_max_size');
    }

    /**
     * Get GC older than days
     * @return int
     */
    public function getGCOlderThanDays()
    {
        return $this->get('gc_olderthan_days');
    }

    /**
     * Tables to get backup before deleting records
     * @param boolean $asArray
     *
     * @return array|string|null
     */
    public function getGCBackupTableList($asArray=TRUE)
    {
        if ($asArray) {
            return t3lib_div::trimExplode(',',$this->get('gc_tablelist_dobackup'));
        }

        return $this->get('gc_tablelist_dobackup');
    }

    public function getGCBackupFolderRoot()
    {
        return $this->get('gc_backup_folderroot');
    }

    public function getGCBackupCommand()
    {
        return $this->get('gc_backup_cmd');
    }

    public function getGCTableRowNumPerFile()
    {
        return $this->get('gc_backup_num_tablerows_per_file');
    }

    /**
     * Used for comparing with another configuration object
     *
     * @param  string $thatConfig
     * @return number
     */
    public function compareTo($thatConfig)
    {
        $thisConfig = serialize($this->getArrayCopy());
        if(count($thatConfig) == 0 || (count($thatConfig) > 0 && $thatConfig['config'] != $thisConfig))

            return 1;

        return 0; // no difference
    }

}

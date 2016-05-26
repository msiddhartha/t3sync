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
 * Module 'Synchronization service' for the 'st9fissync' extension.
*
* @author	André Spindler <sp@studioneun.de>
* @package	TYPO3
* @subpackage	st9fissync
*/

// if script is called directly
if (!defined ('TYPO3_MODE')) {
    unset($MCONF);
    require_once 'conf.php';

    // these definitions are needed to avoid the init script to exit
    define('TYPO3_PROCEED_IF_NO_USER', true);
    require_once($BACK_PATH.'init.php');
    require_once(PATH_t3lib.'class.t3lib_scbase.php');
    require_once($BACK_PATH.'template.php');
    $LANG->includeLLFile('EXT:st9fissync/mod_sync/locallang.xml');
}

if (!defined ('TYPO3_MODE')) {
    die ('Access denied.');
}
// DEFAULT initialization of a module [END]

//do an abstract class for both the mod_sync_* classes
//common stuff like _GP vars etc.
class  tx_st9fissync_mod_sync_service extends t3lib_SCbase
{
    /**
     * Instance of the SOAP server
     *
     * @var tx_st9fissync_t3soap_server
     */
    protected $SOAP_Server;

    /**
     *
     * @var string
     */
    private $SOAP_Server_System = 'tx_st9fissync_t3soap_server_system_calls';

    /**
     * Initializes the Module
     *
     * @return void
     */
    public function init()
    {
        tx_st9fissync::getInstance()->setStageSync();

     //ini_set("soap.wsdl_cache_enabled", 0);
        tx_st9fissync::getInstance()->setSyncT3SOAPWSDLCacheEnable();

     //$this->origEnvironMemLimit = ini_get('memory_limit');
     //ini_set('memory_limit', '-1');
     tx_st9fissync::getInstance()->setSyncRuntimeMemoryLimit();

     //$this->origEnvironSocketTimeout = ini_get('default_socket_timeout');
     //ini_set('default_socket_timeout', 6000);

     tx_st9fissync_t3soap_application::init(ST9FISSYNC_EXTKEY, $GLOBALS['MCONF']);
     tx_st9fissync_t3soap_application::setAuthenticationObject(t3lib_div::makeInstance('tx_st9fissync_t3soap_auth',($GLOBALS['BE_USER'])));

     $wsdl = t3lib_div::_GP('wsdl');
     $securedRequest = t3lib_div::_GP('secured');

     $wsdlURL = t3lib_div::getIndpEnv('TYPO3_SITE_URL')  . 'typo3conf/ext/' . ST9FISSYNC_EXTKEY . '/mod_sync/index.php?wsdl';
     $arrayClassMap = tx_st9fissync::getInstance()->getClassMapforSOAP();
     $namespace = '';
     $options = array(
             'classmap' => $arrayClassMap,
             'cache_wsdl' => WSDL_CACHE_NONE
     );

     $this->SOAP_Server = t3lib_div::makeInstance('tx_st9fissync_t3soap_server',$wsdlURL,$options);
     $this->SystemObj =  t3lib_div::makeInstance($this->SOAP_Server_System, $this->SOAP_Server);

     if (isset($wsdl)) {
         $autodiscover = new Zend_Soap_AutoDiscover();
         $autodiscover->setClass($this->SOAP_Server_System);
         $autodiscover->handle();
     } else {
         $this->SOAP_Server->setObject($this->SystemObj);

         if (isset($securedRequest)) {
             //responses from this SOAP server are always encrypted,
             //this is part of business logic and hence handled by the sync server API
             //methods themselves, check server system methods
             $this->SOAP_Server->setRequestSecurity();
         }

         $this->SOAP_Server->handle();
     }
     tx_st9fissync::getInstance()->resetToOrigRuntimeMemoryLimit();

    }

}

// Make instance:

if (defined ('TYPO3_MODE') && TYPO3_MODE === 'BE') {
    $SOBE = t3lib_div::makeInstance('tx_st9fissync_mod_sync_service');
    $SOBE->init();
}

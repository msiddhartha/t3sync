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

// DEFAULT initialization of a module [BEGIN]
require_once 'conf.php';
// these definitions are needed to avoid the init script to exit
define('TYPO3_PROCEED_IF_NO_USER', true);

//@see: $client = t3lib_div::clientInfo();
//@todo: is ther a better way to check this?
//absolutely critical
if (!array_key_exists('HTTP_USER_AGENT', $_SERVER) || preg_match('/Konqueror|Opera|MSIE|Mozilla/', $_SERVER['HTTP_USER_AGENT']) === 0) {
    //this is mandatory since TYPO3 checks for compatible browsers and most likely clients aren't
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible)';
    //this is mandatory since we do not expect clients to store cookies
    $_COOKIE = null;
    $_SERVER['HTTP_COOKIE'] = null;
}

require_once($BACK_PATH.'init.php');
$LANG->includeLLFile('EXT:st9fissync/mod_sync/locallang.xml');
require_once(PATH_t3lib . 'class.t3lib_scbase.php');

// DEFAULT initialization of a module [END]

if ($GLOBALS['BE_USER']->user['uid'] && $GLOBALS['BE_USER']->user['username'] != tx_st9fissync::getInstance()->getSyncConfigManager()->getRemoteSyncBEUser()) {

    //$BE_USER->modAccess($MCONF, 1);// This checks permissions and exits if the users has no permission for entry.
    require_once(t3lib_extMgm::extPath('st9fissync') . 'mod_sync/class.tx_st9fissync_mod_sync_statistics.php');
    // Make instance:
    $SOBE = t3lib_div::makeInstance('tx_st9fissync_mod_sync_statistics');
    $SOBE->init();
    // Include files?
    foreach($SOBE->include_once as $INC_FILE)
        include_once($INC_FILE);
    $SOBE->main();
    $SOBE->printContent();

}/* elseif (t3lib_div::_GP('curlRequest')==1) {
require_once(t3lib_extMgm::extPath('st9fissync') . 'mod_sync/class.tx_st9fissync_curlrequesthandler.php');
$curlRequestHandler = tx_st9fissync::getInstance()->getCurlRequestHandler();
return $curlRequestHandler->handle();
} */
else {
    //$dispatchSyncService = !is_null(t3lib_div::_GP('wsdl'));
    //if($dispatchSyncService)
    if (defined('TYPO3_MODE') && TYPO3_MODE === 'BE') {
        require_once(t3lib_extMgm::extPath('st9fissync') . 'mod_sync/class.tx_st9fissync_mod_sync_service.php');
    }
}

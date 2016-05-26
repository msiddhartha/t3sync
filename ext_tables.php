<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}
$TCA['tx_st9fissync_resync_entries'] = array (
        'ctrl' => array (
                'title'				=> 'LLL:EXT:st9fissync/language/locallang_tca.xml:tx_st9fissync_resync_entries',
                'label'				=> 'uid',
                'default_sortby'	=> 'ORDER BY crdate DESC',
                'tstamp'			=> 'tstamp',
                'crdate'			=> 'crdate',
                'cruser_id'			=> 'cruser_id',

                'rootLevel'		=> 1,

                'dynamicConfigFile'	=> t3lib_extMgm::extPath($_EXTKEY) . 'conf/tca.php',
                'iconfile'			=> t3lib_extMgm::extRelPath($_EXTKEY) . 'res/icons/icon_tx_st9fissync_resync_entries.gif',
        ),
);

if (TYPO3_MODE=='BE') {

    $extPath = t3lib_extMgm::extPath($_EXTKEY);

    // module sync
    t3lib_extMgm::addModulePath('txst9fisbemoduleM1_txst9fissyncM1', $extPath . 'mod_sync/');
    t3lib_extMgm::addModule('txst9fisbemoduleM1', 'txst9fissyncM1', 'bottom', $extPath . 'mod_sync/');

    // Add context sensitive help (csh) to the backend module
    t3lib_extMgm::addLLrefForTCAdescr('_MOD_txst9fisbemoduleM1_txst9fissyncM1', 'EXT:' . $_EXTKEY . '/mod_sync/locallang_csh_st9fissync.xml');

}

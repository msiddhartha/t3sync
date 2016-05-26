<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

define('ST9FISSYNC_EXTKEY', $_EXTKEY);
define('ST9FISSYNC_LOCK_FILE', PATH_site . 'typo3temp/tx_st9fissync_process.lock');
define('ST9FISSYNC_LOGSFOLDER', PATH_site . tx_em_Tools::uploadFolder($_EXTKEY));

require_once(t3lib_extMgm::extPath('div').'class.tx_div.php');
if (TYPO3_MODE == 'FE') // maybe not necessary as we all tend to use autoload for T3 reflection based class loading
    tx_div::autoLoadAll($_EXTKEY);

$extConf = unserialize($_EXTCONF);
if ($extConf['enable_test_eID'])
    $TYPO3_CONF_VARS['FE']['eID_include']['st9fissynctest'] = 'EXT:st9fissync/res/eID/test.php';

/**
 *
 * T3 native DB API class/functions XCLASS, entry point for all recording/sequencing
 */
if (!isset($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['t3lib/class.t3lib_db.php'])) {
    $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['t3lib/class.t3lib_db.php'] = t3lib_extMgm::extPath($_EXTKEY) . 'xclass/class.ux_t3lib_db.php';
}

/**
 * Sync scheduler task
 */
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_st9fissync_tasks_sync'] = array(
        'extension' => $_EXTKEY,
        'title' => 'Synchronization',
        'description' => 'Scheduler task to trigger syncing user actions recorded on a DB level and replaying them on a remote T3 instance',
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_st9fissync_tasks_add_sysrefindex_syncqueue'] = array(
        'extension' => $_EXTKEY,
        'title' => 'Add sys_refindex DAM references in synchronization queue',
        'description' => 'Scheduler task to simulate user actions recorded on a DB level for table sys_refindex for DAM index refs for softref_key: \'media\', \'mediatag\'',
);

/**
 * Sync garbage collector task
 */
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_st9fissync_tasks_gc'] = array(
        'extension' => $_EXTKEY,
        'title' => 'Synchronization garbage collector',
        'description' => 'Scheduler task to trigger sync garbage collector',
);

/**
 * Sync standalone tests
 */
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_st9fissync_tests'] = array(
        'extension' => $_EXTKEY,
        'title' => 'Standalone func tests for Sync (only for debugging)',
        'description' => 'Trigger a suite of standalone tests (only for debugging)',
);

//was used for debugging
//$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['cliKeys']['testsuite_dbversioning'] = array('EXT:st9fissync/tests/class.tx_st9fissync_tests_dbversioning.php','_CLI_scheduler');
//$TYPO3_CONF_VARS['SC_OPTIONS']['typo3/alt_doc.php']['makeEditForm_accessCheck']['st9fissync'] = t3lib_extMgm::extPath($_EXTKEY) . 'hooks/class.tx_st9fissync_hooks_alt_doc.php:tx_st9fissync_hooks_alt_doc->makeEditForm_accessCheck';

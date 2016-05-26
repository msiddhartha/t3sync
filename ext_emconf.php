<?php

########################################################################
# Extension Manager/Repository config file for ext "st9fissync".
#
# Auto generated 25-07-2012 14:38
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
        'title' => 'TYPO3 Sync',
        'description' => 'Build up a synchronisation for certain content between two TYPO3 installations.',
        'category' => 'services',
        'author' => 'André Spindler',
        'author_email' => 'info@studioneun.de',
        'shy' => '',
        'dependencies' => 'dam,version',
        'conflicts' => '',
        'priority' => '',
        'module' => '',
        'state' => 'excludeFromUpdates',
        'internal' => '',
        'uploadfolder' => 1,
        'createDirs' => '',
        'modify_tables' => '',
        'clearCacheOnLoad' => 0,
        'lockType' => '',
        'author_company' => '',
        'version' => '1.0.0',
        'constraints' => array(
                'depends' => array(
                        'dam' => '',
                        'version' => '',
                        'lib' => '',
                        'div' => '',
                        'caretaker_instance' => '',
                        'zend_framework' => '',
                        'st9fisbemodule' => '',
                        'st9fisutility' => '',
                ),
                'conflicts' => array(
                ),
                'suggests' => array(
                ),
        ),
        '_md5_values_when_last_written' => 'a:14:{s:9:"ChangeLog";s:4:"8b3a";s:10:"README.txt";s:4:"ee2d";s:16:"ext_autoload.php";s:4:"1a3f";s:21:"ext_conf_template.txt";s:4:"5ffc";s:12:"ext_icon.gif";s:4:"58f8";s:17:"ext_localconf.php";s:4:"e59b";s:14:"ext_tables.php";s:4:"fe70";s:14:"ext_tables.sql";s:4:"0908";s:12:"conf/tca.php";s:4:"6011";s:26:"language/locallang_tca.xml";s:4:"53b6";s:37:"lib/class.tx_st9fissync_lib_queue.php";s:4:"db3f";s:50:"models/class.tx_st9fissync_model_resyncentries.php";s:4:"51ca";s:16:"res/eID/test.php";s:4:"45a3";s:47:"res/icons/icon_tx_st9fissync_resync_entries.gif";s:4:"475a";}',
        'suggests' => array(
        ),
);

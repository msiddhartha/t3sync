<?php

// DO NOT REMOVE OR CHANGE THESE 3 LINES:
define('TYPO3_MOD_PATH', '../typo3conf/ext/st9fissync/mod_sync/');
$BACK_PATH='../../../../typo3/';

$MCONF['name']='txst9fisbemoduleM1_txst9fissyncM1';
$MCONF['access']='user,group';

//$MCONF['access']='admin';

$MCONF['script']='_DISPATCH';
$MCONF['workspaces'] = 'online';

$MLANG['default']['tabs_images']['tab'] = 'moduleicon.png';
$MLANG['default']['ll_ref'] = 'LLL:EXT:st9fissync/mod_sync/locallang_mod.xml';

#$MCONF['shy']=true;
//to be configured later
//$MCONF['access'] = 'user,group';

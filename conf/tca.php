<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

$TCA['tx_st9fissync_resync_entries'] = array (
    'ctrl' => $TCA['tx_st9fissync_resync_entries']['ctrl'],
    'interface' => array (
        'showRecordFieldList' => 'record_table,record_uid,record_action,record_tstamp',
    ),
    'feInterface' => $TCA['tx_st9fissync_resync_entries']['feInterface'],
    'columns' => array (
        'record_table' => array (
            'exclude' => 0,
            'label' => 'LLL:EXT:st9fissync/language/locallang_tca.xml:tx_st9fissync_resync_entries.record_table',
            'config' => array (
                'type' => 'input',
                'size' => '30',
                'readOnly' => 1,
            ),
        ),
        'record_uid' => array (
            'exclude' => 0,
            'label' => 'LLL:EXT:st9fissync/language/locallang_tca.xml:tx_st9fissync_resync_entries.record_uid',
            'config' => array (
                'type' => 'input',
                'size' => '30',
                'eval' => 'int',
                'readOnly' => 1,
            ),
        ),
        'record_action' => array (
            'exclude' => 0,
            'label' => 'LLL:EXT:st9fissync/language/locallang_tca.xml:tx_st9fissync_resync_entries.record_action',
            'config' => array (
                'type' => 'select',
                'items' => array (
                    array('LLL:EXT:st9fissync/language/locallang_tca.xml:tx_st9fissync_resync_entries.record_action.I.0', '0'),
                    array('LLL:EXT:st9fissync/language/locallang_tca.xml:tx_st9fissync_resync_entries.record_action.I.1', '1'),
                    array('LLL:EXT:st9fissync/language/locallang_tca.xml:tx_st9fissync_resync_entries.record_action.I.2', '2'),
                    array('LLL:EXT:st9fissync/language/locallang_tca.xml:tx_st9fissync_resync_entries.record_action.I.3', '3'),
                ),
                'size' => 1,
                'minitems' => 1,
                'maxitems' => 1,
                'readOnly' => 1,
            ),
        ),
        'record_tstamp' => array (
            'exclude' => 0,
            'label' => 'LLL:EXT:st9fissync/language/locallang_tca.xml:tx_st9fissync_resync_entries.record_tstamp',
            'config' => array (
                'type' => 'input',
                'size' => '18',
                'eval' => 'datetime',
                'readOnly' => 1,
            ),
        ),
    ),
    'types' => array (
        '0' => array('showitem' => '
            record_table;;;;1-1-1, record_uid, record_action,record_tstamp,',
        ),
    ),
);

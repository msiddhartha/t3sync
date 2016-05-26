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
 * Module 'Synchronization statistics' for the 'st9fissync' extension.
*
* @author	André Spindler <sp@studioneun.de>
* @package	TYPO3
* @subpackage	st9fissync
*/

class  tx_st9fissync_mod_sync_statistics extends t3lib_SCbase
{
    /**
     * Back path to typo3 main dir
     *
     * @var	string		$backPath
     */
    public $backPath;

    /**
     * Array containing submitted data when editing or adding a task
     *
     * @var	array		$submittedData
     */
    protected $submittedData = array();

    /**
     * Array containing all messages issued by the application logic
     * Contains the error's severity and the message itself
     *
     * @var	array	$messages
     */
    protected $messages = array();

    /**
     * @var	string	Key of the CSH file
     */
    protected $cshKey;

    /**
     *
     * @var	tx_st9fissync	Local scheduler instance
     */
    protected $st9fissync;

    /**
     * @var string
     */
    protected $thisScript;

    /**
     * @var string
     */
    public $dateFormat;

    /**
     * @var string
     */
    public $dateTimeFormat;

    /**
     *
     * @var mixed
     */
    private $wsMapping = NULL;

    /**
     * Main table for recording DB actions/queries
     *
     * @var string
     */
    private $queryVersioningTable = 'tx_st9fissync_dbversioning_query';

    /**
     * MM table for associating DB actions/queries to specific tables/corresponding records/rows
     *
     * @var string
     */
    private $queryVersioningMMTable = 'tx_st9fissync_dbversioning_query_tablerows_mm';

    /**
     *
     * Main table for Sync processes
     *
     * @var string
     */
    private $syncProcessTable = 'tx_st9fissync_process';

    /**
     * Soap client object
     */
    protected $objSoapClient = null;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        $this->backPath = $GLOBALS['BACK_PATH'];
        $this->cshKey = '_MOD_' . $GLOBALS['MCONF']['name'];
        $this->dateFormat = $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'];
        $this->dateTimeFormat = $this->dateFormat . ' ' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'];


        //check for all $this once more later
    }

    /**
     * Initializes the backend module
     *
     * @return void
     */
    public function init()
    {
        parent::init();

        $this->thisScript = 'mod.php?M=' . $this->MCONF['name'];

        // Initialize document
        $this->doc = t3lib_div::makeInstance('template');
        $this->doc->setModuleTemplate(t3lib_extMgm::extPath('st9fissync') . 'mod_sync/mod_template.html');
        $this->doc->getPageRenderer()->addCssFile(t3lib_extMgm::extRelPath('st9fissync') . 'mod_sync/mod_styles.css');
        $this->doc->backPath = $this->backPath;
        $this->doc->bodyTagId = 'typo3-mod-php';
        $this->doc->bodyTagAdditions = 'class="tx_st9fissync_mod_sync_statistics"';

        // Create tx_synchronizer instance
        //$this->st9fissync = t3lib_div::makeInstance('tx_st9fissync');
    }

    /**
     * Adds items to the ->MOD_MENU array. Used for the function menu selector.
     *
     * @return void
     */
    public function menuConfig()
    {
        $this->MOD_MENU = array(
                'function' => array(
                        'sync' => $GLOBALS['LANG']->getLL('function.sync'),
                        'synchistory' => $GLOBALS['LANG']->getLL('function.synchistory'),
                        'syncsetupcheck' => $GLOBALS['LANG']->getLL('function.syncsetupcheck'),
                        'queryversioninginfo' => $GLOBALS['LANG']->getLL('function.queryversioninginfo'),
                        'garbagecollector' => $GLOBALS['LANG']->getLL('function.garbagecollector'),
                )
        );

        parent::menuConfig();
    }

    /**
     * Main function of the module. Write the content to $this->content
     *
     * @return void
     */
    public function main()
    {
        // Access check!
        // The page will show only if user has admin rights
        if ($GLOBALS['BE_USER']->user['admin']) {

            // Set the form
            $this->doc->form = '<form name="tx_st9fissync_form" id="tx_st9fissync_form" method="post" action="">';

            // JavaScript for main function menu
            $this->doc->JScode = '
            <script language="javascript" type="text/javascript">
            script_ended = 0;
            function jumpToUrl(URL)
            {
            document.location = URL;
        }
        </script>
        ';
            //	$this->doc->getPageRenderer()->addInlineSetting('st9fissync', 'runningIcon', t3lib_extMgm::extRelPath('st9fissync') . 'res/gfx/status_running.png');

            // Prepare main content
            $this->content  = $this->doc->header(
                    $GLOBALS['LANG']->getLL('function.' . $this->MOD_SETTINGS['function'])
            );
            $this->content .= $this->doc->spacer(5);
            $this->content .= $this->getModuleContent();
        } else {
            // If no access, only display the module's title
            $this->content  = $this->doc->header($GLOBALS['LANG']->getLL('title'));
            $this->content .= $this->doc->spacer(5);
        }

        // Place content inside template

        $content = $this->doc->moduleBody(
                array(),
                $this->getDocHeaderButtons(),
                $this->getTemplateMarkers()
        );

        // Renders the module page
        $this->content = $this->doc->render(
                $GLOBALS['LANG']->getLL('title'),
                $content
        );
    }

    /**
     * Generate the module's content
     *
     * @return string HTML of the module's main content
     */
    protected function getModuleContent()
    {
        $content = '';
        $sectionTitle = '';

        // Get submitted data
        $this->submittedData = t3lib_div::_GPmerged('tx_st9fissync');

        // Handle chosen action
        switch ((string) $this->MOD_SETTINGS['function']) {
            case 'sync':
                // Getting the sync status
                $activeSyncProcesses = tx_st9fissync::getInstance()->getSyncDBOperationsManager()->getActiveSyncProcesses();

                // Executing sync execution (after post/click on button)
                if (!$activeSyncProcesses && count($activeSyncProcesses) <= 0 && $this->submittedData['sync_status'] == 'notrunning') {
                    tx_st9fissync::getInstance()->getSyncProcessHandle()->launch();

                    // Getting the sync status
                    $activeSyncProcesses = tx_st9fissync::getInstance()->getSyncDBOperationsManager()->getActiveSyncProcesses();
                }

                // Getting sync process button
                $content .= $this->getSyncProcessButton($activeSyncProcesses);
                break;
            case 'syncsetupcheck':
                // Setup check screen
                $content .= $this->displaySyncSetupCheckScreen();
                break;
            case 'queryversioninginfo':
                // Information list/details screen
                switch ($this->CMD) {
                    case 'queryVersioningDetails':
                        $content .= $this->doc->spacer(10) . $this->getLinkBackToModule($GLOBALS['LANG']->getLL('qvinfo.link.titletext'),$GLOBALS['LANG']->getLL('qvinfo.link.text')) . $this->doc->spacer(10);
                        $content .= $this->QVInfoDetails();
                        break;
                    default:
                        $content .= $this->QVInfoListing();
                        break;
                }
                break;
            case 'garbagecollector':
                $activeGCProcesses = tx_st9fissync_gc::getGCInstance()->isGCProcNotActive();

                // Executing GC
                if ($activeGCProcesses && $this->submittedData['gc_status'] == 'notrunning') {
                    $tillDatetime = trim($this->submittedData['till_date']);

                    // convert $tillDatetime in unix timestamp
                    $tilltimestamp = 0;
                    if ($tillDatetime != '') {
                        $arrTilltime = explode(".", $tillDatetime);

                        if ($arrTilltime[0] > 0 && $arrTilltime[1] > 0 && $arrTilltime[2] > 0) {
                            // Converting to timestamp
                            $tilltimestamp = mktime(23, 59, 59, $arrTilltime[1], $arrTilltime[0], $arrTilltime[2]);

                            // Executing Garbage collector
                            if ($tilltimestamp > 0) {
                                tx_st9fissync_gc::getGCInstance()->executeGC($tilltimestamp, true);
                            }
                        }
                    }

                    // Invalid date message
                    if ($tilltimestamp <= 0) {
                        tx_st9fissync::getInstance()->addAppMessage($GLOBALS['LANG']->getLL('mod.sync.gc.err.invalid_date'), '', '3');
                    }
                }

                $content .= $this->getGarbageCollectorForm($activeGCProcesses);
                break;
        }

        // Wrap the content in a section
        return $this->doc->section($sectionTitle, '<div class="tx_st9fissync_mod_sync_statistics">' . $content . '</div>', 0, 1);

    }

    public function getLinkToNewWindow()
    {
        return '<a href="' . htmlspecialchars(t3lib_div::linkThisUrl(t3lib_div::linkThisScript(),array('tx_st9fissync[newwindow]' => 1))) . '" target="tx_st9fissync">' . $GLOBALS['LANG']->getLL('newwindow') . '</a>';

    }

    public function getLinkBackToModule($titleText,$linkText)
    {
        $_param_newwindow = array();
        if ($this->submittedData['newwindow']) {
            $_param_newwindow['tx_st9fissync[newwindow]']=1;
        }
        $linkBackToQVInfolisting  = '<a href="' . htmlspecialchars(t3lib_div::linkThisUrl($GLOBALS['MCONF']['_'],$_param_newwindow)) . '" title="' . $titleText . '" class="icon">' . $linkText . '</a>';

        return $linkBackToQVInfolisting;
    }

    /**
     * Gets the filled markers that are used in the HTML template.
     *
     * @return array The filled marker array
     */
    protected function getTemplateMarkers()
    {
        $markers = array(
                'CSH' => t3lib_BEfunc::wrapInHelp('_MOD_txst9fisbemoduleM1_txst9fissyncM1', ''),
                'FUNC_MENU' => $this->getFunctionMenu(),
                'CONTENT'   => $this->content,
                'TITLE'     => $GLOBALS['LANG']->getLL('title'),
        );

        return $markers;
    }

    /**
     * Gets the function menu selector for this backend module.
     *
     * @return string The HTML representation of the function menu selector
     */
    protected function getFunctionMenu()
    {
        $functionMenu = t3lib_BEfunc::getFuncMenu(
                0,
                'SET[function]',
                $this->MOD_SETTINGS['function'],
                $this->MOD_MENU['function']
        );

        return $functionMenu;
    }

    /**
     * Gets the buttons that shall be rendered in the docHeader.
     *
     * @return array Available buttons for the docHeader
     */
    protected function getDocHeaderButtons()
    {
        $buttons = array(
                'reload'   => '',
                'shortcut' => $this->getShortcutButton(),
        );

        $buttons['reload'] = '<a href="' . $GLOBALS['MCONF']['_'] . '" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:labels.reload', TRUE) . '">' .
                t3lib_iconWorks::getSpriteIcon('actions-system-refresh') .
                '</a>';

        return $buttons;
    }

    /**
     * Gets the button to set a new shortcut in the backend (if current user is allowed to).
     *
     * @return string HTML representiation of the shortcut button
     */
    protected function getShortcutButton()
    {
        $result = '';
        if ($GLOBALS['BE_USER']->mayMakeShortcut()) {
            $result = $this->doc->makeShortcutIcon('', 'function', $this->MCONF['name']);
        }

        return $result;
    }

    /**
     * Prints out the module HTML
     *
     * @return void
     */
    public function printContent()
    {
        echo $this->content;
    }

    public function getSyncToInstanceDetails()
    {
        $sectionContent = '';
        $actionsMap = tx_st9fissync::testafunc();
        $request = tx_st9fissync::getClient()->getLastRequest();
        $response  = tx_st9fissync::getClient()->getLastResponse();
        $sectionContent .= $actionsMap;
        var_dump($request);
        var_dump($response);

        return $sectionContent;
    }

    /**
     *
     * @param  unknown_type   $colKey
     * @param  unknown_type   $excludeKeys
     * @return multitype:NULL |multitype:|Ambigous <NULL>
     */
    public function getQVInfoLabelsMapping($colKey=NULL, $excludeKeys=NULL)
    {
        $QVInfoLabelsMapping = array(
                'uid' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.uid'),
                'uid_foreign' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.refrecordId'),
                'pid' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.pid'),
                'sysid' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.sysid'),
                'crdate' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.crdate'),
                'crmsec' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.crtstamp'),
                'timestamp' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.updatetstamp'),
                'cruser_id' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.cruserid'),
                'typo3_mode' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.typo3mode'),
                'updtuser_id' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.updtuserid'),
                'query_text' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.querytext'),
                'query_type' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.querytype'),
                'tablenames' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.tablename'),
                'query_affectedrows' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.queryaffectedrows'),
                'query_info' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.queryinfo'),
                'query_exectime' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.queryexectime'),
                'query_error_number' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.queryerrnum'),
                'query_error_message' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.queryerrmessage'),
                'issyncscheduled' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.issyncscheduled'),
                'issynced' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.issynced'),
                'workspace' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.workspace'),
                'request_url' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.requesturl'),
                'client_ip' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.clientip'),
                'rootid' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.rootid'),
                'excludeid' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.excludeid'),
                'recordRevision' => $GLOBALS['LANG']->getLL('qvinfo.colkey.label.recordRevision'),
        );

        if ($colKey==NULL) {
            if ($excludeKeys == NULL && !is_array($excludeKeys)) {
                return $QVInfoLabelsMapping;
            } else {
                //filter by exclude keys
                return array_diff_key($QVInfoLabelsMapping, $excludeKeys);

            }
        } else {
            return $QVInfoLabelsMapping[$colKey];
        }

    }

    /**
     *
     * @param unknown_type $key
     */
    public function QVInfoColValRenderByKey($key,$valueToTransform)
    {
        switch ($key) {
            case 'crmsec':
            case 'timestamp':
            case 'crdate':
            case 'dbvqtimestamp':
                return $this->microDateTime($valueToTransform);
            case 'typo3_mode':
                return $this->getT3ModeMapping($valueToTransform);
            case 'query_type':
                return $this->getQueryTypeMapping($valueToTransform);
            case 'query_info':
            case 'recordRevision':
                return nl2br(trim(preg_replace('/\)$/', '', preg_replace('/^\(/' ,'',preg_replace('/Array[\r\n]/','', print_r(unserialize($valueToTransform), TRUE))))));
            case 'query_error_number':
                return $valueToTransform == 0 ? $GLOBALS['LANG']->getLL('qvilisting.queryerrnum.none'): $valueToTransform;
            case 'workspace':
                return $indexedT3QueryRecordedRow['workspace']== NULL ? '': $this->getT3WsMapping($valueToTransform);
            case 'client_ip':
                return long2ip($valueToTransform);
            case 'issyncscheduled':
            case 'issynced':
                return $valueToTransform == 0 ?  $GLOBALS['LANG']->getLL('qvilisting.no') : $GLOBALS['LANG']->getLL('qvilisting.yes');
            case 'uid':
            case 'pid':
            case 'sysid':
            case 'cruser_id':
            case 'updtuser_id':
            case 'query_text':
            case 'query_error_message':
            case 'query_affectedrows':
            case 'query_exectime':
            case 'query_affectedrows':
            case 'query_affectedrows':
            case 'request_url':
            case 'rootid':
            case 'excludeid':
            case 'uid_local':
            case 'uid_foreign':
            case 'tablenames':
                return strip_tags($valueToTransform);
            default:
                return '';
        }
    }

    /**
     *
     */
    protected function QVInfoDetails()
    {
        $sectionContent = '';
        $qvId = intval($this->submittedData['qvId']);
        if ($qvId > 0) {
            $select = $this->queryVersioningTable . '.*, ' . $this->queryVersioningMMTable . '.*, ';
            $select = $select . $this->queryVersioningTable . '.timestamp AS dbvqtimestamp ';
            $local_table = $this->queryVersioningTable;
            $mm_table = $this->queryVersioningMMTable;
            $foreign_table = '';
            $where = 'AND ' . $this->queryVersioningTable . '.uid =' . $qvId;
            //$where .= ' AND ' . $this->queryVersioningTable . '.uid =' . $this->queryVersioningMMTable . '.uid_local';
            $groupBy = '';
            $orderBy = $this->queryVersioningTable . '.timestamp DESC';

            //			$queryVersioningDetailsQ = $GLOBALS['TYPO3_DB']->SELECT_mm_query($select, $local_table, $mm_table, $foreign_table,  $where);
            $queryVersioningDetailsRes = $GLOBALS['TYPO3_DB']->exec_SELECT_mm_query($select, $local_table, $mm_table, $foreign_table,  $where);

            if ($GLOBALS['TYPO3_DB']->sql_num_rows($queryVersioningDetailsRes)) {

                $tableLayout = array (
                        'table' => array ('<table border="0" cellspacing="1" cellpadding="2" class="typo3-dblist">', '</table>'),
                        /* '0' => array (
                         'tr' => array('<tr class="t3-row-header" valign="top">', '</tr>'),
                                'defCol' => array('<td>', '</td>')
                        ), */
                        'defRow' => array (
                                'tr' => array('<tr class="db_list_normal">', '</tr>'),
                                'defCol' => array('<td>', '</td>')
                        )
                );

                $buildRevHistParamsArr = array();

                $excludeKeys = array(
                        'tables',
                        'uid_local',
                        'sorting',
                        'timestamp',
                        'row_original_data',
                        'dbvqtimestamp',
                );

                while ($indexedT3QueryRecordedRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($queryVersioningDetailsRes)) {

                    $table = array();
                    $tr = 0;

                    foreach ($indexedT3QueryRecordedRow as $indexedT3QueryRecordedRowColKey => $indexedT3QueryRecordedRowColVal) {
                        if (!in_array($indexedT3QueryRecordedRowColKey,$excludeKeys)) {
                            $table[$tr][] = $this->getQVInfoLabelsMapping($indexedT3QueryRecordedRowColKey);
                            $table[$tr][] = $this->QVInfoColValRenderByKey($indexedT3QueryRecordedRowColKey, $indexedT3QueryRecordedRowColVal);
                            $tr++;
                        }
                    }

                    //$recordedQueryDetailsLink = $GLOBALS['MCONF']['_'] . '&SET[function]=queryversioninginfo&CMD=queryVersioningDetails&tx_st9fissync[qvId]=' . $indexedT3QueryRecordedRow['uid'];
                    //$table[$tr][] = '<a href="' . htmlspecialchars($recordedQueryDetailsLink) . '" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_common.xml:details', TRUE) . '" class="icon">' . t3lib_iconWorks::getSpriteIcon('actions-document-info') . '</a>';
                    //$table[$tr][] = $indexedT3QueryRecordedRow['uid'];

                    $sectionContent  .= '<div>' . tx_st9fissync_mod_sync_statistics::addAppMessage($GLOBALS['LANG']->getLL('appmessage.qvidetails.infoscreenintro'),TRUE,t3lib_FlashMessage::INFO) . '</div>';
                    $sectionContent .= $this->doc->spacer(5);
                    $sectionContent .= $this->doc->table($table, $tableLayout);

                }

            } else {
                //Nothing found
                $sectionContent .=  tx_st9fissync_mod_sync_statistics::addAppMessage($GLOBALS['LANG']->getLL('appmessage.noresults'),TRUE,t3lib_FlashMessage::NOTICE);
            }
        } else {
            // message for correct qvId
            $sectionContent .=  tx_st9fissync_mod_sync_statistics::addAppMessage($GLOBALS['LANG']->getLL('appmessage.invalidqvid'),TRUE,t3lib_FlashMessage::ERROR);

        }

        $GLOBALS['TYPO3_DB']->sql_free_result($queryVersioningDetailsRes);

        return $sectionContent;

    }

    public function QVInfoDetailsSorting()
    {
        throw new t3lib_exception('Unimplemented');
    }

    /**
     *
     */
    protected function QVInfoListing()
    {
        $sectionContent = '';

        $this->feUserObj = t3lib_div::makeInstance('tslib_feUserAuth');
        //fetch all FE groups
        $this->activeFEGroups = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid,title', $this->feUserObj->usergroup_table, '1=1'.t3lib_BEfunc::deleteClause($this->feUserObj->usergroup_table),'','title');
        //fetch all FE users
        $this->activeFEUsers = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($this->feUserObj->userid_column . ',' .  $this->feUserObj->username_column  . ',' . $this->feUserObj->usergroup_column, $this->feUserObj->user_table, '1=1'.t3lib_BEfunc::deleteClause($this->feUserObj->user_table),'',$this->feUserObj->username_column);

        $this->compileQVInfoFuncFilters();
        $sectionContent .= $this->compileQVInfoFuncFilters();
        $sectionContent .= $this->doc->divider(5).$this->doc->spacer(5);

        //build where clause to fetch versioning record reports based on various filtering criterion
        $builtWhereClauseQVIFunc = '';

        // Action (type) filter:
        $builtWhereClauseQVIFunc='';
        if ($this->MOD_SETTINGS['qvifunc_action'] > 0) {
            $builtWhereClauseQVIFunc.=' AND ' . $this->queryVersioningTable  . '.query_type='.intval($this->MOD_SETTINGS['qvifunc_action']);
        }

        // Time filter:
        $starttime=0;
        $endtime = tx_st9fissync::getInstance()->getMicroTime();
        switch ($this->MOD_SETTINGS['qvifunc_time']) {
            case 0:
                // This week
                $week = (date('w') ? date('w') : 7)-1;
                $starttime = mktime (0,0,0)-$week*3600*24;
                break;
            case 1:
                // Last week
                $week = (date('w') ? date('w') : 7)-1;
                $starttime = mktime (0,0,0)-($week+7)*3600*24;
                $endtime = mktime (0,0,0)-$week*3600*24;
                break;
            case 2:
                // Last 7 days
                $starttime = mktime (0,0,0)-7*3600*24;
                break;
            case 10:
                // This month
                $starttime = mktime (0,0,0, date('m'),1);
                break;
            case 11:
                // Last month
                $starttime = mktime (0,0,0, date('m')-1,1);
                $endtime = mktime (0,0,0, date('m'),1);
                break;
            case 12:
                // Last 31 days
                $starttime = mktime (0,0,0)-31*3600*24;
                break;
            case 30:
                $starttime = $this->theTime;
                if ($this->theTime_end) {
                    $endtime = $this->theTime_end;
                } else {
                    //if no end time set, then till now
                    $endtime = tx_st9fissync::getInstance()->getMicroTime();
                }
        }

        if ($starttime) {
            $builtWhereClauseQVIFunc.=' AND ' . $this->queryVersioningTable  . '.crdate>='.$starttime.' AND ' . $this->queryVersioningTable  . '.crdate<'.$endtime;
        }

        // Users (fe/be) filter
        $selectUsers = array();
        if ($this->MOD_SETTINGS['qvifunc_typo3_mode']==0 || $this->MOD_SETTINGS['qvifunc_typo3_mode']!=TYPO3_REQUESTTYPE_FE) {

            $this->be_user_Array = t3lib_BEfunc::getUserNames();
            if (substr($this->MOD_SETTINGS['qvifunc_users'],0,5) == "begr-") {	// All users
                $this->be_user_Array = t3lib_BEfunc::blindUserNames($this->be_user_Array,array(substr($this->MOD_SETTINGS['qvifunc_users'],5)),1);
                if (is_array($this->be_user_Array)) {
                    foreach ($this->be_user_Array as $val) {
                        if ($val['uid']!=$GLOBALS['BE_USER']->user['uid']) {
                            $selectUsers[]=$val['uid'];
                        }
                    }
                }
                $selectUsers[] = 0;
                $builtWhereClauseQVIFunc.=' AND ' . $this->queryVersioningTable  . '.cruser_id in ('.implode($selectUsers,',').')';
            } elseif (substr($this->MOD_SETTINGS['qvifunc_users'],0,5) == "beus-") {
                // All users
                $selectUsers[] = intval(substr($this->MOD_SETTINGS['qvifunc_users'],5));
                $builtWhereClauseQVIFunc.=' AND ' . $this->queryVersioningTable  . '.cruser_id in ('.implode($selectUsers,',').')';
            } elseif ($this->MOD_SETTINGS['qvifunc_users']=='self') {
                $builtWhereClauseQVIFunc.=' AND ' . $this->queryVersioningTable  . '.cruser_id='.$GLOBALS['BE_USER']->user['uid'];	// Self user
            }
        }

        if ($this->MOD_SETTINGS['qvifunc_typo3_mode']==0 || $this->MOD_SETTINGS['qvifunc_typo3_mode']==TYPO3_REQUESTTYPE_FE) {
            if (substr($this->MOD_SETTINGS['qvifunc_users'],0,5) == "fegr-") {	// All users
                foreach ($this->activeFEUsers as $feus) {
                    if ($feus && t3lib_div::inList($feus[$this->feUserObj->usergroup_column], intval(substr($this->MOD_SETTINGS['qvifunc_users'],5)))) {
                        $selectUsers[] = $feus[$this->feUserObj->userid_column];
                    }
                }
                $selectUsers[] = 0;
                $builtWhereClauseQVIFunc.=' AND ' . $this->queryVersioningTable  . '.cruser_id in ('.implode($selectUsers,',').')';
            } elseif (substr($this->MOD_SETTINGS['qvifunc_users'],0,5) == "feus-") {	// All users
                $selectUsers[] = intval(substr($this->MOD_SETTINGS['qvifunc_users'],5));
                $builtWhereClauseQVIFunc.=' AND cruser_id in ('.implode($selectUsers,',').')';
            }

        }

        //workspace filter
        if ($GLOBALS['BE_USER']->workspace!==0) {
            $builtWhereClauseQVIFunc.=' AND ' . $this->queryVersioningTable  . '.workspace='.intval($GLOBALS['BE_USER']->workspace);
        } elseif ($this->MOD_SETTINGS['qvifunc_workspaces']!=-99) {
            $builtWhereClauseQVIFunc.=' AND ' . $this->queryVersioningTable  . '.workspace='.intval($this->MOD_SETTINGS['qvifunc_workspaces']);
        }

        //mode filter
        if ($this->MOD_SETTINGS['qvifunc_typo3_mode']) {
            $builtWhereClauseQVIFunc.=' AND ' . $this->queryVersioningTable  . '.typo3_mode='.intval($this->MOD_SETTINGS['qvifunc_typo3_mode']);
        }

        //is synced filter
        if ($this->MOD_SETTINGS['qvifunc_issynced']) {
            $builtWhereClauseQVIFunc.=' AND ' . $this->queryVersioningTable  . '.issynced='.intval($this->MOD_SETTINGS['qvifunc_issynced']);
        }

        //is synced scheduled filter
        if ($this->MOD_SETTINGS['qvifunc_issyncscheduled']) {
            $builtWhereClauseQVIFunc.=' AND ' . $this->queryVersioningTable  . '.issyncscheduled='.intval($this->MOD_SETTINGS['qvifunc_issyncscheduled']);
        }

        //filter by query errors
        if ($this->MOD_SETTINGS['qvifunc_iserror']) {
            $builtWhereClauseQVIFunc.=' AND ' . $this->queryVersioningTable  . '.query_error_number > 0';
        }

        $select = $this->queryVersioningTable . '.*, ';
        $select = $select . $this->queryVersioningMMTable . '.*';
        $local_table = $this->queryVersioningTable;
        $mm_table = $this->queryVersioningMMTable;
        $foreign_table = '';
        $groupBy = '';
        $orderBy = $this->queryVersioningTable . '.uid DESC';
        $limit = intval($this->MOD_SETTINGS['qvifunc_max']);

        /* $allIndexedT3QueriesQ = $GLOBALS['TYPO3_DB']->SELECT_mm_query($select, $local_table, $mm_table, $foreign_table, $builtWhereClauseQVIFunc, $groupBy, $orderBy, $limit);
         echo '<br> --- indexed t3 query -- ' . $allIndexedT3QueriesQ . ' --- <br>';
        */
        $allIndexedT3QueriesRes = $GLOBALS['TYPO3_DB']->exec_SELECT_mm_query($select, $local_table, $mm_table, $foreign_table, $builtWhereClauseQVIFunc, $groupBy, $orderBy, $limit);

        /**
         *
         * Old style query/res
         * $builtWhereClauseQVIFunc .= ' AND tx_st9fissync_dbversioning_query_tablerows_mm.uid_local=tx_st9fissync_dbversioning_query.uid';
         $allIndexedT3QueriesQ = $GLOBALS['TYPO3_DB']->SELECTquery('tx_st9fissync_dbversioning_query.*, tx_st9fissync_dbversioning_query_tablerows_mm.tablenames', 'tx_st9fissync_dbversioning_query, tx_st9fissync_dbversioning_query_tablerows_mm', '1=1'.$builtWhereClauseQVIFunc, '', 'uid DESC', intval($this->MOD_SETTINGS['qvifunc_max']));
         $allIndexedT3QueriesRes = $GLOBALS['TYPO3_DB']->exec_SELECTquery('tx_st9fissync_dbversioning_query.*, tx_st9fissync_dbversioning_query_tablerows_mm.tablenames', 'tx_st9fissync_dbversioning_query, tx_st9fissync_dbversioning_query_tablerows_mm', '1=1'.$builtWhereClauseQVIFunc, '', 'uid DESC', intval($this->MOD_SETTINGS['qvifunc_max']));
         */

        if ($GLOBALS['TYPO3_DB']->sql_num_rows($allIndexedT3QueriesRes)) {

            $tableLayout = array (
                    'table' => array ('<table border="0" cellspacing="1" cellpadding="2" class="typo3-dblist">', '</table>'),
                    '0' => array (
                            'tr' => array('<tr class="t3-row-header" valign="top">', '</tr>'),
                            'defCol' => array('<td>', '</td>')
                    ),
                    'defRow' => array (
                            'tr' => array('<tr class="db_list_normal">', '</tr>'),
                            'defCol' => array('<td>', '</td>')
                    )
            );

            $table = array();
            $tr = 0;

            // Header row
            $table[$tr][] = '';
            $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.uid');
            $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.pid');
            $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.sysid');
            $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.crtstamp');
            $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.updatetstamp');
            $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.cruserid');
            $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.typo3mode');
            //$table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.updtuserid');
            $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.querytext');
            $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.querytype');
            $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.tablename');
            //$table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.queryaffectedrows');
            //$table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.queryinfo');
            //$table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.queryexectime');
            $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.queryerrnum');
            $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.queryerrmessage');
            $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.issyncscheduled');
            $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.issynced');
            $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.workspace');
            //$table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.requesturl');
            $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.clientip');
            $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.rootid');
            $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.table.rowheader.excludeid');

            $tr++;

            while ($indexedT3QueryRecordedRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($allIndexedT3QueriesRes)) {

                //See details of the recording
                $recordedQueryDetailsLink = $GLOBALS['MCONF']['_'] . '&SET[function]=queryversioninginfo&CMD=queryVersioningDetails&tx_st9fissync[qvId]=' . $indexedT3QueryRecordedRow['uid'];
                $table[$tr][] = '<a href="' . htmlspecialchars($recordedQueryDetailsLink) . '" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_common.xml:details', TRUE) . '" class="icon">' . t3lib_iconWorks::getSpriteIcon('actions-document-info') . '</a>';
                $table[$tr][] = $indexedT3QueryRecordedRow['uid'];
                $table[$tr][] = $indexedT3QueryRecordedRow['pid'];
                $table[$tr][] = $indexedT3QueryRecordedRow['sysid'];
                $table[$tr][] = $this->microDateTime($indexedT3QueryRecordedRow['crmsec']);
                $table[$tr][] = $this->microDateTime($indexedT3QueryRecordedRow['timestamp']);
                $table[$tr][] = $indexedT3QueryRecordedRow['cruser_id'];
                $table[$tr][] = $this->getT3ModeMapping($indexedT3QueryRecordedRow['typo3_mode']);
                $table[$tr][] = substr(trim(strip_tags($indexedT3QueryRecordedRow['query_text'])), 0 , 50) . '....';
                $table[$tr][] = $this->getQueryTypeMapping($indexedT3QueryRecordedRow['query_type']);
                $table[$tr][] = $indexedT3QueryRecordedRow['tablenames'];
                //$table[$tr][] = $indexedT3QueryRecordedRow['query_affectedrows'];
                //$table[$tr][] = $indexedT3QueryRecordedRow['query_info'];
                //$table[$tr][] = $indexedT3QueryRecordedRow['query_exectime'];
                $table[$tr][] = $indexedT3QueryRecordedRow['query_error_number'] == 0 ? $GLOBALS['LANG']->getLL('qvilisting.queryerrnum.none'): $indexedT3QueryRecordedRow['query_error_number'];
                $table[$tr][] = $indexedT3QueryRecordedRow['query_error_number'] == 0 ? $GLOBALS['LANG']->getLL('qvilisting.queryerrmessage.none') : substr(trim($indexedT3QueryRecordedRow['query_error_message'], 0 , 50)) . '.....';
                $table[$tr][] = $indexedT3QueryRecordedRow['issyncscheduled'] == 0 ?  $GLOBALS['LANG']->getLL('qvilisting.no') : $GLOBALS['LANG']->getLL('qvilisting.yes');
                $table[$tr][] = $indexedT3QueryRecordedRow['issynced'] == 0 ?  $GLOBALS['LANG']->getLL('qvilisting.no') : $GLOBALS['LANG']->getLL('qvilisting.yes');
                $table[$tr][] = $indexedT3QueryRecordedRow['workspace']== NULL ? '':$this->getT3WsMapping($indexedT3QueryRecordedRow['workspace']);
                //$table[$tr][] = $indexedT3QueryRecordedRow['request_url'];
                $table[$tr][] = long2ip($indexedT3QueryRecordedRow['client_ip']);
                $table[$tr][] = intval($indexedT3QueryRecordedRow['rootid']);
                $table[$tr][] = intval($indexedT3QueryRecordedRow['excludeid']);

                $tr++;
            }

            $sectionContent  .= '<div>' . tx_st9fissync_mod_sync_statistics::addAppMessage($GLOBALS['LANG']->getLL('appmessage.qvilisting.infoscreenintro'),TRUE,t3lib_FlashMessage::INFO) . '</div>';
            $sectionContent .= $this->doc->spacer(5);
            $sectionContent .= $this->doc->table($table, $tableLayout);

        } else {
            //Nothing found
            $sectionContent .=  tx_st9fissync_mod_sync_statistics::addAppMessage($GLOBALS['LANG']->getLL('appmessage.noresults'),TRUE,t3lib_FlashMessage::NOTICE);
        }

        $GLOBALS['TYPO3_DB']->sql_free_result($allIndexedT3QueriesRes);

        if ($this->MOD_SETTINGS['qvifunc_listfilestobesynced']) {

            $sectionContent .= $this->doc->spacer(20).$this->doc->divider(10).$this->doc->spacer(20);

            $damMainTable = 'tx_dam';

            $select = $this->queryVersioningTable . '.uid AS ' . $this->queryVersioningTable .  '_uid, ';
            $select = $select . $this->queryVersioningTable . '.sysid AS ' . $this->queryVersioningTable .  '_sysid, ';
            //	$select = $select . $this->queryVersioningMMTable . '.*';
            $select = $select . $damMainTable . '.uid AS ' . $damMainTable.'_uid, ';
            $select = $select . $damMainTable . '.pid AS ' . $damMainTable.'_pid, ';
            $select = $select . $damMainTable . '.tstamp AS ' . $damMainTable.'_tstamp, ' . $damMainTable . '.crdate AS ' . $damMainTable.'_crdate, ';
            $select = $select . $damMainTable . '.cruser_id AS ' . $damMainTable.'_cruser_id, ' . $damMainTable . '.deleted AS ' . $damMainTable.'_deleted, ';
            $select = $select . $damMainTable . '.media_type AS ' . $damMainTable.'_media_type, ' . $damMainTable . '.title AS ' . $damMainTable.'_title, ';
            $select = $select . $damMainTable . '.category AS ' . $damMainTable.'_category, ' . $damMainTable . '.index_type AS ' . $damMainTable.'_index_type, ';
            $select = $select . $damMainTable . '.file_type AS ' . $damMainTable.'_file_type, ' . $damMainTable . '.file_name AS ' . $damMainTable.'_file_name, ';
            $select = $select . $damMainTable . '.file_path AS ' . $damMainTable.'_file_path, ' . $damMainTable . '.file_size AS ' . $damMainTable.'_file_size, ';
            $select = $select . $damMainTable . '.file_mtime AS ' . $damMainTable.'_file_mtime, ' . $damMainTable . '.file_ctime AS ' . $damMainTable.'_file_ctime ';

            $foreign_table = $damMainTable;
            $builtWhereClauseQVIFunc .= ' AND '. $this->queryVersioningMMTable . '.tablenames=\''.$damMainTable.'\'';

            //$damRecordRevisionsToBeSyncedQ = $GLOBALS['TYPO3_DB']->SELECT_mm_query($select, $local_table, $mm_table, $foreign_table,  $builtWhereClauseQVIFunc);
            $damRecordRevisionsRes = $GLOBALS['TYPO3_DB']->exec_SELECT_mm_query($select, $local_table, $mm_table, $foreign_table,  $builtWhereClauseQVIFunc);

            if ($GLOBALS['TYPO3_DB']->sql_num_rows($damRecordRevisionsRes)) {

                $tableLayout = array (
                        'table' => array ('<table border="0" cellspacing="1" cellpadding="2" class="typo3-dblist">', '</table>'),
                        '0' => array (
                                'tr' => array('<tr class="t3-row-header" valign="top">', '</tr>'),
                                'defCol' => array('<td>', '</td>')
                        ),
                        'defRow' => array (
                                'tr' => array('<tr class="db_list_normal">', '</tr>'),
                                'defCol' => array('<td>', '</td>')
                        )
                );

                $table = array();
                $tr = 0;

                // Header row
                $table[$tr][] = '';
                $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.damresourcestable.rowheader.qvid');
                $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.damresourcestable.rowheader.qvsysid');
                $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.damresourcestable.rowheader.tx_dam_uid');
                $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.damresourcestable.rowheader.tx_dam_pid');
                $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.damresourcestable.rowheader.tx_dam_title');
                $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.damresourcestable.rowheader.tx_dam_tstamp');
                $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.damresourcestable.rowheader.tx_dam_crdate');
                $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.damresourcestable.rowheader.tx_dam_cruser_id');
                $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.damresourcestable.rowheader.tx_dam_deleted');
                $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.damresourcestable.rowheader.tx_dam_media_type');
                $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.damresourcestable.rowheader.tx_dam_category');
                $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.damresourcestable.rowheader.tx_dam_index_type');
                $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.damresourcestable.rowheader.tx_dam_file_type');
                $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.damresourcestable.rowheader.tx_dam_file_name');
                $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.damresourcestable.rowheader.tx_dam_file_path');
                $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.damresourcestable.rowheader.tx_dam_file_size');
                $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.damresourcestable.rowheader.tx_dam_file_mtime');
                $table[$tr][] = $GLOBALS['LANG']->getLL('qvilisting.damresourcestable.rowheader.tx_dam_file_ctime');

                $tr++;

                while ($indexedDAMResT3QueryRecordedRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($damRecordRevisionsRes)) {

                    $recordedQueryDetailsLink = $GLOBALS['MCONF']['_'] . '&SET[function]=queryversioninginfo&CMD=queryVersioningDetails&tx_st9fissync[qvId]=' . $indexedDAMResT3QueryRecordedRow[$this->queryVersioningTable .  '_uid'];
                    $table[$tr][] = '<a href="' . htmlspecialchars($recordedQueryDetailsLink) . '" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_common.xml:details', TRUE) . '" class="icon">' . t3lib_iconWorks::getSpriteIcon('actions-document-info') . '</a>';

                    $table[$tr][] = $indexedDAMResT3QueryRecordedRow[$this->queryVersioningTable .  '_uid'];
                    $table[$tr][] = $indexedDAMResT3QueryRecordedRow[$this->queryVersioningTable .  '_sysid'];
                    $table[$tr][] = $indexedDAMResT3QueryRecordedRow['tx_dam_uid'];
                    $table[$tr][] = $indexedDAMResT3QueryRecordedRow['tx_dam_pid'];
                    $table[$tr][] = $indexedDAMResT3QueryRecordedRow['tx_dam_title'];
                    $table[$tr][] = $indexedDAMResT3QueryRecordedRow['tx_dam_tstamp'];
                    $table[$tr][] = $indexedDAMResT3QueryRecordedRow['tx_dam_crdate'];
                    $table[$tr][] = $indexedDAMResT3QueryRecordedRow['tx_dam_cruser_id'];
                    $table[$tr][] = $indexedDAMResT3QueryRecordedRow['tx_dam_deleted'];
                    $table[$tr][] = $indexedDAMResT3QueryRecordedRow['tx_dam_media_type'];
                    $table[$tr][] = $indexedDAMResT3QueryRecordedRow['tx_dam_category'];
                    $table[$tr][] = $indexedDAMResT3QueryRecordedRow['tx_dam_index_type'];
                    $table[$tr][] = $indexedDAMResT3QueryRecordedRow['tx_dam_file_type'];
                    $table[$tr][] = $indexedDAMResT3QueryRecordedRow['tx_dam_file_name'];
                    $table[$tr][] = $indexedDAMResT3QueryRecordedRow['tx_dam_file_path'];
                    $table[$tr][] = $indexedDAMResT3QueryRecordedRow['tx_dam_file_size'];
                    $table[$tr][] = $indexedDAMResT3QueryRecordedRow['tx_dam_file_mtime'];
                    $table[$tr][] = $indexedDAMResT3QueryRecordedRow['tx_dam_file_ctime'];

                    $tr++;
                }

                $sectionContent  .= '<div>' . tx_st9fissync_mod_sync_statistics::addAppMessage($GLOBALS['LANG']->getLL('appmessage.damreslisting.infoscreenintro'),TRUE,t3lib_FlashMessage::INFO) . '</div>';
                $sectionContent .= $this->doc->spacer(5);
                $sectionContent .= $this->doc->table($table, $tableLayout);

            } else {
                //Nothing found
                $sectionContent .=  tx_st9fissync_mod_sync_statistics::addAppMessage($GLOBALS['LANG']->getLL('appmessage.damresources.noresults'),TRUE,t3lib_FlashMessage::NOTICE);
            }

        }

        return $sectionContent;

    }

    /**
     *
     * To be moved to tx_st9fissync
     * @param  string $microtime
     * @return string
     */
    public function microDateTime($microtime,$timeStampLengthDelim = 10)
    {
        $microtimestrlen = strlen($microtime);
        $secsToAdd = 0;//default value to be added to seconds
        if ($microtimestrlen > $timeStampLengthDelim) {
            $microSec = substr($microtime, ($timeStampLengthDelim - $microtimestrlen));
            $secsToAdd = $microSec * pow(10, -6);
        }
        $timeStamp =  substr($microtime, 0, $timeStampLengthDelim);
        //date('F jS, Y, H:i:', $timeStamp) . (date('s', $timeStamp) + $microSec);
        return date($GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'],$timeStamp) . ':'  .(date('s', $timeStamp) + $secsToAdd) ."\n". date($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'],$timeStamp);
    }

    /**
     * Adds items to the $QVIFunc_MENU array. Used for the Query Versioning Info function filters.
     *
     * @return array
     */
    public function QVInfoFuncFiltersConfig()
    {
        return $QVIFunc_MENU = array(
                'qvifunc_users' => array(
                        '-99' => $GLOBALS['LANG']->getLL('any'),
                        'self' => $GLOBALS['LANG']->getLL('users.self'),
                        0 => $GLOBALS['LANG']->getLL('users.unknown')
                ),
                'qvifunc_workspaces' => $this->getT3WsMapping(),
                'qvifunc_time' => array(
                        0 => $GLOBALS['LANG']->getLL('time.thisWeek'),
                        1 => $GLOBALS['LANG']->getLL('time.lastWeek'),
                        2 => $GLOBALS['LANG']->getLL('time.last7Days'),
                        10 => $GLOBALS['LANG']->getLL('time.thisMonth'),
                        11 => $GLOBALS['LANG']->getLL('time.lastMonth'),
                        12 => $GLOBALS['LANG']->getLL('time.last31Days'),
                        20 => $GLOBALS['LANG']->getLL('time.noLimit'),
                        30 => $GLOBALS['LANG']->getLL('time.userdefined')
                ),
                'qvifunc_max' => array(
                        20 => $GLOBALS['LANG']->getLL('max.20'),
                        50 => $GLOBALS['LANG']->getLL('max.50'),
                        100 => $GLOBALS['LANG']->getLL('max.100'),
                        200 => $GLOBALS['LANG']->getLL('max.200'),
                        500 => $GLOBALS['LANG']->getLL('max.500'),
                        1000 => $GLOBALS['LANG']->getLL('max.1000'),
                        1000000 => $GLOBALS['LANG']->getLL('any')
                ),
                'qvifunc_action' => $this->getQueryTypeMapping(),
                'qvifunc_typo3_mode' => $this->getT3ModeMapping(),
                'qvifunc_manualdate' => '',
                'qvifunc_manualdate_end' => '',

                'qvifunc_issynced'=> '',
                'qvifunc_issyncscheduled' => '',
                'qvifunc_iserror' => '',
                'qvifunc_listfilestobesynced' => '',

        );
    }

    public function getT3WsMapping($wsid = NULL)
    {
        if (is_null($this->wsMapping) && !is_array($this->wsMapping)) {
            $this->wsMapping = array(
                    '-99' => $GLOBALS['LANG']->getLL('any'),
                    0 => $GLOBALS['LANG']->getLL('workspaces.live'),
                    '-1' => $GLOBALS['LANG']->getLL('workspaces.draft'),
            );

            // Add custom workspaces (selecting all, filtering by BE_USER check):
            if (t3lib_extMgm::isLoaded('workspaces')) {
                $workspaces = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid,title','sys_workspace','pid=0'.t3lib_BEfunc::deleteClause('sys_workspace'),'','title');
                if (count($workspaces)) {
                    foreach ($workspaces as $rec) {
                        $this->wsMapping[$rec['uid']] = $rec['uid'].': '.$rec['title'];
                    }
                }
            }

        }

        if ($wsid == NULL) {
            return $this->wsMapping;
        } else {
            return $this->wsMapping[$wsid];
        }

    }

    public function getT3ModeMapping($mode = NULL)
    {
        if ($mode==NULL) {
            return array(
                    0 => $GLOBALS['LANG']->getLL('any'),
                    TYPO3_REQUESTTYPE_FE => $GLOBALS['LANG']->getLL('t3mode.fe'),
                    TYPO3_REQUESTTYPE_BE => $GLOBALS['LANG']->getLL('t3mode.be'),
                    6 => $GLOBALS['LANG']->getLL('t3mode.be.cli'),
                    10 => $GLOBALS['LANG']->getLL('t3mode.be.ajax'),
                    TYPO3_REQUESTTYPE_CLI => $GLOBALS['LANG']->getLL('t3mode.cli'),
                    TYPO3_REQUESTTYPE_AJAX => $GLOBALS['LANG']->getLL('t3mode.ajax'),
                    TYPO3_REQUESTTYPE_INSTALL => $GLOBALS['LANG']->getLL('t3mode.install')
            );
        } else {
            switch ($mode) {
                case TYPO3_REQUESTTYPE_FE:
                    return $GLOBALS['LANG']->getLL('t3mode.fe');
                case TYPO3_REQUESTTYPE_BE:
                    return $GLOBALS['LANG']->getLL('t3mode.be');
                case TYPO3_REQUESTTYPE_CLI:
                    return $GLOBALS['LANG']->getLL('t3mode.cli');
                case TYPO3_REQUESTTYPE_AJAX:
                    return $GLOBALS['LANG']->getLL('t3mode.ajax');
                case TYPO3_REQUESTTYPE_INSTALL:
                    return $GLOBALS['LANG']->getLL('t3mode.install');
                case 6:
                    return $GLOBALS['LANG']->getLL('t3mode.be.cli');
                case 10:
                    return $GLOBALS['LANG']->getLL('t3mode.be.ajax');
                default:
                    //developer log this -- '"' . $cmd . '" is not a command type: '. $executedQuery;
                    break;
            }
        }

        return $GLOBALS['LANG']->getLL('t3mode.undetermined');// undetermined type, implement parsing query later

    }

    public function getQueryTypeMapping($queryType=NULL)
    {
        if ($queryType==NULL) {
            return array(
                    0 => $GLOBALS['LANG']->getLL('any'),
                    1 => $GLOBALS['LANG']->getLL('dbaction.insert'),
                    2 => $GLOBALS['LANG']->getLL('dbaction.multiinsert'),
                    3 => $GLOBALS['LANG']->getLL('dbaction.update'),
                    4 => $GLOBALS['LANG']->getLL('dbaction.delete'),
                    5 => $GLOBALS['LANG']->getLL('dbaction.truncate'),
            );
        } else {
            switch ($queryType) {
                case 1:
                    return $GLOBALS['LANG']->getLL('dbaction.insert');
                case 2:
                    return $GLOBALS['LANG']->getLL('dbaction.multiinsert');
                case 3:
                    return $GLOBALS['LANG']->getLL('dbaction.update');
                case 4:
                    return $GLOBALS['LANG']->getLL('dbaction.delete');
                case 5:
                    return $GLOBALS['LANG']->getLL('dbaction.truncate');
                default:
                    //developer log this -- '"' . $cmd . '" is not a command type: '. $executedQuery;
                    break;
            }
        }

        return $GLOBALS['LANG']->getLL('dbaction.undetermined');// undetermined type, implement parsing query later
    }

    /**
     * Gets the function menu selector for this backend module.
     *
     * @return string The HTML representation of the function menu selector
     */
    protected function compileQVInfoFuncFilters()
    {
        $QVIFunc_MENU = $this->QVInfoFuncFiltersConfig();

        // Adding groups to the users_array
        $begroups = t3lib_BEfunc::getGroupNames();
        if (is_array($begroups)) {
            foreach ($begroups as $beGrVals) {
                $QVIFunc_MENU['qvifunc_users']['begr-' . $beGrVals['uid']] = $GLOBALS['LANG']->getLL('begroup') . ' ' . $beGrVals['title'];
            }
        }

        $beusers = t3lib_BEfunc::getUserNames();
        if (is_array($beusers)) {
            foreach ($beusers as $beUsVals) {
                $QVIFunc_MENU['qvifunc_users']['beus-' . $beUsVals['uid']] = $GLOBALS['LANG']->getLL('beuser') . ' ' . $beUsVals['username'];
            }
        }

        if (is_array($this->activeFEGroups)) {
            foreach ($this->activeFEGroups as $feGrVals) {
                $QVIFunc_MENU['qvifunc_users']['fegr-' . $feGrVals['uid']] = $GLOBALS['LANG']->getLL('fegroup') . ' ' . $feGrVals['title'];
            }
        }

        if (is_array($this->activeFEUsers)) {
            foreach ($this->activeFEUsers as $feUsVals) {
                $QVIFunc_MENU['qvifunc_users']['feus-' . $feUsVals[$this->feUserObj->userid_column]] = $GLOBALS['LANG']->getLL('feuser') . ' ' . $feUsVals[$this->feUserObj->username_column];
            }
        }

        $QVIFunc_SETTINGS = t3lib_BEfunc::getModuleData($QVIFunc_MENU, t3lib_div::_GP('SET'), $this->MCONF['name']);

        $pageRenderer = $this->doc->getPageRenderer();
        $pageRenderer->loadExtJS();
        $pageRenderer->addJsFile($this->backPath . '../t3lib/js/extjs/tceforms.js');

        // Define settings for Date Picker
        /* $typo3Settings = array(
                'datePickerUSmode' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['USdateFormat'] ? 1 : 0,
                'dateFormat'       => array('j-n-Y', 'G:i j-n-Y'),
                'dateFormatUS'     => array('n-j-Y', 'G:i n-j-Y'),
        ); */
        $typo3Settings = array(
                'datePickerUSmode' => 0,
                'dateFormat'       => array($this->dateFormat, $this->dateTimeFormat),
        );
        $pageRenderer->addInlineSettingArray('', $typo3Settings);

        // manual dates
        if ($QVIFunc_SETTINGS['qvifunc_time'] == 30) {
            if (!trim($QVIFunc_SETTINGS['qvifunc_manualdate'])) {
                $this->theTime = $QVIFunc_SETTINGS['qvifunc_manualdate'] = 0;
            } else {
                $this->theTime = $this->parseDate($QVIFunc_SETTINGS['qvifunc_manualdate']);
                if (!$this->theTime) {
                    $QVIFunc_SETTINGS['qvifunc_manualdate'] = '';
                } else {
                    $QVIFunc_SETTINGS['qvifunc_manualdate'] = date($this->dateTimeFormat, $this->theTime);
                }
            }

            if (!trim($QVIFunc_SETTINGS['qvifunc_manualdate_end'])) {
                $this->theTime_end = $QVIFunc_SETTINGS['qvifunc_manualdate_end'] = 0;
            } else {
                $this->theTime_end = $this->parseDate($QVIFunc_SETTINGS['qvifunc_manualdate_end']);
                if (!$this->theTime_end) {
                    $QVIFunc_SETTINGS['qvifunc_manualdate_end'] = '';
                } else {
                    $QVIFunc_SETTINGS['qvifunc_manualdate_end'] = date($this->dateTimeFormat, $this->theTime_end);
                }
            }
        }

        // Filter functions compiled:
        $filterFunctionsU= t3lib_BEfunc::getFuncMenu(0,'SET[qvifunc_users]',$QVIFunc_SETTINGS['qvifunc_users'],$QVIFunc_MENU['qvifunc_users']);
        $filterFunctionsM= t3lib_BEfunc::getFuncMenu(0,'SET[qvifunc_max]',$QVIFunc_SETTINGS['qvifunc_max'],$QVIFunc_MENU['qvifunc_max']);
        $filterFunctionsT= t3lib_BEfunc::getFuncMenu(0,'SET[qvifunc_time]',$QVIFunc_SETTINGS['qvifunc_time'],$QVIFunc_MENU['qvifunc_time']);
        $filterFunctionsA= t3lib_BEfunc::getFuncMenu(0,'SET[qvifunc_action]',$QVIFunc_SETTINGS['qvifunc_action'],$QVIFunc_MENU['qvifunc_action']);
        $filterFunctionsW= t3lib_BEfunc::getFuncMenu(0,'SET[qvifunc_workspaces]',$QVIFunc_SETTINGS['qvifunc_workspaces'],$QVIFunc_MENU['qvifunc_workspaces']);
        $filterFunctionsT3M= t3lib_BEfunc::getFuncMenu(0,'SET[qvifunc_typo3_mode]',$QVIFunc_SETTINGS['qvifunc_typo3_mode'],$QVIFunc_MENU['qvifunc_typo3_mode']);

        $qvifuncIsSynced = t3lib_BEfunc::getFuncCheck(0, 'SET[qvifunc_issynced]',$QVIFunc_SETTINGS['qvifunc_issynced']);
        $qvifuncIsSyncScheduled = t3lib_BEfunc::getFuncCheck(0, 'SET[qvifunc_issyncscheduled]',$QVIFunc_SETTINGS['qvifunc_issyncscheduled']);
        $qvifuncIsError = t3lib_BEfunc::getFuncCheck(0, 'SET[qvifunc_iserror]',$QVIFunc_SETTINGS['qvifunc_iserror']);
        $qvifuncListFilesToBeSynced = t3lib_BEfunc::getFuncCheck(0, 'SET[qvifunc_listfilestobesynced]',$QVIFunc_SETTINGS['qvifunc_listfilestobesynced']);

        $style = ' style="margin:4px 2px;padding:1px;vertical-align:middle;width: 115px;"';

        $inputDate = '<input type="text" value="' . ($QVIFunc_SETTINGS['qvifunc_manualdate'] ? $QVIFunc_SETTINGS['qvifunc_manualdate'] : '') .'" name="SET[qvifunc_manualdate]" id="tceforms-datetimefield-qvifunc_manualdate"' . $style . ' />';
        $pickerInputDate = t3lib_iconWorks::getSpriteIcon(
                'actions-edit-pick-date',
                array(
                        'style' => 'cursor:pointer;',
                        'id' => 'picker-tceforms-datetimefield-qvifunc_manualdate'
                )
        );

        $inputDate_end = '<input type="text" value="' . ($QVIFunc_SETTINGS['qvifunc_manualdate_end'] ? $QVIFunc_SETTINGS['qvifunc_manualdate_end'] : '') .'" name="SET[qvifunc_manualdate]" id="tceforms-datetimefield-qvifunc_manualdate_end"' . $style . ' />';
        $pickerInputDate_end = t3lib_iconWorks::getSpriteIcon(
                'actions-edit-pick-date',
                array(
                        'style' => 'cursor:pointer;',
                        'id' => 'picker-tceforms-datetimefield-qvifunc_manualdate_end'
                )
        );

        $setButton = '<input type="button" value="' . $GLOBALS['LANG']->getLL('set') . '" onclick="jumpToUrl(\'' . htmlspecialchars($this->thisScript) . '&amp;SET[qvifunc_manualdate]=\'+escape($(\'tceforms-datetimefield-qvifunc_manualdate\').value)+\'&amp;SET[qvifunc_manualdate_end]=\'+escape($(\'tceforms-datetimefield-qvifunc_manualdate_end\').value),this);" />';

        $filterFunctionsMenu = $this->doc->section($GLOBALS['LANG']->getLL('function.queryversioninginfo.filtercriterion'),$this->doc->menuTable(
                array(
                        array($GLOBALS['LANG']->getLL('users'), $filterFunctionsU),
                        array($GLOBALS['LANG']->getLL('time'), $filterFunctionsT . ($QVIFunc_SETTINGS['qvifunc_time'] == 30 ?
                                '<br />' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_common.xml:from', true) . ' ' . $inputDate . $pickerInputDate .
                                ' ' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_common.xml:to', true) . ' ' . $inputDate_end . $pickerInputDate_end . '&nbsp;' . $setButton : ''))
                ),
                array(
                        array($GLOBALS['LANG']->getLL('max'), $filterFunctionsM),
                        array($GLOBALS['LANG']->getLL('action'), $filterFunctionsA)
                ),
                array(
                        $GLOBALS['BE_USER']->workspace !== 0 ? array($GLOBALS['LANG']->getLL('workspaces'), '<strong>'.$GLOBALS['BE_USER']->workspace . '</strong>') : array($GLOBALS['LANG']->getLL('workspaces'), $filterFunctionsW),
                        array($GLOBALS['LANG']->getLL('t3mode'), $filterFunctionsT3M),
                        array($GLOBALS['LANG']->getLL('isSynced'), $qvifuncIsSynced),
                        array($GLOBALS['LANG']->getLL('isSyncScheduled'), $qvifuncIsSyncScheduled),
                        array($GLOBALS['LANG']->getLL('isError'), $qvifuncIsError),
                        array($GLOBALS['LANG']->getLL('listdamindexedfiles'),$qvifuncListFilesToBeSynced)
                )
        ));

        //restore and load the newer settings
        $this->MOD_SETTINGS = $QVIFunc_SETTINGS;

        return $filterFunctionsMenu;
    }

    /**
     * Parse the manual date
     *
     * @param  string $date
     * @return int    timestamp
     */
    public function parseDate($date)
    {
        if (strpos($date, ' ') === FALSE) {
            $date .= ' 0:00';
        }

        $parts = t3lib_div::trimExplode(' ', $date, TRUE);
        $dateParts = preg_split('/[-\.\/]/', $parts[0]);

        if (count($dateParts) < 3) {
            return 0;
        }
        $timeParts = preg_split('/[\.:]/', $parts[1]);

        return mktime($timeParts[0], $timeParts[1], 0, $dateParts[1], $dateParts[0], $dateParts[2]);
    }

    /**
     * This method is used to add a message to the internal queue
     *
     * @param	string	the message itself
     * @param	integer	message level (-1 = success (default), 0 = info, 1 = notice, 2 = warning, 3 = error)
     * @return string $appFlashMsg->render() if $renderInline is TRUE or just add to the queue
     */
    public function addAppMessage($message, $renderInline = TRUE, $severity = t3lib_FlashMessage::OK)
    {
        $appFlashMsg= t3lib_div::makeInstance(
                't3lib_FlashMessage',
                $message,
                '',
                $severity
        );

        if ($renderInline) {
            return $appFlashMsg->render();
        }

        t3lib_FlashMessageQueue::addMessage($appFlashMsg);
    }

    /**
     * Function for getting the sync process button
     *
     * @param $activeSyncProcesses
     * @return string
     */
    public function getSyncProcessButton($activeSyncProcesses)
    {
        // Check if currently sync is running
        $btnSyncDisabled = '';
        $syncStatus = 'notrunning';
        $btnSyncLabel = $GLOBALS['LANG']->getLL('mod.sync.btn_start_synchronization');
        if ($activeSyncProcesses && count($activeSyncProcesses) > 0) {
            $btnSyncDisabled = 'disabled';
            $syncStatus = 'running';
            $btnSyncLabel = $GLOBALS['LANG']->getLL('mod.sync.btn_executing_synchronization');
        }

        // Sync process button
        $btnSync = '<input type="hidden" name="tx_st9fissync[sync_status]" value="'.$syncStatus.'" /> <input type="submit" name="tx_st9fissync[start_sync]" value="'.$btnSyncLabel.'" '.$btnSyncDisabled.' />';
        $content = $this->doc->section('', $btnSync);

        return $content;
    }

    /**
     * Function for checking/displaying sync setup/configuration
     *
     * @return string
     */
    public function displaySyncSetupCheckScreen()
    {
        // Getting server configuration settings
        $content = $this->getServerConfiguration();

        // Checking soap secure connection
        $content .= $this->checkSoapSecureConnection();

        // Checking BE User login authentication
        $content .= $this->checkBEUserSoapAuthentication();

        // Sending test email
        $content .= $this->sendTestEmailForSyncSetupCheck();

        return $content;
    }

    /**
     * Function for getting the server configuration
     *
     * @return string
     */
    protected function getServerConfiguration()
    {
        // Getting the sync configuration object
        $objSyncConfig = tx_st9fissync::getInstance()->getSyncConfigManager();

        // Table Layout
        $tableLayout = array (
                'table' => array ('<table border="0" cellspacing="1" cellpadding="2" class="typo3-dblist">', '</table>'),
                '0' => array (
                        'tr' => array('<tr class="t3-row-header" valign="top">', '</tr>'),
                        'defCol' => array('<td>', '</td>')
                ),
                'defRow' => array (
                        'tr' => array('<tr class="db_list_normal">', '</tr>'),
                        'defCol' => array('<td>', '</td>')
                )
        );

        // Table row array
        $tableRows = array();
        $tr = 0;

        // Header row
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.configuration');
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.values');

        // Sequencer Tables
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.sequencer_tables');
        $tableRows[$tr][] = str_replace(',', '<br/>', $objSyncConfig->getSequencerEnabledTablesList(false));

        // Versioning Tables
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.versioning_tables');
        $tableRows[$tr][] = str_replace(',', '<br/>', $objSyncConfig->getVersionEnabledTablesList(false));

        // Ignore Versioning Tables
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.ignore_versioning_tables');
        $tableRows[$tr][] = str_replace(',', '<br/>', $objSyncConfig->getVersionDisabledTablesList(false));

        // Versioning Root
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.versioning_roots');
        $tableRows[$tr][] = str_replace(',', '<br/>', $objSyncConfig->getVersionEnabledRootPidList(false));

        // Exclude Versioning Root
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.excluded_versioning_roots');
        $tableRows[$tr][] = str_replace(',', '<br/>', $objSyncConfig->getVersionExcludePidList(false));

        // Ignore Logging Tables
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.ignore_tables_for_logging');
        $tableRows[$tr][] = str_replace(',', '<br/>', $objSyncConfig->getLoggerDisabledTablesList(false));

        // Logger Priority
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.db_logger_priority');
        $tableRows[$tr][] = $objSyncConfig->getSyncLoggerPriority();

        // Remote system url
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.remote_system_url');
        $tableRows[$tr][] = $objSyncConfig->getRemoteURL();

        // Remote system http login
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.remote_system_http_login');
        $tableRows[$tr][] = $objSyncConfig->getRemoteHttpLogin();

        // Remote system http password
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.remote_system_http_password');
        $tableRows[$tr][] = $objSyncConfig->getRemoteHttpPassword();

        // BE User right
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.beuser_rights_for_synchronization');
        $tableRows[$tr][] = $objSyncConfig->getRemoteSyncBEUser();

        // BE User password
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.beuser_password_for_synchronization');
        $tableRows[$tr][] = $objSyncConfig->getRemoteSyncBEPassword();

        // No of query to sync per request
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.no_of_query_sync_per_request');
        $tableRows[$tr][] = $objSyncConfig->getSyncQuerySetBatchSize();

        // Size of file
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.size_of_file');
        $tableRows[$tr][] = $objSyncConfig->getSyncFileSetMaxSize();

        // Notification Email
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.notification_email');
        foreach ($objSyncConfig->getSyncNotificationEMail() as $notifyEmail) {
            $strNotificateEmail .= $notifyEmail."<br/>";
        }
        $tableRows[$tr][] = $strNotificateEmail;

        // Sender Email
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.notification_senderemail');
        $tableRows[$tr][] = str_replace(',', '<br/>', $objSyncConfig->getSyncSenderEMail(false));

        // Email Subject
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.notification_emailsubject');
        $tableRows[$tr][] = $objSyncConfig->getSyncNotificationEMailSubject();

        // Test Email Sender Email
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.testemail_sender');
        $tableRows[$tr][] = $objSyncConfig->getSyncTestEmailSenderEMail();

        // Test Email Recipients
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.testemail_recipients');
        foreach ($objSyncConfig->getSyncTestEMail() as $testEmailAddress) {
            $strTestEmailAddress .= $testEmailAddress."<br/>";
        }
        $tableRows[$tr][] = $strTestEmailAddress;

        // Test Email Subject
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.testemail_subject');
        $tableRows[$tr][] = $objSyncConfig->getSyncTestEmailSubject();

        $content  = $this->doc->spacer(5);
        $content .= '<div>' . $GLOBALS['LANG']->getLL('mod.sync.setupcheck.header_configuration') . '</div>';
        $content .= $this->doc->spacer(2);
        $content .= $this->doc->table($tableRows, $tableLayout);

        return $content;
    }

    /**
     * Function for getting soap secure connection
     *
     * @return boolean|soap connection
     */
    protected function getSoapSecureConnection()
    {
        $secureConn = false;

        try {
            if ($this->objSoapClient == null) {
                // Creating Sync Api Instance
                $objSync = tx_st9fissync::getInstance();

                // Creating SOAP Client instance
                $this->objSoapClient = $objSync->getSyncSOAPClient();

                if (!is_null($this->objSoapClient)) {
                    if ($objSync->isSOAPSecured($this->objSoapClient->isSOAPSecure())) {
                        $secureConn = $objSync->getSyncSOAPClient(true);
                    } else {
                        $this->objSoapClient = null;
                    }
                }
            } else {
                $secureConn = $this->objSoapClient;
            }
        } catch (SoapFault $s) {
            // Soap error
        }

        return $secureConn;
    }

    /**
     * Function for checking soap secure connection
     *
     * @return string
     */
    protected function checkSoapSecureConnection()
    {
        // Getting soap connection
        $soapSecureConn = $this->getSoapSecureConnection();

        if ($soapSecureConn) {
            $soapConnStatus = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.soap_successful_secure_connection');
        } else {
            $soapConnStatus = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.soap_failed_secure_connection');
        }

        // Table Layout
        $tableLayout = array (
                'table' => array ('<table border="0" cellspacing="1" cellpadding="2" class="typo3-dblist">', '</table>'),
                '0' => array (
                        'tr' => array('<tr class="t3-row-header" valign="top">', '</tr>'),
                        'defCol' => array('<td>', '</td>')
                ),
                'defRow' => array (
                        'tr' => array('<tr class="db_list_normal">', '</tr>'),
                        'defCol' => array('<td>', '</td>')
                )
        );

        // Table row array
        $tableRows = array();
        $tr = 0;

        // Header row
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.soap_connectionstatus');

        // Soap status
        $tr++;
        $tableRows[$tr][] = $soapConnStatus;

        $content  = $this->doc->spacer(10);
        $content .= '<div>' . $GLOBALS['LANG']->getLL('mod.sync.setupcheck.header_soapconnection') . '</div>';
        $content .= $this->doc->spacer(2);
        $content .= $this->doc->table($tableRows, $tableLayout);

        return $content;
    }

    /**
     * Function for checking BE User soap authentication
     */
    protected function checkBEUserSoapAuthentication()
    {
        $soapBEUserLogin = false;

        try {
            // Getting soap connection
            $soapSecureConn = $this->getSoapSecureConnection();

            if ($soapSecureConn) {
                // Getting the sync configuration object
                $objSyncConfig = tx_st9fissync::getInstance()->getSyncConfigManager();

                // BE User Soap Login
                $syncSessionId = $soapSecureConn->login($objSyncConfig->getRemoteSyncBEUser(), $objSyncConfig->getRemoteSyncBEPassword());

                if ($syncSessionId && trim($syncSessionId) != '') {
                    $soapBEUserLogin = true;
                }
            }
        } catch (Exception $e) {
            // Error
        }

        if ($soapSecureConn) {
            if ($soapBEUserLogin) {
                $soapConnStatus = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.soap_beuser_successful_login');
            } else {
                $soapConnStatus = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.soap_beuser_failed_login');
            }
        } else {
            $soapConnStatus = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.soap_beuser_failed_login');
        }

        // Table Layout
        $tableLayout = array (
                'table' => array ('<table border="0" cellspacing="1" cellpadding="2" class="typo3-dblist">', '</table>'),
                '0' => array (
                        'tr' => array('<tr class="t3-row-header" valign="top">', '</tr>'),
                        'defCol' => array('<td>', '</td>')
                ),
                'defRow' => array (
                        'tr' => array('<tr class="db_list_normal">', '</tr>'),
                        'defCol' => array('<td>', '</td>')
                )
        );

        // Table row array
        $tableRows = array();
        $tr = 0;

        // Header row
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.soap_beuser_loginstatus');

        // Soap status
        $tr++;
        $tableRows[$tr][] = $soapConnStatus;

        $content  = $this->doc->spacer(10);
        $content .= '<div>' . $GLOBALS['LANG']->getLL('mod.sync.setupcheck.header_beuserlogin') . '</div>';
        $content .= $this->doc->spacer(2);
        $content .= $this->doc->table($tableRows, $tableLayout);

        return $content;
    }

    /**
     * Function for sending test email
     */
    protected function sendTestEmailForSyncSetupCheck()
    {
        // Creating sync object
        $objSync = tx_st9fissync::getInstance();

        // Getting the sync configuration object
        $objSyncConfig = $objSync->getSyncConfigManager();

        // Test email message
        $messageBody = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.test_email_message');

        // Process email template
        $templateFileContent = $objSync->getEmailTemplateFileContent('sync_test_email.html');
        if ($templateFileContent != '') {
            $message = $objSync->processEmailTemplateContent($templateFileContent, array('###SYNCHRONIZATION_TEST_EMAIL_MESSAGES###'=>$messageBody));
        } else {
            $message = $messageBody;
        }

        $subject = $objSyncConfig->getSyncTestEmailSubject();
        $recipients = $objSyncConfig->getSyncTestEMail();
        $senderEmail = $objSyncConfig->getSyncTestEmailSenderEMail();

        if (trim($subject) != '' && is_array($recipients) && count($recipients) > 0 && trim($senderEmail) != "") {
            foreach ($recipients as $recipient) {
                if (trim($recipient) != '') {
                    $strEmailAddress .= $recipient . "<br/>";
                    tx_st9fisutility_base::sendNotificationEmail($recipient, $subject, $message, $senderEmail);
                }
            }
        }

        // Table Layout
        $tableLayout = array (
                'table' => array ('<table border="0" cellspacing="1" cellpadding="2" class="typo3-dblist">', '</table>'),
                '0' => array (
                        'tr' => array('<tr class="t3-row-header" valign="top">', '</tr>'),
                        'defCol' => array('<td>', '</td>')
                ),
                'defRow' => array (
                        'tr' => array('<tr class="db_list_normal">', '</tr>'),
                        'defCol' => array('<td>', '</td>')
                )
        );

        // Table row array
        $tableRows = array();
        $tr = 0;

        // Header row
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.test_email_sendstatus');

        // Test email status
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.setupcheck.test_email_statusmessage') . "<br/><br/>" . $strEmailAddress;

        $content  = $this->doc->spacer(10);
        $content .= '<div>' . $GLOBALS['LANG']->getLL('mod.sync.setupcheck.test_email_header') . '</div>';
        $content .= $this->doc->spacer(2);
        $content .= $this->doc->table($tableRows, $tableLayout);

        return $content;
    }

    /**
     * Function for getting Garbage Collector form
     *
     * @param $activeGCProcesses
     * @return string
     */
    public function getGarbageCollectorForm($activeGCProcesses)
    {
        // Check if currently sync gc is running
        $btnGCDisabled = '';
        $gcStatus = 'notrunning';
        $btnGCLabel = $GLOBALS['LANG']->getLL('mod.sync.gc.btn_start_gc');
        if (!$activeGCProcesses) {
            $btnGCDisabled = 'disabled';
            $gcStatus = 'running';
            $btnGCLabel = $GLOBALS['LANG']->getLL('mod.sync.gc.btn_running_gc');
        }

        // Table Layout
        $tableLayout = array (
                'table' => array ('<table border="0" cellspacing="1" cellpadding="2" class="typo3-dblist">', '</table>'),
                '0' => array (
                        'tr' => array('<tr class="t3-row-header" valign="top">', '</tr>'),
                        'defCol' => array('<td>', '</td>')
                ),
                'defRow' => array (
                        'tr' => array('<tr class="db_list_normal">', '</tr>'),
                        'defCol' => array('<td>', '</td>')
                )
        );

        // Table row array
        $tableRows = array();
        $tr = 0;

        // Header row
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.gc.header_cleanup');

        // Sync cleanup date
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.gc.till_date').': <input type="text" name="tx_st9fissync[till_date]" id="tceforms-datetimefield-till_date" />';

        // Sync till date note
        $tr++;
        $tableRows[$tr][] = $GLOBALS['LANG']->getLL('mod.sync.gc.till_date_less_than');

        // Sync button
        $tr++;
        $tableRows[$tr][] = '<input type="hidden" name="tx_st9fissync[gc_status]" value="'.$gcStatus.'" /> <input type="submit" name="tx_st9fissync[start_gc]" value="'.$btnGCLabel.'" '.$btnGCDisabled.'/>';

        $content .= $this->doc->spacer(2);
        $content .= $this->doc->table($tableRows, $tableLayout);

        return $content;
    }
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/st9fissync/mod_sync/class.tx_st9fissync_mod_sync_statistics.php']) {
    include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/st9fissync/mod_sync/class.tx_st9fissync_mod_sync_statistics.php']);
}

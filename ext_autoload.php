<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

$libPath = t3lib_extMgm::extPath('lib');
$extPath = t3lib_extMgm::extPath('st9fissync');

return array(
        'tx_lib_object'				=> $libPath . 'class.tx_lib_object.php',
        'tx_lib_controller'			=> $libPath . 'class.tx_lib_controller.php',
        'tx_lib_phpTemplateEngine'	=> $libPath . 'class.tx_lib_phpTemplateEngine.php',

        'tx_st9fissync_model_resyncentries'		=> $extPath . 'models/class.tx_st9fissync_model_resyncentries.php',
        'tx_st9fissync_lib_queue'		=> $extPath . 'lib/class.tx_st9fissync_lib_queue.php',

        //sync api
        'tx_st9fissync' => $extPath . 'lib/class.tx_st9fissync.php',
        'tx_st9fissync_db' => $extPath . 'lib/class.tx_st9fissync_db.php',
        'tx_st9fissync_config' => $extPath . 'lib/class.tx_st9fissync_config.php',
        'tx_st9fissync_message' => $extPath . 'lib/class.tx_st9fissync_message.php',
        'tx_st9fissync_messagequeue' => $extPath . 'lib/class.tx_st9fissync_messagequeue.php',

        //sync security
        'tx_st9fissync_caretakerinstance_servicefactory' => $extPath . 'lib/caretakerinstance/class.tx_st9fissync_caretakerinstance_servicefactory.php',
        'tx_st9fissync_caretakerinstance_opensslcryptomanager' => $extPath . 'lib/caretakerinstance/class.tx_st9fissync_caretakerinstance_opensslcryptomanager.php',
        'tx_st9fissync_caretakerinstance_securitymanager'  => $extPath . 'lib/caretakerinstance/class.tx_st9fissync_caretakerinstance_securitymanager.php',

        //sequencer
        'tx_st9fissync_dbsequencer_t3service' => $extPath . 'lib/dbsequencer/class.tx_st9fissync_dbsequencer_t3service.php',
        'tx_st9fissync_dbsequencer_sequencer' => $extPath . 'lib/dbsequencer/class.tx_st9fissync_dbsequencer_sequencer.php',

        //versioning
        'tx_st9fissync_dbversioning_t3service' => $extPath . 'lib/dbversioning/class.tx_st9fissync_dbversioning_t3service.php',
        'tx_st9fissync_dbversioning_sqlparser' => $extPath .'lib/dbversioning/sqlparser/class.tx_st9fissync_dbversioning_sqlparser.php',

        //sync process
        'tx_st9fissync_sync' => $extPath . 'lib/class.tx_st9fissync_sync.php',
        'tx_st9fissync_query_dto' => $extPath . 'lib/domain/dto/class.tx_st9fissync_query_dto.php',
        'tx_st9fissync_dam_dto' => $extPath . 'lib/domain/dto/class.tx_st9fissync_dam_dto.php',
        'tx_st9fissync_st9fissupport_dto' =>  $extPath . 'lib/domain/dto/class.tx_st9fissync_st9fissupport_dto.php',
        'tx_st9fissync_resultresponse_dto' => $extPath . 'lib/domain/dto/class.tx_st9fissync_resultresponse_dto.php',

        //T3 SOAP
        //T3 SOAP Client API & Client
        'tx_st9fissync_t3soap_client' => $extPath .'lib/t3soap/class.tx_st9fissync_t3soap_client.php',
        'tx_st9fissync_t3soap_client_common' => $extPath . 'lib/t3soap/client/class.tx_st9fissync_t3soap_client_common.php',

        //T3 SOAP Server API & Server
        'tx_st9fissync_t3soap_server' => $extPath .'lib/t3soap/class.tx_st9fissync_t3soap_server.php',
        'tx_st9fissync_t3soap_application' => $extPath . 'lib/t3soap/class.tx_st9fissync_t3soap_application.php',
        'tx_st9fissync_service_handler' => $extPath . 'lib/t3soap/api/class.tx_st9fissync_service_handler.php',
        'tx_st9fissync_t3soap_auth' => $extPath . 'lib/t3soap/application/class.tx_st9fissync_t3soap_auth.php',
        'tx_st9fissync_t3soap_server_system_calls' => $extPath . 'lib/t3soap/server/class.tx_st9fissync_t3soap_server_system_calls.php',
        'tx_st9fissync_gc_handler' => $extPath . 'lib/t3soap/api/class.tx_st9fissync_gc_handler.php',

        //T3 SOAP Server Exceptions
        'tx_st9fissync_t3soap_server_exception' => $extPath . 'lib/t3soap/server/class.tx_st9fissync_t3soap_server_exceptions.php',
        'tx_st9fissync_t3soap_unauthorized_exception' => $extPath . 'lib/t3soap/server/class.tx_st9fissync_t3soap_server_exceptions.php',
        'tx_st9fissync_t3soap_forbidden_exception' => $extPath . 'lib/t3soap/server/class.tx_st9fissync_t3soap_server_exceptions.php',
        'tx_st9fissync_t3soap_notfound_exception' => $extPath . 'lib/t3soap/server/class.tx_st9fissync_t3soap_server_exceptions.php',
        'tx_st9fissync_t3soap_methodnotallowed_exception' => $extPath . 'lib/t3soap/server/class.tx_st9fissync_t3soap_server_exceptions.php',
        'tx_st9fissync_t3soap_requesttimeout_exception' => $extPath . 'lib/t3soap/server/class.tx_st9fissync_t3soap_server_exceptions.php',
        'tx_st9fissync_t3soap_expectationfailed_exception' => $extPath . 'lib/t3soap/server/class.tx_st9fissync_t3soap_server_exceptions.php',
        'tx_st9fissync_t3soap_exception_unauthorized' => $extPath . 'lib/t3soap/server/class.tx_st9fissync_t3soap_server_exceptions.php',

        //scheduler tasks, Synchronizatin
        'tx_st9fissync_tasks_sync' => $extPath . 'tasks/class.tx_st9fissync_tasks_sync.php',
        'tx_st9fissync_tasks_add_sysrefindex_syncqueue' => $extPath . 'tasks/class.tx_st9fissync_tasks_add_sysrefindex_syncqueue.php',
        'tx_st9fissync_tasks_gc' => $extPath . 'tasks/class.tx_st9fissync_tasks_gc.php',
        'tx_st9fissync_tests' => $extPath . 'tests/class.tx_st9fissync_tests.php',

        //curl request handler, not used/should not be used anymore
        //'tx_st9fissync_curlrequesthandler' =>

        //sync app logger
        'tx_st9fissync_logger' => $extPath . 'lib/class.tx_st9fissync_logger.php',

        //sync all exception
        'tx_st9fissync_exception' => $extPath . 'lib/class.tx_st9fissync_exception.php',

        //model/entities
        'tx_st9fissync_dbversioning_query' => $extPath . 'lib/domain/model/class.tx_st9fissync_dbversioning_query.php',
        'tx_st9fissync_dbversioning_query_mm' => $extPath . 'lib/domain/model/class.tx_st9fissync_dbversioning_query_mm.php',
        'tx_st9fissync_versioningtips'  => $extPath . 'lib/domain/model/class.tx_st9fissync_versioningtips.php',
        'tx_st9fissync_process' => $extPath . 'lib/domain/model/class.tx_st9fissync_process.php',
        'tx_st9fissync_request' => $extPath . 'lib/domain/model/class.tx_st9fissync_request.php',
        'tx_st9fissync_request_dbversioning_query_mm' => $extPath . 'lib/domain/model/class.tx_st9fissync_request_dbversioning_query_mm.php',
        'tx_st9fissync_request_handler' => $extPath . 'lib/domain/model/class.tx_st9fissync_request_handler.php',

        //Garbage collector
        'tx_st9fissync_gc' => $extPath . 'lib/class.tx_st9fissync_gc.php',

        'tx_st9fissync_functests' => $extPath . 'tests/class.tx_st9fissync_tests.php',
);

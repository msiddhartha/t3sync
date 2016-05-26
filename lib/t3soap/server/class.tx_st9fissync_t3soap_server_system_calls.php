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
 * tx_st9fissync.
 *
 *
 * @author	André Spindler <info@studioneun.de>
* @package	TYPO3
* @subpackage	tx_st9fissync
*/

class tx_st9fissync_t3soap_server_system_calls
{
    /**
     * SOAP Server Object
     *
     * @var tx_st9fissync_t3soap_server
     */
    protected $_server;

    /**
     * Overwrites original constructor to implement login and call methods
     *
     * @return void
     * @see Zend_Soap_Server
     */
    public function __construct(tx_st9fissync_t3soap_server $server)
    {
        $this->_server = $server;
    }

    /**
     *
     * @return string|boolean
     */
    public function isSOAPSecure()
    {
        $result = tx_st9fissync::getInstance()->isSOAPSecured();

        return  $result;
    }

    /**
     * Login to TYPO3 with given credentials
     * A valid session ID will be returned on sucessfull login
     *
     * @param  string $username
     * @param  string $password
     * @return string
     */
    public function login($username, $password)
    {
        tx_st9fissync_t3soap_application::$Auth->setCredentials($username, $password);
        $rawReturnval =  tx_st9fissync_t3soap_application::$Auth->login()->id;
        $encryptedReturnval = tx_st9fissync::getInstance()->getEncrypted($rawReturnval);

        return $encryptedReturnval;
    }

    /**
     * Call a function which requires a login with this method.
     * These functions are prefixed with "call." in the method list.
     *
     * The first argument given to this method must be a valid session ID,
     * second the to be instantiated handler object, third the to be called function, fourth an array of arguments.
     * example: call ( $session, 'classname', 'methodname', array($args) )
     *
     * @param string     $session
     * @param string     $handlerClass
     * @param string     $handlerClassMethod
     * @param mixed|void $handlerArgs
     *
     * @return mixed
     */
    public function call($session, $handlerClass, $handlerClassMethod, $handlerArgs = null)
    {
        tx_st9fissync_t3soap_application::$Auth->setSession($session);
        tx_st9fissync_t3soap_application::$Auth->login();

        if (!class_exists($handlerClass)) {
            $handlerNotFoundMessage = "Handler $handlerClass not found";
            //log ??
            throw new tx_st9fissync_t3soap_notfound_exception($handlerNotFoundMessage);
        }

        // Create an instance of the handler
        $obj = t3lib_div::makeInstance($handlerClass);

        if (!method_exists($obj, $handlerClassMethod)) {
            $handlerMethodNotFoundMessage = "Handler method $handlerClassMethod() not found";
            //log ??
            throw new tx_st9fissync_t3soap_notfound_exception($handlerMethodNotFoundMessage);
        }

        $complexResponseResult = array('responseRes' => null,
                'requestHandler' => $this->_server->getLastRequestHandlerProcId());

        try {

            $rawReturnval =  $obj->$handlerClassMethod($handlerArgs);
            $complexResponseResult['responseRes'] = $rawReturnval;

        } catch (tx_st9fissync_t3soap_expectationfailed_exception $ex) {
            //also add this to the error queue
            //Unexpected operation result: $ex->getMessage()
        } catch (Exception $e) {
            //also add this to the error queue
            //Server system call Error:  $e->getMessage()
        }

        $encryptedReturnval = tx_st9fissync::getInstance()->getEncrypted($complexResponseResult);

        return $encryptedReturnval;

    }

    /**
     * Entry point to clear all cache
     *
     *
     * @param string $session
     * @param mixed  $cmdArray
     *
     * @return mixed
     */
    public function purgeVariousCaches($session, $cmdArray = null)
    {
        tx_st9fissync_t3soap_application::$Auth->setSession($session);
        tx_st9fissync_t3soap_application::$Auth->login();

        return tx_st9fissync::getInstance()->getEncrypted(tx_st9fissync::getInstance()->purgeVariousCaches($cmdArray));

    }

}

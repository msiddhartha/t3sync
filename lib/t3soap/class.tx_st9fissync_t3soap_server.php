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
 * T3 SOAP Server API for tx_st9fissync.
 *
 *
 * @author	André Spindler <info@studioneun.de>
* @package	TYPO3
* @subpackage	tx_st9fissync
*/

class tx_st9fissync_t3soap_server extends Zend_Soap_Server
{
    /**
     *
     * @var boolean
     */
    private $_securedRequest = false;

    /**
     * @var int
     */
    private $_lastRequestHandlerProcId = 0;

    /**
     *
     * @param boolean $securedRequest
     */
    public function setRequestSecurity($securedRequest=true)
    {
        $this->_securedRequest = $securedRequest;
    }

    /**
     * @return int
     */
    public function getLastRequestHandlerProcId()
    {
        return intval($this->_lastRequestHandlerProcId);
    }

    /**
     *
     * @param  tx_st9fissync_request_handler $requestHandler
     * @return tx_st9fissync_t3soap_server
     */
    public function setLastRequestHandlerProcId(tx_st9fissync_request_handler $requestHandler)
    {
        if (tx_st9fissync::getInstance()->getSyncDBOperationsManager()->addSyncRequestHandlerArtifact($requestHandler)) {
            $this->_lastRequestHandlerProcId = tx_st9fissync::getInstance()->getSyncDBObject()->sql_insert_id();
        } else {
            $this->_lastRequestHandlerProcId = 0;
        }

        return $this;
    }

    /**
     * Handle a request
     * Decrypts a request
     *
     * If no request is passed, pulls request using php:://input (for
     * cross-platform compatability purposes).
     *
     * @param string $request Optional request
     *
     * @return void|string
     */
    public function handle($request = null)
    {
        if (null === $request) {
            $request = file_get_contents('php://input');
        }

        // Set Zend_Soap_Server error handler
        $displayErrorsOriginalState = $this->_initializeSoapErrorContext();

        if ($this->_securedRequest) {
            //decrypt whole message
            $request = tx_st9fissync::getInstance()->getDecrypted($request);
        }

        $setRequestException = null;

        /**
         * @see Zend_Soap_Server_Exception
         */
        require_once 'Zend/Soap/Server/Exception.php';
        try {
            $this->_setRequest($request);
        } catch (Zend_Soap_Server_Exception $e) {
            $setRequestException = $e;
        }

        $soap = $this->_getSoap();

        //start logging client request here
        $syncRequestHandlerArtifact = tx_st9fissync::getInstance()->buildSyncRequestHandlerArtifact();

        ob_start();
        if ($setRequestException instanceof Exception) {
            // Send SOAP fault message if we've catched exception and also log it
            $syncRequestHandlerArtifact->set('request_received', $setRequestException->getMessage() . ' --> ' .  tx_st9fissync::getInstance()->truncateXMLTagDataContent($request));
            $this->setLastRequestHandlerProcId($syncRequestHandlerArtifact);

            //generate SOAP fault
            $soap->fault("Sender", $setRequestException->getMessage());

        } else {
            try {
                //$this->_request is a valid XML
                $syncRequestHandlerArtifact->set('request_received', tx_st9fissync::getInstance()->truncateXMLTagDataContent($this->_request));
                $this->setLastRequestHandlerProcId($syncRequestHandlerArtifact);

                //handle request
                $soap->handle($this->_request);
            } catch (Exception $e) {
                $fault = $this->fault($e);
                $soap->fault($fault->faultcode, $fault->faultstring);
            }
        }
        $this->_response = ob_get_clean();

        //log response to client here
        if ($this->_lastRequestHandlerProcId > 0) {
            $syncRequestHandlerArtifact = tx_st9fissync::getInstance()->buildSyncRequestHandlerArtifact($syncRequestHandlerArtifact,TRUE);
            $syncRequestHandlerArtifact->set('response_sent', $this->_response);
            $syncRequestHandlerArtifact->set('response_sent_tstamp', tx_st9fissync::getInstance()->getMicroTime());
            $condition = tx_st9fissync::getInstance()->getSyncDBOperationsManager()->getSyncRequestHandlerTableName() . '.uid = ' . $this->_lastRequestHandlerProcId;
            tx_st9fissync::getInstance()->getSyncDBOperationsManager()->updateSyncRequestHandlerArtifact($syncRequestHandlerArtifact, $condition);
        }

        // Restore original error handler
        restore_error_handler();
        ini_set('display_errors', $displayErrorsOriginalState);

        if (!$this->_returnResponse) {
            echo $this->_response;

            return;
        }

        return $this->_response;

    }

    /**
     * Overriding base class method to enumerate more details of an 'Exception'
     * inspite of the unregistered types
     *
     * @param  string|Exception $fault
     * @param  string           $code  SOAP Fault Codes
     * @return SoapFault
     */
    public function fault($fault = null, $code = "Receiver")
    {
        if ($fault instanceof Exception) {
            $class = get_class($fault);
            if (in_array($class, $this->_faultExceptions)) {
                $message = $fault->getMessage();
                $eCode   = $fault->getCode();
                $code    = empty($eCode) ? $code : $eCode;
            } else {
                $eCode   = $fault->getCode();
                $code    = empty($eCode) ? $code : $eCode;
                $message = 'Unregistered Exception: "'.$class.'" '.$fault->getMessage();
            }
        } elseif (is_string($fault)) {
            $message = $fault;
        } else {
            $message = 'Unknown error';
        }

        $allowedFaultModes = array(
                'VersionMismatch', 'MustUnderstand', 'DataEncodingUnknown',
                'Sender', 'Receiver', 'Server'
        );
        if (!in_array($code, $allowedFaultModes)) {
            $code = "Receiver";
        }

        return new SoapFault($code, $message);
    }

}

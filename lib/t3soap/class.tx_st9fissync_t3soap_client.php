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
 * SOAP Client API for tx_st9fissync.
 *
 * @author	André Spindler <info@studioneun.de>
* @package	TYPO3
* @subpackage	tx_st9fissync
*/

class tx_st9fissync_t3soap_client extends Zend_Soap_Client
{
    /**
     * Crucial var to decide if the message should be encrypted or not
     *
     * @var boolean
     */
    private $_channelSecured = false;

    /**
     *
     * @param boolean $channelSecurity
     */
    public function setSOAPChannelSecurity($channelSecurity=true)
    {
        $this->_channelSecured = $channelSecurity;
    }

    /**
     *
     * @return boolean $this->_channelSecured
     */
    public function getSOAPChannelSecurity()
    {
        return $this->_channelSecured;
    }

    //"trace" => 1, "exceptions" => 0

    /**
     * Do request proxy method.
     *
     * May be overridden in subclasses
     *
     * @internal
     * @param  tx_st9fissync_t3soap_client_common $client
     * @param  string                             $request
     * @param  string                             $location
     * @param  string                             $action
     * @param  int                                $version
     * @param  int                                $one_way
     * @return mixed
     */
    public function _doRequest(tx_st9fissync_t3soap_client_common $client, $request, $location, $action, $version, $one_way = null)
    {
        if ($this->_channelSecured) {
            $request = tx_st9fissync::getInstance()->getEncrypted($request);
        }

        return  parent::_doRequest($client, $request, $location, $action, $version, $one_way);

    }

    /**
     * Initialize SOAP Client object
     *
     * @throws Zend_Soap_Client_Exception
     */
    protected function _initSoapClientObject()
    {
        $wsdl = $this->getWsdl();
        $options = array_merge($this->getOptions(), array('trace' => true));

        if ($wsdl == null) {
            if (!isset($options['location'])) {
                require_once 'Zend/Soap/Client/Exception.php';
                throw new Zend_Soap_Client_Exception('\'location\' parameter is required in non-WSDL mode.');
            }
            if (!isset($options['uri'])) {
                require_once 'Zend/Soap/Client/Exception.php';
                throw new Zend_Soap_Client_Exception('\'uri\' parameter is required in non-WSDL mode.');
            }
        } else {
            if (isset($options['use'])) {
                require_once 'Zend/Soap/Client/Exception.php';
                throw new Zend_Soap_Client_Exception('\'use\' parameter only works in non-WSDL mode.');
            }
            if (isset($options['style'])) {
                require_once 'Zend/Soap/Client/Exception.php';
                throw new Zend_Soap_Client_Exception('\'style\' parameter only works in non-WSDL mode.');
            }
        }
        unset($options['wsdl']);

        $this->_soapClient = new tx_st9fissync_t3soap_client_common(array($this, '_doRequest'), $wsdl, $options);
    }

    /**
     *
     * Perform result pre-processing
     * In case of secure channel decrypt results from SOAP call
     *
     * @see __call
     * @param string|array $arguments
     */
    protected function _preProcessResult($result)
    {
        if ($this->_channelSecured) {
            $result = tx_st9fissync::getInstance()->getDecrypted($result);
        }

        return $result;
    }

}

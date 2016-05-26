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
 * tx_st9fissync_t3soap_auth
 *
 * Application authorization/access checks/logins
*
*
* @author	André Spindler <info@studioneun.de>
* @package	TYPO3
* @subpackage	tx_st9fissync
*/

class tx_st9fissync_t3soap_auth
{
    /**
     *
     * @var array
     */
    protected $_credentials = null;

    /**
     *
     * @var string
     */
    protected $_session = null;

    /**
     *
     * @var t3lib_beUserAuth
     */
    protected $_T3User;

    public function __construct($objUser=null)
    {
        if (!$objUser) {
            $this->setUserObject($GLOBALS['BE_USER']);
        } else {
            $this->setUserObject($objUser);
        }
    }

    public function __get($strName)
    {
        switch ($strName) {
            case 'User':
                return $this->_T3User;

            default:
                throw new Exception('property '.$strName.' is not defined');
        }

    }

    /**
     *
     * @param $user
     * @param $pass
     * @return t3lib_beUserAuth
     */
    protected function _loginByUser($user, $pass)
    {
        // some prerequisites
        //TODO: use $BE_USER->veriCode() correctly instead!
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['doNotCheckReferer'] = true;

        // pose to have normal security level and a SSL locked connection
        // FIXME: force SSL by configuration!!
        $GLOBALS['TYPO3_CONF_VARS']['BE']['lockSSL'] = 1;
        $GLOBALS['TYPO3_CONF_VARS']['BE']['loginSecurityLevel'] = 'normal';

        $_POST[$this->_T3User->formfield_status] = 'login';
        $_POST[$this->_T3User->formfield_uname] = $user;
        $_POST[$this->_T3User->formfield_uident] = $pass;
        $_POST[$this->_T3User->formfield_chalvalue] = '';

        $this->_T3User->challengeStoredInCookie = false;

        return $this->_processLogin();
    }

    /**
     *
     * @param  string           $sessionId
     * @return t3lib_beUserAuth
     */
    protected function _loginBySession($sessionId)
    {
        $_COOKIE[$this->_T3User->name] = $sessionId;

        return $this->_processLogin();
    }

    /**
     *
     * @return t3lib_beUserAuth
     */
    protected function _processLogin()
    {
        $this->_T3User->writeDevLog = true;

        $this->_T3User->start();   // Object is initialized

        // Enforce superchallenged to workaround TYPO3 bug present in older versions
        $this->_T3User->security_level = 'superchallenged';

        //TODO: this function may exit() php due to adminOnly setting or IP-Lock which is not wanted
        //To prevent exiting, we must use an own function to do the needed stuff (basically fetch groups).
        $this->_T3User->backendCheckLogin(); // Checking if there's a user logged in

        if (! $this->_T3User->modAccess(tx_st9fissync_t3soap_application::$Config, false)) {	// This checks permissions and returns false if the users has no permission for entry.
            $accessForbiddenMessage = 'SOAP Services access is not allowed for this user';
            //log ??
            throw new tx_st9fissync_t3soap_forbidden_exception($accessForbiddenMessage);
        }

        return $this->_T3User;
    }


    /**
     *
     * @return t3lib_beUserAuth
     * @throws Exception
     */
    public function login()
    {
        $objUser = null;

        if ($this->_session) {
            $objUser = $this->_loginBySession($this->_session);
            if (!$objUser->user['uid']) {
                $requestTimeOutMessage = 'Session invalid or expired';
                //log ??
                throw new tx_st9fissync_t3soap_requesttimeout_exception($requestTimeOutMessage);

            }
        } elseif (is_array($this->_credentials)) {
            $objUser = $this->_loginByUser($this->_credentials['username'], $this->_credentials['password']);
            if (!$objUser->user['uid']) {
                $unauthorizedMessage = 'User authentication failed';
                // log ??
                throw new tx_st9fissync_t3soap_unauthorized_exception($unauthorizedMessage);
            }
        } else {
            throw new tx_st9fissync_t3soap_unauthorized_exception('You have to authenticate first');
        }

        return $objUser;
    }


    /*************************
     *
    * Setters
    *
    *************************/

    /**
     *
     * @return void
     */
    public function setUserObject($objUser)
    {
        $this->_T3User = $objUser;
    }

    /**
     *
     * @return void
     */
    public function setSession($session)
    {
        $this->_session = $session;
    }

    /**
     *
     * @return void
     */
    public function setCredentials($username, $password)
    {
        $this->_credentials['username'] = $username;
        $this->_credentials['password'] = $password;
    }

}

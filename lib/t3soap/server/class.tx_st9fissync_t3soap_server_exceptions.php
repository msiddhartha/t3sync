<?php
/***************************************************************
 *  Copyright notice
*
*  (c) 2012  (info@studioneun.de)
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

class tx_st9fissync_t3soap_server_exception extends Zend_Soap_Server_Exception
{
    /**
     *
     *
     * @param  string $message
     * @param  int    $code
     * @return void
     * @see http://www.lornajane.net/posts/2009/Status-Codes-for-Web-Services
     */
    public function __construct($message=null, $code=null)
    {
        if (null == $message) {
            $exceptionType = get_class($this);
            preg_match('#tx_st9fissync_t3soap_(.*)_exception#', $exceptionType, $matches);
            $exceptionType = $matches[1];
            $exceptionType = $exceptionType ? $exceptionType : 'Bad Request';
        }

        if (null == $code) {
            $code = 400; // Bad Request
        }

        // $this->exceptionType = $exceptionType;

        if (!$message) {
            $message = 'Request failed: '.$exceptionType;
        }
        parent::__construct($message, $code);
    }
}

/**
 * Login failed
 *
 */
class tx_st9fissync_t3soap_unauthorized_exception extends tx_st9fissync_t3soap_server_exception
{
    public function __construct($message=null, $code=401)
    {
        parent::__construct($message, 401);
    }
}
/**
 * Permission denied
 *
 */
class tx_st9fissync_t3soap_forbidden_exception extends tx_st9fissync_t3soap_server_exception
{
    public function __construct($message=null, $code=403)
    {
        parent::__construct($message, 403);
    }
}
class tx_st9fissync_t3soap_notfound_exception extends tx_st9fissync_t3soap_server_exception
{
    public function __construct($message=null, $code=404)
    {
        parent::__construct($message, 404);
    }
}
class tx_st9fissync_t3soap_methodnotallowed_exception extends tx_st9fissync_t3soap_server_exception
{
    public function __construct($message=null, $code=405)
    {
        parent::__construct($message, 405);
    }
}
/**
 * Session expired
 *
 *
 */
class tx_st9fissync_t3soap_requesttimeout_exception extends tx_st9fissync_t3soap_server_exception
{
    public function __construct($message=null, $code=408)
    {
        parent::__construct($message, 408);
    }
}
class tx_st9fissync_t3soap_expectationfailed_exception extends tx_st9fissync_t3soap_server_exception
{
    public function __construct($message=null, $code=417)
    {
        parent::__construct($message, 417);
    }
}

class tx_st9fissync_t3soap_exception_unauthorized extends tx_st9fissync_t3soap_server_exception
{
}

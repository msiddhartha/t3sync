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
 * tx_st9fissync_t3soap_application
*
*
* @author	André Spindler <info@studioneun.de>
* @package	TYPO3
* @subpackage	tx_st9fissync
*/

class tx_st9fissync_t3soap_application
{
    /**
     *
     * @var array
     */
    public static $Config;

    /**
     *
     * @var string
     */
    public static $Namespace;

    /**
     *
     * @var tx_st9fissync_t3soap_auth
     */
    public static $Auth;

    public static function Init($strExtKey, array $arrModuleConfig) //$objBackendUser)
    {
        self::$Namespace = $strExtKey;
        self::$Config = $arrModuleConfig;
    }

    public static function setAuthenticationObject(tx_st9fissync_t3soap_auth $objAuth)
    {
        self::$Auth = $objAuth;
    }

    public static function setConfig(array $arrConfig)
    {
    }

}

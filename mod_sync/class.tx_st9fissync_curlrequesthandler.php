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
 * Curl Request Handler tx_st9fissync.
 *
 *
 * @author	André Spindler <info@studioneun.de>
* @package	TYPO3
* @subpackage	tx_st9fissync
*/

class tx_st9fissync_curlrequesthandler extends t3lib_SCbase
{
    public function __construct()
    {
    }

    public function handle()
    {
        $postData = t3lib_div::_POST();

        $className = $postData['className'];
        $funcName = $postData['funcName'];

        try {
            if (!class_exists($className)) {
                throw new Exception ("Class $className not exists");
            }
        } catch (Exception $e) {
            tx_st9fissync_exception::exceptionPostCatchHandler($e);
        }

        // Create an instance of the class
        $obj = t3lib_div::makeInstance($className);

        try {
            if (!method_exists($obj, $funcName)) {
                throw new Exception ("Function $funcName() not exists");
            }
        } catch (Exception $e) {
            tx_st9fissync_exception::exceptionPostCatchHandler($e);
        }

        $args= array_slice($postData, 2);

        try {
            if (!is_array($args)) {
                throw new Exception ("Argument of function $funcName() is not an array");
            }
        } catch (Exception $e) {
            tx_st9fissync_exception::exceptionPostCatchHandler($e);
        }

        // Call the required function from the class
        $obj->$funcName($args);

    }

}

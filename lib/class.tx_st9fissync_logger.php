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
 * A light, permissions-checking logging class.
*
*  Usage:
*		$log = new tx_st9fissync_logger (tx_st9fissync_logger::INFO);
*		$log->logInfo("Returned a million search results");	//to DB
*		$log->logFATAL("Fatal Error!!");
*		$log->logDebug("x = 5");					//nothing to DB due to priority setting
*
* @author	André Spindler <info@studioneun.de>
* @package	TYPO3
* @subpackage	tx_st9fissync
*/

class tx_st9fissync_logger
{
    const DEBUG 	= 1;	// Most Verbose
    const INFO 		= 2;	// ...
    const WARN 		= 3;	// ...
    const ERROR 	= 4;	// ...
    const FATAL 	= 5;	// Least Verbose
    const OFF 		= 6;	// Nothing at all.

    private $priority = tx_st9fissync_logger::INFO;

    public function __construct($priority)
    {
        if ($priority) {
            $this->priority = $priority;
        }
    }

    public function logDebug($message)
    {
        $this->log($message , tx_st9fissync_logger::DEBUG);
    }

    public function logInfo($message)
    {
        $this->log($message , tx_st9fissync_logger::INFO);
    }

    public function logWarn($message)
    {
        $this->log($message , tx_st9fissync_logger::WARN);
    }

    public function logError($message)
    {
        $this->log($message , tx_st9fissync_logger::ERROR);
    }

    public function logFatal($message)
    {
        $this->log($message , tx_st9fissync_logger::FATAL);
    }

    public function log($message, $priority)
    {
        if ($this->priority <= $priority) {
            if ($this->priority != tx_st9fissync_logger::OFF) {
                return tx_st9fissync::getInstance()->getSyncDBOperationsManager()->logtoDB($message, $priority);
            }
        }

        return false;
    }

}

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
 * SOAP Client for tx_st9fissync.
 *
 * @author	André Spindler <info@studioneun.de>
* @package	TYPO3
* @subpackage	tx_st9fissync
*/

class tx_st9fissync_t3soap_client_common extends Zend_Soap_Client_Common
{
    /* public function __soapCall ($function_name, array $arguments, array $options = null, $input_headers = null, array &$output_headers = null) {
        return tx_st9fissync::getInstance()->getDecrypted(parent::__soapCall($function_name,$arguments,$options,$input_headers,&$output_headers));
    } */

}

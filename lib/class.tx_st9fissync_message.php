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

/**
 *
 * @author <info@studioneun.de>
 * @package TYPO3
* @subpackage st9fissync
*
*/

class tx_st9fissync_message extends t3lib_FlashMessage
{
    /**
     * Renders the flash message.
     *
     * @return string The flash message as HTML.
     */
    public function render()
    {
        $classes = array(
                self::NOTICE =>  'Notice',
                self::INFO =>    'Information',
                self::OK =>      'Ok',
                self::WARNING => 'Warning',
                self::ERROR =>   'Error',
        );

        $title = '';
        if (!empty($this->title)) {
            $title = 'Title: ' . $this->title . '<br />';
        }

        $message = '[' . $classes[$this->severity] . ']: '
        . $title
        . $this->message . ' <br> ';

        return $message;
    }

}

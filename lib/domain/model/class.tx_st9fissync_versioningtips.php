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

class tx_st9fissync_versioningtips extends tx_lib_object
{
    /**
     *
     * @var tx_lib_object
     */
    private $fieldList;

    public function construct()
    {
        $this->getFieldList();
    }

    public function getFieldList()
    {
        if ($this->fieldList == null) {
            $this->fieldList = t3lib_div::makeInstance('tx_lib_object');
            $this->fieldList->set('table','table');
            $this->fieldList->set('cmd','cmd');
            $this->fieldList->set('executedQuery','executedQuery');
            $this->fieldList->set('executionTime','executionTime');
            $this->fieldList->set('resultResource','resultResource');
            $this->fieldList->set('recordRevision','recordRevision');
            $this->fieldList->set('query_affectedrows','query_affectedrows');
            $this->fieldList->set('query_info','query_info');
            $this->fieldList->set('query_error_number','query_error_number');
            $this->fieldList->set('query_error_message','query_error_message');
        }

        return $this->fieldList;
    }

}

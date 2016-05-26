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
 * Mapper, Assembler, DTO generator for Results/Responses
*
* @author <info@studioneun.de>
* @package TYPO3
* @subpackage st9fissync
*
*/

class tx_st9fissync_resultresponse_dto extends tx_lib_object
{
    public function getSyncResultsByDTO($dtoUid)
    {
        if($this->has('syncRes'))

            return $this->get('syncRes')->get($dtoUid);

        return null;
    }

    public function getSyncResults()
    {
        if($this->has('syncRes'))

            return $this->get('syncRes');

        return null;
    }

    public function getSyncErrors()
    {
        if ($this->has('syncErr')) {
            return $this->get('syncErr');
        }

        return null;
    }

    public function getSyncErrorsByDTO($dtoUid)
    {
        if($this->has('syncErr'))

            return $this->get('syncErr')->get($dtoUid);

        return null;
    }

    public function getSyncLastRequestHandler()
    {
        if($this->has('syncRemoteRequestHandler'))

            return intval($this->get('syncRemoteRequestHandler'));

        return 0;
    }

    /**
     *
     * @param array $syncResponse
     *
     * @return tx_st9fissync_resultresponse_dto
     */
    public function setSyncResponse($syncResponse)
    {
        $this->set('syncRemoteRequestHandler', $syncResponse['requestHandler']);

        if (is_a($syncResponse['responseRes'], 'tx_lib_object')) {
            $this->set('syncRes', $syncResponse['responseRes']->get('res'));
            $this->set('syncErr', $syncResponse['responseRes']->get('errors'));
        } else {
            $this->set('syncRes', $syncResponse['responseRes']['res']);
            $this->set('syncErr', $syncResponse['responseRes']['errors']);
        }

        return $this;
    }

    /**
     *
     * @param array $resultResponseDTO
     */
    public function acceptResultResponseDTO($resultResponseDTO, $artifactUID = null)
    {
        if (!$this->has('responseRes')) {
            $this->set('responseRes', t3lib_div::makeInstance('tx_lib_object'));
        }

        foreach ($resultResponseDTO as $dtoKey => $dtoVal) {
            if ($this->get('responseRes')->has($dtoKey)) {
                if ($artifactUID == null) {
                    $this->get('responseRes')->get($dtoKey)->append($dtoVal);
                } else {
                    $this->get('responseRes')->get($dtoKey)->set($artifactUID,$dtoVal);
                }
            } else {
                $_o = t3lib_div::makeInstance('tx_lib_object',($artifactUID == null ? $dtoVal: array($artifactUID => $dtoVal)));
                $this->get('responseRes')->set($dtoKey,$_o);
            }
        }

        return $this->get('responseRes');
    }

    public function getResultResponseDTO()
    {
        return $this->get('responseRes');
    }

}

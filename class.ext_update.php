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
 * This is a file of the Typo3 Sync project.
 * Extension manager update class to generate public / private key pairs.
*
* @author <info@studioneun.de>
* @package TYPO3
* @subpackage st9fissync
*
*
*/

if (t3lib_extMgm::isLoaded('caretaker_instance')) {
    require_once(t3lib_extMgm::extPath('caretaker_instance', 'classes/class.tx_caretakerinstance_ServiceFactory.php'));
}

class ext_update
{
    /**
     * @var tx_caretakerinstance_ServiceFactory
     */
    protected $factory;

    /**
     * @return boolean Whether the update should be shown / allowed
     */
    public function access()
    {
        $extConf = $this->getExtConf();

        $show = !strlen($extConf['crypto_instance_publicKey']) ||
        !strlen($extConf['crypto_instance_privateKey']);

        return $show;
    }

    /**
     * Return the update process HTML content
     *
     * @return string
     */
    public function main()
    {
        $extConf = $this->getExtConf();

        $this->factory = tx_caretakerinstance_ServiceFactory::getInstance();
        list($publicKey, $privateKey) = $this->factory->getCryptoManager()->generateKeyPair();

        $extConf['crypto_instance_publicKey'] = $publicKey;
        $extConf['crypto_instance_privateKey'] = $privateKey;

        $this->writeExtConf($extConf);

        $content = "Generated public / private key";

        return $content;
    }

    /**
     * Write back configuration
     *
     * @param  array $extConf
     * @return void
     */
    protected function writeExtConf($extConf)
    {
        $install = new t3lib_install();
        $install->allowUpdateLocalConf = 1;
        $install->updateIdentity = 'Typo3 Sync installation';

        $lines = $install->writeToLocalconf_control();
        $install->setValueInLocalconfFile($lines, '$TYPO3_CONF_VARS[\'EXT\'][\'extConf\'][\''. ST9FISSYNC_EXTKEY . '\']', serialize($extConf));
        $install->writeToLocalconf_control($lines);

        t3lib_extMgm::removeCacheFiles();
    }

    /**
     * Get the extension configuration
     *
     * @return array
     */
    protected function getExtConf()
    {
        $extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][ST9FISSYNC_EXTKEY]);
        if (!$extConf) {
            $extConf = array();
        }

        return $extConf;
    }

}

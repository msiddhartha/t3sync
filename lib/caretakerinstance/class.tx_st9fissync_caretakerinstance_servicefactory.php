<?php

if (t3lib_extMgm::isLoaded('caretaker_instance')) {
    require_once(t3lib_extMgm::extPath('caretaker_instance', 'classes/class.tx_caretakerinstance_ServiceFactory.php'));
}

class tx_st9fissync_caretakerinstance_servicefactory extends tx_caretakerinstance_ServiceFactory
{
    /**
     * @var tx_st9fissync_caretakerinstance_servicefactory
     */
    protected static $synccaretakerinstance;

    /**
     * @static
     * @return tx_st9fissync_caretakerinstance_servicefactory
     */
    public static function getInstance()
    {
        if (tx_st9fissync_caretakerinstance_servicefactory::$synccaretakerinstance == null) {
            tx_st9fissync_caretakerinstance_servicefactory::$synccaretakerinstance = new tx_st9fissync_caretakerinstance_servicefactory();

        }

        return tx_st9fissync_caretakerinstance_servicefactory::$synccaretakerinstance;
    }

    /**
     * @return tx_caretakerinstance_SecurityManager
     */
    public function getSyncSecurityManager()
    {
        if ($this->syncSecurityManager == null) {
            $this->syncSecurityManager = new tx_st9fissync_caretakerinstance_securitymanager($this->getSyncCryptoManager());
            $this->syncSecurityManager->setPublicKey($this->extConf['crypto.']['instance.']['publicKey']);
            $this->syncSecurityManager->setPrivateKey($this->extConf['crypto.']['instance.']['privateKey']);
            $this->syncSecurityManager->setClientPublicKey($this->extConf['crypto.']['client.']['publicKey']);
            $this->syncSecurityManager->setClientHostAddressRestriction($this->extConf['security.']['clientHostAddressRestriction']);
        }

        return $this->syncSecurityManager;
    }

    /**
     * @return tx_st9fissync_caretakerinstance_opensslcryptomanager
     */
    public function getSyncCryptoManager()
    {
        if ($this->syncCryptoManager == null) {
            $this->syncCryptoManager = new tx_st9fissync_caretakerinstance_opensslcryptomanager();
        }

        return $this->syncCryptoManager;
    }

    /**
     * Destroy the factory instance
     */
    public function destroy()
    {
        self::$synccaretakerinstance = null;
    }

}

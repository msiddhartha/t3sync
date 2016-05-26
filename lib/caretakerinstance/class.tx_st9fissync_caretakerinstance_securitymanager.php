<?php

if (t3lib_extMgm::isLoaded('caretaker_instance')) {
    require_once(t3lib_extMgm::extPath('caretaker_instance', 'classes/class.tx_caretakerinstance_SecurityManager.php'));
}

class tx_st9fissync_caretakerinstance_securitymanager extends tx_caretakerinstance_SecurityManager
{
    /**
     * @var tx_st9fissync_caretakerinstance_opensslcryptomanager
     */
    protected $cryptoManager;

    protected function validateDataIntegrity($dataTobeValidated, $signature)
    {
        return $this->cryptoManager->verifySignature($dataTobeValidated, $signature, $this->clientPublicKey);
    }

    protected function createDataSignature($dataToSign)
    {
        return $this->cryptoManager->createSignature($dataToSign, $this->privateKey);
    }
}

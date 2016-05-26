<?php

if (t3lib_extMgm::isLoaded('caretaker_instance')) {
    require_once(t3lib_extMgm::extPath('caretaker_instance', 'classes/class.tx_caretakerinstance_OpenSSLCryptoManager.php'));
}

class tx_st9fissync_caretakerinstance_opensslcryptomanager  extends tx_caretakerinstance_OpenSSLCryptoManager
{
}

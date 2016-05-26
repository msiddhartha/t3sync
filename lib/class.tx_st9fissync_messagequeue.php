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

class tx_st9fissync_messagequeue extends t3lib_FlashMessageQueue
{
    public static $syncmessages = array();

    /**
     * Adds a message either to the BE_USER session (if the $message has the storeInSession flag set)
     * or it adds the message to self::$syncmessages.
     *
     * @param	object	 instance of tx_st9fissync_message, representing a message
     * @return void
     */
    public static function addMessage(tx_st9fissync_message $message)
    {
        if ($message->isSessionMessage()) {
            $queuedFlashMessages = self::getFlashMessagesFromSession();
            $queuedFlashMessages[] = $message;
            self::storeFlashMessagesInSession($queuedFlashMessages);
        } else {
            self::$syncmessages[] = $message;
        }
    }

    /**
     * Returns all messages from the current PHP session and from the current request.
     *
     * @return array array of tx_st9fissync_message objects
     */
    public static function getAllMessages()
    {
        // get messages from user session
        $queuedFlashMessagesFromSession = self::getFlashMessagesFromSession();
        $queuedFlashMessages = array_merge($queuedFlashMessagesFromSession, self::$syncmessages);

        return $queuedFlashMessages;
    }

    /**
     * Returns all messages from the current PHP session and from the current request.
     * After fetching the messages the internal queue and the message queue in the session
     * will be emptied.
     *
     * @return array array of tx_st9fissync_message objects
     */
    public static function getAllMessagesAndFlush()
    {
        $queuedFlashMessages = self::getAllMessages();

        // reset messages in user session
        self::removeAllFlashMessagesFromSession();
        // reset internal messages
        self::$syncmessages = array();

        return $queuedFlashMessages;
    }

    /**
     * Stores given flash messages in the session
     *
     * @param	array	array of tx_st9fissync_message
     * @return void
     */
    protected static function storeFlashMessagesInSession(array $flashMessages)
    {
        self::getUserByContext()->setAndSaveSessionData('module.sync.flashMessages', $flashMessages);

    }

    /**
     * Removes all flash messages from the session
     *
     * @return void
     */
    protected static function removeAllFlashMessagesFromSession()
    {
        self::getUserByContext()->setAndSaveSessionData('module.sync.flashMessages', NULL);
    }

    /**
     * Returns current flash messages from the session, making sure to always
     * return an array.
     *
     * @return array An array of tx_st9fissync_message flash messages.
     */
    protected static function getFlashMessagesFromSession()
    {
        $flashMessages = self::getUserByContext()->getSessionData('module.sync.flashMessages');

        return is_array($flashMessages) ? $flashMessages : array();
    }

    /**
     * Fetches and renders all available flash messages from the queue.
     *
     * @param int $messageSeverity
     *
     * @return string All flash messages in the queue rendered as HTML.
     */
    public static function renderFlashMessages($messageSeverity = null)
    {
        $content = '';
        $flashMessages = self::getAllMessagesAndFlush();
        if (count($flashMessages)) {
            foreach ($flashMessages as $flashMessage) {

                /**
                 * Show all messages in the BE UI
                 */
                parent::addMessage(tx_st9fissync::getInstance()->buildSyncFlashMessage($flashMessage));

                if (is_null($messageSeverity)) {
                    $content .= $flashMessage->render();
                } elseif ($flashMessage->getSeverity() >= $messageSeverity) {
                    $content .= $flashMessage->render();
                }
            }
        }

        return $content;
    }

}

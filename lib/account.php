<?php
/**
 * Copyright (c) 2012 Bart Visscher <bartv@thisnet.nl>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Mail;

use Horde_Imap_Client;
use OCA\Mail\Db\MailAccount;

class Account {
	private $info;

	// input $conn = IMAP conn, $folder_id = folder id
	function __construct($info) {
		$this->info = $info;
		if ($info instanceof MailAccount) {
			$this->info = array(
				'host' => $info->getInboundHost(),
				'user' => $info->getInboundUser(),
				'password' => $info->getInboundPassword(),
				'port' => $info->getInboundHostPort(),
				'ssl_mode' => $info->getInboundSslMode(),
				'id' => $info->getMailAccountId(),
				'email' => $info->getEmail(),
				'name' => $info->getMailAccountName(),
			);
		}
	}

	public function getId() {
		return $this->info['id'];
	}

	public function getName() {
		return $this->info['name'];
	}

	public function getEMailAddress() {
		return $this->info['email'];
	}

	public function getImapConnection() {
		//
		// TODO: cache connections for / within accounts???
		//
		$host = $this->info['host'];
		$user = $this->info['user'];
		$password = $this->info['password'];
		$port = $this->info['port'];
		$ssl_mode = $this->info['ssl_mode'];

		$client = new \Horde_Imap_Client_Socket(array(
			'username' => $user, 'password' => $password, 'hostspec' => $host, 'port' => $port, 'secure' => $ssl_mode, 'timeout' => 2));
		$client->login();
		return $client;
	}

	/**
	 * @param $pattern
	 * @return Mailbox[]
	 */
	public function listMailboxes($pattern) {
		// open the imap connection
		$conn = $this->getImapConnection();

		// if successful -> get all folders of that account
		$mboxes = $conn->listMailboxes($pattern, Horde_Imap_Client::MBOX_ALL, array(
			'attributes' => true,
			'special_use' => true,
			'sort' => true
		));

		$mailboxes = array();
		foreach ($mboxes as $mailbox) {
			$mailboxes[] = new Mailbox($conn, $mailbox['mailbox']->utf7imap, $mailbox['attributes']);
		}
		return $mailboxes;
	}

	/**
	 * @param $folderId
	 * @return \OCA\Mail\Mailbox
	 */
	public function getMailbox($folderId) {
		$conn = $this->getImapConnection();
		return new Mailbox($conn, $folderId, array());
	}

	/**
	 * @return array
	 */
	public function getListArray() {
		// if successful -> get all folders of that account
		$mboxes = $this->listMailboxes('*');

		$folders = array();
		foreach ($mboxes as $mailbox) {
			$folders[] = $mailbox->getListArray();
		}

		$inbox = null;
		foreach ($folders as $key=>$value) {
			if ($value['id'] === 'INBOX') {
				  $inbox = $key;
			}
		}

		if ($inbox) {
			self::move_to_top($folders, $inbox);
		}

		return array('id' => $this->getId(), 'email' => $this->getEMailAddress(), 'folders' => array_values( $folders));
	}

	private static function move_to_top(&$array, $key) {
		$temp = array($key => $array[$key]);
		unset($array[$key]);
		$array = $temp + $array;
	}

	/**
	 * @return \Horde_Mail_Transport_Sendmail
	 */
	public function createTransport() {
		//
		// TODO: implement according to the SMTP settings
		//
		return new \Horde_Mail_Transport_Sendmail();
	}

	public function getSentFolder() {
		//
		// TODO: read settings/server special folders how the sent folder is named
		//
		$conn = $this->getImapConnection();
		return new Mailbox($conn, 'Sent', array());
	}
}

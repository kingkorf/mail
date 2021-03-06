<?php
/**
 * ownCloud - Mail app
 *
 * @author Thomas Müller
 * @copyright 2012, 2013 Thomas Müller thomas.mueller@tmit.eu
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Mail;

use Horde_Imap_Client_Data_Fetch;

class Attachment {

	/**
	 * @param \Horde_Imap_Client_Socket $conn
	 * @param int $folderId
	 * @param int $messageId
	 * @param int $attachmentId
	 */
	function __construct($conn, $folderId, $messageId, $attachmentId) {
		$this->conn = $conn;
		$this->folderId = $folderId;
		$this->messageId = $messageId;
		$this->attachmentId = $attachmentId;

		$this->load();
	}

	/**
	 * @var \Horde_Imap_Client_Socket
	 */
	private $conn;
	private $folderId;
	private $messageId;
	private $attachmentId;

	/**
	 * @var \Horde_Mime_Part
	 */
	private $mimePart;

	private function load() {
		$headers = array();

		$fetch_query = new \Horde_Imap_Client_Fetch_Query();
		$fetch_query->bodyPart($this->attachmentId);
		$fetch_query->mimeHeader($this->attachmentId);

		$headers = array_merge($headers, array(
			'importance',
			'list-post',
			'x-priority'
		));
		$headers[] = 'content-type';

		$fetch_query->headers('imp', $headers, array(
			'cache' => true,
			'peek'  => true
		));

		// $list is an array of Horde_Imap_Client_Data_Fetch objects.
		$ids = new \Horde_Imap_Client_Ids($this->messageId);
		$headers = $this->conn->fetch($this->folderId, $fetch_query, array('ids' => $ids));
		/** @var $fetch \Horde_Imap_Client_Data_Fetch */
		$fetch = $headers[$this->messageId];
		$mimeHeaders = $fetch->getMimeHeader($this->attachmentId, \Horde_Imap_Client_Data_Fetch::HEADER_PARSE);

		$this->mimePart = new \Horde_Mime_Part();

		// To prevent potential problems with the SOP we serve all files with the
		// MIME type "application/octet-stream"
		$this->mimePart->setType('application/octet-stream');

		// Serve all files with a content-disposition of "attachment" to prevent Cross-Site Scripting
		$this->mimePart->setDisposition('attachment');

		// Extract headers from part
		$vars = array(
			'filename',
		);
		foreach ($mimeHeaders->getValue('content-disposition', \Horde_Mime_Headers::VALUE_PARAMS) as $key => $val) {
			if(in_array($key, $vars)) {
				$this->mimePart->setDispositionParameter($key, $val);
			}
		}

		/* Content transfer encoding. */
		if ($tmp = $mimeHeaders->getValue('content-transfer-encoding')) {
			$this->mimePart->setTransferEncoding($tmp);
		}

		$body = $fetch->getBodyPart($this->attachmentId);
		$this->mimePart->setContents($body);
	}

	/**
	 * @return string
	 */
	public function getContents() {
		return $this->mimePart->getContents();
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->mimePart->getName();
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->mimePart->getType();
	}
}

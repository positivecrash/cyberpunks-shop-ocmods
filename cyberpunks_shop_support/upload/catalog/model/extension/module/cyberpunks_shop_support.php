<?php
class ModelExtensionModuleCyberpunksShopSupport extends Model {
	const STATUS_OPEN = 'open';
	const STATUS_IN_PROGRESS = 'in_progress';
	const STATUS_WAITING = 'waiting';
	const STATUS_CLOSED = 'closed';

	public function ensureSchema() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_support_ticket` (
			`ticket_id` INT(11) NOT NULL AUTO_INCREMENT,
			`request_code` VARCHAR(16) NOT NULL,
			`email` VARCHAR(96) NOT NULL,
			`reason` VARCHAR(64) NOT NULL DEFAULT '',
			`status` VARCHAR(32) NOT NULL DEFAULT 'open',
			`customer_id` INT(11) NOT NULL DEFAULT '0',
			`customer_language` VARCHAR(32) NOT NULL DEFAULT '',
			`customer_currency` VARCHAR(16) NOT NULL DEFAULT '',
			`customer_country` VARCHAR(64) NOT NULL DEFAULT '',
			`date_added` DATETIME NOT NULL,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`ticket_id`),
			UNIQUE KEY `request_code` (`request_code`),
			KEY `email` (`email`),
			KEY `status` (`status`),
			KEY `date_modified` (`date_modified`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_support_message` (
			`message_id` INT(11) NOT NULL AUTO_INCREMENT,
			`ticket_id` INT(11) NOT NULL,
			`author` VARCHAR(16) NOT NULL DEFAULT 'customer',
			`user_id` INT(11) NOT NULL DEFAULT '0',
			`message` TEXT NOT NULL,
			`email_message_id` VARCHAR(255) NOT NULL DEFAULT '',
			`date_added` DATETIME NOT NULL,
			PRIMARY KEY (`message_id`),
			KEY `ticket_id` (`ticket_id`),
			KEY `email_message_id` (`email_message_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

		foreach (array('customer_language' => "VARCHAR(32) NOT NULL DEFAULT ''", 'customer_currency' => "VARCHAR(16) NOT NULL DEFAULT ''", 'customer_country' => "VARCHAR(64) NOT NULL DEFAULT ''") as $column => $definition) {
			$col = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "cyberpunks_support_ticket` LIKE '" . $this->db->escape($column) . "'");
			if (!$col->num_rows) {
				$this->db->query("ALTER TABLE `" . DB_PREFIX . "cyberpunks_support_ticket` ADD COLUMN `" . $column . "` " . $definition);
			}
		}

		$email_msg_col = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "cyberpunks_support_message` LIKE 'email_message_id'");
		if (!$email_msg_col->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "cyberpunks_support_message` ADD COLUMN `email_message_id` VARCHAR(255) NOT NULL DEFAULT '' AFTER `message`");
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "cyberpunks_support_message` ADD INDEX `email_message_id` (`email_message_id`)");
		}
	}

	public function getTicketByCode($request_code) {
		$this->ensureSchema();
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cyberpunks_support_ticket` WHERE request_code = '" . $this->db->escape(strtoupper(trim((string)$request_code))) . "' LIMIT 1");
		return $query->row;
	}

	public function hasEmailMessageId($email_message_id) {
		$email_message_id = trim((string)$email_message_id);
		if ($email_message_id === '') {
			return false;
		}

		$this->ensureSchema();
		$query = $this->db->query("SELECT message_id FROM `" . DB_PREFIX . "cyberpunks_support_message`
			WHERE email_message_id = '" . $this->db->escape($email_message_id) . "' LIMIT 1");

		return (bool)$query->num_rows;
	}

	public function addCustomerMessageFromImap($ticket_id, $message, $email_message_id = '') {
		$message = trim((string)$message);
		if ($message === '') {
			return 0;
		}

		$email_message_id = substr(trim((string)$email_message_id), 0, 255);
		if ($email_message_id !== '' && $this->hasEmailMessageId($email_message_id)) {
			return 0;
		}

		$this->ensureSchema();

		$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_support_message` SET
			ticket_id = '" . (int)$ticket_id . "',
			author = 'customer',
			user_id = '0',
			message = '" . $this->db->escape($message) . "',
			email_message_id = '" . $this->db->escape($email_message_id) . "',
			date_added = NOW()");

		$message_id = (int)$this->db->getLastId();

		$this->db->query("UPDATE `" . DB_PREFIX . "cyberpunks_support_ticket` SET
			status = IF(status = 'closed', 'open', IF(status = 'waiting', 'open', status)),
			date_modified = NOW()
			WHERE ticket_id = '" . (int)$ticket_id . "'");

		return $message_id;
	}

	public function isFirstTicketForEmail($email) {
		$email = trim((string)$email);
		if ($email === '') {
			return false;
		}

		$query = $this->db->query("SELECT ticket_id FROM `" . DB_PREFIX . "cyberpunks_support_ticket`
			WHERE LOWER(email) = '" . $this->db->escape(utf8_strtolower($email)) . "'
			LIMIT 1");

		return !$query->num_rows;
	}

	public function generateRequestCode() {
		for ($i = 0; $i < 8; $i++) {
			$code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
			$exists = $this->db->query("SELECT ticket_id FROM `" . DB_PREFIX . "cyberpunks_support_ticket` WHERE request_code = '" . $this->db->escape($code) . "' LIMIT 1");
			if (!$exists->num_rows) {
				return $code;
			}
		}

		return strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
	}

	/**
	 * Create ticket + first customer message from contact form.
	 * @return array{ticket_id:int,request_code:string,first_for_email:bool}
	 */
	public function createFromContact($data) {
		$this->ensureSchema();

		if (!(int)$this->config->get('module_cyberpunks_shop_support_status')) {
			return array('ticket_id' => 0, 'request_code' => '', 'first_for_email' => false);
		}

		$email = isset($data['email']) ? trim((string)$data['email']) : '';
		$message = isset($data['message']) ? trim((string)$data['message']) : '';
		$reason = isset($data['reason']) ? trim((string)$data['reason']) : '';
		$request_code = isset($data['request_code']) ? strtoupper(trim((string)$data['request_code'])) : '';
		$language = isset($data['language']) ? trim((string)$data['language']) : '';
		$currency = isset($data['currency']) ? trim((string)$data['currency']) : '';
		$country = isset($data['country']) ? trim((string)$data['country']) : '';

		if ($email === '' || $message === '') {
			return array('ticket_id' => 0, 'request_code' => '', 'first_for_email' => false);
		}

		if ($reason === '' && preg_match('/^Reason:\s*(.+?)(?:\r?\n)+/i', $message, $rm)) {
			$reason = trim($rm[1]);
		}
		$message = trim(preg_replace('/^Reason:\s*.+?(?:\r?\n)+/i', '', $message, 1));

		if ($message === '') {
			return array('ticket_id' => 0, 'request_code' => '', 'first_for_email' => false);
		}

		$first_for_email = $this->isFirstTicketForEmail($email);

		if ($request_code === '') {
			$request_code = $this->generateRequestCode();
		}

		$customer_id = 0;
		if ($this->customer && $this->customer->isLogged()) {
			$customer_id = (int)$this->customer->getId();
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_support_ticket` SET
			request_code = '" . $this->db->escape($request_code) . "',
			email = '" . $this->db->escape($email) . "',
			reason = '" . $this->db->escape($reason) . "',
			status = '" . $this->db->escape(self::STATUS_OPEN) . "',
			customer_id = '" . (int)$customer_id . "',
			customer_language = '" . $this->db->escape($language) . "',
			customer_currency = '" . $this->db->escape($currency) . "',
			customer_country = '" . $this->db->escape($country) . "',
			date_added = NOW(),
			date_modified = NOW()");

		$ticket_id = (int)$this->db->getLastId();

		$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_support_message` SET
			ticket_id = '" . (int)$ticket_id . "',
			author = 'customer',
			user_id = '0',
			message = '" . $this->db->escape($message) . "',
			date_added = NOW()");

		return array(
			'ticket_id' => $ticket_id,
			'request_code' => $request_code,
			'first_for_email' => $first_for_email
		);
	}
}

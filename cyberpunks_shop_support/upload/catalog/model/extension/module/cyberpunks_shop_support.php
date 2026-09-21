<?php
class ModelExtensionModuleCyberpunksShopSupport extends Model {
	const STATUS_OPEN = 'open';
	const STATUS_IN_PROGRESS = 'in_progress';
	const STATUS_WAITING = 'waiting';
	const STATUS_CLOSED = 'closed';

	/** Minimum gap between two customer messages appended to the same ticket. */
	const APPEND_THROTTLE_SECONDS = 60;

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

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_support_blocklist` (
			`block_id` INT(11) NOT NULL AUTO_INCREMENT,
			`email` VARCHAR(96) NOT NULL,
			`note` VARCHAR(255) NOT NULL DEFAULT '',
			`user_id` INT(11) NOT NULL DEFAULT '0',
			`date_added` DATETIME NOT NULL,
			PRIMARY KEY (`block_id`),
			UNIQUE KEY `email` (`email`)
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
		$email = $this->normalizeEmail($email);
		if ($email === '') {
			return false;
		}

		$query = $this->db->query("SELECT ticket_id FROM `" . DB_PREFIX . "cyberpunks_support_ticket`
			WHERE LOWER(email) = '" . $this->db->escape($email) . "'
			LIMIT 1");

		return !$query->num_rows;
	}

	public function normalizeEmail($email) {
		$email = trim((string)$email);
		return ($email === '') ? '' : utf8_strtolower($email);
	}

	public function isEmailBlocked($email) {
		$email = $this->normalizeEmail($email);
		if ($email === '') {
			return false;
		}

		$this->ensureSchema();
		$query = $this->db->query("SELECT block_id FROM `" . DB_PREFIX . "cyberpunks_support_blocklist`
			WHERE email = '" . $this->db->escape($email) . "' LIMIT 1");

		return (bool)$query->num_rows;
	}

	/**
	 * Newest ticket of this email that is still being handled (not closed).
	 */
	public function getOpenTicketForEmail($email) {
		$email = $this->normalizeEmail($email);
		if ($email === '') {
			return array();
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cyberpunks_support_ticket`
			WHERE LOWER(email) = '" . $this->db->escape($email) . "'
			AND status IN ('" . $this->db->escape(self::STATUS_OPEN) . "', '" . $this->db->escape(self::STATUS_IN_PROGRESS) . "', '" . $this->db->escape(self::STATUS_WAITING) . "')
			ORDER BY date_modified DESC, ticket_id DESC
			LIMIT 1");

		return $query->row;
	}

	/**
	 * Seconds since the last customer message on a ticket, or null when there is none.
	 */
	public function getSecondsSinceLastCustomerMessage($ticket_id) {
		$query = $this->db->query("SELECT TIMESTAMPDIFF(SECOND, date_added, NOW()) AS age FROM `" . DB_PREFIX . "cyberpunks_support_message`
			WHERE ticket_id = '" . (int)$ticket_id . "' AND author = 'customer'
			ORDER BY message_id DESC
			LIMIT 1");

		if (!$query->num_rows) {
			return null;
		}

		return max(0, (int)$query->row['age']);
	}

	/**
	 * How many customer messages this email produced in the last N hours (contact/IMAP).
	 */
	public function countCustomerMessagesForEmail($email, $hours = 24) {
		$email = $this->normalizeEmail($email);
		$hours = max(1, (int)$hours);

		if ($email === '') {
			return 0;
		}

		$query = $this->db->query("SELECT COUNT(*) AS total
			FROM `" . DB_PREFIX . "cyberpunks_support_message` m
			INNER JOIN `" . DB_PREFIX . "cyberpunks_support_ticket` t ON (t.ticket_id = m.ticket_id)
			WHERE LOWER(t.email) = '" . $this->db->escape($email) . "'
			AND m.author = 'customer'
			AND m.date_added >= DATE_SUB(NOW(), INTERVAL " . (int)$hours . " HOUR)");

		return (int)$query->row['total'];
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
	 * Contact-form entry point: blocklist → daily limit → append to open ticket → new ticket.
	 * @return array{action:string,ticket_id:int,request_code:string,first_for_email:bool,wait_seconds:int}
	 */
	public function submitFromContact($data) {
		$this->ensureSchema();

		$result = array(
			'action' => 'skipped',
			'ticket_id' => 0,
			'request_code' => '',
			'first_for_email' => false,
			'wait_seconds' => 0
		);

		if (!(int)$this->config->get('module_cyberpunks_shop_support_status')) {
			return $result;
		}

		$email = $this->normalizeEmail(isset($data['email']) ? $data['email'] : '');
		$raw_message = isset($data['message']) ? trim((string)$data['message']) : '';

		if ($email === '' || $raw_message === '') {
			return $result;
		}

		if ($this->isEmailBlocked($email)) {
			$result['action'] = 'blocked';
			return $result;
		}

		$window_hours = $this->config->get('module_cyberpunks_shop_support_antispam_cooldown_hours');
		$window_hours = ($window_hours === null || $window_hours === '') ? 24 : max(1, (int)$window_hours);

		$daily_max = $this->config->get('module_cyberpunks_shop_support_antispam_daily_max');
		$daily_max = ($daily_max === null || $daily_max === '') ? 2 : max(0, (int)$daily_max);

		if ($daily_max > 0 && $this->countCustomerMessagesForEmail($email, $window_hours) >= $daily_max) {
			// Soft refusal: look like success on the storefront, do not store or email.
			$result['action'] = 'soft_limit';
			return $result;
		}

		$append_open = $this->config->get('module_cyberpunks_shop_support_antispam_append_open');
		$append_open = ($append_open === null || $append_open === '') ? 1 : (int)$append_open;

		if ($append_open) {
			$open_ticket = $this->getOpenTicketForEmail($email);

			if ($open_ticket) {
				$last_message_age = $this->getSecondsSinceLastCustomerMessage((int)$open_ticket['ticket_id']);

				if ($last_message_age !== null && $last_message_age < self::APPEND_THROTTLE_SECONDS) {
					$result['action'] = 'throttled';
					$result['wait_seconds'] = max(1, self::APPEND_THROTTLE_SECONDS - $last_message_age);
					return $result;
				}

				$message_id = $this->addCustomerMessageFromImap((int)$open_ticket['ticket_id'], $raw_message, '');

				if ($message_id) {
					$result['action'] = 'appended';
					$result['ticket_id'] = (int)$open_ticket['ticket_id'];
					$result['request_code'] = $open_ticket['request_code'];
					return $result;
				}
			}
		}

		$created = $this->createFromContact($data);
		$created['action'] = $created['ticket_id'] ? 'created' : 'skipped';
		$created['wait_seconds'] = 0;

		return $created;
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

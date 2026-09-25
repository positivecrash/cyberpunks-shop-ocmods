<?php
class ModelExtensionModuleCyberpunksShopSupport extends Model {
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

	public function getStatuses() {
		return array('open', 'in_progress', 'waiting', 'closed', 'spam');
	}

	public function getActiveStatuses() {
		return array('open', 'in_progress', 'waiting', 'closed');
	}

	/**
	 * @param array $data filter_* plus optional exclude_spam (bool) when filter_status empty
	 */
	public function getTickets($data = array()) {
		$this->ensureSchema();

		$sql = "SELECT t.*, (
				SELECT m.message FROM `" . DB_PREFIX . "cyberpunks_support_message` m
				WHERE m.ticket_id = t.ticket_id ORDER BY m.message_id ASC LIMIT 1
			) AS first_message,
			(
				SELECT COUNT(*) FROM `" . DB_PREFIX . "cyberpunks_support_message` m2
				WHERE m2.ticket_id = t.ticket_id
			) AS message_count
			FROM `" . DB_PREFIX . "cyberpunks_support_ticket` t WHERE 1";

		$sql .= $this->buildTicketFilterSql($data);

		$sort_data = array(
			't.request_code',
			't.email',
			't.status',
			't.date_added',
			't.date_modified'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY t.date_modified";
		}

		$sql .= (isset($data['order']) && strtoupper($data['order']) === 'ASC') ? " ASC" : " DESC";

		$start = isset($data['start']) ? (int)$data['start'] : 0;
		$limit = isset($data['limit']) ? (int)$data['limit'] : 20;
		if ($start < 0) {
			$start = 0;
		}
		if ($limit < 1) {
			$limit = 20;
		}

		$sql .= " LIMIT " . $start . "," . $limit;

		return $this->db->query($sql)->rows;
	}

	public function getTotalTickets($data = array()) {
		$this->ensureSchema();

		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "cyberpunks_support_ticket` t WHERE 1";
		$sql .= $this->buildTicketFilterSql($data);

		$query = $this->db->query($sql);
		return (int)$query->row['total'];
	}

	public function getTotalTicketsByStatus($status) {
		$this->ensureSchema();
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "cyberpunks_support_ticket`
			WHERE status = '" . $this->db->escape($status) . "'");
		return (int)$query->row['total'];
	}

	private function buildTicketFilterSql($data) {
		$sql = '';

		if (!empty($data['filter_request_code'])) {
			$sql .= " AND t.request_code LIKE '" . $this->db->escape($data['filter_request_code']) . "%'";
		}
		if (!empty($data['filter_email'])) {
			$sql .= " AND t.email LIKE '%" . $this->db->escape($data['filter_email']) . "%'";
		}
		if (!empty($data['filter_status'])) {
			$sql .= " AND t.status = '" . $this->db->escape($data['filter_status']) . "'";
		} elseif (!empty($data['exclude_spam'])) {
			$sql .= " AND t.status <> 'spam'";
		}

		return $sql;
	}

	public function getTicket($ticket_id) {
		$this->ensureSchema();
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cyberpunks_support_ticket` WHERE ticket_id = '" . (int)$ticket_id . "' LIMIT 1");
		return $query->row;
	}

	public function getTicketByCode($request_code) {
		$this->ensureSchema();
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cyberpunks_support_ticket` WHERE request_code = '" . $this->db->escape($request_code) . "' LIMIT 1");
		return $query->row;
	}

	public function getMessages($ticket_id) {
		$this->ensureSchema();
		return $this->db->query("SELECT * FROM `" . DB_PREFIX . "cyberpunks_support_message`
			WHERE ticket_id = '" . (int)$ticket_id . "'
			ORDER BY message_id ASC")->rows;
	}

	public function updateStatus($ticket_id, $status) {
		$allowed = $this->getStatuses();
		if (!in_array($status, $allowed, true)) {
			return;
		}
		$this->db->query("UPDATE `" . DB_PREFIX . "cyberpunks_support_ticket` SET
			status = '" . $this->db->escape($status) . "',
			date_modified = NOW()
			WHERE ticket_id = '" . (int)$ticket_id . "'");
	}

	public function addAdminMessage($ticket_id, $message, $user_id = 0) {
		$message = trim((string)$message);
		if ($message === '') {
			return 0;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_support_message` SET
			ticket_id = '" . (int)$ticket_id . "',
			author = 'admin',
			user_id = '" . (int)$user_id . "',
			message = '" . $this->db->escape($message) . "',
			date_added = NOW()");

		$message_id = (int)$this->db->getLastId();

		$this->db->query("UPDATE `" . DB_PREFIX . "cyberpunks_support_ticket` SET
			date_modified = NOW()
			WHERE ticket_id = '" . (int)$ticket_id . "'");

		return $message_id;
	}

	public function addInternalNote($ticket_id, $message, $user_id = 0) {
		$message = trim((string)$message);
		if ($message === '') {
			return 0;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_support_message` SET
			ticket_id = '" . (int)$ticket_id . "',
			author = 'internal',
			user_id = '" . (int)$user_id . "',
			message = '" . $this->db->escape($message) . "',
			date_added = NOW()");

		$message_id = (int)$this->db->getLastId();

		$this->db->query("UPDATE `" . DB_PREFIX . "cyberpunks_support_ticket` SET
			date_modified = NOW()
			WHERE ticket_id = '" . (int)$ticket_id . "'");

		return $message_id;
	}

	public function addCustomerMessage($ticket_id, $message) {
		return $this->addCustomerMessageFromImap($ticket_id, $message, '');
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

	public function deleteTicket($ticket_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_support_message` WHERE ticket_id = '" . (int)$ticket_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_support_ticket` WHERE ticket_id = '" . (int)$ticket_id . "'");
	}

	public function normalizeEmail($email) {
		$email = trim((string)$email);
		return ($email === '') ? '' : utf8_strtolower($email);
	}

	public function getBlocklist($data = array()) {
		$this->ensureSchema();

		$sql = "SELECT * FROM `" . DB_PREFIX . "cyberpunks_support_blocklist` WHERE 1";

		if (!empty($data['filter_email'])) {
			$sql .= " AND email LIKE '%" . $this->db->escape($this->normalizeEmail($data['filter_email'])) . "%'";
		}

		$sql .= " ORDER BY date_added DESC, block_id DESC";

		$start = isset($data['start']) ? max(0, (int)$data['start']) : 0;
		$limit = isset($data['limit']) ? (int)$data['limit'] : 20;
		if ($limit < 1) {
			$limit = 20;
		}

		$sql .= " LIMIT " . $start . "," . $limit;

		return $this->db->query($sql)->rows;
	}

	public function getTotalBlocklist($data = array()) {
		$this->ensureSchema();

		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "cyberpunks_support_blocklist` WHERE 1";

		if (!empty($data['filter_email'])) {
			$sql .= " AND email LIKE '%" . $this->db->escape($this->normalizeEmail($data['filter_email'])) . "%'";
		}

		return (int)$this->db->query($sql)->row['total'];
	}

	/**
	 * @return bool true when a new row was inserted
	 */
	public function addBlockedEmail($email, $note = '', $user_id = 0) {
		$email = $this->normalizeEmail($email);

		if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return false;
		}

		$this->ensureSchema();

		$exists = $this->db->query("SELECT block_id FROM `" . DB_PREFIX . "cyberpunks_support_blocklist`
			WHERE email = '" . $this->db->escape($email) . "' LIMIT 1");

		if ($exists->num_rows) {
			return false;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_support_blocklist` SET
			email = '" . $this->db->escape(utf8_substr($email, 0, 96)) . "',
			note = '" . $this->db->escape(utf8_substr(trim((string)$note), 0, 255)) . "',
			user_id = '" . (int)$user_id . "',
			date_added = NOW()");

		return true;
	}

	public function deleteBlockedEmail($block_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_support_blocklist` WHERE block_id = '" . (int)$block_id . "'");
	}

	/**
	 * Normalize a customer phrase for the stop-words list (trim + strip trailing dots).
	 */
	public function normalizeStopPhrase($message) {
		$message = trim((string)$message);
		$message = preg_replace('/^Reason:\s*.+?(?:\r?\n)+/i', '', $message, 1);
		$message = trim((string)$message);
		$message = rtrim($message, ". \t");
		$message = trim($message);

		return $message;
	}

	/**
	 * All customer message bodies from the given tickets (oldest first).
	 * @return string[]
	 */
	public function getCustomerPhrasesFromTickets($ticket_ids) {
		$ids = array();
		foreach ((array)$ticket_ids as $ticket_id) {
			$ticket_id = (int)$ticket_id;
			if ($ticket_id > 0) {
				$ids[$ticket_id] = $ticket_id;
			}
		}

		if (!$ids) {
			return array();
		}

		$this->ensureSchema();

		$rows = $this->db->query("SELECT message FROM `" . DB_PREFIX . "cyberpunks_support_message`
			WHERE ticket_id IN (" . implode(',', $ids) . ")
			AND author = 'customer'
			ORDER BY message_id ASC")->rows;

		$phrases = array();
		$seen = array();

		foreach ($rows as $row) {
			$phrase = $this->normalizeStopPhrase(isset($row['message']) ? $row['message'] : '');

			if ($phrase === '') {
				continue;
			}

			$key = function_exists('mb_strtolower') ? mb_strtolower($phrase, 'UTF-8') : utf8_strtolower($phrase);

			if (isset($seen[$key])) {
				continue;
			}

			$seen[$key] = true;
			$phrases[] = $phrase;
		}

		return $phrases;
	}

	/**
	 * Append unique phrases to module stop-words setting.
	 * @return int number of newly added lines
	 */
	public function appendStopWords($phrases) {
		$phrases = (array)$phrases;
		$added = 0;

		if (!$phrases) {
			return 0;
		}

		$key = 'module_cyberpunks_shop_support_antispam_stopwords';
		$current = (string)$this->config->get($key);
		$lines = preg_split('/\r\n|\r|\n/', $current);
		if (!is_array($lines)) {
			$lines = array();
		}

		$existing = array();
		foreach ($lines as $line) {
			$line = trim((string)$line);
			if ($line === '' || strpos($line, '#') === 0) {
				continue;
			}
			$norm = $this->normalizeStopPhrase($line);
			if ($norm === '') {
				continue;
			}
			$lk = function_exists('mb_strtolower') ? mb_strtolower($norm, 'UTF-8') : utf8_strtolower($norm);
			$existing[$lk] = true;
		}

		foreach ($phrases as $phrase) {
			$phrase = $this->normalizeStopPhrase($phrase);
			if ($phrase === '') {
				continue;
			}
			$lk = function_exists('mb_strtolower') ? mb_strtolower($phrase, 'UTF-8') : utf8_strtolower($phrase);
			if (isset($existing[$lk])) {
				continue;
			}
			$existing[$lk] = true;
			$lines[] = $phrase;
			$added++;
		}

		if ($added < 1) {
			return 0;
		}

		// Preserve blank/comment lines; rewrite as joined list ending without extra blanks.
		$out = array();
		foreach ($lines as $line) {
			$line = rtrim((string)$line, "\r\n");
			$out[] = $line;
		}
		while ($out && trim(end($out)) === '') {
			array_pop($out);
		}
		$value = implode("\n", $out);

		$this->load->model('setting/setting');
		$settings = $this->model_setting_setting->getSetting('module_cyberpunks_shop_support');
		if (!is_array($settings)) {
			$settings = array();
		}
		$settings[$key] = $value;
		$this->model_setting_setting->editSetting('module_cyberpunks_shop_support', $settings);
		$this->config->set($key, $value);

		return $added;
	}

	/**
	 * Mark tickets as Spam.
	 * @return int number of tickets updated
	 */
	public function markTicketsAsSpam($ticket_ids) {
		$ids = array();
		foreach ((array)$ticket_ids as $ticket_id) {
			$ticket_id = (int)$ticket_id;
			if ($ticket_id > 0) {
				$ids[$ticket_id] = $ticket_id;
			}
		}

		if (!$ids) {
			return 0;
		}

		$this->ensureSchema();

		$this->db->query("UPDATE `" . DB_PREFIX . "cyberpunks_support_ticket` SET
			status = 'spam',
			date_modified = NOW()
			WHERE ticket_id IN (" . implode(',', $ids) . ")");

		return count($ids);
	}

	/**
	 * Block emails of the given tickets, move them to Spam, optionally append customer phrases to stop words.
	 * @param bool $add_stopwords
	 * @return array{added:int,skipped:int,spam:int,stopwords:int}
	 */
	public function blockEmailsFromTickets($ticket_ids, $user_id = 0, $add_stopwords = false) {
		$result = array('added' => 0, 'skipped' => 0, 'spam' => 0, 'stopwords' => 0);

		$ids = array();
		foreach ((array)$ticket_ids as $ticket_id) {
			$ticket_id = (int)$ticket_id;
			if ($ticket_id > 0) {
				$ids[$ticket_id] = $ticket_id;
			}
		}

		if (!$ids) {
			return $result;
		}

		$this->ensureSchema();

		$rows = $this->db->query("SELECT DISTINCT email, request_code FROM `" . DB_PREFIX . "cyberpunks_support_ticket`
			WHERE ticket_id IN (" . implode(',', $ids) . ")")->rows;

		foreach ($rows as $row) {
			if ($this->addBlockedEmail($row['email'], 'Blocked from request ' . $row['request_code'], $user_id)) {
				$result['added']++;
			} else {
				$result['skipped']++;
			}
		}

		$result['spam'] = $this->markTicketsAsSpam($ids);

		if ($add_stopwords) {
			$result['stopwords'] = $this->appendStopWords($this->getCustomerPhrasesFromTickets($ids));
		}

		return $result;
	}
}

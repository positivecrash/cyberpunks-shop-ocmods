<?php
/**
 * Multilingual note on product_special + product_discount (JSON map language_id => text).
 */
class ModelExtensionModuleCyberpunksShopOfferNotes extends Model {
	public function ensureSchema() {
		$this->ensureNoteColumn('product_special');
		$this->ensureNoteColumn('product_discount');
	}

	private function ensureNoteColumn($table) {
		$full = DB_PREFIX . $table;
		$exists = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($full) . "'");

		if (!$exists->num_rows) {
			return false;
		}

		$col = $this->db->query("SHOW COLUMNS FROM `" . $full . "` LIKE 'note'");

		if ($col->num_rows) {
			$type = isset($col->row['Type']) ? strtolower($col->row['Type']) : '';

			if (strpos($type, 'varchar') !== false || strpos($type, 'char') !== false) {
				$this->db->query("ALTER TABLE `" . $full . "` MODIFY `note` TEXT NULL");
			}

			return true;
		}

		$this->db->query("ALTER TABLE `" . $full . "` ADD `note` TEXT NULL AFTER `date_end`");

		return true;
	}

	public function decodeNote($raw) {
		$raw = trim((string)$raw);

		if ($raw === '') {
			return array();
		}

		$decoded = json_decode($raw, true);

		if (is_array($decoded)) {
			$out = array();

			foreach ($decoded as $language_id => $text) {
				$out[(int)$language_id] = (string)$text;
			}

			return $out;
		}

		$language_id = (int)$this->config->get('config_language_id');

		if ($language_id < 1) {
			$language_id = 1;
		}

		return array($language_id => $raw);
	}

	public function resolveNote($raw, $language_id = 0) {
		$map = $this->decodeNote($raw);
		$language_id = (int)$language_id;

		if ($language_id < 1) {
			$language_id = (int)$this->config->get('config_language_id');
		}

		if ($language_id > 0 && isset($map[$language_id]) && trim($map[$language_id]) !== '') {
			return trim($map[$language_id]);
		}

		foreach ($map as $text) {
			if (trim((string)$text) !== '') {
				return trim((string)$text);
			}
		}

		return '';
	}

	/**
	 * Note from the same special row that drives the active sale price.
	 */
	public function getActiveSpecialNote($product_id, $language_id = 0) {
		$this->ensureSchema();

		$product_id = (int)$product_id;

		if ($product_id < 1) {
			return '';
		}

		$customer_group_id = (int)$this->config->get('config_customer_group_id');

		$query = $this->db->query(
			"SELECT `note` FROM `" . DB_PREFIX . "product_special`"
			. " WHERE `product_id` = '" . $product_id . "'"
			. " AND `customer_group_id` = '" . $customer_group_id . "'"
			. " AND ((`date_start` = '0000-00-00' OR `date_start` < NOW())"
			. " AND (`date_end` = '0000-00-00' OR `date_end` > NOW()))"
			. " ORDER BY `priority` ASC, `price` ASC LIMIT 1"
		);

		if (!$query->num_rows) {
			return '';
		}

		return $this->resolveNote($query->row['note'], $language_id);
	}
}

<?php
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

	/**
	 * Encode admin POST note map (language_id => text) to JSON for DB storage.
	 */
	public function encodeNote($note) {
		$map = array();

		if (is_array($note)) {
			foreach ($note as $language_id => $text) {
				$text = trim((string)$text);

				if ($text !== '') {
					$map[(string)(int)$language_id] = $text;
				}
			}
		} elseif (is_string($note)) {
			$text = trim($note);

			if ($text !== '') {
				$language_id = (int)$this->config->get('config_language_id');

				if ($language_id < 1) {
					$language_id = 1;
				}

				$map[(string)$language_id] = $text;
			}
		}

		if (!$map) {
			return '';
		}

		return json_encode($map, JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Decode DB note (JSON or legacy plain string) to language_id => text.
	 */
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

	public function decodeNoteRows($rows) {
		foreach ($rows as &$row) {
			$row['note'] = $this->decodeNote(isset($row['note']) ? $row['note'] : '');
		}
		unset($row);

		return $rows;
	}
}

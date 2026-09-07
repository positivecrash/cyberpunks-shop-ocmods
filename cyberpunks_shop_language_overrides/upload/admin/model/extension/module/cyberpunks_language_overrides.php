<?php
class ModelExtensionModuleCyberpunksLanguageOverrides extends Model {
	public function install() {
		$this->ensureSchema();
		$this->seedDefaults();
	}

	public function ensureSchema() {
		if (is_file(DIR_SYSTEM . 'library/cyberpunks_cb_lang.php')) {
			require_once(DIR_SYSTEM . 'library/cyberpunks_cb_lang.php');
			CyberpunksCbLang::ensureSchema($this->db);
		} else {
			$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_cb_lang` (
				`string_id` INT(11) NOT NULL AUTO_INCREMENT,
				`source_text` MEDIUMTEXT NOT NULL,
				`source_hash` CHAR(64) NOT NULL,
				`comment` TEXT NOT NULL,
				`date_added` DATETIME NOT NULL,
				`date_modified` DATETIME NOT NULL,
				PRIMARY KEY (`string_id`),
				UNIQUE KEY `source_hash` (`source_hash`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

			$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_cb_lang_value` (
				`string_id` INT(11) NOT NULL,
				`language_id` INT(11) NOT NULL,
				`translation` MEDIUMTEXT NOT NULL,
				PRIMARY KEY (`string_id`, `language_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
		}

		// Ensure SEO keywords (products, information, routes, …) exist for all active languages.
		if (is_file(DIR_SYSTEM . 'library/cyberpunks_url_locale.php')) {
			require_once(DIR_SYSTEM . 'library/cyberpunks_url_locale.php');
			CyberpunksUrlLocale::ensureSeoUrls($this->db, (int)$this->config->get('config_language_id'));
		}

		// OpenCart htmlspecialchars()'s request->post — undo so hashes match cb_lang('…').
		$this->repairHtmlEncodedStrings();
	}

	/**
	 * Decode OpenCart request encoding (e.g. All &gt;&gt; → All >>) and rehash.
	 */
	public function repairHtmlEncodedStrings() {
		$query = $this->db->query("SELECT string_id, source_text, source_hash, comment FROM `" . DB_PREFIX . "cyberpunks_cb_lang`");

		foreach ($query->rows as $row) {
			$source = $this->decodeRequestText($row['source_text']);
			$comment = $this->decodeRequestText($row['comment']);

			if ($source === $row['source_text'] && $comment === $row['comment']) {
				continue;
			}

			$hash = hash('sha256', $source);
			$dup = $this->db->query("SELECT string_id FROM `" . DB_PREFIX . "cyberpunks_cb_lang` WHERE source_hash = '" . $this->db->escape($hash) . "' AND string_id != '" . (int)$row['string_id'] . "'");

			if ($dup->num_rows) {
				// Keep the already-decoded row; drop this encoded duplicate.
				$this->deleteString((int)$row['string_id']);
				continue;
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "cyberpunks_cb_lang` SET
				source_text = '" . $this->db->escape($source) . "',
				source_hash = '" . $this->db->escape($hash) . "',
				comment = '" . $this->db->escape($comment) . "'
				WHERE string_id = '" . (int)$row['string_id'] . "'");
		}

		$values = $this->db->query("SELECT string_id, language_id, translation FROM `" . DB_PREFIX . "cyberpunks_cb_lang_value`");

		foreach ($values->rows as $row) {
			$translation = $this->decodeRequestText($row['translation']);

			if ($translation === $row['translation']) {
				continue;
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "cyberpunks_cb_lang_value` SET
				translation = '" . $this->db->escape($translation) . "'
				WHERE string_id = '" . (int)$row['string_id'] . "' AND language_id = '" . (int)$row['language_id'] . "'");
		}

		if (is_file(DIR_SYSTEM . 'library/cyberpunks_cb_lang.php')) {
			require_once(DIR_SYSTEM . 'library/cyberpunks_cb_lang.php');
			CyberpunksCbLang::flushCache();
		}
	}

	private function decodeRequestText($text) {
		$text = trim((string)$text);

		if ($text === '' || (strpos($text, '&') === false && strpos($text, '<') === false)) {
			return $text;
		}

		$flags = defined('ENT_HTML5') ? (ENT_QUOTES | ENT_HTML5) : ENT_QUOTES;

		return trim(html_entity_decode($text, $flags, 'UTF-8'));
	}

	public function getLanguages() {
		$query = $this->db->query("SELECT language_id, name, code, sort_order FROM `" . DB_PREFIX . "language` WHERE status = '1' ORDER BY sort_order ASC, name ASC");

		return $query->rows;
	}

	public function getStrings() {
		$this->ensureSchema();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cyberpunks_cb_lang` ORDER BY string_id DESC");
		$strings = array();

		foreach ($query->rows as $row) {
			$row['translations'] = $this->getTranslations((int)$row['string_id']);
			$strings[] = $row;
		}

		return $strings;
	}

	public function getString($string_id) {
		$this->ensureSchema();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cyberpunks_cb_lang` WHERE string_id = '" . (int)$string_id . "'");

		if (!$query->num_rows) {
			return null;
		}

		$row = $query->row;
		$row['translations'] = $this->getTranslations((int)$row['string_id']);

		return $row;
	}

	public function getTranslations($string_id) {
		$query = $this->db->query("SELECT language_id, translation FROM `" . DB_PREFIX . "cyberpunks_cb_lang_value` WHERE string_id = '" . (int)$string_id . "'");
		$out = array();

		foreach ($query->rows as $row) {
			$out[(int)$row['language_id']] = $row['translation'];
		}

		return $out;
	}

	public function saveString($data) {
		$this->ensureSchema();

		$source = isset($data['source_text']) ? $this->decodeRequestText($data['source_text']) : '';
		$comment = isset($data['comment']) ? $this->decodeRequestText($data['comment']) : '';
		$translations = isset($data['translations']) && is_array($data['translations']) ? $data['translations'] : array();
		$string_id = isset($data['string_id']) ? (int)$data['string_id'] : 0;

		if ($source === '') {
			return false;
		}

		// Drop English rows — Original (EN) is the English text.
		$en_ids = array();
		foreach ($this->getLanguages() as $language) {
			$code = strtolower(isset($language['code']) ? (string)$language['code'] : '');
			if ($code === 'en-gb' || $code === 'en' || strpos($code, 'en-') === 0) {
				$en_ids[(int)$language['language_id']] = true;
			}
		}
		foreach (array_keys($translations) as $language_id) {
			if (isset($en_ids[(int)$language_id])) {
				unset($translations[$language_id]);
			}
		}

		$hash = hash('sha256', $source);
		$now = date('Y-m-d H:i:s');

		if ($string_id > 0) {
			$existing = $this->getString($string_id);

			if (!$existing) {
				return false;
			}

			$dup = $this->db->query("SELECT string_id, source_text FROM `" . DB_PREFIX . "cyberpunks_cb_lang` WHERE source_hash = '" . $this->db->escape($hash) . "' AND string_id != '" . (int)$string_id . "' LIMIT 1");

			if ($dup->num_rows) {
				return array(
					'error'       => 'duplicate',
					'string_id'   => (int)$dup->row['string_id'],
					'source_text' => (string)$dup->row['source_text']
				);
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "cyberpunks_cb_lang` SET
				source_text = '" . $this->db->escape($source) . "',
				source_hash = '" . $this->db->escape($hash) . "',
				comment = '" . $this->db->escape($comment) . "',
				date_modified = '" . $this->db->escape($now) . "'
				WHERE string_id = '" . (int)$string_id . "'");
		} else {
			$dup = $this->db->query("SELECT string_id, source_text FROM `" . DB_PREFIX . "cyberpunks_cb_lang` WHERE source_hash = '" . $this->db->escape($hash) . "' LIMIT 1");

			if ($dup->num_rows) {
				return array(
					'error'       => 'duplicate',
					'string_id'   => (int)$dup->row['string_id'],
					'source_text' => (string)$dup->row['source_text']
				);
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_cb_lang` SET
				source_text = '" . $this->db->escape($source) . "',
				source_hash = '" . $this->db->escape($hash) . "',
				comment = '" . $this->db->escape($comment) . "',
				date_added = '" . $this->db->escape($now) . "',
				date_modified = '" . $this->db->escape($now) . "'");

			$string_id = (int)$this->db->getLastId();
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_cb_lang_value` WHERE string_id = '" . (int)$string_id . "'");

		foreach ($translations as $language_id => $translation) {
			$language_id = (int)$language_id;
			$translation = trim($this->decodeRequestText($translation));

			if ($language_id < 1 || $translation === '') {
				continue;
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_cb_lang_value` SET
				string_id = '" . (int)$string_id . "',
				language_id = '" . (int)$language_id . "',
				translation = '" . $this->db->escape($translation) . "'");
		}

		if (is_file(DIR_SYSTEM . 'library/cyberpunks_cb_lang.php')) {
			require_once(DIR_SYSTEM . 'library/cyberpunks_cb_lang.php');
			CyberpunksCbLang::flushCache();
		}

		return $string_id;
	}

	public function deleteString($string_id) {
		$this->ensureSchema();
		$string_id = (int)$string_id;

		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_cb_lang_value` WHERE string_id = '" . (int)$string_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_cb_lang` WHERE string_id = '" . (int)$string_id . "'");

		if (is_file(DIR_SYSTEM . 'library/cyberpunks_cb_lang.php')) {
			require_once(DIR_SYSTEM . 'library/cyberpunks_cb_lang.php');
			CyberpunksCbLang::flushCache();
		}
	}

	/**
	 * Build CSV rows for theme strings.
	 * Columns: source_text, comment, then one column per language code (non-English).
	 *
	 * @param array $languages language rows with language_id + code (already filtered)
	 * @return array{headers: string[], rows: array<int, string[]>}
	 */
	public function buildExportCsv($languages) {
		$this->ensureSchema();

		$headers = array('source_text', 'comment');
		$lang_codes = array();

		foreach ($languages as $language) {
			$code = strtolower(trim((string)$language['code']));
			$headers[] = $code;
			$lang_codes[] = array(
				'code' => $code,
				'language_id' => (int)$language['language_id']
			);
		}

		$rows = array();

		foreach ($this->getStrings() as $string) {
			$row = array(
				(string)$string['source_text'],
				isset($string['comment']) ? (string)$string['comment'] : ''
			);
			$translations = isset($string['translations']) && is_array($string['translations'])
				? $string['translations']
				: array();

			foreach ($lang_codes as $lang) {
				$lid = $lang['language_id'];
				$row[] = isset($translations[$lid]) ? (string)$translations[$lid] : '';
			}

			$rows[] = $row;
		}

		return array(
			'headers' => $headers,
			'rows' => $rows
		);
	}

	/**
	 * Import theme strings from parsed CSV (header + data rows).
	 * Empty translation cells leave existing values unchanged.
	 * Empty comment keeps existing comment on update.
	 *
	 * @param array $headers
	 * @param array $rows
	 * @return array{created: int, updated: int, skipped: int, errors: string[]}
	 */
	public function importCsv($headers, $rows) {
		$this->ensureSchema();

		$stats = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors' => array()
		);

		if (!$headers || !is_array($headers)) {
			$stats['errors'][] = 'Missing CSV header row.';

			return $stats;
		}

		$map = array();
		foreach ($headers as $i => $header) {
			$key = strtolower(trim((string)$header));
			if ($key !== '') {
				$map[$key] = (int)$i;
			}
		}

		if (!isset($map['source_text'])) {
			$stats['errors'][] = 'CSV must include a source_text column.';

			return $stats;
		}

		$code_to_id = array();
		foreach ($this->getLanguages() as $language) {
			$code = strtolower(trim((string)$language['code']));
			if ($code === 'en-gb' || $code === 'en' || strpos($code, 'en-') === 0) {
				continue;
			}
			$code_to_id[$code] = (int)$language['language_id'];
		}

		foreach ($rows as $line_no => $cols) {
			if (!is_array($cols)) {
				$stats['skipped']++;
				continue;
			}

			$source = isset($cols[$map['source_text']]) ? trim((string)$cols[$map['source_text']]) : '';

			if ($source === '') {
				$stats['skipped']++;
				continue;
			}

			$comment = '';
			if (isset($map['comment']) && isset($cols[$map['comment']])) {
				$comment = trim((string)$cols[$map['comment']]);
			}

			$file_translations = array();
			foreach ($code_to_id as $code => $language_id) {
				if (!isset($map[$code])) {
					continue;
				}
				$value = isset($cols[$map[$code]]) ? trim((string)$cols[$map[$code]]) : '';
				if ($value !== '') {
					$file_translations[$language_id] = $value;
				}
			}

			$hash = hash('sha256', $source);
			$existing = $this->db->query("SELECT string_id, comment FROM `" . DB_PREFIX . "cyberpunks_cb_lang` WHERE source_hash = '" . $this->db->escape($hash) . "' LIMIT 1");

			if ($existing->num_rows) {
				$string_id = (int)$existing->row['string_id'];
				$merged = $this->getTranslations($string_id);

				foreach ($file_translations as $language_id => $translation) {
					$merged[(int)$language_id] = $translation;
				}

				$save_comment = $comment !== '' ? $comment : (string)$existing->row['comment'];

				$result = $this->saveString(array(
					'string_id' => $string_id,
					'source_text' => $source,
					'comment' => $save_comment,
					'translations' => $merged
				));

				if (is_array($result) || $result === false) {
					$stats['errors'][] = 'Line ' . ((int)$line_no + 2) . ': could not update “' . $this->previewText($source) . '”.';
					$stats['skipped']++;
				} else {
					$stats['updated']++;
				}
			} else {
				$result = $this->saveString(array(
					'source_text' => $source,
					'comment' => $comment,
					'translations' => $file_translations
				));

				if (is_array($result) || $result === false) {
					$stats['errors'][] = 'Line ' . ((int)$line_no + 2) . ': could not create “' . $this->previewText($source) . '”.';
					$stats['skipped']++;
				} else {
					$stats['created']++;
				}
			}
		}

		return $stats;
	}

	private function previewText($text) {
		$text = (string)$text;

		if (function_exists('utf8_strlen') && utf8_strlen($text) > 80) {
			return utf8_substr($text, 0, 77) . '...';
		}

		if (strlen($text) > 80) {
			return substr($text, 0, 77) . '...';
		}

		return $text;
	}

	public function seedDefaults() {
		$this->ensureSchema();

		// No hardcoded seeding: storefront/theme uses cb_lang() and you add/edit strings manually in admin.
		//
		// Intentionally no-op: all default translations are removed.
		return;
	}
}

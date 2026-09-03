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

		// Ensure route-based SEO URLs (cart, checkout, etc.) exist for all active languages.
		$this->ensureRouteSeoUrls();

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

			$dup = $this->db->query("SELECT string_id FROM `" . DB_PREFIX . "cyberpunks_cb_lang` WHERE source_hash = '" . $this->db->escape($hash) . "' AND string_id != '" . (int)$string_id . "'");

			if ($dup->num_rows) {
				return 'duplicate';
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "cyberpunks_cb_lang` SET
				source_text = '" . $this->db->escape($source) . "',
				source_hash = '" . $this->db->escape($hash) . "',
				comment = '" . $this->db->escape($comment) . "',
				date_modified = '" . $this->db->escape($now) . "'
				WHERE string_id = '" . (int)$string_id . "'");
		} else {
			$dup = $this->db->query("SELECT string_id FROM `" . DB_PREFIX . "cyberpunks_cb_lang` WHERE source_hash = '" . $this->db->escape($hash) . "'");

			if ($dup->num_rows) {
				return 'duplicate';
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
	 * Copy ALL route-based SEO URLs to every active language.
	 * Automatically covers new languages added later — no hardcoded route list.
	 */
	private function ensureRouteSeoUrls() {
		$languages = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE status = '1'");
		$all_routes = $this->db->query("SELECT DISTINCT store_id, `query`, keyword, language_id FROM `" . DB_PREFIX . "seo_url` WHERE `query` LIKE 'route=%'");

		$groups = array();
		foreach ($all_routes->rows as $row) {
			$key = (int)$row['store_id'] . '|' . $row['query'];
			if (!isset($groups[$key])) {
				$groups[$key] = array(
					'store_id' => (int)$row['store_id'],
					'query'    => $row['query'],
					'keyword'  => $row['keyword'],
					'have'     => array(),
				);
			}
			$groups[$key]['have'][(int)$row['language_id']] = true;
		}

		foreach ($groups as $g) {
			foreach ($languages->rows as $lang) {
				$lid = (int)$lang['language_id'];
				if (isset($g['have'][$lid])) {
					continue;
				}
				$this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET
					store_id = '" . $g['store_id'] . "',
					language_id = '" . $lid . "',
					`query` = '" . $this->db->escape($g['query']) . "',
					keyword = '" . $this->db->escape($g['keyword']) . "'");
			}
		}
	}

	public function seedDefaults() {
		$this->ensureSchema();

		// No hardcoded seeding: storefront/theme uses cb_lang() and you add/edit strings manually in admin.
		//
		// Intentionally no-op: all default translations are removed.
		return;
	}
}

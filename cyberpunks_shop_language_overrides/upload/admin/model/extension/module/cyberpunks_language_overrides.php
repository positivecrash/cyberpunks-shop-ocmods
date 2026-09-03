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

	public function seedDefaults() {
		$this->ensureSchema();

		$count = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "cyberpunks_cb_lang`");

		if (!empty($count->row['total'])) {
			return;
		}

		$languages = $this->getLanguages();
		$by_code = array();

		foreach ($languages as $language) {
			$by_code[$language['code']] = (int)$language['language_id'];
		}

		$en_id = isset($by_code['en-gb']) ? $by_code['en-gb'] : 0;
		$nl_id = isset($by_code['nl-nl']) ? $by_code['nl-nl'] : 0;

		$seeds = array(
			array(
				'source' => 'Unique open-source smart hardware',
				'comment' => 'Header top banner',
				'nl' => 'Unieke open-source smart hardware'
			),
			array(
				'source' => 'Worldwide delivery',
				'comment' => 'Header top banner',
				'nl' => 'Wereldwijde levering'
			),
			array(
				'source' => 'Made and shipped from the EU',
				'comment' => 'Header top banner',
				'nl' => 'Gemaakt en verzonden vanuit de EU'
			),
			array(
				'source' => '2-year guarantee',
				'comment' => 'Header top banner',
				'nl' => '2 jaar garantie'
			),
			array(
				'source' => 'Site announcements',
				'comment' => 'Header banner aria-label',
				'nl' => 'Site-aankondigingen'
			),
			array(
				'source' => 'Select country',
				'comment' => 'Header country select aria-label',
				'nl' => 'Selecteer land'
			),
			array(
				'source' => 'Select language',
				'comment' => 'Header language select aria-label',
				'nl' => 'Selecteer taal'
			),
			array(
				'source' => 'Buy now',
				'comment' => 'Home / product CTA',
				'nl' => 'Nu kopen'
			),
			array(
				'source' => 'Featured products',
				'comment' => 'Home slider aria-label',
				'nl' => 'Uitgelichte producten'
			),
			array(
				'source' => 'Previous slide',
				'comment' => 'Home slider',
				'nl' => 'Vorige slide'
			),
			array(
				'source' => 'Next slide',
				'comment' => 'Home slider',
				'nl' => 'Volgende slide'
			),
			array(
				'source' => 'Connect your sleep, HRV, and recovery metrics with real environmental conditions around your home using detailed air-quality, CO₂, noise, and climate analytics',
				'comment' => 'Home slider — Altruist Dual',
				'nl' => 'Koppel je slaap-, HRV- en herstelmetrics aan echte omgevingsomstandigheden rondom je huis met gedetailleerde lucht-, CO₂-, geluids- en klimaatanalyses'
			),
			array(
				'source' => 'Go beyond simplified AQI maps with real-time local air-quality monitoring, detailed timelines, and noise analytics',
				'comment' => 'Home slider — Altruist Urban',
				'nl' => 'Ga verder dan vereenvoudigde AQI-kaarten met realtime lokale luchtkwaliteitsmonitoring, gedetailleerde tijdlijnen en geluidsanalyses'
			),
			array(
				'source' => 'Altruist Insight is designed for precise and stable indoor CO₂ monitoring without false spikes caused by perfumes, cooking smells, candles, or other everyday VOC sources.',
				'comment' => 'Home slider — Altruist Insight',
				'nl' => 'Altruist Insight is ontworpen voor nauwkeurige en stabiele indoor CO₂-meting zonder valse pieken door parfums, kookluchtjes, kaarsen of andere alledaagse VOC-bronnen.'
			),
			array(
				'source' => 'Built through years of environmental research, combining open-source hardware, transparent software, community-driven development, and full local autonomy for users — without surveillance or vendor lock-in.',
				'comment' => 'Home closing statement',
				'nl' => 'Gebouwd op jarenlange milieuonderzoeken, met open-source hardware, transparante software, community-gedreven ontwikkeling en volledige lokale autonomie voor gebruikers — zonder surveillance of vendor lock-in.'
			),
			array(
				'source' => 'Add to Cart',
				'comment' => 'Product page',
				'nl' => 'In winkelwagen'
			),
			array(
				'source' => 'Your cart updated',
				'comment' => 'Product page',
				'nl' => 'Je winkelwagen is bijgewerkt'
			),
			array(
				'source' => 'Buy now — %s',
				'comment' => 'Product sticky buy now aria (%s = product name)',
				'nl' => 'Nu kopen — %s'
			),
			array(
				'source' => 'From',
				'comment' => 'Product price prefix',
				'nl' => 'Vanaf'
			),
			array(
				'source' => 'In stock',
				'comment' => 'Product availability',
				'nl' => 'Op voorraad'
			),
			array(
				'source' => 'Availability: In stock',
				'comment' => 'Product availability aria',
				'nl' => 'Beschikbaarheid: Op voorraad'
			),
			array(
				'source' => 'BUY ON',
				'comment' => 'Product Amazon button',
				'nl' => 'KOPEN OP'
			),
			array(
				'source' => 'Buy on Amazon',
				'comment' => 'Product Amazon button aria',
				'nl' => 'Kopen op Amazon'
			),
			array(
				'source' => 'FREE',
				'comment' => 'Product option free label',
				'nl' => 'GRATIS'
			),
			array(
				'source' => 'Pricing Information',
				'comment' => 'Product price section aria',
				'nl' => 'Prijsinformatie'
			),
			array(
				'source' => 'Current price: %s',
				'comment' => 'Product price aria (%s = price)',
				'nl' => 'Huidige prijs: %s'
			),
			array(
				'source' => 'Original price: %s',
				'comment' => 'Product old price aria (%s = price)',
				'nl' => 'Originele prijs: %s'
			)
		);

		foreach ($seeds as $seed) {
			$translations = array();

			// EN = source_text itself (no duplicate row). Only seed other languages.
			if ($nl_id && !empty($seed['nl'])) {
				$translations[$nl_id] = $seed['nl'];
			}

			$this->saveString(array(
				'source_text' => $seed['source'],
				'comment' => $seed['comment'],
				'translations' => $translations
			));
		}
	}
}

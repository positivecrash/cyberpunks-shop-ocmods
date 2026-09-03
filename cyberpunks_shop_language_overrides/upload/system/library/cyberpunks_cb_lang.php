<?php

class CyberpunksCbLang {
	private static $registry = null;
	private static $cache = array();
	private static $loaded = array();
	private static $schema_ready = false;

	public static function init($registry) {
		self::$registry = $registry;
	}

	public static function registerTwig($twig) {
		if (!$twig || !is_object($twig) || !method_exists($twig, 'addFunction')) {
			return;
		}

		if (class_exists('\\Twig\\TwigFunction')) {
			$twig->addFunction(new \Twig\TwigFunction('cb_lang', function ($source) {
				$args = func_get_args();
				array_shift($args);
				return CyberpunksCbLang::translate($source, $args);
			}));
		} elseif (class_exists('Twig_SimpleFunction')) {
			$twig->addFunction(new \Twig_SimpleFunction('cb_lang', function ($source) {
				$args = func_get_args();
				array_shift($args);
				return CyberpunksCbLang::translate($source, $args);
			}));
		}
	}

	public static function sourceHash($source) {
		return hash('sha256', (string)$source);
	}

	public static function translate($source, $args = array()) {
		$source = (string)$source;
		$out = $source;

		if (self::$registry) {
			$config = self::$registry->get('config');
			$language_id = $config ? (int)$config->get('config_language_id') : 0;

			if ($language_id > 0) {
				self::ensureLoaded($language_id);
				$hash = self::sourceHash($source);

				if (isset(self::$cache[$language_id][$hash]) && self::$cache[$language_id][$hash] !== '') {
					$out = self::$cache[$language_id][$hash];
				}
			}
		}

		if ($args) {
			$out = vsprintf($out, $args);
		}

		return $out;
	}

	public static function flushCache() {
		self::$cache = array();
		self::$loaded = array();
	}

	public static function ensureSchema($db) {
		if (self::$schema_ready || !$db) {
			return;
		}

		$db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_cb_lang` (
			`string_id` INT(11) NOT NULL AUTO_INCREMENT,
			`source_text` MEDIUMTEXT NOT NULL,
			`source_hash` CHAR(64) NOT NULL,
			`comment` TEXT NOT NULL,
			`date_added` DATETIME NOT NULL,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`string_id`),
			UNIQUE KEY `source_hash` (`source_hash`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_cb_lang_value` (
			`string_id` INT(11) NOT NULL,
			`language_id` INT(11) NOT NULL,
			`translation` MEDIUMTEXT NOT NULL,
			PRIMARY KEY (`string_id`, `language_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		self::$schema_ready = true;
	}

	private static function ensureLoaded($language_id) {
		$language_id = (int)$language_id;

		if (isset(self::$loaded[$language_id])) {
			return;
		}

		self::$cache[$language_id] = array();
		self::$loaded[$language_id] = true;

		$db = self::$registry->get('db');

		if (!$db) {
			return;
		}

		self::ensureSchema($db);

		$query = $db->query("SELECT s.source_hash, v.translation
			FROM `" . DB_PREFIX . "cyberpunks_cb_lang` s
			INNER JOIN `" . DB_PREFIX . "cyberpunks_cb_lang_value` v ON (s.string_id = v.string_id)
			WHERE v.language_id = '" . (int)$language_id . "'");

			foreach ($query->rows as $row) {
			$flags = defined('ENT_HTML5') ? (ENT_QUOTES | ENT_HTML5) : ENT_QUOTES;
			self::$cache[$language_id][$row['source_hash']] = html_entity_decode((string)$row['translation'], $flags, 'UTF-8');
		}
	}
}

if (!function_exists('cb_lang')) {
	function cb_lang($source) {
		$args = func_get_args();
		array_shift($args);

		return CyberpunksCbLang::translate($source, $args);
	}
}

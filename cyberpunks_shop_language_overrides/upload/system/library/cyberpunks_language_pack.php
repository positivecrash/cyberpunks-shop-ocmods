<?php

/**
 * Creates minimal OpenCart language directories so a new language can be added
 * from admin without manually uploading packs.
 *
 * Bootstraps {code}/{code}.php and a flag PNG (downloaded when possible).
 * Other route files keep falling back to en-gb via Language::load().
 */
class CyberpunksLanguagePack {
	/**
	 * @param string $code Directory code, e.g. de-de, fr-fr, nl-nl
	 * @return bool True when stubs exist (created or already present)
	 */
	public static function ensureStub($code) {
		$code = strtolower(trim((string)$code));

		if (!self::isValidCode($code) || $code === 'en-gb' || $code === 'en') {
			return false;
		}

		$ok = true;
		$ok = self::ensureSide(DIR_CATALOG . 'language/', $code) && $ok;

		if (defined('DIR_APPLICATION')) {
			$ok = self::ensureSide(DIR_APPLICATION . 'language/', $code) && $ok;
		}

		return $ok;
	}

	public static function isValidCode($code) {
		return (bool)preg_match('/^[a-z]{2}(-[a-z]{2})?$/', (string)$code);
	}

	private static function ensureSide($language_root, $code) {
		$language_root = rtrim(str_replace('\\', '/', (string)$language_root), '/') . '/';
		$target_dir = $language_root . $code . '/';
		$source_dir = $language_root . 'en-gb/';

		if (!is_dir($source_dir) || !is_file($source_dir . 'en-gb.php')) {
			return false;
		}

		if (!is_dir($target_dir) && !@mkdir($target_dir, 0755, true) && !is_dir($target_dir)) {
			return false;
		}

		$target_php = $target_dir . $code . '.php';

		if (!is_file($target_php)) {
			$php = file_get_contents($source_dir . 'en-gb.php');

			if ($php === false) {
				return false;
			}

			$short = self::shortCode($code);
			$php = preg_replace(
				'/\\$_\\[\'code\'\\]\\s*=\\s*\'[^\']*\'\\s*;/',
				"\$_['code']                  = '" . $short . "';",
				$php,
				1
			);

			if (@file_put_contents($target_php, $php) === false) {
				return false;
			}
		}

		$target_png = $target_dir . $code . '.png';
		$source_png = $source_dir . 'en-gb.png';
		$needs_flag = !is_file($target_png);

		// Replace placeholder copied from en-gb on first stub create.
		if (!$needs_flag && is_file($source_png) && self::sameFile($target_png, $source_png)) {
			$needs_flag = true;
		}

		if ($needs_flag) {
			if (!self::downloadFlag($target_png, $code) && !is_file($target_png) && is_file($source_png)) {
				@copy($source_png, $target_png);
			}
		}

		return is_file($target_php);
	}

	private static function downloadFlag($target_png, $code) {
		$country = self::flagCountryFromCode($code);

		if ($country === '' || !preg_match('/^[a-z]{2}$/', $country)) {
			return false;
		}

		$urls = array(
			'https://flagcdn.com/16x12/' . $country . '.png',
			'https://flagcdn.com/w20/' . $country . '.png'
		);

		foreach ($urls as $url) {
			$data = self::httpGet($url);

			if ($data === '' || strlen($data) < 40) {
				continue;
			}

			// PNG signature
			if (substr($data, 0, 8) !== "\x89PNG\r\n\x1a\n") {
				continue;
			}

			if (@file_put_contents($target_png, $data) !== false) {
				return true;
			}
		}

		return false;
	}

	private static function httpGet($url) {
		if (function_exists('curl_init')) {
			$ch = curl_init($url);

			if ($ch === false) {
				return '';
			}

			curl_setopt_array($ch, array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_TIMEOUT => 10,
				CURLOPT_USERAGENT => 'CyberpunksLanguagePack/1.0'
			));

			$data = curl_exec($ch);
			$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($data !== false && $code >= 200 && $code < 300) {
				return (string)$data;
			}

			return '';
		}

		if (ini_get('allow_url_fopen')) {
			$ctx = stream_context_create(array(
				'http' => array(
					'timeout' => 10,
					'header' => "User-Agent: CyberpunksLanguagePack/1.0\r\n"
				)
			));
			$data = @file_get_contents($url, false, $ctx);

			return $data === false ? '' : (string)$data;
		}

		return '';
	}

	/**
	 * ISO 3166-1 alpha-2 for flagcdn / similar.
	 * Prefer region from de-de → de; map language-only codes when needed.
	 */
	private static function flagCountryFromCode($code) {
		$parts = explode('-', strtolower((string)$code));

		if (isset($parts[1]) && $parts[1] !== '') {
			$region = $parts[1];

			if ($region === 'uk') {
				return 'gb';
			}

			return $region;
		}

		$lang = isset($parts[0]) ? $parts[0] : '';
		$map = array(
			'en' => 'gb',
			'uk' => 'gb',
			'el' => 'gr',
			'he' => 'il',
			'ja' => 'jp',
			'ko' => 'kr',
			'zh' => 'cn',
			'cs' => 'cz',
			'da' => 'dk',
			'sv' => 'se',
			'nb' => 'no',
			'nn' => 'no'
		);

		return isset($map[$lang]) ? $map[$lang] : $lang;
	}

	private static function sameFile($a, $b) {
		if (!is_file($a) || !is_file($b)) {
			return false;
		}

		if (filesize($a) !== filesize($b)) {
			return false;
		}

		return md5_file($a) === md5_file($b);
	}

	private static function shortCode($code) {
		$parts = explode('-', strtolower((string)$code));

		return isset($parts[0]) && $parts[0] !== '' ? $parts[0] : 'en';
	}
}

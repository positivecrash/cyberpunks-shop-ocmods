<?php

class CyberpunksLanguageSwitcher {
	/**
	 * Language labels as endonyms (name in that language), keyed by primary subtag.
	 * OpenCart admin "name" is often English or admin-locale and must not drive the storefront select.
	 */
	private static $endonyms = array(
		'en' => 'English',
		'nl' => 'Nederlands',
		'de' => 'Deutsch',
		'fr' => 'Français',
		'es' => 'Español',
		'it' => 'Italiano',
		'pt' => 'Português',
		'pl' => 'Polski',
		'ru' => 'Русский',
		'uk' => 'Українська',
		'cs' => 'Čeština',
		'sk' => 'Slovenčina',
		'sv' => 'Svenska',
		'da' => 'Dansk',
		'fi' => 'Suomi',
		'nb' => 'Norsk',
		'nn' => 'Norsk',
		'no' => 'Norsk',
		'tr' => 'Türkçe',
		'el' => 'Ελληνικά',
		'hu' => 'Magyar',
		'ro' => 'Română',
		'bg' => 'Български',
		'hr' => 'Hrvatski',
		'sl' => 'Slovenščina',
		'lt' => 'Lietuvių',
		'lv' => 'Latviešu',
		'et' => 'Eesti',
		'ja' => '日本語',
		'zh' => '中文',
		'ko' => '한국어',
		'ar' => 'العربية',
		'he' => 'עברית'
	);

	public static function endonym($code, $fallback = '') {
		$code = strtolower(trim((string)$code));
		$primary = $code;

		if (strpos($code, '-') !== false) {
			$parts = explode('-', $code, 2);
			$primary = $parts[0];
		}

		if (isset(self::$endonyms[$code])) {
			return self::$endonyms[$code];
		}

		if (isset(self::$endonyms[$primary])) {
			return self::$endonyms[$primary];
		}

		$fallback = trim((string)$fallback);

		return $fallback !== '' ? $fallback : $code;
	}

	public static function build($controller, $results) {
		$languages = array();

		if (!$controller || !is_array($results)) {
			return $languages;
		}

		$url_data = $controller->request->get;
		unset($url_data['_route_'], $url_data['language']);

		if (!isset($url_data['route'])) {
			$route = 'common/home';
			$query = '';
		} else {
			$route = $url_data['route'];
			unset($url_data['route']);
			$query = $url_data ? urldecode(http_build_query($url_data, '', '&')) : '';
		}

		$prev_code = isset($controller->session->data['language']) ? $controller->session->data['language'] : '';
		$prev_id = $controller->config->get('config_language_id');

		foreach ($results as $result) {
			if (empty($result['status'])) {
				continue;
			}

			$controller->session->data['language'] = $result['code'];
			$controller->config->set('config_language_id', (int)$result['language_id']);

			$languages[] = array(
				// Prefer admin "Language name" (the `name` field from localisation/language),
				// fallback to endonym if admin name is empty for some reason.
				'name' => (isset($result['name']) && trim((string)$result['name']) !== '')
					? trim((string)$result['name'])
					: self::endonym(isset($result['code']) ? $result['code'] : '', isset($result['name']) ? $result['name'] : ''),
				'code' => $result['code'],
				'href' => str_replace('&amp;', '&', $controller->url->link($route, $query, !empty($controller->request->server['HTTPS'])))
			);
		}

		$controller->session->data['language'] = $prev_code;
		$controller->config->set('config_language_id', $prev_id);

		return $languages;
	}
}

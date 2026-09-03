<?php

class CyberpunksUrlLocale {
	public static function shortFromCode($code) {
		$parts = explode('-', strtolower((string)$code));

		return isset($parts[0]) ? $parts[0] : '';
	}

	public static function codeFromShort($short, $languages) {
		$short = strtolower((string)$short);

		if ($short === '' || !is_array($languages)) {
			return '';
		}

		foreach ($languages as $code => $language) {
			if (empty($language['status'])) {
				continue;
			}

			if (self::shortFromCode($code) === $short) {
				return $code;
			}
		}

		return '';
	}

	public static function consumeRouteLocale($request, $languages) {
		if (!isset($request->get['_route_'])) {
			return;
		}

		$route = trim((string)$request->get['_route_'], '/');

		if ($route === '') {
			unset($request->get['_route_']);

			return;
		}

		$parts = explode('/', $route);
		$code = self::codeFromShort($parts[0], $languages);

		if ($code === '') {
			return;
		}

		$request->get['language'] = $code;
		$request->get['cyberpunks_locale_from_url'] = 1;
		array_shift($parts);

		if ($parts) {
			$request->get['_route_'] = implode('/', $parts);
		} else {
			unset($request->get['_route_']);
		}
	}

	/**
	 * Fix broken /nl/index.php?... (path prefix must not wrap index.php).
	 */
	public static function repairIndexPhpLocaleUrl($registry) {
		if (!$registry) {
			return;
		}

		$request = $registry->get('request');
		$response = $registry->get('response');
		$session = $registry->get('session');
		$config = $registry->get('config');

		if (!$request || !$response || !$session || !$config) {
			return;
		}

		$method = isset($request->server['REQUEST_METHOD']) ? strtoupper($request->server['REQUEST_METHOD']) : 'GET';

		if ($method !== 'GET') {
			return;
		}

		$uri = isset($request->server['REQUEST_URI']) ? (string)$request->server['REQUEST_URI'] : '';
		$path = parse_url($uri, PHP_URL_PATH);

		if (!is_string($path) || !preg_match('#^/([a-z]{2})/index\.php$#i', $path, $match)) {
			return;
		}

		$languages = array();
		if ($registry->has('load')) {
			// languages already available via session code
		}

		$code = isset($session->data['language']) ? $session->data['language'] : $config->get('config_language');
		$short = self::shortFromCode($code);

		if ($short === '' || strtolower($match[1]) !== $short) {
			// Still repair using the path short code if we can map it — otherwise use session
			$code = $code ?: $config->get('config_language');
		}

		$https = !empty($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off';
		$host = isset($request->server['HTTP_HOST']) ? $request->server['HTTP_HOST'] : '';

		if ($host === '') {
			return;
		}

		$query = parse_url($uri, PHP_URL_QUERY);
		parse_str($query ? $query : '', $params);
		$params['language'] = $code;
		$target = ($https ? 'https' : 'http') . '://' . $host . '/index.php?' . http_build_query($params);

		$response->redirect($target);
	}

	public static function redirectIfMissingPrefix($registry) {
		if (!$registry) {
			return;
		}

		$request = $registry->get('request');
		$response = $registry->get('response');
		$session = $registry->get('session');
		$config = $registry->get('config');

		if (!$request || !$response || !$session || !$config) {
			return;
		}

		if (!empty($request->get['cyberpunks_locale_from_url']) || !empty($request->get['language'])) {
			return;
		}

		$method = isset($request->server['REQUEST_METHOD']) ? strtoupper($request->server['REQUEST_METHOD']) : 'GET';

		if ($method !== 'GET' || !$config->get('config_seo_url')) {
			return;
		}

		$uri = isset($request->server['REQUEST_URI']) ? (string)$request->server['REQUEST_URI'] : '/';
		$path = parse_url($uri, PHP_URL_PATH);

		if (!is_string($path) || $path === '') {
			$path = '/';
		}

		// Never force /nl/ onto index.php URLs — those use ?language=
		if (stripos($path, 'index.php') !== false) {
			return;
		}

		// Cart and checkout stay on default URLs without locale prefix.
		if (self::isSkippedPath($path)) {
			return;
		}

		$code = isset($session->data['language']) ? $session->data['language'] : $config->get('config_language');
		$short = self::shortFromCode($code);

		if ($short === '') {
			return;
		}

		$trim = trim($path, '/');
		$first = ($trim === '') ? '' : explode('/', $trim)[0];

		if (strtolower($first) === $short) {
			return;
		}

		$https = !empty($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off';
		$host = isset($request->server['HTTP_HOST']) ? $request->server['HTTP_HOST'] : '';

		if ($host === '') {
			return;
		}

		$query = parse_url($uri, PHP_URL_QUERY);
		$current = ($https ? 'https' : 'http') . '://' . $host . $path . ($query ? ('?' . $query) : '');
		$target = html_entity_decode(self::applyPrefix($current, $code), ENT_QUOTES, 'UTF-8');

		if ($target !== '' && $target !== $current) {
			$response->redirect($target);
		}
	}

	/**
	 * Routes that must stay on the default language URL (no /nl/ prefix).
	 * Cart and checkout rely on non-SEO AJAX endpoints and break with locale prefixes.
	 */
	private static $skipPrefixPatterns = array(
		'/checkout',
		'/cart',
		'/payment',
		'/order-success',
	);

	private static function isSkippedPath($path) {
		$path = strtolower(trim((string)$path, '/'));

		foreach (self::$skipPrefixPatterns as $pattern) {
			$pattern = ltrim($pattern, '/');

			if ($path === $pattern || strpos($path, $pattern . '/') === 0) {
				return true;
			}

			// Also match when locale prefix is already present: /nl/checkout → checkout
			$parts = explode('/', $path, 2);
			if (count($parts) === 2 && strlen($parts[0]) === 2) {
				$rest = $parts[1];

				if ($rest === $pattern || strpos($rest, $pattern . '/') === 0) {
					return true;
				}
			}
		}

		return false;
	}

	public static function applyPrefix($link, $code) {
		$short = self::shortFromCode($code);

		if ($short === '' || $code === '') {
			return $link;
		}

		$url_info = parse_url(str_replace('&amp;', '&', (string)$link));

		if (empty($url_info['scheme']) || empty($url_info['host'])) {
			return $link;
		}

		$path = isset($url_info['path']) ? $url_info['path'] : '/';

		// Non-SEO OpenCart links keep index.php in the path — use ?language= instead of /nl/index.php
		if (stripos($path, 'index.php') !== false) {
			return self::applyLanguageQuery($url_info, $code);
		}

		// Cart and checkout stay on default URLs without locale prefix.
		if (self::isSkippedPath($path)) {
			return $link;
		}

		$path_trim = trim($path, '/');
		$first = ($path_trim === '') ? '' : explode('/', $path_trim)[0];

		if (strtolower($first) === $short) {
			return $link;
		}

		if ($path === '' || $path === '/') {
			$new_path = '/' . $short . '/';
		} elseif (substr($path, -1) === '/') {
			$new_path = '/' . $short . '/' . ltrim($path, '/');
		} else {
			$new_path = '/' . $short . $path;
		}

		$out = $url_info['scheme'] . '://' . $url_info['host'];

		if (!empty($url_info['port'])) {
			$out .= ':' . $url_info['port'];
		}

		$out .= $new_path;

		if (!empty($url_info['query'])) {
			$out .= '?' . str_replace('&', '&amp;', $url_info['query']);
		}

		if (!empty($url_info['fragment'])) {
			$out .= '#' . $url_info['fragment'];
		}

		return $out;
	}

	private static function applyLanguageQuery($url_info, $code) {
		$path = isset($url_info['path']) ? $url_info['path'] : '/index.php';

		// Normalize accidental /nl/index.php back to /index.php
		$path = preg_replace('#^/([a-z]{2})/index\.php$#i', '/index.php', $path);

		$query = array();

		if (!empty($url_info['query'])) {
			parse_str(str_replace('&amp;', '&', $url_info['query']), $query);
		}

		$query['language'] = $code;

		$out = $url_info['scheme'] . '://' . $url_info['host'];

		if (!empty($url_info['port'])) {
			$out .= ':' . $url_info['port'];
		}

		$out .= $path . '?' . str_replace('&', '&amp;', http_build_query($query));

		if (!empty($url_info['fragment'])) {
			$out .= '#' . $url_info['fragment'];
		}

		return $out;
	}
}

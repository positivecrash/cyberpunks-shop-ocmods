<?php

class ControllerExtensionModuleCyberpunksLocale extends Controller {
	public function early() {
		$file = DIR_SYSTEM . 'library/cyberpunks_url_locale.php';

		if (!is_file($file)) {
			return;
		}

		require_once($file);

		$this->load->model('localisation/language');

		// Ensure route-based SEO URLs (cart, checkout, etc.) exist for every active language.
		$this->ensureRouteSeoUrls();

		CyberpunksUrlLocale::consumeRouteLocale($this->request, $this->model_localisation_language->getLanguages());

		if (!empty($this->request->get['language'])) {
			$this->session->data['language'] = $this->request->get['language'];
		}

		// Redirect /nl/cart → /cart, /nl/checkout → /checkout (cart & checkout have no locale prefix).
		$this->redirectSkippedPaths();

		// OpenCart doesn't rewrite checkout/success into SEO keyword automatically.
		// If payment modules redirect to index.php?route=checkout/success, convert it to /order-success.
		$this->redirectCheckoutSuccessToSeo();
	}

	/**
	 * Ensure ALL route-based SEO URLs exist for every active language.
	 * Lightweight: single COUNT query; only runs full logic when gaps exist.
	 */
	private function ensureRouteSeoUrls() {
		$lang_count = $this->db->query("SELECT COUNT(*) AS cnt FROM `" . DB_PREFIX . "language` WHERE status = '1'");
		$route_count = $this->db->query("SELECT COUNT(DISTINCT `query`) AS cnt FROM `" . DB_PREFIX . "seo_url` WHERE `query` LIKE 'route=%'");
		$total = $this->db->query("SELECT COUNT(*) AS cnt FROM `" . DB_PREFIX . "seo_url` WHERE `query` LIKE 'route=%'");

		$expected = (int)$lang_count->row['cnt'] * (int)$route_count->row['cnt'];

		if ((int)$total->row['cnt'] >= $expected) {
			return;
		}

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

	/**
	 * If user visits /nl/cart or /nl/checkout, 301-redirect to /cart or /checkout.
	 */
	private function redirectSkippedPaths() {
		$uri = isset($this->request->server['REQUEST_URI']) ? (string)$this->request->server['REQUEST_URI'] : '';
		$path = parse_url($uri, PHP_URL_PATH);

		if (!is_string($path) || $path === '') {
			return;
		}

		$method = isset($this->request->server['REQUEST_METHOD']) ? strtoupper($this->request->server['REQUEST_METHOD']) : 'GET';

		if ($method !== 'GET') {
			return;
		}

		// Check if path starts with a 2-letter locale prefix followed by a skipped route.
		$trim = trim($path, '/');
		$parts = explode('/', $trim, 2);

		if (count($parts) < 2 || strlen($parts[0]) !== 2) {
			return;
		}

		$rest = $parts[1];
		$skipped = array('cart', 'checkout', 'payment', 'order-success');

		$match = false;
		foreach ($skipped as $s) {
			if ($rest === $s || strpos($rest, $s . '/') === 0) {
				$match = true;
				break;
			}
		}

		if (!$match) {
			return;
		}

		$https = !empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off';
		$host = isset($this->request->server['HTTP_HOST']) ? $this->request->server['HTTP_HOST'] : '';

		if ($host === '') {
			return;
		}

		$query = parse_url($uri, PHP_URL_QUERY);
		$target = ($https ? 'https' : 'http') . '://' . $host . '/' . $rest . ($query ? ('?' . $query) : '');

		$this->response->addHeader('HTTP/1.1 301 Moved Permanently');
		$this->response->redirect($target);
	}

	/**
	 * Redirect index.php success URLs to SEO keyword /order-success.
	 */
	private function redirectCheckoutSuccessToSeo() {
		$uri = isset($this->request->server['REQUEST_URI']) ? (string)$this->request->server['REQUEST_URI'] : '';
		if ($uri === '') {
			return;
		}

		// Only act on index.php based checkout success URLs.
		if (stripos($uri, 'index.php') === false) {
			return;
		}

		$route = isset($this->request->get['route']) ? (string)$this->request->get['route'] : '';
		if ($route !== 'checkout/success') {
			return;
		}

		$order_id = isset($this->request->get['orderId']) ? (int)$this->request->get['orderId'] : 0;
		if ($order_id <= 0) {
			return;
		}

		$lang_code = isset($this->request->get['language']) ? (string)$this->request->get['language'] : (isset($this->session->data['language']) ? (string)$this->session->data['language'] : '');
		if ($lang_code === '') {
			$lang_code = (string)$this->config->get('config_language');
		}

		$language_id = (int)$this->config->get('config_language_id');
		$store_id = (int)$this->config->get('config_store_id');

		$keyword_q = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "seo_url` WHERE `query` = 'route=checkout/success' AND store_id = '" . $store_id . "' AND language_id = '" . $language_id . "' LIMIT 1");
		if (!$keyword_q->num_rows) {
			return;
		}

		$keyword = trim((string)$keyword_q->row['keyword']);
		if ($keyword === '') {
			return;
		}

		$https = !empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off';
		$host = isset($this->request->server['HTTP_HOST']) ? (string)$this->request->server['HTTP_HOST'] : '';
		if ($host === '') {
			return;
		}

		$target = ($https ? 'https' : 'http') . '://' . $host . '/' . $keyword . '?orderId=' . (int)$order_id . '&language=' . rawurlencode($lang_code);
		$this->response->redirect($target);
	}

	public function late() {
		$file = DIR_SYSTEM . 'library/cyberpunks_url_locale.php';

		if (!is_file($file)) {
			return;
		}

		require_once($file);

		$this->url->addRewrite($this);
		CyberpunksUrlLocale::repairIndexPhpLocaleUrl($this->registry);
		CyberpunksUrlLocale::redirectIfMissingPrefix($this->registry);
	}

	public function rewrite($link) {
		$code = isset($this->session->data['language']) ? $this->session->data['language'] : $this->config->get('config_language');

		return CyberpunksUrlLocale::applyPrefix($link, $code);
	}
}

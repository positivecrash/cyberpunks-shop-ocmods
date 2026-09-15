<?php
class ModelExtensionAdvertiseCyberpunksShopMarketing extends Model {
	private const CONSENT_CONFIG_ELEMENT_ID = 'cyberpunks-consent-config';
	private const CONSENT_STORAGE_KEY = 'cyberpunks_google_consent';
	private const CONSENT_COOKIE_NAME = 'cyberpunks_ad_storage';
	private const CONSENT_DEFAULT_EXPIRY_DAYS = 30;
	private const CONSENT_WAIT_FOR_UPDATE_MS = 500;
	private const META_GRAPH_VERSION = 'v21.0';

	public function isEnabled() {
		return (bool)$this->config->get('advertise_cyberpunks_shop_marketing_status');
	}

	public function isGtmEnabled() {
		return $this->isEnabled() && (bool)$this->config->get('advertise_cyberpunks_shop_marketing_gtm_status');
	}

	public function isMatomoEnabled() {
		return $this->isEnabled() && (bool)$this->config->get('advertise_cyberpunks_shop_marketing_matomo_status');
	}

	public function isPurchaseEnabled() {
		return $this->isGtmEnabled() && (bool)$this->config->get('advertise_cyberpunks_shop_marketing_gtm_event_purchase');
	}

	public function isMetaCapiEnabled() {
		if (!$this->isEnabled()) {
			return false;
		}

		$status = $this->config->get('advertise_cyberpunks_shop_marketing_meta_capi_status');

		if ($status === null || $status === '') {
			$status = 1;
		}

		if (!(bool)$status) {
			return false;
		}

		return $this->getMetaPixelId() !== '' && $this->getMetaAccessToken() !== '';
	}

	public function isViewItemEnabled() {
		return $this->isGtmEnabled() && (bool)$this->config->get('advertise_cyberpunks_shop_marketing_gtm_event_view_item');
	}

	public function isAddToCartEnabled() {
		if (!$this->isGtmEnabled()) {
			return false;
		}

		$value = $this->config->get('advertise_cyberpunks_shop_marketing_gtm_event_add_to_cart');

		return ($value === null || $value === '') ? true : (bool)$value;
	}

	public function isBeginCheckoutEnabled() {
		if (!$this->isGtmEnabled()) {
			return false;
		}

		$value = $this->config->get('advertise_cyberpunks_shop_marketing_gtm_event_begin_checkout');

		return ($value === null || $value === '') ? true : (bool)$value;
	}

	public function isMatomoEcommerceEnabled() {
		return $this->isMatomoEnabled() && (bool)$this->config->get('advertise_cyberpunks_shop_marketing_matomo_ecommerce');
	}

	public function isConsentEnabled() {
		if (!$this->isGtmEnabled()) {
			return false;
		}

		$status = $this->config->get('advertise_cyberpunks_shop_marketing_consent_status');

		if ($status === null || $status === '') {
			return true;
		}

		return (bool)$status;
	}

	public function getConsentExpiryDays() {
		$days = (int)$this->config->get('advertise_cyberpunks_shop_marketing_consent_expiry_days');

		return $days > 0 ? $days : self::CONSENT_DEFAULT_EXPIRY_DAYS;
	}

	private function getConsentConfig() {
		return array(
			'storageKey' => self::CONSENT_STORAGE_KEY,
			'cookieName' => self::CONSENT_COOKIE_NAME,
			'expiryDays' => $this->getConsentExpiryDays(),
			'waitForUpdate' => self::CONSENT_WAIT_FOR_UPDATE_MS,
		);
	}

	public function getMetaPixelId() {
		return trim((string)$this->config->get('advertise_cyberpunks_shop_marketing_meta_pixel_id'));
	}

	public function getMetaAccessToken() {
		return trim((string)$this->config->get('advertise_cyberpunks_shop_marketing_meta_access_token'));
	}

	public function getMetaTestEventCode() {
		return trim((string)$this->config->get('advertise_cyberpunks_shop_marketing_meta_test_event_code'));
	}

	/**
	 * Server-side ad_storage gate: cookie mirrored from the consent banner.
	 * If the consent banner is disabled, CAPI is allowed (same as ungated Meta tags).
	 */
	public function hasAdStorageConsent() {
		if (!$this->isConsentEnabled()) {
			return true;
		}

		$cookie = isset($this->request->cookie[self::CONSENT_COOKIE_NAME])
			? strtolower(trim((string)$this->request->cookie[self::CONSENT_COOKIE_NAME]))
			: '';

		return $cookie === 'granted';
	}

	private function loadCatalogJavascript($filename) {
		$file = DIR_APPLICATION . 'view/javascript/' . $filename;

		if (!is_file($file) || !is_readable($file)) {
			return '';
		}

		$contents = file_get_contents($file);

		return $contents !== false ? trim($contents) : '';
	}

	private function renderCatalogTemplate($relative_path, array $vars) {
		$file = DIR_APPLICATION . $relative_path;

		if (!is_file($file) || !is_readable($file)) {
			return '';
		}

		$html = file_get_contents($file);

		if ($html === false) {
			return '';
		}

		foreach ($vars as $key => $value) {
			$html = str_replace('{{' . $key . '}}', $value, $html);
		}

		return trim($html);
	}

	public function getContainerId() {
		return trim((string)$this->config->get('advertise_cyberpunks_shop_marketing_gtm_container_id'));
	}

	public function getItemIdField() {
		$field = (string)$this->config->get('advertise_cyberpunks_shop_marketing_item_id_field');

		return $field === 'sku' ? 'sku' : 'model';
	}

	public function renderHeadSnippet() {
		$parts = array();

		$consent = $this->renderGoogleConsentHead();
		if ($consent !== '') {
			$parts[] = $consent;
		}

		$gtm = $this->renderGtmHeadSnippet();
		if ($gtm !== '') {
			$parts[] = $gtm;
		}

		$flags = $this->renderEcommerceFlagsSnippet();
		if ($flags !== '') {
			$parts[] = $flags;
		}

		$matomo = $this->renderMatomoHeadSnippet();
		if ($matomo !== '') {
			$parts[] = $matomo;
		}

		return implode("\n", $parts);
	}

	/**
	 * Client flag for product-page add_to_cart (fires after cart/add AJAX success).
	 */
	public function renderEcommerceFlagsSnippet() {
		if (!$this->isAddToCartEnabled()) {
			return '';
		}

		return "<script>window.__cyberpunksMarketing=window.__cyberpunksMarketing||{};window.__cyberpunksMarketing.addToCart=true;</script>";
	}

	public function renderBodySnippet() {
		return $this->renderGtmBodySnippet();
	}

	public function renderGoogleConsentHead() {
		if (!$this->isConsentEnabled()) {
			return '';
		}

		$javascript = $this->loadCatalogJavascript('cyberpunks_google_consent.js');
		if ($javascript === '') {
			return '';
		}

		$config = json_encode($this->getConsentConfig(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$config_id = self::CONSENT_CONFIG_ELEMENT_ID;

		return "<!-- Google Consent Mode -->\n"
			. '<script type="application/json" id="' . $config_id . '">' . $config . '</script>' . "\n"
			. "<script>\n" . $javascript . "\n</script>\n"
			. "<!-- End Google Consent Mode -->";
	}

	public function renderConsentFooterSnippet() {
		if (!$this->isConsentEnabled()) {
			return '';
		}

		try {
			$message = $this->resolveLocalizedText($this->config->get('advertise_cyberpunks_shop_marketing_consent_message'));
			$privacy_label = $this->resolveLocalizedText($this->config->get('advertise_cyberpunks_shop_marketing_consent_privacy_label'));
			$privacy_url = trim((string)$this->config->get('advertise_cyberpunks_shop_marketing_consent_privacy_url'));
			$deny_label = $this->resolveLocalizedText($this->config->get('advertise_cyberpunks_shop_marketing_consent_deny_label'));
			$grant_label = $this->resolveLocalizedText($this->config->get('advertise_cyberpunks_shop_marketing_consent_grant_label'));

			// Incomplete admin config: do not break the page with an empty/broken banner.
			if ($message === '' || ($deny_label === '' && $grant_label === '')) {
				return '';
			}

			$message_html = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
			$privacy_html = '';

			if ($privacy_url !== '' && $privacy_label !== '') {
				$privacy_html = ' <a href="' . htmlspecialchars($privacy_url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($privacy_label, ENT_QUOTES, 'UTF-8') . '</a>';
			}

			$deny_button_html = '';
			if ($deny_label !== '') {
				$deny_button_html = '<button type="button" class="button button-bordered button-inline" data-google-consent="deny">' . htmlspecialchars($deny_label, ENT_QUOTES, 'UTF-8') . '</button>';
			}

			$grant_button_html = '';
			if ($grant_label !== '') {
				$grant_button_html = '<button type="button" class="button button-green button-inline" data-google-consent="grant">' . htmlspecialchars($grant_label, ENT_QUOTES, 'UTF-8') . '</button>';
			}

			return $this->renderCatalogTemplate('view/javascript/cyberpunks_google_consent_banner.html', array(
				'message_html' => $message_html,
				'privacy_html' => $privacy_html,
				'deny_button_html' => $deny_button_html,
				'grant_button_html' => $grant_button_html,
			));
		} catch (Exception $e) {
			return '';
		} catch (Throwable $e) {
			return '';
		}
	}

	/**
	 * Resolve consent text for the current storefront language.
	 * Supports legacy plain string and language_id => text map.
	 * If the current language has no text, fall back to the store default language, then any filled language.
	 */
	private function resolveLocalizedText($value) {
		if ($value === null) {
			return '';
		}

		$language_id = (int)$this->config->get('config_language_id');

		if (is_string($value) && $value !== '' && ($value[0] === '{' || $value[0] === '[')) {
			$decoded = json_decode($value, true);

			if (is_array($decoded)) {
				$value = $decoded;
			}
		}

		if (is_array($value)) {
			if ($language_id > 0 && !empty($value[$language_id])) {
				$text = trim((string)$value[$language_id]);

				if ($text !== '') {
					return $text;
				}
			}

			$default_language_id = $this->getStoreDefaultLanguageId();

			if ($default_language_id > 0 && !empty($value[$default_language_id])) {
				$text = trim((string)$value[$default_language_id]);

				if ($text !== '') {
					return $text;
				}
			}

			ksort($value, SORT_NUMERIC);

			foreach ($value as $text) {
				$text = trim((string)$text);

				if ($text !== '') {
					return $text;
				}
			}

			return '';
		}

		return trim((string)$value);
	}

	/**
	 * Storefront default language_id from settings (not the visitor's selected language).
	 */
	private function getStoreDefaultLanguageId() {
		static $cached = null;

		if ($cached !== null) {
			return $cached;
		}

		$cached = 0;
		$code = '';

		try {
			$query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `code` = 'config' AND `key` = 'config_language' AND `store_id` = '" . (int)$this->config->get('config_store_id') . "' LIMIT 1");

			if ($query->num_rows) {
				$code = trim((string)$query->row['value']);
			}

			if ($code === '') {
				$query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `code` = 'config' AND `key` = 'config_language' AND `store_id` = '0' LIMIT 1");

				if ($query->num_rows) {
					$code = trim((string)$query->row['value']);
				}
			}

			if ($code !== '') {
				$lang = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE `code` = '" . $this->db->escape($code) . "' AND `status` = '1' LIMIT 1");

				if ($lang->num_rows) {
					$cached = (int)$lang->row['language_id'];
				}
			}
		} catch (Exception $e) {
			$cached = 0;
		} catch (Throwable $e) {
			$cached = 0;
		}

		return $cached;
	}

	public function renderGtmHeadSnippet() {
		if (!$this->isGtmEnabled()) {
			return '';
		}

		$container_id = $this->getContainerId();

		if ($container_id === '') {
			return '';
		}

		$id = json_encode($container_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

		return "<!-- Google Tag Manager -->\n"
			. "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n"
			. "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n"
			. "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n"
			. "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n"
			. "})(window,document,'script','dataLayer'," . $id . ");</script>\n"
			. "<!-- End Google Tag Manager -->";
	}

	public function renderGtmBodySnippet() {
		if (!$this->isGtmEnabled()) {
			return '';
		}

		$container_id = $this->getContainerId();

		if ($container_id === '') {
			return '';
		}

		$id = htmlspecialchars($container_id, ENT_QUOTES, 'UTF-8');

		return "<!-- Google Tag Manager (noscript) -->\n"
			. '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $id . "\"\n"
			. "height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n"
			. "<!-- End Google Tag Manager (noscript) -->";
	}

	public function renderMatomoHeadSnippet() {
		if (!$this->isMatomoEnabled()) {
			return '';
		}

		if ($this->config->get('advertise_cyberpunks_shop_marketing_matomo_respect_dnt')) {
			if (!empty($_SERVER['HTTP_DNT']) && (string)$_SERVER['HTTP_DNT'] === '1') {
				return '';
			}
		}

		$server = rtrim(trim((string)$this->config->get('advertise_cyberpunks_shop_marketing_matomo_server')), '/');
		$site_id = (int)$this->config->get('advertise_cyberpunks_shop_marketing_matomo_site_id');

		if ($server === '' || $site_id < 1) {
			return '';
		}

		$server_js = json_encode($server . '/', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

		$lines = array(
			'<!-- Matomo -->',
			'<script id="cyberpunks-matomo-loader">',
			'var _paq = window._paq = window._paq || [];',
		);

		if ($this->config->get('advertise_cyberpunks_shop_marketing_matomo_disable_cookies')) {
			$lines[] = "_paq.push(['disableCookies']);";
		}

		$lines[] = "_paq.push(['setTrackerUrl', " . $server_js . " + 'matomo.php']);";
		$lines[] = "_paq.push(['setSiteId', " . json_encode((string)$site_id) . ']);';
		$lines[] = "_paq.push(['trackPageView']);";
		$lines[] = "_paq.push(['enableLinkTracking']);";
		$lines[] = '(function() {';
		$lines[] = 'var d=document, g=d.createElement("script"), s=d.getElementById("cyberpunks-matomo-loader");';
		$lines[] = 'g.async=true; g.src=' . $server_js . ' + "matomo.js";';
		$lines[] = 'if (s && s.parentNode) { s.parentNode.insertBefore(g, s.nextSibling); }';
		$lines[] = '})();';
		$lines[] = '</script>';
		$lines[] = '<!-- End Matomo -->';

		return implode("\n", $lines);
	}

	public function buildPurchaseEcommerce($order_id) {
		if (!$this->isPurchaseEnabled()) {
			return null;
		}

		$order_id = (int)$order_id;

		if ($order_id <= 0) {
			return null;
		}

		$this->load->model('checkout/order');

		$order = $this->model_checkout_order->getOrder($order_id);

		if (!$order) {
			return null;
		}

		$products = $this->model_checkout_order->getOrderProducts($order_id);
		$items = array();

		foreach ($products as $product) {
			$order_product_id = isset($product['order_product_id']) ? (int)$product['order_product_id'] : 0;
			$options = $order_product_id > 0
				? $this->model_checkout_order->getOrderOptions($order_id, $order_product_id)
				: array();

			$items[] = array(
				'item_id'   => $this->resolveEcommerceItemId($product, $options),
				'item_name' => isset($product['name']) ? (string)$product['name'] : '',
				'price'     => $this->roundMoney(isset($product['price']) ? (float)$product['price'] : 0.0),
				'quantity'  => isset($product['quantity']) ? (int)$product['quantity'] : 1,
			);
		}

		if (!$items) {
			return null;
		}

		$currency_value = isset($order['currency_value']) ? (float)$order['currency_value'] : 1.0;

		return array(
			'transaction_id' => (string)$order_id,
			'value'          => $this->roundMoney((float)$order['total'] * $currency_value),
			'currency'       => isset($order['currency_code']) ? (string)$order['currency_code'] : '',
			'items'          => $items,
		);
	}

	/**
	 * Meta Conversions API Purchase (issue #22).
	 * event_id must match browser Meta Pixel eventID (= ecommerce.transaction_id in GTM).
	 */
	public function sendMetaPurchaseCapi($order_id, $ecommerce = null) {
		if (!$this->isMetaCapiEnabled()) {
			return false;
		}

		if (!$this->hasAdStorageConsent()) {
			return false;
		}

		$order_id = (int)$order_id;

		if ($order_id <= 0) {
			return false;
		}

		if (!is_array($ecommerce) || empty($ecommerce['transaction_id'])) {
			$ecommerce = $this->buildPurchaseEcommerce($order_id);

			if (!$ecommerce) {
				// CAPI can still fire without GTM purchase toggle if we have order totals.
				$this->load->model('checkout/order');
				$order = $this->model_checkout_order->getOrder($order_id);

				if (!$order) {
					return false;
				}

				$currency_value = isset($order['currency_value']) ? (float)$order['currency_value'] : 1.0;
				$ecommerce = array(
					'transaction_id' => (string)$order_id,
					'value'          => $this->roundMoney((float)$order['total'] * $currency_value),
					'currency'       => isset($order['currency_code']) ? (string)$order['currency_code'] : '',
					'items'          => array(),
				);
			}
		}

		$this->load->model('checkout/order');
		$order = $this->model_checkout_order->getOrder($order_id);

		if (!$order) {
			return false;
		}

		$event_id = isset($ecommerce['transaction_id']) ? (string)$ecommerce['transaction_id'] : (string)$order_id;
		$event_time = !empty($order['date_added']) ? (int)strtotime($order['date_added']) : time();

		if ($event_time <= 0) {
			$event_time = time();
		}

		$user_data = array(
			'client_ip_address' => $this->getClientIpAddress(),
			'client_user_agent' => isset($this->request->server['HTTP_USER_AGENT'])
				? (string)$this->request->server['HTTP_USER_AGENT']
				: '',
		);

		$email_hash = $this->hashSha256Normalized(isset($order['email']) ? $order['email'] : '');
		if ($email_hash !== '') {
			$user_data['em'] = array($email_hash);
		}

		$phone_hash = $this->hashSha256Phone(isset($order['telephone']) ? $order['telephone'] : '');
		if ($phone_hash !== '') {
			$user_data['ph'] = array($phone_hash);
		}

		$fbp = $this->readCookieValue('_fbp');
		if ($fbp !== '') {
			$user_data['fbp'] = $fbp;
		}

		$fbc = $this->readCookieValue('_fbc');
		if ($fbc !== '') {
			$user_data['fbc'] = $fbc;
		}

		$custom_data = array(
			'value'    => isset($ecommerce['value']) ? (float)$ecommerce['value'] : 0.0,
			'currency' => isset($ecommerce['currency']) ? (string)$ecommerce['currency'] : '',
		);

		$content_ids = array();
		$contents = array();
		$num_items = 0;

		if (!empty($ecommerce['items']) && is_array($ecommerce['items'])) {
			foreach ($ecommerce['items'] as $item) {
				$id = isset($item['item_id']) ? (string)$item['item_id'] : '';
				$qty = isset($item['quantity']) ? (int)$item['quantity'] : 1;
				$price = isset($item['price']) ? (float)$item['price'] : 0.0;

				if ($qty < 1) {
					$qty = 1;
				}

				if ($id !== '') {
					$content_ids[] = $id;
					$contents[] = array(
						'id'         => $id,
						'quantity'   => $qty,
						'item_price' => $price,
					);
				}

				$num_items += $qty;
			}
		}

		if ($content_ids) {
			$custom_data['content_ids'] = $content_ids;
			$custom_data['contents'] = $contents;
			$custom_data['content_type'] = 'product';
			$custom_data['num_items'] = $num_items;
		}

		$payload = array(
			'data' => array(
				array(
					'event_name'       => 'Purchase',
					'event_time'       => $event_time,
					'event_id'         => $event_id,
					'event_source_url' => $this->getEventSourceUrl(),
					'action_source'    => 'website',
					'user_data'        => $user_data,
					'custom_data'      => $custom_data,
				),
			),
		);

		$test_code = $this->getMetaTestEventCode();
		if ($test_code !== '') {
			$payload['test_event_code'] = $test_code;
		}

		return $this->postMetaCapiEvents($payload);
	}

	private function postMetaCapiEvents(array $payload) {
		$pixel_id = rawurlencode($this->getMetaPixelId());
		$token = $this->getMetaAccessToken();
		$url = 'https://graph.facebook.com/' . self::META_GRAPH_VERSION . '/' . $pixel_id . '/events?access_token=' . rawurlencode($token);

		$body = json_encode($payload);

		if ($body === false) {
			return false;
		}

		$response = false;

		if (function_exists('curl_init')) {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 8);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
			$response = curl_exec($ch);
			$errno = curl_errno($ch);
			curl_close($ch);

			if ($errno) {
				$this->log->write('cyberpunks_shop_marketing Meta CAPI curl error #' . $errno);
				return false;
			}
		} else {
			$context = stream_context_create(array(
				'http' => array(
					'method'  => 'POST',
					'header'  => "Content-Type: application/json\r\n",
					'content' => $body,
					'timeout' => 8,
				),
			));
			$response = @file_get_contents($url, false, $context);
		}

		if ($response === false || $response === '') {
			$this->log->write('cyberpunks_shop_marketing Meta CAPI empty response');
			return false;
		}

		$decoded = json_decode($response, true);

		if (!is_array($decoded) || empty($decoded['events_received'])) {
			$this->log->write('cyberpunks_shop_marketing Meta CAPI unexpected response');
			return false;
		}

		return true;
	}

	private function getEventSourceUrl() {
		if (isset($this->request->server['HTTP_HOST'])) {
			$https = !empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off';
			$scheme = $https ? 'https://' : 'http://';
			$uri = isset($this->request->server['REQUEST_URI']) ? (string)$this->request->server['REQUEST_URI'] : '/';

			return $scheme . $this->request->server['HTTP_HOST'] . $uri;
		}

		return $this->url->link('checkout/success', '', true);
	}

	private function getClientIpAddress() {
		if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
			$parts = explode(',', (string)$this->request->server['HTTP_X_FORWARDED_FOR']);

			return trim($parts[0]);
		}

		if (!empty($this->request->server['REMOTE_ADDR'])) {
			return (string)$this->request->server['REMOTE_ADDR'];
		}

		return '';
	}

	private function readCookieValue($name) {
		return isset($this->request->cookie[$name]) ? trim((string)$this->request->cookie[$name]) : '';
	}

	private function hashSha256Normalized($value) {
		$value = strtolower(trim((string)$value));

		if ($value === '') {
			return '';
		}

		return hash('sha256', $value);
	}

	private function hashSha256Phone($value) {
		$digits = preg_replace('/\D+/', '', (string)$value);

		if ($digits === null || $digits === '') {
			return '';
		}

		return hash('sha256', $digits);
	}

	public function buildMatomoEcommerceSnapshot($order_id) {
		if (!$this->isMatomoEcommerceEnabled()) {
			return null;
		}

		$order_id = (int)$order_id;

		if ($order_id <= 0) {
			return null;
		}

		$this->load->model('checkout/order');

		$order = $this->model_checkout_order->getOrder($order_id);

		if (!$order) {
			return null;
		}

		$products = $this->model_checkout_order->getOrderProducts($order_id);
		$totals = $this->model_checkout_order->getOrderTotals($order_id);

		$shipping = 0.0;
		$tax = 0.0;
		$discount = 0.0;

		foreach ($totals as $total) {
			$code = isset($total['code']) ? $total['code'] : '';
			$value = isset($total['value']) ? (float)$total['value'] : 0.0;

			if ($code === 'shipping') {
				$shipping += $value;
			} elseif ($code === 'tax') {
				$tax += $value;
			} elseif (in_array($code, array('coupon', 'voucher', 'reward'), true)) {
				$discount += abs($value);
			}
		}

		$subtotal = 0.0;

		foreach ($products as $product) {
			$subtotal += isset($product['total']) ? (float)$product['total'] : 0.0;
		}

		$items = array();

		foreach ($products as $product) {
			$order_product_id = isset($product['order_product_id']) ? (int)$product['order_product_id'] : 0;
			$options = $order_product_id > 0
				? $this->model_checkout_order->getOrderOptions($order_id, $order_product_id)
				: array();
			$sku = $this->resolveEcommerceItemId($product, $options);

			if ($sku === '') {
				$sku = 'item-' . $order_product_id;
			}

			$qty = isset($product['quantity']) ? (int)$product['quantity'] : 1;
			$line_total = isset($product['total']) ? (float)$product['total'] : 0.0;
			$unit = $qty > 0 ? $line_total / $qty : $line_total;

			$items[] = array(
				'sku'      => $sku,
				'name'     => isset($product['name']) ? (string)$product['name'] : '',
				'category' => '',
				'price'    => round($unit, 4),
				'quantity' => $qty,
			);
		}

		if (!$items) {
			return null;
		}

		return array(
			'order_id'    => (string)$order_id,
			'grand_total' => isset($order['total']) ? (float)$order['total'] : 0.0,
			'subtotal'    => round($subtotal, 4),
			'tax'         => round($tax, 4),
			'shipping'    => round($shipping, 4),
			'discount'    => round($discount, 4),
			'items'       => $items,
		);
	}

	public function buildViewItemEcommerce($product_id) {
		if (!$this->isViewItemEnabled()) {
			return null;
		}

		$product_id = (int)$product_id;

		if ($product_id <= 0) {
			return null;
		}

		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if (!$product_info) {
			return null;
		}

		$raw_price = (!is_null($product_info['special']) && (float)$product_info['special'] >= 0)
			? (float)$product_info['special']
			: (float)$product_info['price'];

		$raw_price = $this->tax->calculate($raw_price, $product_info['tax_class_id'], $this->config->get('config_tax'));
		$raw_price = $this->currency->convert($raw_price, $this->config->get('config_currency'), $this->session->data['currency']);
		$price = $this->roundMoney($raw_price);

		$currency = isset($this->session->data['currency']) ? (string)$this->session->data['currency'] : 'EUR';
		$item_id = $this->resolveViewItemId($product_id, $product_info);

		return array(
			'currency' => $currency,
			'value'    => $price,
			'items'    => array(
				array(
					'item_id'   => $item_id,
					'item_name' => isset($product_info['name']) ? (string)$product_info['name'] : '',
					'price'     => $price,
					'quantity'  => 1,
				),
			),
		);
	}

	public function buildBeginCheckoutEcommerce() {
		if (!$this->isBeginCheckoutEnabled()) {
			return null;
		}

		$products = $this->cart->getProducts();

		if (!$products) {
			return null;
		}

		$currency = isset($this->session->data['currency']) ? (string)$this->session->data['currency'] : 'EUR';
		$items = array();
		$value = 0.0;

		foreach ($products as $product) {
			$unit = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'));
			$unit = $this->currency->convert($unit, $this->config->get('config_currency'), $currency);
			$unit = $this->roundMoney($unit);
			$quantity = isset($product['quantity']) ? (int)$product['quantity'] : 1;

			if ($quantity < 1) {
				$quantity = 1;
			}

			$options = (!empty($product['option']) && is_array($product['option'])) ? $product['option'] : array();
			$item_id = $this->resolveEcommerceItemId($product, $options);

			$variant_parts = array();

			foreach ($options as $option) {
				if (!empty($option['value'])) {
					$variant_parts[] = (string)$option['value'];
				}
			}

			$item = array(
				'item_id'   => $item_id,
				'item_name' => isset($product['name']) ? (string)$product['name'] : '',
				'price'     => $unit,
				'quantity'  => $quantity,
			);

			if ($variant_parts) {
				$item['item_variant'] = implode(' / ', $variant_parts);
			}

			$items[] = $item;
			$value += $unit * $quantity;
		}

		if (!$items) {
			return null;
		}

		return array(
			'currency' => $currency,
			'value'    => $this->roundMoney($value),
			'items'    => $items,
		);
	}

	public function renderDataLayerScript($event, array $ecommerce, $event_id = '') {
		$payload = json_encode($ecommerce, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if ($payload === false) {
			return '';
		}

		$event_name = json_encode((string)$event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$push = '{ event: ' . $event_name . ', ecommerce: ' . $payload;

		if ($event_id !== '') {
			$push .= ', eventID: ' . json_encode((string)$event_id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		$push .= ' }';

		return "<!-- cyberpunks-marketing: " . htmlspecialchars((string)$event, ENT_QUOTES, 'UTF-8') . " -->\n"
			. "<script>\n"
			. "window.dataLayer = window.dataLayer || [];\n"
			. "dataLayer.push({ ecommerce: null });\n"
			. "dataLayer.push(" . $push . ");\n"
			. "</script>";
	}

	public function renderMatomoOrderScript(array $snapshot) {
		if (empty($snapshot['items'])) {
			return '';
		}

		$lines = array(
			'<!-- cyberpunks-marketing: matomo-purchase -->',
			'<script>',
			'var _paq = window._paq = window._paq || [];',
		);

		foreach ($snapshot['items'] as $item) {
			$lines[] = "_paq.push(['addEcommerceItem', " . json_encode($item['sku']) . ', ' . json_encode($item['name']) . ', ' . json_encode($item['category']) . ', ' . json_encode((float)$item['price']) . ', ' . json_encode((int)$item['quantity']) . ']);';
		}

		$lines[] = "_paq.push(['trackEcommerceOrder', " . json_encode($snapshot['order_id']) . ', ' . json_encode((float)$snapshot['grand_total']) . ', ' . json_encode((float)$snapshot['subtotal']) . ', ' . json_encode((float)$snapshot['tax']) . ', ' . json_encode((float)$snapshot['shipping']) . ', ' . json_encode((float)$snapshot['discount']) . ']);';
		$lines[] = '</script>';

		return implode("\n", $lines);
	}

	/**
	 * Prefer Variant Identifiers SKU (same as merchant feed g:id / add_to_cart).
	 * Falls back to admin item_id field (model/sku) when no mapping matches.
	 */
	private function resolveEcommerceItemId($product_row, array $options = array()) {
		if (!empty($product_row['variant_sku'])) {
			return trim((string)$product_row['variant_sku']);
		}

		$product_id = !empty($product_row['product_id']) ? (int)$product_row['product_id'] : 0;
		$variant_sku = $this->resolveVariantSku($product_id, $options);

		if ($variant_sku !== '') {
			return $variant_sku;
		}

		return $this->resolveItemId($product_row);
	}

	/**
	 * view_item: use ?variant=SKU when it belongs to this product's identifiers.
	 */
	private function resolveViewItemId($product_id, $product_info) {
		$product_id = (int)$product_id;
		$requested = isset($this->request->get['variant']) ? trim((string)$this->request->get['variant']) : '';

		if ($requested !== '' && $product_id > 0 && $this->variantSkuBelongsToProduct($product_id, $requested)) {
			return $requested;
		}

		return $this->resolveItemId($product_info);
	}

	private function resolveVariantSku($product_id, array $options) {
		$product_id = (int)$product_id;

		if ($product_id <= 0 || !$options) {
			return '';
		}

		if (!class_exists('CyberpunksShopVariantIdentifiersStorage')) {
			$library = DIR_SYSTEM . 'library/cyberpunks_shop_variant_identifiers_storage.php';

			if (!is_file($library)) {
				return '';
			}

			require_once($library);
		}

		if (!class_exists('CyberpunksShopVariantIdentifiersStorage')) {
			return '';
		}

		$mappings = $this->config->get('module_cyberpunks_variant_identifiers_mappings');

		if (!is_array($mappings) || !$mappings) {
			$mappings = CyberpunksShopVariantIdentifiersStorage::loadAll($this->registry);
		}

		if (!is_array($mappings) || !$mappings) {
			return '';
		}

		$match = CyberpunksShopVariantIdentifiersStorage::resolveCartIdentifiers($mappings, $product_id, $options);

		return !empty($match['sku']) ? trim((string)$match['sku']) : '';
	}

	private function variantSkuBelongsToProduct($product_id, $sku) {
		$product_id = (int)$product_id;
		$sku = trim((string)$sku);

		if ($product_id <= 0 || $sku === '') {
			return false;
		}

		if (!class_exists('CyberpunksShopVariantIdentifiersStorage')) {
			$library = DIR_SYSTEM . 'library/cyberpunks_shop_variant_identifiers_storage.php';

			if (!is_file($library)) {
				return false;
			}

			require_once($library);
		}

		if (!class_exists('CyberpunksShopVariantIdentifiersStorage')) {
			return false;
		}

		$mappings = $this->config->get('module_cyberpunks_variant_identifiers_mappings');

		if (!is_array($mappings) || !$mappings) {
			$mappings = CyberpunksShopVariantIdentifiersStorage::loadAll($this->registry);
		}

		if (!is_array($mappings)) {
			return false;
		}

		foreach ($mappings as $row) {
			if (!is_array($row)) {
				continue;
			}

			$row_pid = isset($row['p']) ? (int)$row['p'] : (isset($row['product_id']) ? (int)$row['product_id'] : 0);
			$row_sku = isset($row['k']) ? trim((string)$row['k']) : (isset($row['sku']) ? trim((string)$row['sku']) : '');
			$row_status = isset($row['t']) ? $row['t'] : (isset($row['status']) ? $row['status'] : 1);

			if ($row_pid === $product_id && $row_sku === $sku && !empty($row_status)) {
				return true;
			}
		}

		return false;
	}

	private function resolveItemId($product_row) {
		$field = $this->getItemIdField();

		if ($field === 'sku' && !empty($product_row['product_id'])) {
			$this->load->model('catalog/product');
			$product_info = $this->model_catalog_product->getProduct((int)$product_row['product_id']);

			if ($product_info && !empty($product_info['sku'])) {
				return (string)$product_info['sku'];
			}
		}

		if (!empty($product_row['sku'])) {
			return (string)$product_row['sku'];
		}

		if (!empty($product_row['model'])) {
			return (string)$product_row['model'];
		}

		return '';
	}

	private function roundMoney($value) {
		return round((float)$value, 2);
	}
}

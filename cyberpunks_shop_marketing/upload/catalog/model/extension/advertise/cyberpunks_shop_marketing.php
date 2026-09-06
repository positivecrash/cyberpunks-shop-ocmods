<?php
class ModelExtensionAdvertiseCyberpunksShopMarketing extends Model {
	private const CONSENT_CONFIG_ELEMENT_ID = 'cyberpunks-consent-config';
	private const CONSENT_STORAGE_KEY = 'cyberpunks_google_consent';
	private const CONSENT_DEFAULT_EXPIRY_DAYS = 30;
	private const CONSENT_WAIT_FOR_UPDATE_MS = 500;

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

	public function isViewItemEnabled() {
		return $this->isGtmEnabled() && (bool)$this->config->get('advertise_cyberpunks_shop_marketing_gtm_event_view_item');
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
			'expiryDays' => $this->getConsentExpiryDays(),
			'waitForUpdate' => self::CONSENT_WAIT_FOR_UPDATE_MS,
		);
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

		$matomo = $this->renderMatomoHeadSnippet();
		if ($matomo !== '') {
			$parts[] = $matomo;
		}

		return implode("\n", $parts);
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
	 * Supports legacy plain string and language_id => text map. No hardcoded defaults.
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
			if ($language_id > 0 && isset($value[$language_id])) {
				return trim((string)$value[$language_id]);
			}

			return '';
		}

		return trim((string)$value);
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
			$items[] = array(
				'item_id'   => $this->resolveItemId($product),
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
			$sku = $this->resolveItemId($product);

			if ($sku === '') {
				$sku = 'item-' . (isset($product['order_product_id']) ? (int)$product['order_product_id'] : 0);
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

		return array(
			'currency' => $currency,
			'value'    => $price,
			'items'    => array(
				array(
					'item_id'   => $this->resolveItemId($product_info),
					'item_name' => isset($product_info['name']) ? (string)$product_info['name'] : '',
					'price'     => $price,
					'quantity'  => 1,
				),
			),
		);
	}

	public function renderDataLayerScript($event, array $ecommerce) {
		$payload = json_encode($ecommerce, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if ($payload === false) {
			return '';
		}

		$event_name = json_encode((string)$event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return "<!-- cyberpunks-marketing: " . htmlspecialchars((string)$event, ENT_QUOTES, 'UTF-8') . " -->\n"
			. "<script>\n"
			. "window.dataLayer = window.dataLayer || [];\n"
			. "dataLayer.push({ ecommerce: null });\n"
			. "dataLayer.push({ event: " . $event_name . ", ecommerce: " . $payload . " });\n"
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

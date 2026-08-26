<?php
class ControllerExtensionModuleCyberpunksCheckoutFacade extends Controller {
	/** @var string Payment method `code` to restore after `payment_methods` is rebuilt (see hydrateSections). */
	private $cpPreservePaymentMethodCode = '';

	public function review_totals() {
		$this->load->language('checkout/checkout');

		$data = array();
		$data['coupon_code'] = isset($this->session->data['coupon']) ? (string)$this->session->data['coupon'] : '';

		// Compute totals (same style as checkout/confirm).
		$this->load->model('setting/extension');

		$cp_total_label_overrides = $this->config->get('module_cyberpunks_language_overrides_total_labels');
		if (!is_array($cp_total_label_overrides)) {
			$cp_total_label_overrides = array();
		}

		$totals = array();
		$taxes = $this->cart->getTaxes();
		$total = 0;

		$total_data = array(
			'totals' => &$totals,
			'taxes'  => &$taxes,
			'total'  => &$total
		);

		$sort_order = array();
		$results = $this->model_setting_extension->getExtensions('total');

		foreach ($results as $key => $value) {
			$sort_order[$key] = (int)$this->config->get('total_' . $value['code'] . '_sort_order');
		}

		array_multisort($sort_order, SORT_ASC, $results);

		foreach ($results as $result) {
			if ($this->config->get('total_' . $result['code'] . '_status')) {
				$this->load->model('extension/total/' . $result['code']);
				$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
			}
		}

		$sort_order = array();
		foreach ($totals as $key => $value) {
			$sort_order[$key] = $value['sort_order'];
		}
		array_multisort($sort_order, SORT_ASC, $totals);

		$data['totals'] = array();
		foreach ($totals as $total_row) {
			$cp_total_code = isset($total_row['code']) ? (string)$total_row['code'] : '';
			$cp_total_title = $total_row['title'];
			if ($cp_total_code !== '' && !empty($cp_total_label_overrides[$cp_total_code])) {
				$cp_total_title = $cp_total_label_overrides[$cp_total_code];
			}

			$data['totals'][] = array(
				'code'  => isset($total_row['code']) ? $total_row['code'] : '',
				'title' => $cp_total_title,
				'text'  => $this->currency->format($total_row['value'], $this->session->data['currency'])
			);
		}

		$this->response->setOutput($this->load->view('checkout/review_totals', $data));
	}

	public function section() {
		$this->load->language('checkout/checkout');

		$section = isset($this->request->get['section']) ? $this->request->get['section'] : '';
		$allowed = array('guest', 'payment_address', 'shipping_method', 'payment_method', 'confirm');

		if (!in_array($section, $allowed, true)) {
			$this->response->setOutput('');
			return;
		}

		$redirect = $this->getCheckoutRedirect();

		if ($redirect && $section !== 'guest') {
			$this->response->setOutput('');
			return;
		}

		if ($section === 'guest') {
			$output = $this->renderControllerOutput('checkout/guest');
		} elseif ($section === 'payment_address') {
			$output = $this->renderControllerOutput('checkout/payment_address');
		} elseif ($section === 'shipping_method') {
			$output = $this->cart->hasShipping() ? $this->renderControllerOutput('checkout/shipping_method') : '';
		} elseif ($section === 'payment_method') {
			$output = $this->renderPaymentMethodSection();
		} else {
			$output = $this->renderControllerOutput('checkout/confirm');
		}

		$this->response->setOutput($output);
	}

	public function save_guest() {
		$this->load->language('checkout/checkout');

		$json = array();

		if ($this->customer->isLogged()) {
			$json['redirect'] = $this->url->link('checkout/checkout', '', true);
		}

		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$json['redirect'] = $this->url->link('checkout/cart');
		}

		if (!$this->config->get('config_checkout_guest') || $this->config->get('config_customer_price') || $this->cart->hasDownload()) {
			$json['redirect'] = $this->url->link('checkout/checkout', '', true);
		}

		if (!$json) {
			$json = $this->validateGuestPayload();
		}

		if (!$json) {
			$this->persistGuestPayload();
			$this->hydrateSections($json);
		}

		$this->json($json);
	}

	// Lightweight address update for instant shipping quote recalculation.
	// Used when user changes country/zone before completing full guest form.
	public function save_address_meta() {
		$this->load->language('checkout/checkout');

		$json = array();

		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$json['redirect'] = $this->url->link('checkout/cart');
			$this->json($json);
			return;
		}

		$country_id = isset($this->request->post['country_id']) ? (int)$this->request->post['country_id'] : 0;
		$zone_id = isset($this->request->post['zone_id']) ? (int)$this->request->post['zone_id'] : 0;

		if (!$country_id) {
			$this->json($json);
			return;
		}

		if (!isset($this->session->data['payment_address']) || !is_array($this->session->data['payment_address'])) {
			$this->session->data['payment_address'] = array();
		}

		$this->session->data['payment_address']['country_id'] = $country_id;
		$this->session->data['payment_address']['zone_id'] = $zone_id;

		$this->load->model('localisation/country');
		$country_info = $this->model_localisation_country->getCountry($country_id);
		if ($country_info) {
			$this->session->data['payment_address']['country'] = $country_info['name'];
			$this->session->data['payment_address']['iso_code_2'] = $country_info['iso_code_2'];
			$this->session->data['payment_address']['iso_code_3'] = $country_info['iso_code_3'];
			$this->session->data['payment_address']['address_format'] = $country_info['address_format'];
		}

		$this->load->model('localisation/zone');
		$zone_info = $zone_id ? $this->model_localisation_zone->getZone($zone_id) : null;
		if ($zone_info) {
			$this->session->data['payment_address']['zone'] = $zone_info['name'];
			$this->session->data['payment_address']['zone_code'] = $zone_info['code'];
		} else {
			$this->session->data['payment_address']['zone'] = '';
			$this->session->data['payment_address']['zone_code'] = '';
		}

		$this->tax->setPaymentAddress($country_id, $zone_id);

		if ($this->cart->hasShipping()) {
			$preserve_shipping_code = '';
			if (isset($this->session->data['shipping_method']['code']) && is_string($this->session->data['shipping_method']['code'])) {
				$preserve_shipping_code = $this->session->data['shipping_method']['code'];
			}

			if (!isset($this->session->data['shipping_address']) || !is_array($this->session->data['shipping_address'])) {
				$this->session->data['shipping_address'] = array();
			}

			$this->session->data['shipping_address']['country_id'] = $country_id;
			$this->session->data['shipping_address']['zone_id'] = $zone_id;

			if ($country_info) {
				$this->session->data['shipping_address']['country'] = $country_info['name'];
				$this->session->data['shipping_address']['iso_code_2'] = $country_info['iso_code_2'];
				$this->session->data['shipping_address']['iso_code_3'] = $country_info['iso_code_3'];
				$this->session->data['shipping_address']['address_format'] = $country_info['address_format'];
			}

			if ($zone_info) {
				$this->session->data['shipping_address']['zone'] = $zone_info['name'];
				$this->session->data['shipping_address']['zone_code'] = $zone_info['code'];
			} else {
				$this->session->data['shipping_address']['zone'] = '';
				$this->session->data['shipping_address']['zone_code'] = '';
			}

			$this->tax->setShippingAddress($country_id, $zone_id);

			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);

			$this->rebuildShippingMethodsSession();
			$this->tryRestoreShippingMethod($preserve_shipping_code);
		}

		$this->cpPreservePaymentMethodCode = $this->getCurrentPaymentMethodCode();

		unset($this->session->data['payment_method']);
		unset($this->session->data['payment_methods']);

		$this->hydrateSections($json);
		$this->json($json);
	}

	public function save_shipping() {
		$this->load->language('checkout/checkout');

		$json = array();

		if (!$this->cart->hasShipping()) {
			$json['redirect'] = $this->url->link('checkout/checkout', '', true);
		}

		if (!isset($this->session->data['shipping_address'])) {
			$json['redirect'] = $this->url->link('checkout/checkout', '', true);
		}

		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$json['redirect'] = $this->url->link('checkout/cart');
		}

		if ($this->hasMinimumQuantityViolation()) {
			$json['redirect'] = $this->url->link('checkout/cart');
		}

		if (!isset($this->request->post['shipping_method'])) {
			$json['error']['warning'] = $this->language->get('error_shipping');
		} else {
			$shipping = explode('.', $this->request->post['shipping_method']);

			if (!isset($shipping[0]) || !isset($shipping[1]) || !isset($this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]])) {
				$json['error']['warning'] = $this->language->get('error_shipping');
			}
		}

		if (!$json) {
			$this->session->data['shipping_method'] = $this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]];
			$this->session->data['comment'] = strip_tags(isset($this->request->post['comment']) ? $this->request->post['comment'] : '');
			$this->hydrateSections($json);
		}

		$this->json($json);
	}

	public function save_card_holder() {
		$this->load->language('checkout/checkout');

		$json = array();

		$name = isset($this->request->post['card_holder']) ? trim((string)$this->request->post['card_holder']) : '';
		$this->session->data['cyberpunks_card_holder'] = $name;

		if ($name !== '' && isset($this->session->data['order_id']) && (int)$this->session->data['order_id'] > 0) {
			$order_id = (int)$this->session->data['order_id'];

			$q = $this->db->query("SELECT comment FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "'");
			$current = ($q && $q->num_rows) ? (string)$q->row['comment'] : '';

			$line = 'Card holder: ' . $name;

			// Replace previous card-holder line if present.
			$updated = preg_replace('/(^|\R)Card holder:\s*[^\R]*/m', '$1' . $line, $current);
			if ($updated === null) {
				$updated = $current;
			}

			if ($updated === $current) {
				$updated = rtrim($current);
				if ($updated !== '') {
					$updated .= "\n";
				}
				$updated .= $line;
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "order` SET comment = '" . $this->db->escape($updated) . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");
		}

		$this->json($json);
	}

	public function save_payment() {
		$this->load->language('checkout/checkout');

		$json = array();

		if (!isset($this->session->data['payment_address'])) {
			$json['redirect'] = $this->url->link('checkout/checkout', '', true);
		}

		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$json['redirect'] = $this->url->link('checkout/cart');
		}

		if ($this->hasMinimumQuantityViolation()) {
			$json['redirect'] = $this->url->link('checkout/cart');
		}

		if (!isset($this->request->post['payment_method'])) {
			$json['error']['warning'] = $this->language->get('error_payment');
		} elseif (!isset($this->session->data['payment_methods'][$this->request->post['payment_method']])) {
			$json['error']['warning'] = $this->language->get('error_payment');
		}

		if ($this->config->get('config_checkout_id')) {
			$this->load->model('catalog/information');

			$information_info = $this->model_catalog_information->getInformation($this->config->get('config_checkout_id'));

			if ($information_info && !isset($this->request->post['agree'])) {
				$json['error']['warning'] = sprintf($this->language->get('error_agree'), $information_info['title']);
			}
		}

		if (!$json) {
			$this->session->data['payment_method'] = $this->session->data['payment_methods'][$this->request->post['payment_method']];
			$this->session->data['comment'] = strip_tags(isset($this->request->post['comment']) ? $this->request->post['comment'] : '');

			// Start payment with a fresh Revolut session state to avoid redirecting to stale completed orders.
			unset($this->session->data['revolut_order_id']);

			$this->hydrateSections($json);
			// Return SEO-rewritten URL (if SEO module supports this route).
			$json['payment_url'] = $this->url->link('extension/cyberpunks_checkout_facade/payment', '', true);
		}

		$this->json($json);
	}

	public function confirm() {
		$this->load->language('checkout/checkout');

		$json = array();
		$redirect = $this->getCheckoutRedirect();

		if ($redirect) {
			$json['redirect'] = $redirect;
		} else {
			$json['confirm_html'] = $this->renderControllerOutput('checkout/confirm');
		}

		$this->json($json);
	}

	public function payment() {
		$this->load->language('checkout/checkout');

		$redirect = $this->getCheckoutRedirect();
		if ($redirect) {
			$this->response->redirect($redirect);
			return;
		}

		$data = array();
		$data['heading_title'] = $this->language->get('heading_title');
		$data['header'] = $this->load->controller('common/header');
		$data['footer'] = $this->load->controller('common/footer');

		// Ensure payment methods are available and selected in session.
		// checkout/confirm requires $this->session->data['payment_method'] to create an order.
		$this->renderPaymentMethodSection();

		// /payment should always create a fresh Revolut order for the current checkout session.
		unset($this->session->data['revolut_order_id']);

		// Run checkout/confirm to ensure an OpenCart order is created and stored in session.
		// Do NOT render its HTML on /payment (it contains default table layout).
		$this->renderControllerOutput('checkout/confirm');

		// Render just the payment widget/form for the selected payment method.
		$code = 'revolut_card';
		if (isset($this->session->data['payment_method']['code']) && is_string($this->session->data['payment_method']['code']) && $this->session->data['payment_method']['code'] !== '') {
			$code = $this->session->data['payment_method']['code'];
		}
		$data['payment_code'] = $code;
		$data['cp_payment_show_pay_total'] = (strncmp($code, 'revolut', 7) === 0);
		$data['payment_html'] = $this->renderControllerOutput('extension/payment/' . $code);

		// Review order (right column): expose cart products and totals to the payment template
		// using the same logic as checkout.php review-data patch in cyberpunks_shop_checkout.
		$this->loadPaymentReviewData($data);

		$this->response->setOutput($this->load->view('checkout/payment_review', $data));
	}

	public function express_params() {
		$json = array('enabled' => false);

		try {
			if ($this->getCheckoutRedirect()) {
				$json['reason'] = 'cart';
				$this->json($json);
				return;
			}

			$public_key = (string)$this->config->get('payment_revolut_api_public_key');
			$card_enabled = (int)$this->config->get('payment_revolut_card_status');
			$gateway_enabled = (int)$this->config->get('payment_revolut_status');

			if ($public_key === '' || ($card_enabled !== 1 && $gateway_enabled !== 1)) {
				$json['reason'] = 'revolut_disabled';
				$this->json($json);
				return;
			}

			$country_iso = isset($this->request->get['country_iso']) ? (string)$this->request->get['country_iso'] : '';
			$shipping_options = array();

			if ($this->cart->hasShipping()) {
				$shipping_options = $this->getExpressShippingOptionsForCountryIso($country_iso);
			}

			$total_minor = $this->getOrderTotalMinor();

			$express_token = bin2hex(function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16));
			$this->session->data['cp_express_token'] = $express_token;

			if (isset($this->cache)) {
				$this->cache->set('cp_express_' . $express_token, array(
					'currency' => (string)$this->session->data['currency'],
					'created'  => time(),
				));
			}

			$json = array(
				'enabled'              => true,
				'facade_version'       => '1.0.0',
				'public_token'         => $public_key,
				'mode'                 => $this->config->get('payment_revolut_test') ? 'sandbox' : 'prod',
				'embed_domain'         => $this->config->get('payment_revolut_test') ? 'sandbox-merchant' : 'merchant',
				'shipping_required'    => (bool)$this->cart->hasShipping(),
				'shipping_options'     => $shipping_options,
				'currency'             => (string)$this->session->data['currency'],
				'cart_amount'          => $total_minor,
				'total'                => array('amount' => $total_minor),
				'express_token'        => $express_token,
				'mobile_redirect_url'  => $this->url->link('extension/payment/revolut/appRedirection', '', true),
			);
		} catch (Throwable $e) {
			$json = array(
				'enabled' => false,
				'reason'  => 'server_error',
				'error'   => $e->getMessage(),
			);
		}

		$this->json($json);
	}

	/**
	 * @deprecated Not called from express_params — Apple Pay domain registration
	 * must be done in admin (Revolut PRB → Save) to avoid blocking checkout.
	 */
	private function ensureApplePayDomain() {
		$result = array(
			'ok'     => false,
			'reason' => 'unknown',
			'domain' => '',
		);

		if ($this->config->get('payment_revolut_test')) {
			$result['reason'] = 'sandbox';
			return $result;
		}

		$host = '';
		if (defined('HTTPS_SERVER') && HTTPS_SERVER) {
			$host = (string)parse_url(HTTPS_SERVER, PHP_URL_HOST);
		}
		if ($host === '' && defined('HTTP_SERVER') && HTTP_SERVER) {
			$host = (string)parse_url(HTTP_SERVER, PHP_URL_HOST);
		}
		$result['domain'] = $host;

		if ($host === '' || $host === 'localhost' || strpos($host, '.local') !== false) {
			$result['reason'] = 'local_host';
			return $result;
		}

		if ((string)$this->config->get('cp_apple_pay_domain') === 'OK'
			|| (string)$this->config->get('payment_revolut_prb_apple_pay_domain') === 'OK') {
			$result['ok'] = true;
			$result['reason'] = 'cached';
			return $result;
		}

		if (isset($this->cache)) {
			$cached = $this->cache->get('cp_apple_pay_domain_status');
			if (is_array($cached) && !empty($cached['ok'])) {
				$result['ok'] = true;
				$result['reason'] = 'cache_file';
				return $result;
			}
			// Avoid hammering Revolut if the last attempt failed recently.
			if (is_array($cached) && isset($cached['tried_at']) && (time() - (int)$cached['tried_at']) < 3600) {
				$result['reason'] = isset($cached['reason']) ? (string)$cached['reason'] : 'recent_fail';
				$result['http_code'] = isset($cached['http_code']) ? $cached['http_code'] : null;
				return $result;
			}
		}

		$api_key = (string)$this->config->get('payment_revolut_api_key');
		if ($api_key === '') {
			$result['reason'] = 'no_api_key';
			return $result;
		}

		$api_file = DIR_SYSTEM . 'library/vendor/revolut/api_request.php';
		if (!is_file($api_file)) {
			$result['reason'] = 'no_api_client';
			return $result;
		}

		// Keep association file permanently (Apple may re-check).
		$root = str_replace('catalog/', '', DIR_CATALOG);
		$well_known = $root . '.well-known';
		$association = $well_known . '/apple-developer-merchantid-domain-association';

		if (!is_file($association)) {
			if (!is_dir($well_known) && !@mkdir($well_known, 0755, true)) {
				$result['reason'] = 'mkdir_failed';
				return $result;
			}

			$remote = 'https://assets.revolut.com/api-docs/merchant-api/files/apple-developer-merchantid-domain-association';
			$contents = @file_get_contents($remote);
			if ($contents === false || $contents === '') {
				$result['reason'] = 'download_failed';
				return $result;
			}
			if (@file_put_contents($association, $contents) === false) {
				$result['reason'] = 'write_failed';
				return $result;
			}
		}

		require_once($api_file);

		try {
			$api_client = new ApiRequest($api_key, false, 'api/');
			$api_result = $api_client->post('apple-pay/domains/register', array('domain' => $host));
		} catch (Exception $e) {
			$result['reason'] = 'api_exception';
			$result['error'] = $e->getMessage();
			$this->storeApplePayDomainCache(false, $result['reason'], null);
			return $result;
		}

		$http_code = isset($api_result['http_code']) ? (int)$api_result['http_code'] : 0;
		$result['http_code'] = $http_code;

		// 200/204 = registered; 409 = already registered (treat as success).
		if ($http_code === 200 || $http_code === 204 || $http_code === 409) {
			$result['ok'] = true;
			$result['reason'] = ($http_code === 409) ? 'already_registered' : 'registered';

			$this->persistApplePayDomainOk($host);
			$this->storeApplePayDomainCache(true, $result['reason'], $http_code);

			return $result;
		}

		$result['reason'] = 'register_failed';
		$result['response'] = isset($api_result['response']) ? $api_result['response'] : null;
		$this->storeApplePayDomainCache(false, $result['reason'], $http_code);

		return $result;
	}

	private function storeApplePayDomainCache($ok, $reason, $http_code) {
		if (!isset($this->cache)) {
			return;
		}

		$this->cache->set('cp_apple_pay_domain_status', array(
			'ok'        => (bool)$ok,
			'reason'    => (string)$reason,
			'http_code' => $http_code,
			'tried_at'  => time(),
		));
	}

	private function persistApplePayDomainOk($host) {
		// Catalog setting model cannot editSetting — write rows directly.
		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND `code` = 'module_cyberpunks_checkout_facade' AND `key` IN ('cp_apple_pay_domain', 'cp_apple_pay_domain_host')");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = '0', `code` = 'module_cyberpunks_checkout_facade', `key` = 'cp_apple_pay_domain', `value` = 'OK', serialized = '0'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = '0', `code` = 'module_cyberpunks_checkout_facade', `key` = 'cp_apple_pay_domain_host', `value` = '" . $this->db->escape((string)$host) . "', serialized = '0'");

		$prb = $this->db->query("SELECT setting_id FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND `key` = 'payment_revolut_prb_apple_pay_domain' LIMIT 1");
		if ($prb->num_rows) {
			$this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = 'OK', serialized = '0' WHERE setting_id = '" . (int)$prb->row['setting_id'] . "'");
		}
	}

	/**
	 * Revolut Pay Fast checkout synchronous webhook.
	 * Register via Merchant API: POST /api/synchronous-webhooks
	 * event_type=fast_checkout.validate_address
	 * url=https://your.shop/index.php?route=extension/module/cyberpunks_checkout_facade/express_validate_address
	 */
	public function express_validate_address() {
		try {
			$input = $this->getJsonInput();
			$shipping_address = $this->extractValidateAddressPayload($input);

			$token = '';
			if (isset($input['metadata']) && is_array($input['metadata']) && isset($input['metadata']['cp_token'])) {
				$token = (string)$input['metadata']['cp_token'];
			}

			// Currency must exist before shipping quotes call currency->format().
			if (empty($this->session->data['currency']) && $token !== '' && isset($this->cache)) {
				$cached = $this->cache->get('cp_express_' . $token);
				if (is_array($cached) && !empty($cached['currency'])) {
					$this->session->data['currency'] = $cached['currency'];
				}
			}

			if (empty($this->session->data['currency'])) {
				$this->session->data['currency'] = $this->config->get('config_currency');
			}

			$wallet = array(
				'address' => array(
					'countryCode' => $this->pickAddressField($shipping_address, array('country_code', 'countryCode', 'country')),
					'region'      => $this->pickAddressField($shipping_address, array('region', 'administrativeArea', 'state')),
					'city'        => $this->pickAddressField($shipping_address, array('city', 'locality')),
					'postcode'    => $this->pickAddressField($shipping_address, array('postcode', 'postalCode', 'zip')),
					'streetLine1' => $this->pickAddressField($shipping_address, array('street_line_1', 'streetLine1', 'line_1', 'address_line_1')),
					'streetLine2' => $this->pickAddressField($shipping_address, array('street_line_2', 'streetLine2', 'line_2', 'address_line_2')),
				),
			);

			$this->logValidateAddress('incoming', array(
				'order_id' => isset($input['order_id']) ? $input['order_id'] : null,
				'address'  => $wallet['address'],
				'raw_keys' => array_keys($shipping_address),
			));

			if (!$this->seedExpressQuoteFromWalletAddress($wallet)) {
				$this->logValidateAddress('reject_country', $wallet['address']);
				$this->json(array(
					'valid'            => false,
					'delivery_methods' => array(),
				));
				return;
			}

			$this->rebuildShippingMethodsSession();
			$options = $this->buildExpressShippingOptions();

			if (!$options) {
				$this->logValidateAddress('reject_no_methods', array(
					'country_id' => isset($this->session->data['shipping_address']['country_id']) ? $this->session->data['shipping_address']['country_id'] : null,
					'methods'    => isset($this->session->data['shipping_methods']) ? array_keys($this->session->data['shipping_methods']) : array(),
				));
				$this->json(array(
					'valid'            => false,
					'delivery_methods' => array(),
				));
				return;
			}

			$delivery_methods = array();

			foreach ($options as $index => $option) {
				// Keep refs short/simple — some Revolut clients are picky about dotted ids.
				$ref = 'ship_' . (int)$index;
				if (!empty($option['id'])) {
					$ref = substr(preg_replace('/[^a-zA-Z0-9_]+/', '_', (string)$option['id']), 0, 100);
				}

				$delivery_methods[] = array(
					'ref'         => $ref,
					'amount'      => (int)$option['amount'],
					'label'       => substr((string)$option['label'], 0, 100),
					'description' => isset($option['description']) ? substr((string)$option['description'], 0, 1024) : '',
				);
			}

			if ($token !== '' && isset($this->cache)) {
				$this->cache->set('cp_express_addr_' . $token, array(
					'address'          => $wallet['address'],
					'delivery_methods' => $delivery_methods,
					'shipping_options' => $options,
					'updated'          => time(),
				));
			}

			// Also cache by Revolut order id when present (metadata may be empty pre-createOrder).
			if (!empty($input['order_id']) && isset($this->cache)) {
				$this->cache->set('cp_express_addr_order_' . (string)$input['order_id'], array(
					'address'          => $wallet['address'],
					'delivery_methods' => $delivery_methods,
					'shipping_options' => $options,
					'updated'          => time(),
				));
			}

			$this->logValidateAddress('ok', array(
				'count'   => count($delivery_methods),
				'methods' => $delivery_methods,
			));

			$this->json(array(
				'valid'            => true,
				'delivery_methods' => $delivery_methods,
			));
		} catch (Throwable $e) {
			$this->logValidateAddress('exception', array('error' => $e->getMessage()));
			$this->json(array(
				'valid'            => false,
				'delivery_methods' => array(),
			));
		}
	}

	private function extractValidateAddressPayload($input) {
		if (isset($input['shipping_address']) && is_array($input['shipping_address'])) {
			return $input['shipping_address'];
		}

		if (isset($input['shippingAddress']) && is_array($input['shippingAddress'])) {
			return $input['shippingAddress'];
		}

		if (isset($input['address']) && is_array($input['address'])) {
			return $input['address'];
		}

		return array();
	}

	private function pickAddressField($address, $keys) {
		if (!is_array($address)) {
			return '';
		}

		foreach ($keys as $key) {
			if (!array_key_exists($key, $address)) {
				continue;
			}

			$value = $address[$key];

			if ($value === null) {
				continue;
			}

			$value = trim((string)$value);

			if ($value !== '') {
				return $value;
			}
		}

		return '';
	}

	private function logValidateAddress($stage, $data) {
		$message = 'Revolut Fast checkout validate_address [' . $stage . ']: ' . json_encode($data);

		if (isset($this->log) && is_object($this->log) && method_exists($this->log, 'write')) {
			$this->log->write($message);
			return;
		}

		$logger = new Log('revolut_fast_checkout.log');
		$logger->write($message);
	}

	public function express_shipping() {
		$json = array('status' => 'fail');

		if ($this->getCheckoutRedirect()) {
			$json['error'] = 'cart';
			$this->json($json);
			return;
		}

		if (!$this->cart->hasShipping()) {
			$json['status'] = 'success';
			$json['total'] = array('amount' => $this->getOrderTotalMinor());
			$this->json($json);
			return;
		}

		$input = $this->getJsonInput();

		// Apple/Google often send country-only on first address change — quote without full guest payload.
		$applied = $this->applyExpressWalletPayload($input, true);

		if (!$applied['success']) {
			$quoted = $this->seedExpressQuoteFromWalletAddress($input);

			if (!$quoted) {
				$json['error'] = $applied['error'];
				$this->json($json);
				return;
			}
		}

		$this->rebuildShippingMethodsSession();

		if (empty($this->session->data['shipping_methods'])) {
			$json['error'] = 'shipping_unavailable';
			$this->json($json);
			return;
		}

		$shipping_options = $this->buildExpressShippingOptions();

		if (!$shipping_options) {
			$json['error'] = 'shipping_unavailable';
			$this->json($json);
			return;
		}

		$first = $shipping_options[0];
		$this->setExpressShippingMethodById($first['id']);

		$json['status'] = 'success';
		$json['shippingOptions'] = $shipping_options;
		$json['total'] = array('amount' => $this->getOrderTotalMinor());

		$this->json($json);
	}

	public function express_shipping_option() {
		$json = array('status' => 'fail');

		if ($this->getCheckoutRedirect()) {
			$json['error'] = 'cart';
			$this->json($json);
			return;
		}

		$input = $this->getJsonInput();
		$option_id = isset($input['shipping_option_id']) ? (string)$input['shipping_option_id'] : '';

		if ($option_id === '' && isset($input['id'])) {
			$option_id = (string)$input['id'];
		}

		if ($option_id === '' || !$this->setExpressShippingMethodById($option_id)) {
			$json['error'] = 'shipping_option';
			$this->json($json);
			return;
		}

		$json['status'] = 'success';
		$json['total'] = array('amount' => $this->getOrderTotalMinor());

		$this->json($json);
	}

	public function express_prepare_order() {
		$json = array('success' => false);

		if ($this->getCheckoutRedirect()) {
			$json['error'] = 'cart';
			$this->json($json);
			return;
		}

		$input = $this->getJsonInput();

		// Apply Revolut Pay Fast checkout address cached from validate_address webhook.
		$token = isset($this->session->data['cp_express_token']) ? (string)$this->session->data['cp_express_token'] : '';
		if ($token !== '' && isset($this->cache) && empty($input)) {
			$cached = $this->cache->get('cp_express_addr_' . $token);
			if (is_array($cached) && !empty($cached['address'])) {
				$input = array('address' => $cached['address']);
				if (!empty($cached['shipping_options'][0]['id'])) {
					$this->seedExpressQuoteFromWalletAddress($input);
					$this->rebuildShippingMethodsSession();
					$this->setExpressShippingMethodById((string)$cached['shipping_options'][0]['id']);
				}
			}
		}

		if ($input) {
			$applied = $this->applyExpressWalletPayload($input);

			if (!$applied['success']) {
				// Revolut Pay webhook may have partial contact — try quote address + guest defaults.
				if (!$this->seedExpressQuoteFromWalletAddress($input)) {
					$json['error'] = $applied['error'];
					$this->json($json);
					return;
				}

				if (empty($this->session->data['guest'])) {
					$this->session->data['account'] = 'guest';
					$this->session->data['guest'] = array(
						'customer_group_id' => $this->config->get('config_customer_group_id'),
						'firstname'         => 'Guest',
						'lastname'          => 'Customer',
						'email'             => 'express@cyberpunks.shop',
						'telephone'         => '0000000',
						'custom_field'      => array(),
					);
				}

				if (!empty($this->session->data['shipping_address'])) {
					$this->session->data['payment_address'] = $this->session->data['shipping_address'];
				}
			}
		}

		if ($this->cart->hasShipping() && empty($this->session->data['shipping_method'])) {
			$this->rebuildShippingMethodsSession();
			$options = $this->buildExpressShippingOptions();

			if ($options) {
				$this->setExpressShippingMethodById($options[0]['id']);
			}
		}

		if ($this->cart->hasShipping() && empty($this->session->data['shipping_method'])) {
			$json['error'] = 'shipping_required';
			$this->json($json);
			return;
		}

		if (!$this->ensureExpressPaymentMethod()) {
			$json['error'] = 'payment_method';
			$this->json($json);
			return;
		}

		unset($this->session->data['revolut_order_id']);

		if (!$this->createCheckoutOrderFromSession()) {
			$json['error'] = 'order';
			$this->json($json);
			return;
		}

		$json['success'] = true;
		$json['order_id'] = (int)$this->session->data['order_id'];

		$this->json($json);
	}

	private function loadPaymentReviewData(&$data) {
		$this->load->language('extension/module/cyberpunks_checkout_facade');
		$data['text_cp_payment_method_label'] = $this->language->get('text_payment_method_label');

		$this->load->model('tool/image');
		$this->load->model('setting/extension');

		$data['products'] = array();

		foreach ($this->cart->getProducts() as $product) {
			$option_data = array();

			foreach ($product['option'] as $option) {
				$cp_display_name = '';

				if (is_file(DIR_APPLICATION . 'model/extension/module/cyberpunks_shop_option_fields.php') && isset($option['option_id'])) {
					$this->load->model('extension/module/cyberpunks_shop_option_fields');
					$cp_display_name = $this->model_extension_module_cyberpunks_shop_option_fields->resolveDisplayName((int)$product['product_id'], (int)$option['option_id']);
				}

				if ($cp_display_name === '') {
					$cp_display_name = isset($option['display_name']) ? (string)$option['display_name'] : '';
				}

				if ($cp_display_name === '') {
					$cp_display_name = str_replace(array('-', '_'), ' ', (string)$option['name']);
					$cp_display_name = ucwords($cp_display_name);
				}

				$option_data[] = array(
					'display_name' => $cp_display_name,
					'name'         => $option['name'],
					'value'        => (utf8_strlen($option['value']) > 60) ? utf8_substr($option['value'], 0, 60) . '..' : $option['value']
				);
			}

			// Default product thumb + optional variant_image / fields — theme picks priority.
			$thumb = '';
			$variant_image = '';
			$cart_image = isset($product['image']) ? ltrim((string)$product['image'], '/') : '';

			if ($cart_image !== '' && strpos($cart_image, 'catalog/view/theme/') === 0) {
				$thumb = '/' . $cart_image;
			} elseif ($cart_image !== '' && is_file(DIR_IMAGE . $cart_image)) {
				$thumb = $this->model_tool_image->resize($cart_image, 80, 80);
			}

			if ($thumb === '') {
				$thumb = '/catalog/view/theme/cybershops/media/altruist-bundle/Banner-1.webp';
			}

			if (is_file(DIR_SYSTEM . 'library/cyberpunks_shop_variant_images_storage.php')) {
				require_once(DIR_SYSTEM . 'library/cyberpunks_shop_variant_images_storage.php');

				if (method_exists('CyberpunksShopVariantImagesStorage', 'resolveCartImage')) {
					$variant_mappings = $this->config->get('module_cyberpunks_variant_images_mappings');
					if (!is_array($variant_mappings)) {
						$variant_mappings = array();
					}

					$resolved_image = CyberpunksShopVariantImagesStorage::resolveCartImage(
						$variant_mappings,
						(int)$product['product_id'],
						isset($product['option']) && is_array($product['option']) ? $product['option'] : array()
					);

					if ($resolved_image !== '') {
						$variant_image = CyberpunksShopVariantImagesStorage::pathToUrl($resolved_image, $this->model_tool_image, 80, 80);
					}
				}
			}

			$product_row = array(
				'product_id'    => $product['product_id'],
				'name'          => $product['name'],
				'thumb'         => $thumb,
				'variant_image' => $variant_image,
				'quantity'      => $product['quantity'],
				'total'         => $this->currency->format($product['total'], $this->session->data['currency']),
				'option'        => $option_data,
				'href'          => $this->url->link('product/product', 'product_id=' . $product['product_id'])
			);

			if (is_file(DIR_APPLICATION . 'model/extension/module/cyberpunks_shop_product_fields.php')) {
				$this->load->model('extension/module/cyberpunks_shop_product_fields');
				$product_row = $this->model_extension_module_cyberpunks_shop_product_fields->attachProductFields($product_row);
			}

			$data['products'][] = $product_row;
		}

		$cp_total_label_overrides = $this->config->get('module_cyberpunks_language_overrides_total_labels');
		if (!is_array($cp_total_label_overrides)) {
			$cp_total_label_overrides = array();
		}

		$totals = array();
		$taxes = $this->cart->getTaxes();
		$total = 0;

		$total_data = array(
			'totals' => &$totals,
			'taxes'  => &$taxes,
			'total'  => &$total
		);

		$sort_order = array();
		$results = $this->model_setting_extension->getExtensions('total');

		foreach ($results as $key => $value) {
			$sort_order[$key] = (int)$this->config->get('total_' . $value['code'] . '_sort_order');
		}

		array_multisort($sort_order, SORT_ASC, $results);

		foreach ($results as $result) {
			if ($this->config->get('total_' . $result['code'] . '_status')) {
				$this->load->model('extension/total/' . $result['code']);
				$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
			}
		}

		$sort_order = array();
		foreach ($totals as $key => $value) {
			$sort_order[$key] = $value['sort_order'];
		}
		array_multisort($sort_order, SORT_ASC, $totals);

		$data['totals'] = array();
		foreach ($totals as $total_row) {
			$cp_total_code = isset($total_row['code']) ? (string)$total_row['code'] : '';
			$cp_total_title = $total_row['title'];
			if ($cp_total_code !== '' && !empty($cp_total_label_overrides[$cp_total_code])) {
				$cp_total_title = $cp_total_label_overrides[$cp_total_code];
			}

			$data['totals'][] = array(
				'code'  => isset($total_row['code']) ? $total_row['code'] : '',
				'title' => $cp_total_title,
				'text'  => $this->currency->format($total_row['value'], $this->session->data['currency'])
			);
		}

		$data['coupon_code'] = isset($this->session->data['coupon']) ? (string)$this->session->data['coupon'] : '';

		$data['cp_checkout_summary'] = $this->buildCheckoutOrderSummary();
	}

	/**
	 * Name, contact, shipping label and one-line address for the /payment review page (e.g. COD).
	 *
	 * @return array<string,string>
	 */
	private function buildCheckoutOrderSummary() {
		$out = array(
			'full_name'              => '',
			'email'                  => '',
			'telephone'              => '',
			'shipping_method'        => '',
			'payment_method_title'   => '',
			'ships_to'               => '',
		);

		$pa = (isset($this->session->data['payment_address']) && is_array($this->session->data['payment_address'])) ? $this->session->data['payment_address'] : array();
		$sa = (isset($this->session->data['shipping_address']) && is_array($this->session->data['shipping_address'])) ? $this->session->data['shipping_address'] : array();
		$guest = (isset($this->session->data['guest']) && is_array($this->session->data['guest'])) ? $this->session->data['guest'] : array();

		$fn = isset($pa['firstname']) ? trim((string)$pa['firstname']) : '';
		$ln = isset($pa['lastname']) ? trim((string)$pa['lastname']) : '';

		if ($fn === '' && $ln === '' && $guest) {
			$fn = isset($guest['firstname']) ? trim((string)$guest['firstname']) : '';
			$ln = isset($guest['lastname']) ? trim((string)$guest['lastname']) : '';
		}

		$out['full_name'] = trim($fn . ' ' . $ln);

		if ($guest && isset($guest['email'])) {
			$out['email'] = trim((string)$guest['email']);
		} elseif (isset($pa['email'])) {
			$out['email'] = trim((string)$pa['email']);
		}

		if ($guest && isset($guest['telephone'])) {
			$out['telephone'] = trim((string)$guest['telephone']);
		} elseif (isset($pa['telephone'])) {
			$out['telephone'] = trim((string)$pa['telephone']);
		}

		if (isset($this->session->data['shipping_method']['title'])) {
			$out['shipping_method'] = trim((string)$this->session->data['shipping_method']['title']);
		}

		if (isset($this->session->data['payment_method']['title'])) {
			$out['payment_method_title'] = trim((string)$this->session->data['payment_method']['title']);
		}

		$addr_src = $pa;

		if ($this->cart->hasShipping() && $sa && isset($sa['address_1']) && trim((string)$sa['address_1']) !== '') {
			$addr_src = $sa;
		}

		$out['ships_to'] = $this->formatCheckoutSummaryAddressLine($addr_src);

		return $out;
	}

	/**
	 * @param array $a payment or shipping address row from session
	 */
	private function formatCheckoutSummaryAddressLine($a) {
		if (!is_array($a)) {
			return '';
		}

		$parts = array();

		$append = function ($v) use (&$parts) {
			$v = trim((string)$v);

			if ($v !== '') {
				$parts[] = $v;
			}
		};

		$append(isset($a['postcode']) ? $a['postcode'] : '');
		$append(isset($a['country']) ? $a['country'] : '');
		$append(isset($a['zone']) ? $a['zone'] : '');
		$append(isset($a['city']) ? $a['city'] : '');
		$append(isset($a['address_1']) ? $a['address_1'] : '');
		$append(isset($a['company']) ? $a['company'] : '');
		$append(isset($a['address_2']) ? $a['address_2'] : '');

		return implode(', ', $parts);
	}

	private function hydrateSections(&$json) {
		$json['sections'] = array(
			'payment_method' => $this->renderPaymentMethodSection()
		);

		$preserve_payment = $this->cpPreservePaymentMethodCode;
		$this->cpPreservePaymentMethodCode = '';

		if ($preserve_payment !== '' && $this->tryRestorePaymentMethod($preserve_payment)) {
			$json['sections']['payment_method'] = $this->renderPaymentMethodSection();
		}

		if ($this->cart->hasShipping()) {
			$json['sections']['shipping_method'] = $this->renderControllerOutput('checkout/shipping_method');
		}
	}

	private function renderControllerOutput($route) {
		$previous = $this->response->getOutput();
		$this->response->setOutput('');

		$output = $this->load->controller($route);

		if (!is_string($output) || $output === '') {
			$output = $this->response->getOutput();
		}

		$this->response->setOutput($previous);

		return is_string($output) ? $output : '';
	}

	private function renderPaymentMethodSection() {
		$saved_status = array();

		// Express wallets live on /checkout; hide duplicate Revolut methods from the list.
		foreach (array('payment_revolut_prb_status', 'payment_revolut_pay_status') as $key) {
			$saved_status[$key] = $this->config->get($key);
			$this->config->set($key, 0);
		}

		$output = $this->renderControllerOutput('checkout/payment_method');

		if ($this->maybeAutoSelectSinglePayment()) {
			$output = $this->renderControllerOutput('checkout/payment_method');
		}

		foreach ($saved_status as $key => $value) {
			$this->config->set($key, $value);
		}

		if (isset($this->session->data['payment_method']['code'])
			&& in_array($this->session->data['payment_method']['code'], array('revolut_prb', 'revolut_pay'), true)
		) {
			unset($this->session->data['payment_method']);
			$this->maybeAutoSelectSinglePayment();
		}

		return $output;
	}

	private function isAutoSinglePaymentEnabled() {
		$v = $this->config->get('module_cyberpunks_checkout_facade_auto_single_payment');

		if ($v === null) {
			return true;
		}

		return (string)$v === '1';
	}

	private function maybeAutoSelectSinglePayment() {
		if (!$this->isAutoSinglePaymentEnabled()) {
			return false;
		}

		if (!isset($this->session->data['payment_methods']) || !is_array($this->session->data['payment_methods'])) {
			return false;
		}

		if (count($this->session->data['payment_methods']) !== 1) {
			return false;
		}

		$code = key($this->session->data['payment_methods']);

		if (isset($this->session->data['payment_method']['code']) && $this->session->data['payment_method']['code'] === $code) {
			return false;
		}

		$this->session->data['payment_method'] = $this->session->data['payment_methods'][$code];

		return true;
	}

	private function hasMinimumQuantityViolation() {
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			$product_total = 0;

			foreach ($products as $product_2) {
				if ($product_2['product_id'] == $product['product_id']) {
					$product_total += $product_2['quantity'];
				}
			}

			if ($product['minimum'] > $product_total) {
				return true;
			}
		}

		return false;
	}

	private function validateGuestPayload() {
		$json = array();

		if ((utf8_strlen(trim($this->request->post['firstname'])) < 1) || (utf8_strlen(trim($this->request->post['firstname'])) > 32)) {
			$json['error']['firstname'] = $this->language->get('error_firstname');
		}

		if ((utf8_strlen(trim($this->request->post['lastname'])) < 1) || (utf8_strlen(trim($this->request->post['lastname'])) > 32)) {
			$json['error']['lastname'] = $this->language->get('error_lastname');
		}

		if ((utf8_strlen($this->request->post['email']) > 96) || !filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
			$json['error']['email'] = $this->language->get('error_email');
		}

		if ((utf8_strlen($this->request->post['telephone']) < 3) || (utf8_strlen($this->request->post['telephone']) > 32)) {
			$json['error']['telephone'] = $this->language->get('error_telephone');
		}

		if ((utf8_strlen(trim($this->request->post['address_1'])) < 3) || (utf8_strlen(trim($this->request->post['address_1'])) > 128)) {
			$json['error']['address_1'] = $this->language->get('error_address_1');
		}

		if ((utf8_strlen(trim($this->request->post['city'])) < 2) || (utf8_strlen(trim($this->request->post['city'])) > 128)) {
			$json['error']['city'] = $this->language->get('error_city');
		}

		$country_id = isset($this->request->post['country_id']) ? (int)$this->request->post['country_id'] : 0;
		$postcode = isset($this->request->post['postcode']) ? (string)$this->request->post['postcode'] : '';

		$this->load->model('localisation/country');

		$country_info = $country_id ? $this->model_localisation_country->getCountry($country_id) : null;

		if ($country_info && $country_info['postcode_required'] && (utf8_strlen(trim($postcode)) < 2 || utf8_strlen(trim($postcode)) > 10)) {
			$json['error']['postcode'] = $this->language->get('error_postcode');
		}

		if ($country_id <= 0) {
			$json['error']['country'] = $this->language->get('error_country');
		}

		// Zone/region is optional for our checkout: shipping & payments are determined by country.
		// Use zone_id=0 when user didn't pick a region.
		if (!isset($this->request->post['zone_id']) || $this->request->post['zone_id'] === '' || !is_numeric($this->request->post['zone_id'])) {
			$this->request->post['zone_id'] = 0;
		}

		if (isset($this->request->post['customer_group_id']) && is_array($this->config->get('config_customer_group_display')) && in_array($this->request->post['customer_group_id'], $this->config->get('config_customer_group_display'))) {
			$customer_group_id = $this->request->post['customer_group_id'];
		} else {
			$customer_group_id = $this->config->get('config_customer_group_id');
		}

		$this->load->model('account/custom_field');
		$custom_fields = $this->model_account_custom_field->getCustomFields($customer_group_id);

		foreach ($custom_fields as $custom_field) {
			if ($custom_field['location'] == 'affiliate') {
				continue;
			}

			if ($custom_field['required'] && empty($this->request->post['custom_field'][$custom_field['location']][$custom_field['custom_field_id']])) {
				$json['error']['custom_field' . $custom_field['custom_field_id']] = sprintf($this->language->get('error_custom_field'), $custom_field['name']);
			} elseif (($custom_field['type'] == 'text') && !empty($custom_field['validation']) && !filter_var($this->request->post['custom_field'][$custom_field['location']][$custom_field['custom_field_id']], FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => $custom_field['validation'])))) {
				$json['error']['custom_field' . $custom_field['custom_field_id']] = sprintf($this->language->get('error_custom_field'), $custom_field['name']);
			}
		}

		if ($this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('guest', (array)$this->config->get('config_captcha_page'))) {
			$captcha = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha') . '/validate');

			if ($captcha) {
				$json['error']['captcha'] = $captcha;
			}
		}

		return $json;
	}

	private function persistGuestPayload() {
		if (isset($this->request->post['customer_group_id']) && is_array($this->config->get('config_customer_group_display')) && in_array($this->request->post['customer_group_id'], $this->config->get('config_customer_group_display'))) {
			$customer_group_id = $this->request->post['customer_group_id'];
		} else {
			$customer_group_id = $this->config->get('config_customer_group_id');
		}

		$this->session->data['account'] = 'guest';
		$this->session->data['guest']['customer_group_id'] = $customer_group_id;
		$this->session->data['guest']['firstname'] = $this->request->post['firstname'];
		$this->session->data['guest']['lastname'] = $this->request->post['lastname'];
		$this->session->data['guest']['email'] = $this->request->post['email'];
		$this->session->data['guest']['telephone'] = $this->request->post['telephone'];
		$this->session->data['guest']['custom_field'] = isset($this->request->post['custom_field']['account']) ? $this->request->post['custom_field']['account'] : array();

		$this->session->data['payment_address']['firstname'] = $this->request->post['firstname'];
		$this->session->data['payment_address']['lastname'] = $this->request->post['lastname'];
		$this->session->data['payment_address']['company'] = $this->request->post['company'];
		$this->session->data['payment_address']['address_1'] = $this->request->post['address_1'];
		$this->session->data['payment_address']['address_2'] = $this->request->post['address_2'];
		$this->session->data['payment_address']['postcode'] = $this->request->post['postcode'];
		$this->session->data['payment_address']['city'] = $this->request->post['city'];
		$this->session->data['payment_address']['country_id'] = $this->request->post['country_id'];
		$this->session->data['payment_address']['zone_id'] = $this->request->post['zone_id'];
		$this->session->data['payment_address']['custom_field'] = isset($this->request->post['custom_field']['address']) ? $this->request->post['custom_field']['address'] : array();

		$this->load->model('localisation/country');
		$country_info = $this->model_localisation_country->getCountry($this->request->post['country_id']);

		if ($country_info) {
			$this->session->data['payment_address']['country'] = $country_info['name'];
			$this->session->data['payment_address']['iso_code_2'] = $country_info['iso_code_2'];
			$this->session->data['payment_address']['iso_code_3'] = $country_info['iso_code_3'];
			$this->session->data['payment_address']['address_format'] = $country_info['address_format'];
		} else {
			$this->session->data['payment_address']['country'] = '';
			$this->session->data['payment_address']['iso_code_2'] = '';
			$this->session->data['payment_address']['iso_code_3'] = '';
			$this->session->data['payment_address']['address_format'] = '';
		}

		$this->load->model('localisation/zone');
		$zone_info = $this->model_localisation_zone->getZone($this->request->post['zone_id']);

		if ($zone_info) {
			$this->session->data['payment_address']['zone'] = $zone_info['name'];
			$this->session->data['payment_address']['zone_code'] = $zone_info['code'];
		} else {
			$this->session->data['payment_address']['zone'] = '';
			$this->session->data['payment_address']['zone_code'] = '';
		}

		// Ensure tax calculations (including shipping quotes) use the latest address.
		$this->tax->setPaymentAddress((int)$this->request->post['country_id'], (int)$this->request->post['zone_id']);

		$this->session->data['guest']['shipping_address'] = true;

		$preserve_shipping_code = '';

		if (isset($this->session->data['shipping_method']['code'])) {
			$preserve_shipping_code = $this->session->data['shipping_method']['code'];
		}

		if ($this->cart->hasShipping()) {
			$this->session->data['shipping_address']['firstname'] = $this->request->post['firstname'];
			$this->session->data['shipping_address']['lastname'] = $this->request->post['lastname'];
			$this->session->data['shipping_address']['company'] = $this->request->post['company'];
			$this->session->data['shipping_address']['address_1'] = $this->request->post['address_1'];
			$this->session->data['shipping_address']['address_2'] = $this->request->post['address_2'];
			$this->session->data['shipping_address']['postcode'] = $this->request->post['postcode'];
			$this->session->data['shipping_address']['city'] = $this->request->post['city'];
			$this->session->data['shipping_address']['country_id'] = $this->request->post['country_id'];
			$this->session->data['shipping_address']['zone_id'] = $this->request->post['zone_id'];
			$this->session->data['shipping_address']['custom_field'] = isset($this->request->post['custom_field']['address']) ? $this->request->post['custom_field']['address'] : array();

			if ($country_info) {
				$this->session->data['shipping_address']['country'] = $country_info['name'];
				$this->session->data['shipping_address']['iso_code_2'] = $country_info['iso_code_2'];
				$this->session->data['shipping_address']['iso_code_3'] = $country_info['iso_code_3'];
				$this->session->data['shipping_address']['address_format'] = $country_info['address_format'];
			} else {
				$this->session->data['shipping_address']['country'] = '';
				$this->session->data['shipping_address']['iso_code_2'] = '';
				$this->session->data['shipping_address']['iso_code_3'] = '';
				$this->session->data['shipping_address']['address_format'] = '';
			}

			if ($zone_info) {
				$this->session->data['shipping_address']['zone'] = $zone_info['name'];
				$this->session->data['shipping_address']['zone_code'] = $zone_info['code'];
			} else {
				$this->session->data['shipping_address']['zone'] = '';
				$this->session->data['shipping_address']['zone_code'] = '';
			}

			$this->tax->setShippingAddress((int)$this->request->post['country_id'], (int)$this->request->post['zone_id']);
		}

		// Rebuild payment method list for the new address, then apply UI selection from save_guest POST (see checkout.twig collectGuestPayload).
		$this->renderControllerOutput('checkout/payment_method');

		if (isset($this->request->post['payment_method']) && is_string($this->request->post['payment_method']) && $this->request->post['payment_method'] !== '') {
			$pm = $this->request->post['payment_method'];

			if (isset($this->session->data['payment_methods'][$pm])) {
				$this->session->data['payment_method'] = $this->session->data['payment_methods'][$pm];
			}
		}

		$this->cpPreservePaymentMethodCode = $this->getCurrentPaymentMethodCode();

		unset($this->session->data['shipping_method']);
		unset($this->session->data['shipping_methods']);
		unset($this->session->data['payment_method']);
		unset($this->session->data['payment_methods']);

		if ($this->cart->hasShipping()) {
			$this->rebuildShippingMethodsSession();
			$this->tryRestoreShippingMethod($preserve_shipping_code);
		}
	}

	private function getCurrentPaymentMethodCode() {
		if (isset($this->session->data['payment_method']['code']) && is_string($this->session->data['payment_method']['code'])) {
			return $this->session->data['payment_method']['code'];
		}

		return '';
	}

	/**
	 * Re-select payment in session after checkout/payment_method rebuilt the list.
	 *
	 * @param string $preserve_code Value from payment_method['code'] before unset.
	 * @return bool True if session payment_method was set.
	 */
	private function tryRestorePaymentMethod($preserve_code) {
		if ($preserve_code === '' || !is_string($preserve_code)) {
			return false;
		}

		if (!isset($this->session->data['payment_methods']) || !is_array($this->session->data['payment_methods'])) {
			return false;
		}

		if (isset($this->session->data['payment_methods'][$preserve_code])) {
			$this->session->data['payment_method'] = $this->session->data['payment_methods'][$preserve_code];

			return true;
		}

		foreach ($this->session->data['payment_methods'] as $method) {
			if (isset($method['code']) && $method['code'] === $preserve_code) {
				$this->session->data['payment_method'] = $method;

				return true;
			}
		}

		return false;
	}

	private function rebuildShippingMethodsSession() {
		if (!isset($this->session->data['shipping_address'])) {
			return;
		}

		$method_data = array();

		$this->load->model('setting/extension');

		$results = $this->model_setting_extension->getExtensions('shipping');

		foreach ($results as $result) {
			if ($this->config->get('shipping_' . $result['code'] . '_status')) {
				$this->load->model('extension/shipping/' . $result['code']);

				$quote = $this->{'model_extension_shipping_' . $result['code']}->getQuote($this->session->data['shipping_address']);

				if ($quote) {
					$method_data[$result['code']] = array(
						'title'      => $quote['title'],
						'quote'      => $quote['quote'],
						'sort_order' => $quote['sort_order'],
						'error'      => $quote['error']
					);
				}
			}
		}

		$sort_order = array();

		foreach ($method_data as $key => $value) {
			$sort_order[$key] = $value['sort_order'];
		}

		array_multisort($sort_order, SORT_ASC, $method_data);

		$this->session->data['shipping_methods'] = $method_data;
	}

	private function tryRestoreShippingMethod($code) {
		if ($code === '' || !is_string($code)) {
			return;
		}

		if (!isset($this->session->data['shipping_methods']) || !is_array($this->session->data['shipping_methods'])) {
			return;
		}

		$parts = explode('.', $code, 2);

		if (!isset($parts[0]) || !isset($parts[1])) {
			return;
		}

		if (!isset($this->session->data['shipping_methods'][$parts[0]]['quote'][$parts[1]])) {
			return;
		}

		$this->session->data['shipping_method'] = $this->session->data['shipping_methods'][$parts[0]]['quote'][$parts[1]];
	}

	private function getJsonInput() {
		$raw = file_get_contents('php://input');
		$data = json_decode($raw, true);

		return is_array($data) ? $data : array();
	}

	private function splitExpressName($name) {
		$name = trim(preg_replace('/\s+/', ' ', (string)$name));

		if ($name === '') {
			return array('Guest', 'Customer');
		}

		$parts = explode(' ', $name, 2);

		return array($parts[0], isset($parts[1]) ? $parts[1] : '');
	}

	private function getCountryByIso2($iso_code_2) {
		$iso = strtoupper(trim((string)$iso_code_2));

		if ($iso === '') {
			return null;
		}

		// Common aliases
		if ($iso === 'UK') {
			$iso = 'GB';
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE iso_code_2 = '" . $this->db->escape($iso) . "' AND status = '1' LIMIT 1");

		if ($query && $query->num_rows) {
			return $query->row;
		}

		// Revolut occasionally sends ISO3 (e.g. CYP) or a country name.
		if (strlen($iso) === 3) {
			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE iso_code_3 = '" . $this->db->escape($iso) . "' AND status = '1' LIMIT 1");

			if ($query && $query->num_rows) {
				return $query->row;
			}
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE LCASE(name) = '" . $this->db->escape(strtolower($iso_code_2)) . "' AND status = '1' LIMIT 1");

		return ($query && $query->num_rows) ? $query->row : null;
	}

	private function resolveZoneId($country_id, $region) {
		$region = trim((string)$region);

		if (!$country_id || $region === '') {
			return 0;
		}

		$this->load->model('localisation/zone');

		$zones = $this->model_localisation_zone->getZonesByCountryId((int)$country_id);

		foreach ($zones as $zone) {
			if (strcasecmp((string)$zone['name'], $region) === 0 || strcasecmp((string)$zone['code'], $region) === 0) {
				return (int)$zone['zone_id'];
			}
		}

		return 0;
	}

	private function extractExpressWalletAddress($input) {
		if (isset($input['address']) && is_array($input['address'])) {
			return $input['address'];
		}

		if (isset($input['shippingAddress']) && is_array($input['shippingAddress'])) {
			return $input['shippingAddress'];
		}

		return is_array($input) ? $input : array();
	}

	private function seedExpressQuoteFromWalletAddress($input) {
		$address = $this->extractExpressWalletAddress($input);
		$country_code = '';

		if (isset($address['countryCode'])) {
			$country_code = $address['countryCode'];
		} elseif (isset($address['country_code'])) {
			$country_code = $address['country_code'];
		} elseif (isset($address['country'])) {
			$country_code = $address['country'];
		}

		$country_info = $this->getCountryByIso2($country_code);

		if (!$country_info) {
			return false;
		}

		$region = '';
		if (isset($address['region'])) {
			$region = (string)$address['region'];
		} elseif (isset($address['administrativeArea'])) {
			$region = (string)$address['administrativeArea'];
		}

		$zone_id = $this->resolveZoneId((int)$country_info['country_id'], $region);
		$postcode = '';

		if (isset($address['postcode'])) {
			$postcode = trim((string)$address['postcode']);
		} elseif (isset($address['postalCode'])) {
			$postcode = trim((string)$address['postalCode']);
		}

		$city = '';
		if (isset($address['city'])) {
			$city = trim((string)$address['city']);
		} elseif (isset($address['locality'])) {
			$city = trim((string)$address['locality']);
		}

		if ($city === '') {
			$city = 'City';
		}

		if ($postcode === '') {
			$postcode = '00000';
		}

		$address_row = array(
			'firstname'      => 'Guest',
			'lastname'       => 'Customer',
			'company'        => '',
			'address_1'      => 'Express checkout',
			'address_2'      => '',
			'postcode'       => $postcode,
			'city'           => $city,
			'country_id'     => (int)$country_info['country_id'],
			'zone_id'        => (int)$zone_id,
			'country'        => $country_info['name'],
			'iso_code_2'     => $country_info['iso_code_2'],
			'iso_code_3'     => $country_info['iso_code_3'],
			'address_format' => $country_info['address_format'],
			'zone'           => $region,
			'zone_code'      => '',
			'custom_field'   => array(),
		);

		$this->session->data['shipping_address'] = $address_row;
		$this->tax->setShippingAddress((int)$country_info['country_id'], (int)$zone_id);

		return true;
	}

	private function applyExpressWalletPayload($input, $allow_incomplete = false) {
		$address = $this->extractExpressWalletAddress($input);

		$country_code = '';

		if (isset($address['countryCode'])) {
			$country_code = $address['countryCode'];
		} elseif (isset($address['country_code'])) {
			$country_code = $address['country_code'];
		} elseif (isset($address['country'])) {
			$country_code = $address['country'];
		}

		$country_info = $this->getCountryByIso2($country_code);

		if (!$country_info) {
			return array('success' => false, 'error' => 'country');
		}

		$region = isset($address['region']) ? (string)$address['region'] : '';
		if ($region === '' && isset($address['administrativeArea'])) {
			$region = (string)$address['administrativeArea'];
		}
		$zone_id = $this->resolveZoneId((int)$country_info['country_id'], $region);

		$street_line_1 = isset($address['streetLine1']) ? trim((string)$address['streetLine1']) : '';
		if ($street_line_1 === '' && isset($address['addressLines'][0])) {
			$street_line_1 = trim((string)$address['addressLines'][0]);
		}
		$street_line_2 = isset($address['streetLine2']) ? trim((string)$address['streetLine2']) : '';
		if ($street_line_2 === '' && isset($address['addressLines'][1])) {
			$street_line_2 = trim((string)$address['addressLines'][1]);
		}
		$city = isset($address['city']) ? trim((string)$address['city']) : '';
		if ($city === '' && isset($address['locality'])) {
			$city = trim((string)$address['locality']);
		}
		$postcode = isset($address['postcode']) ? trim((string)$address['postcode']) : '';
		if ($postcode === '' && isset($address['postalCode'])) {
			$postcode = trim((string)$address['postalCode']);
		}

		if ($street_line_1 === '' || $city === '' || $postcode === '') {
			if ($allow_incomplete) {
				return array('success' => false, 'error' => 'address');
			}

			return array('success' => false, 'error' => 'address');
		}

		$name = '';
		if (isset($input['name'])) {
			$name = (string)$input['name'];
		} elseif (isset($input['contact']['name'])) {
			$name = (string)$input['contact']['name'];
		}

		list($firstname, $lastname) = $this->splitExpressName($name);

		$email = '';
		if (isset($input['email'])) {
			$email = trim((string)$input['email']);
		} elseif (isset($input['contact']['email'])) {
			$email = trim((string)$input['contact']['email']);
		}

		$telephone = '';
		if (isset($input['phone'])) {
			$telephone = trim((string)$input['phone']);
		} elseif (isset($input['contact']['phone'])) {
			$telephone = trim((string)$input['contact']['phone']);
		}

		if ($telephone === '') {
			$telephone = '0000000';
		}

		if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			if ($allow_incomplete) {
				return array('success' => false, 'error' => 'email');
			}

			return array('success' => false, 'error' => 'email');
		}

		$this->session->data['account'] = 'guest';
		$this->session->data['guest'] = array(
			'customer_group_id' => $this->config->get('config_customer_group_id'),
			'firstname'         => $firstname,
			'lastname'          => $lastname,
			'email'             => $email,
			'telephone'         => $telephone,
			'custom_field'      => array(),
		);

		$address_row = array(
			'firstname'    => $firstname,
			'lastname'     => $lastname,
			'company'      => '',
			'address_1'    => $street_line_1,
			'address_2'    => $street_line_2,
			'postcode'     => $postcode,
			'city'         => $city,
			'country_id'   => (int)$country_info['country_id'],
			'zone_id'        => $zone_id,
			'country'      => $country_info['name'],
			'iso_code_2'   => $country_info['iso_code_2'],
			'iso_code_3'   => $country_info['iso_code_3'],
			'address_format' => $country_info['address_format'],
			'zone'         => $region,
			'zone_code'    => '',
			'custom_field' => array(),
		);

		$this->session->data['payment_address'] = $address_row;
		$this->session->data['shipping_address'] = $address_row;

		$this->tax->setPaymentAddress((int)$country_info['country_id'], $zone_id);
		$this->tax->setShippingAddress((int)$country_info['country_id'], $zone_id);

		return array('success' => true);
	}

	private function getOrderTotalMinor() {
		$totals = array();
		$taxes = $this->cart->getTaxes();
		$total = 0;

		$total_data = array(
			'totals' => &$totals,
			'taxes'  => &$taxes,
			'total'  => &$total
		);

		$this->load->model('setting/extension');
		$results = $this->model_setting_extension->getExtensions('total');
		$sort_order = array();

		foreach ($results as $key => $value) {
			$sort_order[$key] = (int)$this->config->get('total_' . $value['code'] . '_sort_order');
		}

		array_multisort($sort_order, SORT_ASC, $results);

		foreach ($results as $result) {
			if ($this->config->get('total_' . $result['code'] . '_status')) {
				$this->load->model('extension/total/' . $result['code']);
				$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
			}
		}

		return $this->convertAmountToMinorUnits((float)$total, (string)$this->session->data['currency']);
	}

	private function convertAmountToMinorUnits($amount, $currency_code) {
		$formatter = new NumberFormatter('en_GB', NumberFormatter::CURRENCY);
		$formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currency_code);
		$fractional_length = (int)$formatter->getAttribute(NumberFormatter::FRACTION_DIGITS);
		$minor_unit_factor = pow(10, $fractional_length);

		return (int)round($amount * $minor_unit_factor);
	}

	private function buildExpressShippingOptions() {
		$options = array();

		if (empty($this->session->data['shipping_methods']) || !is_array($this->session->data['shipping_methods'])) {
			return $options;
		}

		foreach ($this->session->data['shipping_methods'] as $method) {
			if (empty($method['quote']) || !is_array($method['quote'])) {
				continue;
			}

			foreach ($method['quote'] as $quote) {
				if (empty($quote['code'])) {
					continue;
				}

				$cost = isset($quote['cost']) ? (float)$quote['cost'] : 0;
				$tax_class_id = isset($quote['tax_class_id']) ? (int)$quote['tax_class_id'] : 0;
				$cost_with_tax = $this->tax->calculate($cost, $tax_class_id, $this->config->get('config_tax'));

				$options[] = array(
					'id'          => (string)$quote['code'],
					'label'       => isset($quote['title']) ? (string)$quote['title'] : 'Shipping',
					'amount'      => $this->convertAmountToMinorUnits($cost_with_tax, (string)$this->session->data['currency']),
					'description' => isset($quote['delivery_days']) ? (string)$quote['delivery_days'] : '',
				);
			}
		}

		return $options;
	}

	private function getExpressShippingOptionsForCountryIso($country_iso) {
		$country_iso = strtoupper(trim((string)$country_iso));
		$country_id = 0;

		if ($country_iso !== '') {
			$country_info = $this->getCountryByIso2($country_iso);
			if ($country_info) {
				$country_id = (int)$country_info['country_id'];
			}
		}

		if (!$country_id && !empty($this->session->data['shipping_address']['country_id'])) {
			$country_id = (int)$this->session->data['shipping_address']['country_id'];
		}

		if (!$country_id && !empty($this->session->data['payment_address']['country_id'])) {
			$country_id = (int)$this->session->data['payment_address']['country_id'];
		}

		if (!$country_id) {
			$country_id = (int)$this->config->get('config_country');
		}

		if (!$country_id) {
			return array();
		}

		$this->seedExpressQuoteAddress($country_id);
		$this->rebuildShippingMethodsSession();

		return $this->buildExpressShippingOptions();
	}

	private function seedExpressQuoteAddress($country_id) {
		$this->load->model('localisation/country');
		$country_info = $this->model_localisation_country->getCountry((int)$country_id);

		if (!$country_info) {
			return;
		}

		$address_row = array(
			'firstname'      => 'Guest',
			'lastname'       => 'Customer',
			'company'        => '',
			'address_1'      => 'Express checkout',
			'address_2'      => '',
			'postcode'       => '00000',
			'city'           => 'City',
			'country_id'     => (int)$country_info['country_id'],
			'zone_id'        => 0,
			'country'        => $country_info['name'],
			'iso_code_2'     => $country_info['iso_code_2'],
			'iso_code_3'     => $country_info['iso_code_3'],
			'address_format' => $country_info['address_format'],
			'zone'           => '',
			'zone_code'      => '',
			'custom_field'   => array(),
		);

		$this->session->data['shipping_address'] = $address_row;
		$this->tax->setShippingAddress((int)$country_info['country_id'], 0);
	}

	private function setExpressShippingMethodById($option_id) {
		$option_id = (string)$option_id;

		if ($option_id === '' || empty($this->session->data['shipping_methods']) || !is_array($this->session->data['shipping_methods'])) {
			return false;
		}

		$parts = explode('.', $option_id, 2);

		if (!isset($parts[0]) || !isset($parts[1])) {
			return false;
		}

		if (!isset($this->session->data['shipping_methods'][$parts[0]]['quote'][$parts[1]])) {
			return false;
		}

		$this->session->data['shipping_method'] = $this->session->data['shipping_methods'][$parts[0]]['quote'][$parts[1]];

		return true;
	}

	private function ensureExpressPaymentMethod() {
		$this->renderPaymentMethodSection();

		if (isset($this->session->data['payment_methods']['revolut_card'])) {
			$this->session->data['payment_method'] = $this->session->data['payment_methods']['revolut_card'];

			return true;
		}

		return $this->maybeAutoSelectSinglePayment() && !empty($this->session->data['payment_method']);
	}

	private function createCheckoutOrderFromSession() {
		if (empty($this->session->data['payment_address'])) {
			return false;
		}

		if ($this->cart->hasShipping() && empty($this->session->data['shipping_method'])) {
			return false;
		}

		if (empty($this->session->data['payment_method'])) {
			return false;
		}

		$this->renderControllerOutput('checkout/confirm');

		return !empty($this->session->data['order_id']);
	}

	private function getCheckoutRedirect() {
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			return $this->url->link('checkout/cart');
		}

		if ($this->hasMinimumQuantityViolation()) {
			return $this->url->link('checkout/cart');
		}

		return '';
	}

	private function json($json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}

<?php
class ControllerExtensionModuleCyberpunksCheckoutFacade extends Controller {
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

		$data = array();
		$data['heading_title'] = $this->language->get('heading_title');
		$data['header'] = $this->load->controller('common/header');
		$data['footer'] = $this->load->controller('common/footer');

		// /payment should always create a fresh Revolut order for the current checkout session.
		unset($this->session->data['revolut_order_id']);

		// This will run checkout/confirm (creates the order) and output order summary + payment widget.
		$data['confirm_html'] = $this->renderControllerOutput('checkout/confirm');

		$this->response->setOutput($this->load->view('checkout/payment_review', $data));
	}

	private function hydrateSections(&$json) {
		$json['sections'] = array(
			'payment_method' => $this->renderPaymentMethodSection()
		);

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
		$output = $this->renderControllerOutput('checkout/payment_method');

		if ($this->maybeAutoSelectSinglePayment()) {
			$output = $this->renderControllerOutput('checkout/payment_method');
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

		$this->load->model('localisation/country');

		$country_info = $this->model_localisation_country->getCountry($this->request->post['country_id']);

		if ($country_info && $country_info['postcode_required'] && (utf8_strlen(trim($this->request->post['postcode'])) < 2 || utf8_strlen(trim($this->request->post['postcode'])) > 10)) {
			$json['error']['postcode'] = $this->language->get('error_postcode');
		}

		if ($this->request->post['country_id'] == '') {
			$json['error']['country'] = $this->language->get('error_country');
		}

		if (!isset($this->request->post['zone_id']) || $this->request->post['zone_id'] == '' || !is_numeric($this->request->post['zone_id'])) {
			$json['error']['zone'] = $this->language->get('error_zone');
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
		}

		unset($this->session->data['shipping_method']);
		unset($this->session->data['shipping_methods']);
		unset($this->session->data['payment_method']);
		unset($this->session->data['payment_methods']);

		if ($this->cart->hasShipping()) {
			$this->rebuildShippingMethodsSession();
			$this->tryRestoreShippingMethod($preserve_shipping_code);
		}
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

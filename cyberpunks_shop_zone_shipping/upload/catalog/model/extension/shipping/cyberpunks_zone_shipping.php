<?php
class ModelExtensionShippingCyberpunksZoneShipping extends Model {
	public function getQuote($address) {
		$this->load->language('extension/shipping/cyberpunks_zone_shipping');

		if (!$this->config->get('shipping_cyberpunks_zone_shipping_status')) {
			return array();
		}

		$zones = $this->config->get('shipping_cyberpunks_zone_shipping_zones');
		if (!is_array($zones) || !$zones) {
			return array();
		}

		$country_id = (int)($address['country_id'] ?? 0);
		$matched_zone = null;

		usort($zones, function($a, $b) {
			return (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0);
		});

		foreach ($zones as $zone) {
			if (empty($zone['status'])) {
				continue;
			}
			$countries = $zone['countries'] ?? array();
			if (!is_array($countries)) {
				$countries = array();
			}

			$allowed = empty($countries) || in_array((string)$country_id, array_map('strval', $countries), true);
			if ($allowed) {
				$matched_zone = $zone;
				break;
			}
		}

		if (!$matched_zone || empty($matched_zone['methods']) || !is_array($matched_zone['methods'])) {
			return array();
		}

		$quote_data = array();
		$zone_name = trim((string)($matched_zone['name'] ?? ''));
		$zone_key = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $zone_name ?: 'zone'));

		$methods = $matched_zone['methods'];
		usort($methods, function($a, $b) {
			return (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0);
		});

		foreach ($methods as $idx => $method) {
			if (empty($method['status'])) {
				continue;
			}
			$name = trim((string)($method['name'] ?? ''));
			$code = trim((string)($method['code'] ?? ''));
			$cost = (float)($method['cost'] ?? 0);
			$tax_class_id = (int)($method['tax_class_id'] ?? 0);

			if ($name === '' || $code === '') {
				continue;
			}

			$method_code = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $code));
			$key = $zone_key . '_' . $method_code . '_' . $idx;
			$title = $name;

			$quote_data[$key] = array(
				'code'         => 'cyberpunks_zone_shipping.' . $key,
				'title'        => $title,
				'cost'         => $cost,
				'tax_class_id' => $tax_class_id,
				'text'         => $this->currency->format($this->tax->calculate($cost, $tax_class_id, $this->config->get('config_tax')), $this->session->data['currency'])
			);
		}

		if (!$quote_data) {
			return array();
		}

		return array(
			'code'       => 'cyberpunks_zone_shipping',
			'title'      => $this->language->get('text_title'),
			'quote'      => $quote_data,
			'sort_order' => (int)$this->config->get('shipping_cyberpunks_zone_shipping_sort_order'),
			'error'      => false
		);
	}
}

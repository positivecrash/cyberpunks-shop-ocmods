<?php
class cyberpunks_zone_shipping_preview {	private $registry;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function getPreviewJsonFromConfig() {
		$config = $this->registry->get('config');

		return json_encode($this->buildPreview(
			(int)$config->get('shipping_cyberpunks_zone_shipping_status'),
			$config->get('shipping_cyberpunks_zone_shipping_zones')
		), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	public function writeThemeFileFromPayload(array $payload) {
		$status = isset($payload['shipping_cyberpunks_zone_shipping_status'])
			? (int)$payload['shipping_cyberpunks_zone_shipping_status']
			: 0;
		$zones = $payload['shipping_cyberpunks_zone_shipping_zones'] ?? array();

		if (!is_array($zones)) {
			$zones = array();
		}

		return $this->writeThemeFile($this->buildPreview($status, $zones));
	}

	public function writeThemeFile(array $preview) {
		$path = DIR_CATALOG . 'view/theme/cybershops/js/zone-shipping-preview.json';
		$dir = dirname($path);

		if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
			$this->logError('Cannot create directory: ' . $dir);
			return false;
		}

		$json = json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			$this->logError('Cannot encode zone shipping preview JSON');
			return false;
		}

		if (@file_put_contents($path, $json, LOCK_EX) === false) {
			$this->logError('Cannot write zone shipping preview file: ' . $path);
			return false;
		}

		return true;
	}

	public function buildPreview($status, $zones) {
		if (!(int)$status || !is_array($zones) || !$zones) {
			return array();
		}

		$this->registry->get('load')->model('localisation/country');
		$country_model = $this->registry->get('model_localisation_country');
		$all_countries = $country_model->getCountries();
		$by_id = array();

		foreach ($all_countries as $country) {
			$by_id[(int)$country['country_id']] = $country;
		}

		usort($zones, function($a, $b) {
			return (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0);
		});

		$preview = array();
		$assigned = array();

		foreach ($zones as $zone) {
			if (empty($zone['status'])) {
				continue;
			}

			$zone_methods = isset($zone['methods']) && is_array($zone['methods']) ? $zone['methods'] : array();
			usort($zone_methods, function($a, $b) {
				return (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0);
			});

			$methods_out = array();
			foreach ($zone_methods as $method) {
				if (empty($method['status'])) {
					continue;
				}

				$name = trim((string)($method['name'] ?? ''));
				$code = trim((string)($method['code'] ?? ''));
				if ($name === '' || $code === '') {
					continue;
				}

				$cost = (float)($method['cost'] ?? 0);
				$tax_class_id = (int)($method['tax_class_id'] ?? 0);
				$config = $this->registry->get('config');
				$currency = $this->registry->get('currency');
				$tax = $this->registry->get('tax');
				$session = $this->registry->get('session');
				$currency_code = isset($session->data['currency']) ? $session->data['currency'] : $config->get('config_currency');
				$price_text = $currency->format(
					$tax->calculate($cost, $tax_class_id, $config->get('config_tax')),
					$currency_code
				);

				$methods_out[] = array(
					'name' => $name,
					'code' => $code,
					'cost' => $cost,
					'text' => $price_text,
					'delivery_days' => trim((string)($method['delivery_days'] ?? ''))
				);
			}

			if (!$methods_out) {
				continue;
			}

			$countries = isset($zone['countries']) && is_array($zone['countries']) ? $zone['countries'] : array();
			if (!$countries) {
				$countries = array_keys($by_id);
			}

			foreach ($countries as $country_id) {
				$country_id = (int)$country_id;
				if (!$country_id || isset($assigned[$country_id]) || !isset($by_id[$country_id])) {
					continue;
				}

				$assigned[$country_id] = true;
				$preview[] = array(
					'country_id' => (string)$country_id,
					'iso_code_2' => $by_id[$country_id]['iso_code_2'],
					'name' => $by_id[$country_id]['name'],
					'methods' => $methods_out
				);
			}
		}

		return $preview;
	}

	private function logError($message) {
		if ($this->registry->get('log')) {
			$this->registry->get('log')->write('CyberpunksZoneShippingPreview: ' . $message);
		}
	}
}

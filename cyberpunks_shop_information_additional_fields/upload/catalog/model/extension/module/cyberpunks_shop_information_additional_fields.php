<?php
class ModelExtensionModuleCyberpunksShopInformationAdditionalFields extends Model {
	public function getFooterInfoLinks() {
		$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "information` LIKE 'show_in_footer_info_links'");

		if (!$query->num_rows) {
			return array();
		}

		$query = $this->db->query("SELECT i.information_id, id.title FROM " . DB_PREFIX . "information i LEFT JOIN " . DB_PREFIX . "information_description id ON (i.information_id = id.information_id) LEFT JOIN " . DB_PREFIX . "information_to_store i2s ON (i.information_id = i2s.information_id) WHERE id.language_id = '" . (int)$this->config->get('config_language_id') . "' AND i2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND i.status = '1' AND i.show_in_footer_info_links = '1' ORDER BY i.sort_order, LCASE(id.title) ASC");

		$links = array();

		foreach ($query->rows as $row) {
			$links[] = array(
				'title' => $row['title'],
				'href'  => $this->url->link('information/information', 'information_id=' . (int)$row['information_id'])
			);
		}

		return $links;
	}

	public function getShippingRatesOverview() {
		if (!$this->config->get('shipping_cyberpunks_zone_shipping_status')) {
			return array();
		}

		$zones = $this->config->get('shipping_cyberpunks_zone_shipping_zones');

		if (!is_array($zones) || !$zones) {
			return array();
		}

		$this->load->model('localisation/country');
		$this->load->language('extension/module/cyberpunks_shop_information_additional_fields');

		$countries = $this->model_localisation_country->getCountries();
		$country_names = array();

		foreach ($countries as $country) {
			$country_names[(int)$country['country_id']] = $country['name'];
		}

		usort($zones, function($a, $b) {
			return (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0);
		});

		$overview = array();

		foreach ($zones as $zone) {
			if (empty($zone['status'])) {
				continue;
			}

			$zone_countries = isset($zone['countries']) && is_array($zone['countries']) ? $zone['countries'] : array();
			$country_labels = array();

			if (!$zone_countries) {
				$country_labels[] = $this->language->get('text_all_countries');
			} else {
				foreach ($zone_countries as $country_id) {
					$country_id = (int)$country_id;

					if (isset($country_names[$country_id])) {
						$country_labels[] = $country_names[$country_id];
					}
				}

				sort($country_labels, SORT_NATURAL | SORT_FLAG_CASE);
			}

			$methods = array();
			$zone_methods = isset($zone['methods']) && is_array($zone['methods']) ? $zone['methods'] : array();

			usort($zone_methods, function($a, $b) {
				return (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0);
			});

			foreach ($zone_methods as $method) {
				if (empty($method['status'])) {
					continue;
				}

				$name = trim((string)($method['name'] ?? ''));

				if ($name === '') {
					continue;
				}

				$cost = (float)($method['cost'] ?? 0);
				$tax_class_id = (int)($method['tax_class_id'] ?? 0);

				$methods[] = array(
					'name'          => $name,
					'delivery_days' => trim((string)($method['delivery_days'] ?? '')),
					'price'         => $this->currency->format(
						$this->tax->calculate($cost, $tax_class_id, $this->config->get('config_tax')),
						$this->session->data['currency']
					)
				);
			}

			if (!$methods) {
				continue;
			}

			$overview[] = array(
				'name'           => trim((string)($zone['name'] ?? '')),
				'countries'      => $country_labels,
				'countries_text' => implode(', ', $country_labels),
				'methods'        => $methods
			);
		}

		return $overview;
	}
}

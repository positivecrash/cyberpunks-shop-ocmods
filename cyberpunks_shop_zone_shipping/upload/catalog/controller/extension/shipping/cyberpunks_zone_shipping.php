<?php
class ControllerExtensionShippingCyberpunksZoneShipping extends Controller {
	/**
	 * Product-page shipping preview (does not require cart contents).
	 * GET/POST country_id
	 */
	public function quote() {
		$json = array();

		$country_id = 0;
		if (isset($this->request->get['country_id'])) {
			$country_id = (int)$this->request->get['country_id'];
		} elseif (isset($this->request->post['country_id'])) {
			$country_id = (int)$this->request->post['country_id'];
		}

		if (!$country_id) {
			$json['error'] = 'country';
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		$this->load->model('localisation/country');
		$country_info = $this->model_localisation_country->getCountry($country_id);

		if (!$country_info) {
			$json['error'] = 'country';
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		$this->load->model('extension/shipping/cyberpunks_zone_shipping');

		$quote = $this->model_extension_shipping_cyberpunks_zone_shipping->getQuote(array(
			'country_id' => $country_id,
			'zone_id'    => 0,
			'postcode'   => ''
		));

		$methods = array();

		if (!empty($quote['quote']) && is_array($quote['quote'])) {
			foreach ($quote['quote'] as $row) {
				$methods[] = array(
					'code'           => isset($row['code']) ? $row['code'] : '',
					'title'          => isset($row['title']) ? $row['title'] : '',
					'cost'           => isset($row['cost']) ? (float)$row['cost'] : 0,
					'text'           => isset($row['text']) ? $row['text'] : '',
					'delivery_days'  => isset($row['delivery_days']) ? $row['delivery_days'] : ''
				);
			}
		}

		$json['country'] = array(
			'country_id' => (int)$country_info['country_id'],
			'name'       => $country_info['name'],
			'iso_code_2' => $country_info['iso_code_2']
		);
		$json['methods'] = $methods;

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}

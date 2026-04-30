<?php
class ControllerExtensionShippingCyberpunksZoneShipping extends Controller {
	private $error = array();
	private $backup_code = 'cyberpunks_zone_shipping_backup';
	private $backup_key = 'cyberpunks_zone_shipping_backup_payload';

	public function index() {
		$this->load->language('extension/shipping/cyberpunks_zone_shipping');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

		$this->restoreFromBackupIfNeeded();

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('shipping_cyberpunks_zone_shipping', $this->request->post);
			$this->saveBackupPayload($this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/shipping/cyberpunks_zone_shipping', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true));
		}

		$this->load->model('localisation/country');
		$this->load->model('localisation/tax_class');
		$data['countries'] = $this->model_localisation_country->getCountries();
		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();
		$data['store_currency_code'] = (string)$this->config->get('config_currency');

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/shipping/cyberpunks_zone_shipping', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/shipping/cyberpunks_zone_shipping', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true);
		$data['export'] = $this->url->link('extension/shipping/cyberpunks_zone_shipping/export', 'user_token=' . $this->session->data['user_token'], true);
		$data['import'] = $this->url->link('extension/shipping/cyberpunks_zone_shipping/import', 'user_token=' . $this->session->data['user_token'], true);

		$data['shipping_cyberpunks_zone_shipping_status'] = $this->request->post['shipping_cyberpunks_zone_shipping_status'] ?? $this->config->get('shipping_cyberpunks_zone_shipping_status');
		$data['shipping_cyberpunks_zone_shipping_sort_order'] = $this->request->post['shipping_cyberpunks_zone_shipping_sort_order'] ?? $this->config->get('shipping_cyberpunks_zone_shipping_sort_order');
		$data['shipping_cyberpunks_zone_shipping_status'] = (int)$data['shipping_cyberpunks_zone_shipping_status'];
		$data['shipping_cyberpunks_zone_shipping_sort_order'] = (int)$data['shipping_cyberpunks_zone_shipping_sort_order'];

		$zones = $this->request->post['shipping_cyberpunks_zone_shipping_zones'] ?? $this->config->get('shipping_cyberpunks_zone_shipping_zones');
		if (!is_array($zones)) {
			$zones = array();
		}
		foreach ($zones as &$zone) {
			if (!isset($zone['countries']) || !is_array($zone['countries'])) {
				$zone['countries'] = array();
			}
			if (!isset($zone['methods']) || !is_array($zone['methods'])) {
				$zone['methods'] = array();
			}
			foreach ($zone['methods'] as &$method) {
				if (!isset($method['delivery_days'])) {
					$method['delivery_days'] = '';
				}
			}
			unset($method);
		}
		unset($zone);
		$data['zones'] = $zones;

		$data['error_warning'] = $this->error['warning'] ?? '';
		$data['error_zone'] = $this->error['zone'] ?? array();
		$data['error_method'] = $this->error['method'] ?? array();

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/shipping/cyberpunks_zone_shipping', $data));
	}

	public function export() {
		$this->load->language('extension/shipping/cyberpunks_zone_shipping');
		$this->load->model('setting/setting');

		if (!$this->user->hasPermission('modify', 'extension/shipping/cyberpunks_zone_shipping')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('extension/shipping/cyberpunks_zone_shipping', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true));
			return;
		}

		$payload = array(
			'shipping_cyberpunks_zone_shipping_status' => (int)$this->config->get('shipping_cyberpunks_zone_shipping_status'),
			'shipping_cyberpunks_zone_shipping_sort_order' => (int)$this->config->get('shipping_cyberpunks_zone_shipping_sort_order'),
			'shipping_cyberpunks_zone_shipping_zones' => $this->config->get('shipping_cyberpunks_zone_shipping_zones')
		);

		$filename = 'cyberpunks-zone-shipping-' . date('Ymd-His') . '.json';
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->addHeader('Content-Disposition: attachment; filename="' . $filename . '"');
		$this->response->setOutput(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	public function import() {
		$this->load->language('extension/shipping/cyberpunks_zone_shipping');
		$this->load->model('setting/setting');

		if (!$this->user->hasPermission('modify', 'extension/shipping/cyberpunks_zone_shipping')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('extension/shipping/cyberpunks_zone_shipping', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true));
			return;
		}

		if ($this->request->server['REQUEST_METHOD'] !== 'POST' || empty($this->request->files['import_file']['tmp_name'])) {
			$this->session->data['error_warning'] = $this->language->get('error_import_file');
			$this->response->redirect($this->url->link('extension/shipping/cyberpunks_zone_shipping', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true));
			return;
		}

		$content = file_get_contents($this->request->files['import_file']['tmp_name']);
		$data = json_decode($content, true);

		if (!is_array($data)) {
			$this->session->data['error_warning'] = $this->language->get('error_import_format');
			$this->response->redirect($this->url->link('extension/shipping/cyberpunks_zone_shipping', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true));
			return;
		}

		$payload = array(
			'shipping_cyberpunks_zone_shipping_status' => isset($data['shipping_cyberpunks_zone_shipping_status']) ? (int)$data['shipping_cyberpunks_zone_shipping_status'] : 0,
			'shipping_cyberpunks_zone_shipping_sort_order' => isset($data['shipping_cyberpunks_zone_shipping_sort_order']) ? (int)$data['shipping_cyberpunks_zone_shipping_sort_order'] : 0,
			'shipping_cyberpunks_zone_shipping_zones' => isset($data['shipping_cyberpunks_zone_shipping_zones']) && is_array($data['shipping_cyberpunks_zone_shipping_zones']) ? $data['shipping_cyberpunks_zone_shipping_zones'] : array()
		);

		$this->model_setting_setting->editSetting('shipping_cyberpunks_zone_shipping', $payload);
		$this->saveBackupPayload($payload);
		$this->session->data['success'] = $this->language->get('text_import_success');

		$this->response->redirect($this->url->link('extension/shipping/cyberpunks_zone_shipping', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/shipping/cyberpunks_zone_shipping')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$zones = $this->request->post['shipping_cyberpunks_zone_shipping_zones'] ?? array();
		if (!is_array($zones)) {
			$zones = array();
		}

		foreach ($zones as $zone_row => $zone) {
			$zone_name = trim((string)($zone['name'] ?? ''));
			if ($zone_name === '') {
				$this->error['zone'][$zone_row]['name'] = $this->language->get('error_zone_name');
			}

			$methods = $zone['methods'] ?? array();
			if (!is_array($methods) || !$methods) {
				$this->error['zone'][$zone_row]['methods'] = $this->language->get('error_zone_methods');
				continue;
			}

			foreach ($methods as $method_row => $method) {
				$method_name = trim((string)($method['name'] ?? ''));
				$method_code = trim((string)($method['code'] ?? ''));
				$cost = $method['cost'] ?? '';

				if ($method_name === '') {
					$this->error['method'][$zone_row][$method_row]['name'] = $this->language->get('error_method_name');
				}
				if ($method_code === '') {
					$this->error['method'][$zone_row][$method_row]['code'] = $this->language->get('error_method_code');
				}
				if ($cost !== '' && !is_numeric($cost)) {
					$this->error['method'][$zone_row][$method_row]['cost'] = $this->language->get('error_method_cost');
				}
			}
		}

		return !$this->error;
	}

	private function saveBackupPayload($post_data) {
		$payload = array(
			'shipping_cyberpunks_zone_shipping_status' => $post_data['shipping_cyberpunks_zone_shipping_status'] ?? 0,
			'shipping_cyberpunks_zone_shipping_sort_order' => $post_data['shipping_cyberpunks_zone_shipping_sort_order'] ?? 0,
			'shipping_cyberpunks_zone_shipping_zones' => $post_data['shipping_cyberpunks_zone_shipping_zones'] ?? array()
		);
		$this->model_setting_setting->editSetting($this->backup_code, array(
			$this->backup_key => $payload
		));
	}

	private function restoreFromBackupIfNeeded() {
		$has_status = $this->config->has('shipping_cyberpunks_zone_shipping_status');
		$has_zones = $this->config->has('shipping_cyberpunks_zone_shipping_zones');

		if ($has_status || $has_zones) {
			return;
		}

		$backup = $this->model_setting_setting->getSetting($this->backup_code);
		$payload = $backup[$this->backup_key] ?? null;

		if (is_array($payload) && $payload) {
			$this->model_setting_setting->editSetting('shipping_cyberpunks_zone_shipping', $payload);
		}
	}
}

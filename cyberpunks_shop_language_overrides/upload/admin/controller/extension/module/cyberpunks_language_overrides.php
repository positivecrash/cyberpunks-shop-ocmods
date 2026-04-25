<?php
class ControllerExtensionModuleCyberpunksLanguageOverrides extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/cyberpunks_language_overrides');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$posted = isset($this->request->post['module_cyberpunks_language_overrides_map']) && is_array($this->request->post['module_cyberpunks_language_overrides_map'])
				? $this->request->post['module_cyberpunks_language_overrides_map']
				: array();

			$clean = array();
			foreach ($posted as $key => $value) {
				$key = trim((string)$key);
				$value = trim((string)$value);
				if ($key !== '' && $value !== '') {
					$clean[$key] = $value;
				}
			}

			$posted_total_labels = isset($this->request->post['module_cyberpunks_language_overrides_total_labels']) && is_array($this->request->post['module_cyberpunks_language_overrides_total_labels'])
				? $this->request->post['module_cyberpunks_language_overrides_total_labels']
				: array();

			$clean_total_labels = array();
			foreach ($posted_total_labels as $code => $label) {
				$code = trim((string)$code);
				$label = trim((string)$label);
				if ($code !== '' && $label !== '') {
					$clean_total_labels[$code] = $label;
				}
			}

			$this->model_setting_setting->editSetting('module_cyberpunks_language_overrides', array(
				'module_cyberpunks_language_overrides_status' => 1,
				'module_cyberpunks_language_overrides_map' => $clean,
				'module_cyberpunks_language_overrides_total_labels' => $clean_total_labels
			));

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/cyberpunks_language_overrides', 'user_token=' . $this->session->data['user_token'], true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
		unset($this->session->data['success']);

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/cyberpunks_language_overrides', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/cyberpunks_language_overrides', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		$overrides = $this->config->get('module_cyberpunks_language_overrides_map');
		if (!is_array($overrides)) {
			$overrides = array();
		}

		$strings = $this->readCheckoutCartStrings();
		usort($strings, function($a, $b) use ($overrides) {
			$a_over = isset($overrides[$a['key']]) && $overrides[$a['key']] !== '' ? 1 : 0;
			$b_over = isset($overrides[$b['key']]) && $overrides[$b['key']] !== '' ? 1 : 0;
			if ($a_over !== $b_over) {
				return $b_over - $a_over;
			}
			return strcmp($a['key'], $b['key']);
		});

		$data['strings'] = array();
		foreach ($strings as $row) {
			$data['strings'][] = array(
				'key' => $row['key'],
				'original' => $row['value'],
				'override' => isset($overrides[$row['key']]) ? $overrides[$row['key']] : '',
				'has_override' => isset($overrides[$row['key']]) && $overrides[$row['key']] !== ''
			);
		}

		$total_label_defaults = $this->getCartTotalLabelDefaults();
		$total_label_overrides = $this->config->get('module_cyberpunks_language_overrides_total_labels');
		if (!is_array($total_label_overrides)) {
			$total_label_overrides = array();
		}

		$data['total_labels'] = array();
		foreach ($total_label_defaults as $total_code => $total_default_label) {
			$data['total_labels'][] = array(
				'code' => $total_code,
				'default' => $total_default_label,
				'override' => isset($total_label_overrides[$total_code]) ? $total_label_overrides[$total_code] : ''
			);
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/cyberpunks_language_overrides', $data));
	}

	private function readCheckoutCartStrings() {
		$file = DIR_CATALOG . 'language/en-gb/checkout/cart.php';
		$result = array();

		if (!is_file($file)) {
			return $result;
		}

		$lines = file($file);
		if (!is_array($lines)) {
			return $result;
		}

		foreach ($lines as $line) {
			$matches = array();
			if (preg_match('/^\$_\[\'([^\']+)\'\]\s*=\s*\'(.*)\';\s*$/', trim($line), $matches) === 1) {
				$result[] = array(
					'key' => $matches[1],
					'value' => stripcslashes($matches[2])
				);
			}
		}

		return $result;
	}

	private function getCartTotalLabelDefaults() {
		return array(
			'sub_total' => 'Sub-Total',
			'shipping'  => 'Shipping',
			'coupon'    => 'Coupon',
			'voucher'   => 'Voucher',
			'reward'    => 'Reward Points',
			'tax'       => 'Tax',
			'total'     => 'Total'
		);
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/cyberpunks_language_overrides')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}

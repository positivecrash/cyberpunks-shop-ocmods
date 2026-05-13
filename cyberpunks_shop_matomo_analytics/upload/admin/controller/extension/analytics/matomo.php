<?php

class ControllerExtensionAnalyticsMatomo extends Controller {
	private $error = array();

	public function install() {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('analytics_matomo');
		$this->model_setting_event->addEvent('analytics_matomo', 'catalog/controller/checkout/success/before', 'extension/analytics/matomo/captureOrder', 1, 0);
		$this->model_setting_event->addEvent('analytics_matomo', 'catalog/controller/checkout/success/after', 'extension/analytics/matomo/injectOrderTracking', 1, 1);

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('analytics_matomo', array(
			'analytics_matomo_server'           => '',
			'analytics_matomo_site_id'         => '1',
			'analytics_matomo_status'         => '0',
			'analytics_matomo_ecommerce'      => '1',
			'analytics_matomo_sku_field'       => 'model',
			'analytics_matomo_disable_cookies' => '1',
			'analytics_matomo_respect_dnt'      => '0',
		), 0);
	}

	public function uninstall() {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('analytics_matomo');
	}

	public function index() {
		$this->load->language('extension/analytics/matomo');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('analytics_matomo', $this->request->post, $this->request->get['store_id']);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=analytics', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['server'])) {
			$data['error_server'] = $this->error['server'];
		} else {
			$data['error_server'] = '';
		}

		if (isset($this->error['site_id'])) {
			$data['error_site_id'] = $this->error['site_id'];
		} else {
			$data['error_site_id'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=analytics', true),
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/analytics/matomo', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $this->request->get['store_id'], true),
		);

		$data['action'] = $this->url->link('extension/analytics/matomo', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $this->request->get['store_id'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=analytics', true);

		$store_id = isset($this->request->get['store_id']) ? (int) $this->request->get['store_id'] : 0;

		$fields = array(
			'analytics_matomo_server',
			'analytics_matomo_site_id',
			'analytics_matomo_status',
			'analytics_matomo_ecommerce',
			'analytics_matomo_sku_field',
			'analytics_matomo_disable_cookies',
			'analytics_matomo_respect_dnt',
		);

		foreach ($fields as $key) {
			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} else {
				$data[$key] = $this->model_setting_setting->getSettingValue($key, $store_id);
			}
		}

		if ($data['analytics_matomo_sku_field'] === null || $data['analytics_matomo_sku_field'] === '') {
			$data['analytics_matomo_sku_field'] = 'model';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/analytics/matomo', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/analytics/matomo')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$server = isset($this->request->post['analytics_matomo_server']) ? trim((string) $this->request->post['analytics_matomo_server']) : '';

		if ($server === '') {
			$this->error['server'] = $this->language->get('error_server');
		} elseif (!filter_var($server, FILTER_VALIDATE_URL)) {
			$this->error['server'] = $this->language->get('error_server_url');
		}

		$site_id = isset($this->request->post['analytics_matomo_site_id']) ? (int) $this->request->post['analytics_matomo_site_id'] : 0;

		if ($site_id < 1) {
			$this->error['site_id'] = $this->language->get('error_site_id');
		}

		return !$this->error;
	}
}

<?php
class ControllerExtensionFeedCyberpunksShopMerchant extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/feed/cyberpunks_shop_merchant');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('feed_cyberpunks_shop_merchant', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/feed/cyberpunks_shop_merchant', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/feed/cyberpunks_shop_merchant', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true);
		$data['data_feed'] = HTTP_CATALOG . 'index.php?route=extension/feed/cyberpunks_shop_merchant';

		$fields = array(
			'feed_cyberpunks_shop_merchant_status',
			'feed_cyberpunks_shop_merchant_currency',
			'feed_cyberpunks_shop_merchant_google_category'
		);

		foreach ($fields as $field) {
			if (isset($this->request->post[$field])) {
				$data[$field] = $this->request->post[$field];
			} else {
				$data[$field] = $this->config->get($field);
			}
		}

		if ($data['feed_cyberpunks_shop_merchant_currency'] === null || $data['feed_cyberpunks_shop_merchant_currency'] === '') {
			$data['feed_cyberpunks_shop_merchant_currency'] = $this->config->get('config_currency');
		}

		$this->load->model('localisation/currency');
		$data['currencies'] = $this->model_localisation_currency->getCurrencies();

		foreach ($this->language->all() as $key => $value) {
			if (!isset($data[$key])) {
				$data[$key] = $value;
			}
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/feed/cyberpunks_shop_merchant', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/feed/cyberpunks_shop_merchant')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function install() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('feed_cyberpunks_shop_merchant', array(
			'feed_cyberpunks_shop_merchant_status' => 0,
			'feed_cyberpunks_shop_merchant_currency' => $this->config->get('config_currency'),
			'feed_cyberpunks_shop_merchant_google_category' => ''
		));
	}

	public function uninstall() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('feed_cyberpunks_shop_merchant');
	}
}

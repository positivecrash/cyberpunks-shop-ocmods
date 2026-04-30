<?php
class ControllerExtensionModuleCyberpunksCheckoutFacade extends Controller {
	private $error = array();

	public function install() {
		$this->load->model('user/user_group');

		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/cyberpunks_checkout_facade');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/cyberpunks_checkout_facade');

		$this->load->model('setting/setting');

		$this->model_setting_setting->editSetting('module_cyberpunks_checkout_facade', array(
			'module_cyberpunks_checkout_facade_status'               => 1,
			'module_cyberpunks_checkout_facade_auto_single_payment' => 1
		));
	}

	public function index() {
		$this->load->language('extension/module/cyberpunks_checkout_facade');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_cyberpunks_checkout_facade', array(
				'module_cyberpunks_checkout_facade_status'               => !empty($this->request->post['module_cyberpunks_checkout_facade_status']) ? 1 : 0,
				'module_cyberpunks_checkout_facade_auto_single_payment' => !empty($this->request->post['module_cyberpunks_checkout_facade_auto_single_payment']) ? 1 : 0
			));

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/cyberpunks_checkout_facade', 'user_token=' . $this->session->data['user_token'], true));
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
			'href' => $this->url->link('extension/module/cyberpunks_checkout_facade', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/cyberpunks_checkout_facade', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if (isset($this->request->post['module_cyberpunks_checkout_facade_status'])) {
			$data['module_cyberpunks_checkout_facade_status'] = (int)$this->request->post['module_cyberpunks_checkout_facade_status'];
		} else {
			$st = $this->config->get('module_cyberpunks_checkout_facade_status');
			$data['module_cyberpunks_checkout_facade_status'] = ($st === null) ? 1 : (int)$st;
		}

		if (isset($this->request->post['module_cyberpunks_checkout_facade_auto_single_payment'])) {
			$data['module_cyberpunks_checkout_facade_auto_single_payment'] = (int)$this->request->post['module_cyberpunks_checkout_facade_auto_single_payment'];
		} else {
			$auto = $this->config->get('module_cyberpunks_checkout_facade_auto_single_payment');
			$data['module_cyberpunks_checkout_facade_auto_single_payment'] = ($auto === null) ? 1 : (int)$auto;
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/cyberpunks_checkout_facade', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/cyberpunks_checkout_facade')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}

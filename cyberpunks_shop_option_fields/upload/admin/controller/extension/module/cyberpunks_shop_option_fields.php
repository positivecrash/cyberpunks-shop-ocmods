<?php
class ControllerExtensionModuleCyberpunksShopOptionFields extends Controller {
	private $error = array();

	public function install() {
		$this->load->model('user/user_group');
		$user_groups = $this->db->query("SELECT user_group_id FROM `" . DB_PREFIX . "user_group`");

		foreach ($user_groups->rows as $user_group) {
			$this->model_user_user_group->addPermission($user_group['user_group_id'], 'access', 'extension/module/cyberpunks_shop_option_fields');
			$this->model_user_user_group->addPermission($user_group['user_group_id'], 'modify', 'extension/module/cyberpunks_shop_option_fields');
		}

		$this->load->model('extension/module/cyberpunks_shop_option_fields');
		$this->model_extension_module_cyberpunks_shop_option_fields->install();
	}

	public function index() {
		$this->load->language('extension/module/cyberpunks_shop_option_fields');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/cyberpunks_shop_option_fields');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$fields = isset($this->request->post['cyberpunks_custom_fields']) && is_array($this->request->post['cyberpunks_custom_fields']) ? $this->request->post['cyberpunks_custom_fields'] : array();
			$this->model_extension_module_cyberpunks_shop_option_fields->saveCustomFields($fields);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/cyberpunks_shop_option_fields', 'user_token=' . $this->session->data['user_token'], true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

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
			'href' => $this->url->link('extension/module/cyberpunks_shop_option_fields', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/cyberpunks_shop_option_fields', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		$data['fields'] = $this->model_extension_module_cyberpunks_shop_option_fields->getCustomFields(false);
		if (isset($this->request->post['cyberpunks_custom_fields'])) {
			$data['fields'] = $this->request->post['cyberpunks_custom_fields'];
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['entry_key'] = $this->language->get('entry_key');
		$data['entry_label'] = $this->language->get('entry_label');
		$data['entry_type'] = $this->language->get('entry_type');
		$data['entry_scope'] = $this->language->get('entry_scope');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_text'] = $this->language->get('text_text');
		$data['text_textarea'] = $this->language->get('text_textarea');
		$data['text_boolean'] = $this->language->get('text_boolean');
		$data['text_scope_option'] = $this->language->get('text_scope_option');
		$data['text_scope_option_value'] = $this->language->get('text_scope_option_value');
		$data['button_add'] = $this->language->get('button_add');
		$data['button_remove'] = $this->language->get('button_remove');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/cyberpunks_shop_option_fields', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/cyberpunks_shop_option_fields')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}

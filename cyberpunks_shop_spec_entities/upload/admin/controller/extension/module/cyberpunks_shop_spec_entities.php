<?php
class ControllerExtensionModuleCyberpunksShopSpecEntities extends Controller {
	private $error = array();

	public function install() {
		$this->load->model('user/user_group');
		$user_groups = $this->db->query("SELECT user_group_id FROM `" . DB_PREFIX . "user_group`");

		foreach ($user_groups->rows as $user_group) {
			$this->model_user_user_group->addPermission($user_group['user_group_id'], 'access', 'extension/module/cyberpunks_shop_spec_entities');
			$this->model_user_user_group->addPermission($user_group['user_group_id'], 'modify', 'extension/module/cyberpunks_shop_spec_entities');
		}

		$this->load->model('extension/module/cyberpunks_shop_spec_entities');
		$this->model_extension_module_cyberpunks_shop_spec_entities->install();
	}

	public function index() {
		$this->load->language('extension/module/cyberpunks_shop_spec_entities');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/cyberpunks_shop_spec_entities');
		$this->getList();
	}

	public function add() {
		$this->load->language('extension/module/cyberpunks_shop_spec_entities');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/cyberpunks_shop_spec_entities');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_extension_module_cyberpunks_shop_spec_entities->addEntity($this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/cyberpunks_shop_spec_entities', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('extension/module/cyberpunks_shop_spec_entities');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/cyberpunks_shop_spec_entities');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_extension_module_cyberpunks_shop_spec_entities->editEntity($this->request->get['entity_id'], $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/cyberpunks_shop_spec_entities', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('extension/module/cyberpunks_shop_spec_entities');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/cyberpunks_shop_spec_entities');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $entity_id) {
				$this->model_extension_module_cyberpunks_shop_spec_entities->deleteEntity($entity_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/cyberpunks_shop_spec_entities', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getList();
	}

	protected function getList() {
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
			'href' => $this->url->link('extension/module/cyberpunks_shop_spec_entities', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['add'] = $this->url->link('extension/module/cyberpunks_shop_spec_entities/add', 'user_token=' . $this->session->data['user_token'], true);
		$data['delete'] = $this->url->link('extension/module/cyberpunks_shop_spec_entities/delete', 'user_token=' . $this->session->data['user_token'], true);

		$data['entities'] = $this->model_extension_module_cyberpunks_shop_spec_entities->getEntities();
		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
		unset($this->session->data['success']);

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_list'] = $this->language->get('text_list');
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['text_confirm'] = $this->language->get('text_confirm');
		$data['column_name'] = $this->language->get('column_name');
		$data['column_sort_order'] = $this->language->get('column_sort_order');
		$data['column_status'] = $this->language->get('column_status');
		$data['column_action'] = $this->language->get('column_action');
		$data['button_add'] = $this->language->get('button_add');
		$data['button_edit'] = $this->language->get('button_edit');
		$data['button_delete'] = $this->language->get('button_delete');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');

		foreach ($data['entities'] as $index => $entity) {
			$data['entities'][$index]['edit'] = $this->url->link('extension/module/cyberpunks_shop_spec_entities/edit', 'user_token=' . $this->session->data['user_token'] . '&entity_id=' . $entity['entity_id'], true);
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/cyberpunks_shop_spec_entities_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['entity_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_name'] = isset($this->error['name']) ? $this->error['name'] : array();

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
			'href' => $this->url->link('extension/module/cyberpunks_shop_spec_entities', 'user_token=' . $this->session->data['user_token'], true)
		);

		if (!isset($this->request->get['entity_id'])) {
			$data['action'] = $this->url->link('extension/module/cyberpunks_shop_spec_entities/add', 'user_token=' . $this->session->data['user_token'], true);
		} else {
			$data['action'] = $this->url->link('extension/module/cyberpunks_shop_spec_entities/edit', 'user_token=' . $this->session->data['user_token'] . '&entity_id=' . $this->request->get['entity_id'], true);
		}

		$data['cancel'] = $this->url->link('extension/module/cyberpunks_shop_spec_entities', 'user_token=' . $this->session->data['user_token'], true);

		if (isset($this->request->get['entity_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$entity_info = $this->model_extension_module_cyberpunks_shop_spec_entities->getEntity($this->request->get['entity_id']);
		} else {
			$entity_info = array();
		}

		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['summernote'] = $this->config->get('config_language');

		if (isset($this->request->post['spec_entity_description'])) {
			$data['spec_entity_description'] = $this->request->post['spec_entity_description'];
		} elseif (!empty($entity_info)) {
			$data['spec_entity_description'] = $this->model_extension_module_cyberpunks_shop_spec_entities->getEntityDescriptions($entity_info['entity_id']);
		} else {
			$data['spec_entity_description'] = array();
		}

		if (isset($this->request->post['sort_order'])) {
			$data['sort_order'] = $this->request->post['sort_order'];
		} elseif (!empty($entity_info)) {
			$data['sort_order'] = $entity_info['sort_order'];
		} else {
			$data['sort_order'] = 0;
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($entity_info)) {
			$data['status'] = $entity_info['status'];
		} else {
			$data['status'] = 1;
		}

		if (isset($this->request->post['spec_entity_item'])) {
			$data['spec_entity_items'] = $this->request->post['spec_entity_item'];
		} elseif (!empty($entity_info)) {
			$data['spec_entity_items'] = $this->model_extension_module_cyberpunks_shop_spec_entities->getEntityItems($entity_info['entity_id']);
		} else {
			$data['spec_entity_items'] = array();
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['tab_general'] = $this->language->get('tab_general');
		$data['tab_items'] = $this->language->get('tab_items');
		$data['entry_name'] = $this->language->get('entry_name');
		$data['entry_description'] = $this->language->get('entry_description');
		$data['entry_item_name'] = $this->language->get('entry_item_name');
		$data['entry_item_description'] = $this->language->get('entry_item_description');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['button_add'] = $this->language->get('button_add');
		$data['button_remove'] = $this->language->get('button_remove');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/cyberpunks_shop_spec_entities_form', $data));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'extension/module/cyberpunks_shop_spec_entities')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$default_language_id = (int)$this->config->get('config_language_id');
		$name = isset($this->request->post['spec_entity_description'][$default_language_id]['name']) ? trim($this->request->post['spec_entity_description'][$default_language_id]['name']) : '';

		if (utf8_strlen($name) < 1 || utf8_strlen($name) > 255) {
			$this->error['name'][$default_language_id] = $this->language->get('error_name');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'extension/module/cyberpunks_shop_spec_entities')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}

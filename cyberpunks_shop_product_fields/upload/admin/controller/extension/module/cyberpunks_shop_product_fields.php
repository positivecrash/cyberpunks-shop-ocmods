<?php
class ControllerExtensionModuleCyberpunksShopProductFields extends Controller {
	private $error = array();

	public function install() {
		$this->load->model('user/user_group');
		$user_groups = $this->db->query("SELECT user_group_id FROM `" . DB_PREFIX . "user_group`");

		foreach ($user_groups->rows as $user_group) {
			$this->model_user_user_group->addPermission($user_group['user_group_id'], 'access', 'extension/module/cyberpunks_shop_product_fields');
			$this->model_user_user_group->addPermission($user_group['user_group_id'], 'modify', 'extension/module/cyberpunks_shop_product_fields');
		}

		$this->load->model('extension/module/cyberpunks_shop_product_fields');
		$this->model_extension_module_cyberpunks_shop_product_fields->install();
	}

	public function index() {
		$this->load->language('extension/module/cyberpunks_shop_product_fields');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/cyberpunks_shop_product_fields');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$sections = isset($this->request->post['cyberpunks_product_field_sections']) && is_array($this->request->post['cyberpunks_product_field_sections']) ? $this->request->post['cyberpunks_product_field_sections'] : array();
			$fields = isset($this->request->post['cyberpunks_product_field_defs']) && is_array($this->request->post['cyberpunks_product_field_defs']) ? $this->request->post['cyberpunks_product_field_defs'] : array();
			$duplicate_keys = $this->model_extension_module_cyberpunks_shop_product_fields->saveSectionsAndFields($sections, $fields);

			if ($duplicate_keys) {
				$this->error['warning'] = sprintf($this->language->get('error_duplicate_key'), implode(', ', $duplicate_keys));
			} else {
				$this->session->data['success'] = $this->language->get('text_success');
				$this->response->redirect($this->url->link('extension/module/cyberpunks_shop_product_fields', 'user_token=' . $this->session->data['user_token'], true));
			}
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
			'href' => $this->url->link('extension/module/cyberpunks_shop_product_fields', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/cyberpunks_shop_product_fields', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if (isset($this->request->post['cyberpunks_product_field_sections']) || isset($this->request->post['cyberpunks_product_field_defs'])) {
			$data['layout'] = $this->buildLayoutFromPost(
				isset($this->request->post['cyberpunks_product_field_sections']) ? $this->request->post['cyberpunks_product_field_sections'] : array(),
				isset($this->request->post['cyberpunks_product_field_defs']) ? $this->request->post['cyberpunks_product_field_defs'] : array()
			);
		} else {
			$data['layout'] = $this->model_extension_module_cyberpunks_shop_product_fields->getAdminLayout();
		}

		$data['next_section_row'] = 0;
		foreach ($data['layout']['sections'] as $section) {
			$row = isset($section['section_row']) ? (int)$section['section_row'] : 0;
			if ($row >= $data['next_section_row']) {
				$data['next_section_row'] = $row + 1;
			}
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_ungrouped'] = $this->language->get('text_ungrouped');
		$data['entry_key'] = $this->language->get('entry_key');
		$data['entry_label'] = $this->language->get('entry_label');
		$data['entry_type'] = $this->language->get('entry_type');
		$data['entry_select_options'] = $this->language->get('entry_select_options');
		$data['entry_admin_hint'] = $this->language->get('entry_admin_hint');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_section_title'] = $this->language->get('entry_section_title');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_text'] = $this->language->get('text_text');
		$data['text_checkbox'] = $this->language->get('text_checkbox');
		$data['text_checkboxes'] = $this->language->get('text_checkboxes');
		$data['text_select'] = $this->language->get('text_select');
		$data['text_html'] = $this->language->get('text_html');
		$data['text_textarea'] = $this->language->get('text_textarea');
		$data['text_image'] = $this->language->get('text_image');
		$data['help_select_options'] = $this->language->get('help_select_options');
		$data['help_admin_hint'] = $this->language->get('help_admin_hint');
		$data['help_sections'] = $this->language->get('help_sections');
		$data['button_add'] = $this->language->get('button_add');
		$data['button_add_section'] = $this->language->get('button_add_section');
		$data['button_remove'] = $this->language->get('button_remove');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/cyberpunks_shop_product_fields', $data));
	}

	private function buildLayoutFromPost($sections, $fields) {
		$layout = array(
			'ungrouped_fields' => array(),
			'sections' => array()
		);

		if (!is_array($sections)) {
			$sections = array();
		}

		if (!is_array($fields)) {
			$fields = array();
		}

		foreach ($sections as $section_row => $section) {
			$layout['sections'][(int)$section_row] = array(
				'section_id' => isset($section['section_id']) ? (int)$section['section_id'] : 0,
				'title' => isset($section['title']) ? $section['title'] : '',
				'sort_order' => isset($section['sort_order']) ? (int)$section['sort_order'] : 0,
				'section_row' => (int)$section_row,
				'fields' => array()
			);
		}

		foreach ($fields as $field) {
			$section_row = isset($field['section_row']) ? (int)$field['section_row'] : -1;

			if ($section_row >= 0 && isset($layout['sections'][$section_row])) {
				$layout['sections'][$section_row]['fields'][] = $field;
			} else {
				$layout['ungrouped_fields'][] = $field;
			}
		}

		$layout['sections'] = array_values($layout['sections']);

		usort($layout['sections'], function ($a, $b) {
			if ($a['sort_order'] === $b['sort_order']) {
				return $a['section_row'] <=> $b['section_row'];
			}

			return $a['sort_order'] <=> $b['sort_order'];
		});

		return $layout;
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/cyberpunks_shop_product_fields')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}

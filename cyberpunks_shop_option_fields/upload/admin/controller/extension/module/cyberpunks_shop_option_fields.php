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

			$palettes = isset($this->request->post['cyberpunks_color_palettes']) && is_array($this->request->post['cyberpunks_color_palettes']) ? $this->request->post['cyberpunks_color_palettes'] : array();
			$this->model_extension_module_cyberpunks_shop_option_fields->savePalettes($palettes);

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/cyberpunks_shop_option_fields', 'user_token=' . $this->session->data['user_token'] . '&tab=' . $this->getActiveTab(), true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['active_tab'] = $this->getActiveTab();

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

		if (isset($this->request->post['cyberpunks_custom_fields'])) {
			$data['fields'] = $this->request->post['cyberpunks_custom_fields'];
		} else {
			$data['fields'] = $this->model_extension_module_cyberpunks_shop_option_fields->getCustomFields(false);
		}

		if (isset($this->request->post['cyberpunks_color_palettes'])) {
			$data['palettes'] = $this->request->post['cyberpunks_color_palettes'];
		} else {
			$data['palettes'] = $this->model_extension_module_cyberpunks_shop_option_fields->getAdminPalettesWithColors();
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['tab_fields'] = $this->language->get('tab_fields');
		$data['tab_palettes'] = $this->language->get('tab_palettes');
		$data['entry_key'] = $this->language->get('entry_key');
		$data['entry_label'] = $this->language->get('entry_label');
		$data['entry_type'] = $this->language->get('entry_type');
		$data['entry_scope'] = $this->language->get('entry_scope');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_name'] = $this->language->get('entry_name');
		$data['entry_swatch_color'] = $this->language->get('entry_swatch_color');
		$data['entry_model_color'] = $this->language->get('entry_model_color');
		$data['entry_in_stock'] = $this->language->get('entry_in_stock');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_text'] = $this->language->get('text_text');
		$data['text_textarea'] = $this->language->get('text_textarea');
		$data['text_boolean'] = $this->language->get('text_boolean');
		$data['text_scope_option'] = $this->language->get('text_scope_option');
		$data['text_scope_option_value'] = $this->language->get('text_scope_option_value');
		$data['text_scope_product'] = $this->language->get('text_scope_product');
		$data['text_palette'] = $this->language->get('text_palette');
		$data['help_palettes'] = $this->language->get('help_palettes');
		$data['button_add'] = $this->language->get('button_add');
		$data['button_add_field'] = $this->language->get('button_add_field');
		$data['button_add_palette'] = $this->language->get('button_add_palette');
		$data['button_add_color'] = $this->language->get('button_add_color');
		$data['button_remove'] = $this->language->get('button_remove');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		$data['palette_row'] = 0;
		$data['color_row'] = 0;

		foreach ($data['palettes'] as $palette) {
			$data['palette_row']++;
			if (!empty($palette['colors']) && is_array($palette['colors'])) {
				$data['color_row'] += count($palette['colors']);
			}
		}

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

	public function syncProducts() {
		$this->load->language('extension/module/cyberpunks_shop_option_fields');

		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/option') && !$this->user->hasPermission('modify', 'extension/module/cyberpunks_shop_option_fields')) {
			$json['error'] = $this->language->get('error_permission_sync_products');
		} else {
			$option_id = isset($this->request->get['option_id']) ? (int)$this->request->get['option_id'] : 0;

			if ($option_id <= 0) {
				$json['error'] = $this->language->get('error_sync_products_option');
			} else {
				$this->load->model('extension/module/cyberpunks_shop_option_fields');
				$this->model_extension_module_cyberpunks_shop_option_fields->install();

				$palette_result = $this->model_extension_module_cyberpunks_shop_option_fields->syncOptionValuesFromPalettes($option_id);
				$product_result = $this->model_extension_module_cyberpunks_shop_option_fields->syncOptionValuesToProducts($option_id);

				$json['success'] = sprintf(
					$this->language->get('text_sync_products_success'),
					(int)$palette_result['option_values_added'],
					(int)$palette_result['option_values_updated'],
					(int)$product_result['products'],
					(int)$product_result['added'],
					(int)$product_result['removed'],
					(int)$product_result['kept']
				);
				$json['result'] = array(
					'palette' => $palette_result,
					'products' => $product_result
				);
				$json['reload'] = true;
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function getActiveTab() {
		$tab = isset($this->request->get['tab']) ? (string)$this->request->get['tab'] : '';

		if (isset($this->request->post['active_tab'])) {
			$tab = (string)$this->request->post['active_tab'];
		}

		return ($tab === 'palettes') ? 'palettes' : 'fields';
	}
}

<?php
class ControllerExtensionModuleCyberpunksShopColorPalettes extends Controller {
	private $error = array();

	public function install() {
		$this->load->model('user/user_group');

		foreach ($this->db->query("SELECT user_group_id FROM `" . DB_PREFIX . "user_group`")->rows as $user_group) {
			$this->model_user_user_group->addPermission($user_group['user_group_id'], 'access', 'extension/module/cyberpunks_shop_color_palettes');
			$this->model_user_user_group->addPermission($user_group['user_group_id'], 'modify', 'extension/module/cyberpunks_shop_color_palettes');
		}

		$this->load->model('extension/module/cyberpunks_shop_color_palettes');
		$this->model_extension_module_cyberpunks_shop_color_palettes->install();
	}

	public function index() {
		$this->load->language('extension/module/cyberpunks_shop_color_palettes');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/cyberpunks_shop_color_palettes');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$palettes = isset($this->request->post['cyberpunks_color_palettes']) && is_array($this->request->post['cyberpunks_color_palettes']) ? $this->request->post['cyberpunks_color_palettes'] : array();
			$this->model_extension_module_cyberpunks_shop_color_palettes->savePalettes($palettes);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/cyberpunks_shop_color_palettes', 'user_token=' . $this->session->data['user_token'], true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

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
			'href' => $this->url->link('extension/module/cyberpunks_shop_color_palettes', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/cyberpunks_shop_color_palettes', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if (isset($this->request->post['cyberpunks_color_palettes'])) {
			$data['palettes'] = $this->request->post['cyberpunks_color_palettes'];
		} else {
			$data['palettes'] = $this->model_extension_module_cyberpunks_shop_color_palettes->getAdminPalettesWithColors();
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_palette'] = $this->language->get('text_palette');
		$data['text_color'] = $this->language->get('text_color');
		$data['entry_name'] = $this->language->get('entry_name');
		$data['entry_swatch_color'] = $this->language->get('entry_swatch_color');
		$data['entry_model_color'] = $this->language->get('entry_model_color');
		$data['entry_is_random'] = $this->language->get('entry_is_random');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['help_palettes'] = $this->language->get('help_palettes');
		$data['button_add'] = $this->language->get('button_add');
		$data['button_add_palette'] = $this->language->get('button_add_palette');
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

		$this->response->setOutput($this->load->view('extension/module/cyberpunks_shop_color_palettes', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/cyberpunks_shop_color_palettes')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}

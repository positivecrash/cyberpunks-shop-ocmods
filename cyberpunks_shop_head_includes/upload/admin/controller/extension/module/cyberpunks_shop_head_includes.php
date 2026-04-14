<?php
class ControllerExtensionModuleCyberpunksShopHeadIncludes extends Controller {
	private $error = array();

	public function install() {
		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/cyberpunks_shop_head_includes');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/cyberpunks_shop_head_includes');
	}

	public function index() {
		$this->load->language('extension/module/cyberpunks_shop_head_includes');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$rules = array();

			if (!empty($this->request->post['cyberpunks_shop_head_includes_rules']) && is_array($this->request->post['cyberpunks_shop_head_includes_rules'])) {
				foreach ($this->request->post['cyberpunks_shop_head_includes_rules'] as $rule) {
					$template = isset($rule['template']) ? trim((string)$rule['template']) : '';
					if ($template === '') {
						continue;
					}

					$rules[] = array(
						'template'    => $template,
						'js'          => isset($rule['js']) ? trim((string)$rule['js']) : '',
						'css'         => isset($rule['css']) ? trim((string)$rule['css']) : '',
						'js_exclude'  => isset($rule['js_exclude']) ? trim((string)$rule['js_exclude']) : '',
						'css_exclude' => isset($rule['css_exclude']) ? trim((string)$rule['css_exclude']) : '',
						'status'      => !empty($rule['status']) ? 1 : 0
					);
				}
			}

			$this->model_setting_setting->editSetting('cyberpunks_shop_head_includes', array(
				'cyberpunks_shop_head_includes_rules' => $rules
			));

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/cyberpunks_shop_head_includes', 'user_token=' . $this->session->data['user_token'], true));
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
			'href' => $this->url->link('extension/module/cyberpunks_shop_head_includes', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/cyberpunks_shop_head_includes', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		$data['rules'] = $this->config->get('cyberpunks_shop_head_includes_rules');

		if (isset($this->request->post['cyberpunks_shop_head_includes_rules'])) {
			$data['rules'] = $this->request->post['cyberpunks_shop_head_includes_rules'];
		}

		if (!is_array($data['rules'])) {
			$data['rules'] = array();
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_rule'] = $this->language->get('text_rule');
		$data['entry_rule_status'] = $this->language->get('entry_rule_status');
		$data['entry_template'] = $this->language->get('entry_template');
		$data['entry_js'] = $this->language->get('entry_js');
		$data['entry_css'] = $this->language->get('entry_css');
		$data['entry_js_exclude'] = $this->language->get('entry_js_exclude');
		$data['entry_css_exclude'] = $this->language->get('entry_css_exclude');
		$data['help_template'] = $this->language->get('help_template');
		$data['help_js'] = $this->language->get('help_js');
		$data['help_css'] = $this->language->get('help_css');
		$data['help_js_exclude'] = $this->language->get('help_js_exclude');
		$data['help_css_exclude'] = $this->language->get('help_css_exclude');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_add_rule'] = $this->language->get('button_add_rule');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/cyberpunks_shop_head_includes', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/cyberpunks_shop_head_includes')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}

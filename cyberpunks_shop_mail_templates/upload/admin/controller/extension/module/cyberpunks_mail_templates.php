<?php
class ControllerExtensionModuleCyberpunksMailTemplates extends Controller {
	private $error = array();

	public function install() {
		$this->load->model('user/user_group');

		foreach ($this->db->query("SELECT user_group_id FROM `" . DB_PREFIX . "user_group`")->rows as $user_group) {
			$this->model_user_user_group->addPermission($user_group['user_group_id'], 'access', 'extension/module/cyberpunks_mail_templates');
			$this->model_user_user_group->addPermission($user_group['user_group_id'], 'modify', 'extension/module/cyberpunks_mail_templates');
		}

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('module_cyberpunks_mail_templates', array(
			'module_cyberpunks_mail_templates_status'  => 0,
			'module_cyberpunks_mail_templates_layouts' => array(),
			'module_cyberpunks_mail_templates_layout'  => array(),
			'module_cyberpunks_mail_templates_subject' => array(),
			'module_cyberpunks_mail_templates_html'    => array()
		));
	}

	public function uninstall() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('module_cyberpunks_mail_templates');
	}

	public function index() {
		$this->load->language('extension/module/cyberpunks_mail_templates');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$subjects = isset($this->request->post['module_cyberpunks_mail_templates_subject']) && is_array($this->request->post['module_cyberpunks_mail_templates_subject'])
				? $this->request->post['module_cyberpunks_mail_templates_subject']
				: array();
			$html = isset($this->request->post['module_cyberpunks_mail_templates_html']) && is_array($this->request->post['module_cyberpunks_mail_templates_html'])
				? $this->request->post['module_cyberpunks_mail_templates_html']
				: array();
			$layout_map = isset($this->request->post['module_cyberpunks_mail_templates_layout']) && is_array($this->request->post['module_cyberpunks_mail_templates_layout'])
				? $this->request->post['module_cyberpunks_mail_templates_layout']
				: array();
			$layouts_post = isset($this->request->post['module_cyberpunks_mail_templates_layouts']) && is_array($this->request->post['module_cyberpunks_mail_templates_layouts'])
				? $this->request->post['module_cyberpunks_mail_templates_layouts']
				: array();

			$clean_layouts = $this->normalizeLayouts($layouts_post);
			$valid_ids = array();
			foreach ($clean_layouts as $layout) {
				$valid_ids[$layout['id']] = true;
			}

			$clean_subjects = array();
			$clean_html = array();
			$clean_layout_map = array();

			foreach ($this->getMailTypes() as $code => $meta) {
				$clean_subjects[$code] = isset($subjects[$code]) ? trim((string)$subjects[$code]) : '';
				$clean_html[$code] = isset($html[$code]) ? $this->decodeStoredHtml((string)$html[$code]) : '';
				$layout_id = isset($layout_map[$code]) ? trim((string)$layout_map[$code]) : '';
				$clean_layout_map[$code] = ($layout_id !== '' && isset($valid_ids[$layout_id])) ? $layout_id : '';
			}

			$this->model_setting_setting->editSetting('module_cyberpunks_mail_templates', array(
				'module_cyberpunks_mail_templates_status'  => !empty($this->request->post['module_cyberpunks_mail_templates_status']) ? 1 : 0,
				'module_cyberpunks_mail_templates_layouts' => $clean_layouts,
				'module_cyberpunks_mail_templates_layout'  => $clean_layout_map,
				'module_cyberpunks_mail_templates_subject' => $clean_subjects,
				'module_cyberpunks_mail_templates_html'    => $clean_html
			));

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/cyberpunks_mail_templates', 'user_token=' . $this->session->data['user_token'], true));
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
			'href' => $this->url->link('extension/module/cyberpunks_mail_templates', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/cyberpunks_mail_templates', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if (isset($this->request->post['module_cyberpunks_mail_templates_status'])) {
			$data['module_cyberpunks_mail_templates_status'] = (int)$this->request->post['module_cyberpunks_mail_templates_status'];
		} else {
			$st = $this->config->get('module_cyberpunks_mail_templates_status');
			$data['module_cyberpunks_mail_templates_status'] = ($st === null) ? 0 : (int)$st;
		}

		if (isset($this->request->post['module_cyberpunks_mail_templates_layouts'])) {
			$data['layouts'] = $this->normalizeLayouts($this->request->post['module_cyberpunks_mail_templates_layouts']);
		} else {
			$layouts = $this->config->get('module_cyberpunks_mail_templates_layouts');
			$data['layouts'] = $this->normalizeLayoutsForDisplay(is_array($layouts) ? $layouts : array());
		}

		$saved_html = $this->config->get('module_cyberpunks_mail_templates_html');
		if (!is_array($saved_html)) {
			$saved_html = array();
		}

		$saved_subjects = $this->config->get('module_cyberpunks_mail_templates_subject');
		if (!is_array($saved_subjects)) {
			$saved_subjects = array();
		}

		$saved_layout_map = $this->config->get('module_cyberpunks_mail_templates_layout');
		if (!is_array($saved_layout_map)) {
			$saved_layout_map = array();
		}

		if (isset($this->request->post['module_cyberpunks_mail_templates_html'])) {
			$saved_html = $this->request->post['module_cyberpunks_mail_templates_html'];
		}
		if (isset($this->request->post['module_cyberpunks_mail_templates_subject'])) {
			$saved_subjects = $this->request->post['module_cyberpunks_mail_templates_subject'];
		}
		if (isset($this->request->post['module_cyberpunks_mail_templates_layout'])) {
			$saved_layout_map = $this->request->post['module_cyberpunks_mail_templates_layout'];
		}

		$status_map = $this->getStoreStatusMap();
		$shortcodes = $this->getShortcodeHelp();
		$data['main_shortcodes'] = $this->getMainShortcodeHelp();

		$data['mail_types'] = array();
		foreach ($this->getMailTypes() as $code => $meta) {
			$mapped = isset($status_map[$code]) ? $status_map[$code] : null;

			$data['mail_types'][] = array(
				'code'            => $code,
				'title'           => $meta['title'],
				'help'            => $meta['help'],
				'default_subject' => $meta['default_subject'],
				'subject'         => isset($saved_subjects[$code]) ? $saved_subjects[$code] : '',
				'html'            => isset($saved_html[$code]) ? $this->decodeStoredHtml($saved_html[$code]) : '',
				'layout_id'       => isset($saved_layout_map[$code]) ? (string)$saved_layout_map[$code] : '',
				'shortcodes'      => $shortcodes,
				'status_id'       => $mapped ? (int)$mapped['order_status_id'] : 0,
				'status_name'     => $mapped ? $mapped['name'] : '',
				'status_found'    => $mapped ? true : false
			);
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_setup_hint'] = $this->language->get('text_setup_hint');
		$data['text_raw_html_hint'] = $this->language->get('text_raw_html_hint');
		$data['text_variables'] = $this->language->get('text_variables');
		$data['text_html'] = $this->language->get('text_html');
		$data['text_status_mapped'] = $this->language->get('text_status_mapped');
		$data['text_status_missing'] = $this->language->get('text_status_missing');
		$data['text_main_tab'] = $this->language->get('text_main_tab');
		$data['text_main_help'] = $this->language->get('text_main_help');
		$data['text_layout_none'] = $this->language->get('text_layout_none');
		$data['text_layout_item'] = $this->language->get('text_layout_item');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_subject'] = $this->language->get('entry_subject');
		$data['entry_layout'] = $this->language->get('entry_layout');
		$data['entry_layout_name'] = $this->language->get('entry_layout_name');
		$data['entry_layout_html'] = $this->language->get('entry_layout_html');
		$data['help_status'] = $this->language->get('help_status');
		$data['help_subject'] = $this->language->get('help_subject');
		$data['help_layout'] = $this->language->get('help_layout');
		$data['help_layout_html'] = $this->language->get('help_layout_html');
		$data['help_status_html'] = $this->language->get('help_status_html');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_add_layout'] = $this->language->get('button_add_layout');
		$data['button_remove'] = $this->language->get('button_remove');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/cyberpunks_mail_templates', $data));
	}

	private function decodeStoredHtml($html) {
		if (!is_file(DIR_SYSTEM . 'library/cyberpunks_mail_html.php')) {
			return html_entity_decode((string)$html, ENT_QUOTES, 'UTF-8');
		}

		require_once(DIR_SYSTEM . 'library/cyberpunks_mail_html.php');

		return CyberpunksMailHtml::decodeStored($html);
	}

	private function normalizeLayoutHtml($html) {
		if (!is_file(DIR_SYSTEM . 'library/cyberpunks_mail_html.php')) {
			return (string)$html;
		}

		require_once(DIR_SYSTEM . 'library/cyberpunks_mail_html.php');

		return CyberpunksMailHtml::normalizeDocument($html, true);
	}

	private function normalizeLayoutsForDisplay($layouts) {
		$result = array();

		if (!is_array($layouts)) {
			return $result;
		}

		foreach ($layouts as $row) {
			if (!is_array($row)) {
				continue;
			}

			$name = isset($row['name']) ? trim((string)$row['name']) : '';
			$html = isset($row['html']) ? $this->decodeStoredHtml((string)$row['html']) : '';
			$id = isset($row['id']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$row['id']) : '';

			if ($name === '' && trim($html) === '') {
				continue;
			}

			if ($id === '') {
				$id = 'tpl_legacy';
			}

			if ($name === '') {
				$name = 'Template';
			}

			$result[] = array(
				'id'   => $id,
				'name' => $name,
				'html' => $html
			);
		}

		return $result;
	}

	private function normalizeLayouts($layouts) {
		$result = array();

		if (!is_array($layouts)) {
			return $result;
		}

		foreach ($layouts as $row) {
			if (!is_array($row)) {
				continue;
			}

			$name = isset($row['name']) ? trim((string)$row['name']) : '';
			$html = isset($row['html']) ? $this->decodeStoredHtml((string)$row['html']) : '';
			$html = $this->normalizeLayoutHtml($html);
			$id = isset($row['id']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$row['id']) : '';

			if ($name === '' && trim($html) === '') {
				continue;
			}

			if ($id === '') {
				$id = 'tpl_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 10);
			}

			if ($name === '') {
				$name = 'Template';
			}

			$result[] = array(
				'id'   => $id,
				'name' => $name,
				'html' => $html
			);
		}

		return $result;
	}

	private function getMailTypes() {
		return array(
			'paid' => array(
				'title'           => 'Paid',
				'help'            => $this->language->get('help_status_paid'),
				'default_subject' => '{store_name} - Order {order_id} — Paid'
			),
			'pending' => array(
				'title'           => 'Pending',
				'help'            => $this->language->get('help_status_pending'),
				'default_subject' => '{store_name} - Order {order_id} — Pending'
			),
			'processing' => array(
				'title'           => 'Processing',
				'help'            => $this->language->get('help_status_processing'),
				'default_subject' => '{store_name} - Order {order_id} — Processing'
			),
			'shipped' => array(
				'title'           => 'Shipped',
				'help'            => $this->language->get('help_status_shipped'),
				'default_subject' => '{store_name} - Order {order_id} — Shipped'
			),
			'canceled' => array(
				'title'           => 'Canceled',
				'help'            => $this->language->get('help_status_canceled'),
				'default_subject' => '{store_name} - Order {order_id} — Canceled'
			),
			'complete' => array(
				'title'           => 'Complete',
				'help'            => $this->language->get('help_status_complete'),
				'default_subject' => '{store_name} - Order {order_id} — Complete'
			)
		);
	}

	private function getStoreStatusMap() {
		$language_id = (int)$this->config->get('config_language_id');
		$query = $this->db->query("SELECT order_status_id, name FROM `" . DB_PREFIX . "order_status` WHERE language_id = '" . (int)$language_id . "' ORDER BY name ASC");

		if (!$query->num_rows) {
			$query = $this->db->query("SELECT order_status_id, name FROM `" . DB_PREFIX . "order_status` ORDER BY language_id ASC, name ASC");
		}

		$map = array();

		foreach ($query->rows as $row) {
			$code = $this->statusNameToCode($row['name']);

			if ($code === '' || isset($map[$code])) {
				continue;
			}

			$map[$code] = array(
				'order_status_id' => (int)$row['order_status_id'],
				'name'            => $row['name']
			);
		}

		return $map;
	}

	private function statusNameToCode($name) {
		$key = strtolower(trim(html_entity_decode((string)$name, ENT_QUOTES, 'UTF-8')));
		$map = array(
			'paid'       => 'paid',
			'pending'    => 'pending',
			'processing' => 'processing',
			'shipped'    => 'shipped',
			'canceled'   => 'canceled',
			'cancelled'  => 'canceled',
			'complete'   => 'complete'
		);

		return isset($map[$key]) ? $map[$key] : '';
	}

	private function getShortcodeHelp() {
		return array(
			array('code' => '{order_id}', 'hint' => 'Order ID'),
			array('code' => '{order_status}', 'hint' => 'Status name'),
			array('code' => '{firstname}', 'hint' => 'Customer first name'),
			array('code' => '{lastname}', 'hint' => 'Customer last name'),
			array('code' => '{email}', 'hint' => 'Customer email'),
			array('code' => '{comment}', 'hint' => 'Add History → Comment'),
			array('code' => '{store_name}', 'hint' => 'Store name'),
			array('code' => '{logo}', 'hint' => 'Logo image URL'),
			array('code' => '{order_products}', 'hint' => 'Products block: image, name, options, qty'),
		);
	}

	private function getMainShortcodeHelp() {
		return array(
			array('code' => '{content}', 'hint' => 'Status HTML body (required placeholder)'),
			array('code' => '{order_id}', 'hint' => 'Order ID'),
			array('code' => '{order_status}', 'hint' => 'Status name'),
			array('code' => '{firstname}', 'hint' => 'Customer first name'),
			array('code' => '{lastname}', 'hint' => 'Customer last name'),
			array('code' => '{email}', 'hint' => 'Customer email'),
			array('code' => '{comment}', 'hint' => 'Add History → Comment'),
			array('code' => '{store_name}', 'hint' => 'Store name'),
			array('code' => '{logo}', 'hint' => 'Logo image URL'),
			array('code' => '{order_products}', 'hint' => 'Products block: image, name, options, qty'),
		);
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/cyberpunks_mail_templates')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}

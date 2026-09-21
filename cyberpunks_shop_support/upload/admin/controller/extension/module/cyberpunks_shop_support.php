<?php
class ControllerExtensionModuleCyberpunksShopSupport extends Controller {
	private $error = array();

	public function install() {
		$this->load->model('extension/module/cyberpunks_shop_support');
		$this->model_extension_module_cyberpunks_shop_support->ensureSchema();

		$this->load->model('user/user_group');
		foreach ($this->db->query("SELECT user_group_id FROM `" . DB_PREFIX . "user_group`")->rows as $user_group) {
			$this->model_user_user_group->addPermission($user_group['user_group_id'], 'access', 'extension/module/cyberpunks_shop_support');
			$this->model_user_user_group->addPermission($user_group['user_group_id'], 'modify', 'extension/module/cyberpunks_shop_support');
		}

		$this->load->model('setting/setting');
		$cron_key = bin2hex(random_bytes(16));
		$this->model_setting_setting->editSetting('module_cyberpunks_shop_support', array(
			'module_cyberpunks_shop_support_status' => 1,
			'module_cyberpunks_shop_support_email_signature' => '',
			'module_cyberpunks_shop_support_imap_status' => 0,
			'module_cyberpunks_shop_support_imap_host' => '',
			'module_cyberpunks_shop_support_imap_port' => 993,
			'module_cyberpunks_shop_support_imap_encryption' => 'ssl',
			'module_cyberpunks_shop_support_imap_user' => '',
			'module_cyberpunks_shop_support_imap_password' => '',
			'module_cyberpunks_shop_support_imap_folder' => 'INBOX',
			'module_cyberpunks_shop_support_imap_subject' => 'Cyberpunks.shop - Support request',
			'module_cyberpunks_shop_support_imap_require_from_match' => 1,
			'module_cyberpunks_shop_support_imap_mark_seen' => 1,
			'module_cyberpunks_shop_support_imap_max' => 25,
			'module_cyberpunks_shop_support_imap_cron_key' => $cron_key,
			'module_cyberpunks_shop_support_antispam_cooldown_hours' => 24,
			'module_cyberpunks_shop_support_antispam_daily_max' => 2,
			'module_cyberpunks_shop_support_antispam_append_open' => 1,
			'module_cyberpunks_shop_support_form_token' => 1,
			'module_cyberpunks_shop_support_form_min_seconds' => 3,
			'module_cyberpunks_shop_support_msg_success' => '',
			'module_cyberpunks_shop_support_msg_token' => '',
			'module_cyberpunks_shop_support_msg_too_fast' => '',
			'module_cyberpunks_shop_support_msg_blocked' => '',
			'module_cyberpunks_shop_support_msg_soft_limit' => ''
		));

		$this->ensureMenuEvent();
	}

	public function uninstall() {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('cyberpunks_shop_support');
	}

	/**
	 * Module settings (Enabled / Disabled). Extensions → Modules → Edit.
	 */
	public function index() {
		$this->load->language('extension/module/cyberpunks_shop_support');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');
		$this->load->model('extension/module/cyberpunks_shop_support');
		$this->model_extension_module_cyberpunks_shop_support->ensureSchema();
		$this->ensureMenuEvent();

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateModify()) {
			$signature = isset($this->request->post['module_cyberpunks_shop_support_email_signature'])
				? (string)$this->request->post['module_cyberpunks_shop_support_email_signature']
				: '';

			$password = isset($this->request->post['module_cyberpunks_shop_support_imap_password'])
				? (string)$this->request->post['module_cyberpunks_shop_support_imap_password']
				: '';
			if ($password === '') {
				$password = (string)$this->config->get('module_cyberpunks_shop_support_imap_password');
			}

			$cron_key = isset($this->request->post['module_cyberpunks_shop_support_imap_cron_key'])
				? trim((string)$this->request->post['module_cyberpunks_shop_support_imap_cron_key'])
				: '';
			if ($cron_key === '') {
				$cron_key = (string)$this->config->get('module_cyberpunks_shop_support_imap_cron_key');
			}
			if ($cron_key === '') {
				$cron_key = bin2hex(random_bytes(16));
			}

			$subject = isset($this->request->post['module_cyberpunks_shop_support_imap_subject'])
				? trim((string)$this->request->post['module_cyberpunks_shop_support_imap_subject'])
				: '';
			if ($subject === '') {
				$subject = 'Cyberpunks.shop - Support request';
			}

			$messages = array();
			foreach (array('success', 'token', 'too_fast', 'blocked', 'soft_limit') as $code) {
				$key = 'module_cyberpunks_shop_support_msg_' . $code;
				$messages[$key] = isset($this->request->post[$key]) ? trim((string)$this->request->post[$key]) : '';
			}

			$this->model_setting_setting->editSetting('module_cyberpunks_shop_support', array_merge(array(
				'module_cyberpunks_shop_support_status' => !empty($this->request->post['module_cyberpunks_shop_support_status']) ? 1 : 0,
				'module_cyberpunks_shop_support_email_signature' => $signature,
				'module_cyberpunks_shop_support_imap_status' => !empty($this->request->post['module_cyberpunks_shop_support_imap_status']) ? 1 : 0,
				'module_cyberpunks_shop_support_imap_host' => isset($this->request->post['module_cyberpunks_shop_support_imap_host']) ? trim((string)$this->request->post['module_cyberpunks_shop_support_imap_host']) : '',
				'module_cyberpunks_shop_support_imap_port' => isset($this->request->post['module_cyberpunks_shop_support_imap_port']) ? (int)$this->request->post['module_cyberpunks_shop_support_imap_port'] : 993,
				'module_cyberpunks_shop_support_imap_encryption' => isset($this->request->post['module_cyberpunks_shop_support_imap_encryption']) ? (string)$this->request->post['module_cyberpunks_shop_support_imap_encryption'] : 'ssl',
				'module_cyberpunks_shop_support_imap_user' => isset($this->request->post['module_cyberpunks_shop_support_imap_user']) ? trim((string)$this->request->post['module_cyberpunks_shop_support_imap_user']) : '',
				'module_cyberpunks_shop_support_imap_password' => $password,
				'module_cyberpunks_shop_support_imap_folder' => isset($this->request->post['module_cyberpunks_shop_support_imap_folder']) ? trim((string)$this->request->post['module_cyberpunks_shop_support_imap_folder']) : 'INBOX',
				'module_cyberpunks_shop_support_imap_subject' => $subject,
				'module_cyberpunks_shop_support_imap_require_from_match' => !empty($this->request->post['module_cyberpunks_shop_support_imap_require_from_match']) ? 1 : 0,
				'module_cyberpunks_shop_support_imap_mark_seen' => !empty($this->request->post['module_cyberpunks_shop_support_imap_mark_seen']) ? 1 : 0,
				'module_cyberpunks_shop_support_imap_max' => isset($this->request->post['module_cyberpunks_shop_support_imap_max']) ? max(1, min(100, (int)$this->request->post['module_cyberpunks_shop_support_imap_max'])) : 25,
				'module_cyberpunks_shop_support_imap_cron_key' => $cron_key,
				'module_cyberpunks_shop_support_antispam_cooldown_hours' => isset($this->request->post['module_cyberpunks_shop_support_antispam_cooldown_hours']) ? max(1, min(720, (int)$this->request->post['module_cyberpunks_shop_support_antispam_cooldown_hours'])) : 24,
				'module_cyberpunks_shop_support_antispam_daily_max' => isset($this->request->post['module_cyberpunks_shop_support_antispam_daily_max']) ? max(0, min(50, (int)$this->request->post['module_cyberpunks_shop_support_antispam_daily_max'])) : 2,
				'module_cyberpunks_shop_support_antispam_append_open' => !empty($this->request->post['module_cyberpunks_shop_support_antispam_append_open']) ? 1 : 0,
				'module_cyberpunks_shop_support_form_token' => !empty($this->request->post['module_cyberpunks_shop_support_form_token']) ? 1 : 0,
				'module_cyberpunks_shop_support_form_min_seconds' => isset($this->request->post['module_cyberpunks_shop_support_form_min_seconds']) ? max(0, min(120, (int)$this->request->post['module_cyberpunks_shop_support_form_min_seconds'])) : 3
			), $messages));

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/cyberpunks_shop_support', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$data = array();
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['entry_status'] = $this->language->get('entry_module_status');
		$data['entry_email_signature'] = $this->language->get('entry_email_signature');
		$data['help_email_signature'] = $this->language->get('help_email_signature');
		$data['entry_imap_status'] = $this->language->get('entry_imap_status');
		$data['entry_imap_host'] = $this->language->get('entry_imap_host');
		$data['entry_imap_port'] = $this->language->get('entry_imap_port');
		$data['entry_imap_encryption'] = $this->language->get('entry_imap_encryption');
		$data['entry_imap_user'] = $this->language->get('entry_imap_user');
		$data['entry_imap_password'] = $this->language->get('entry_imap_password');
		$data['entry_imap_folder'] = $this->language->get('entry_imap_folder');
		$data['entry_imap_subject'] = $this->language->get('entry_imap_subject');
		$data['entry_imap_require_from_match'] = $this->language->get('entry_imap_require_from_match');
		$data['entry_imap_mark_seen'] = $this->language->get('entry_imap_mark_seen');
		$data['entry_imap_max'] = $this->language->get('entry_imap_max');
		$data['entry_imap_cron_key'] = $this->language->get('entry_imap_cron_key');
		$data['help_imap'] = $this->language->get('help_imap');
		$data['help_imap_subject'] = $this->language->get('help_imap_subject');
		$data['help_imap_from'] = $this->language->get('help_imap_from');
		$data['help_imap_password'] = $this->language->get('help_imap_password');
		$data['help_imap_cron'] = $this->language->get('help_imap_cron');
		$data['text_imap_heading'] = $this->language->get('text_imap_heading');
		$data['text_antispam_heading'] = $this->language->get('text_antispam_heading');
		$data['entry_antispam_cooldown_hours'] = $this->language->get('entry_antispam_cooldown_hours');
		$data['entry_antispam_daily_max'] = $this->language->get('entry_antispam_daily_max');
		$data['entry_antispam_append_open'] = $this->language->get('entry_antispam_append_open');
		$data['entry_form_token'] = $this->language->get('entry_form_token');
		$data['entry_form_min_seconds'] = $this->language->get('entry_form_min_seconds');
		$data['help_antispam'] = $this->language->get('help_antispam');
		$data['help_antispam_cooldown_hours'] = $this->language->get('help_antispam_cooldown_hours');
		$data['help_antispam_daily_max'] = $this->language->get('help_antispam_daily_max');
		$data['help_antispam_append_open'] = $this->language->get('help_antispam_append_open');
		$data['help_form_token'] = $this->language->get('help_form_token');
		$data['help_form_min_seconds'] = $this->language->get('help_form_min_seconds');
		$data['text_form_messages_heading'] = $this->language->get('text_form_messages_heading');
		$data['entry_msg_success'] = $this->language->get('entry_msg_success');
		$data['entry_msg_token'] = $this->language->get('entry_msg_token');
		$data['entry_msg_too_fast'] = $this->language->get('entry_msg_too_fast');
		$data['entry_msg_blocked'] = $this->language->get('entry_msg_blocked');
		$data['entry_msg_soft_limit'] = $this->language->get('entry_msg_soft_limit');
		$data['help_form_messages'] = $this->language->get('help_form_messages');
		$data['help_msg_too_fast'] = $this->language->get('help_msg_too_fast');
		$data['button_blocklist'] = $this->language->get('button_blocklist');
		$data['blocklist'] = $this->url->link('extension/module/cyberpunks_shop_support/blocklist', 'user_token=' . $this->session->data['user_token'], true);
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');
		$data['button_save'] = $this->language->get('button_save_settings');
		$data['button_cancel'] = $this->language->get('button_cancel_extension');
		$data['button_tickets'] = $this->language->get('button_tickets');
		$data['button_imap_fetch'] = $this->language->get('button_imap_fetch');
		$data['help_status'] = $this->language->get('help_status');
		$data['imap_extension_ok'] = function_exists('imap_open');
		$data['text_imap_extension_missing'] = $this->language->get('text_imap_extension_missing');
		$data['fetch'] = $this->url->link('extension/module/cyberpunks_shop_support/fetchImap', 'user_token=' . $this->session->data['user_token'], true);

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} elseif (!empty($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		} else {
			$data['error_warning'] = '';
		}

		if (!empty($this->session->data['success'])) {
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
			'href' => $this->url->link('extension/module/cyberpunks_shop_support', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/cyberpunks_shop_support', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		$data['tickets'] = $this->url->link('extension/module/cyberpunks_shop_support/tickets', 'user_token=' . $this->session->data['user_token'], true);

		if (isset($this->request->post['module_cyberpunks_shop_support_status'])) {
			$data['module_cyberpunks_shop_support_status'] = (int)$this->request->post['module_cyberpunks_shop_support_status'];
		} else {
			$st = $this->config->get('module_cyberpunks_shop_support_status');
			$data['module_cyberpunks_shop_support_status'] = ($st === null || $st === '') ? 1 : (int)$st;
		}

		if (isset($this->request->post['module_cyberpunks_shop_support_email_signature'])) {
			$data['module_cyberpunks_shop_support_email_signature'] = (string)$this->request->post['module_cyberpunks_shop_support_email_signature'];
		} else {
			$stored_sig = $this->config->get('module_cyberpunks_shop_support_email_signature');
			$data['module_cyberpunks_shop_support_email_signature'] = ($stored_sig === null) ? '' : (string)$stored_sig;
		}

		$setting_fields = array(
			'module_cyberpunks_shop_support_imap_status' => 0,
			'module_cyberpunks_shop_support_imap_host' => '',
			'module_cyberpunks_shop_support_imap_port' => 993,
			'module_cyberpunks_shop_support_imap_encryption' => 'ssl',
			'module_cyberpunks_shop_support_imap_user' => '',
			'module_cyberpunks_shop_support_imap_folder' => 'INBOX',
			'module_cyberpunks_shop_support_imap_subject' => 'Cyberpunks.shop - Support request',
			'module_cyberpunks_shop_support_imap_require_from_match' => 1,
			'module_cyberpunks_shop_support_imap_mark_seen' => 1,
			'module_cyberpunks_shop_support_imap_max' => 25,
			'module_cyberpunks_shop_support_imap_cron_key' => '',
			'module_cyberpunks_shop_support_antispam_cooldown_hours' => 24,
			'module_cyberpunks_shop_support_antispam_daily_max' => 2,
			'module_cyberpunks_shop_support_antispam_append_open' => 1,
			'module_cyberpunks_shop_support_form_token' => 1,
			'module_cyberpunks_shop_support_form_min_seconds' => 3,
			'module_cyberpunks_shop_support_msg_success' => '',
			'module_cyberpunks_shop_support_msg_token' => '',
			'module_cyberpunks_shop_support_msg_too_fast' => '',
			'module_cyberpunks_shop_support_msg_blocked' => '',
			'module_cyberpunks_shop_support_msg_soft_limit' => ''
		);

		foreach ($setting_fields as $key => $default) {
			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} else {
				$stored = $this->config->get($key);
				if (strpos($key, '_msg_') !== false) {
					$data[$key] = ($stored === null) ? '' : (string)$stored;
				} else {
					$data[$key] = ($stored === null || $stored === '') ? $default : $stored;
				}
			}
		}

		if ($data['module_cyberpunks_shop_support_imap_cron_key'] === '') {
			$data['module_cyberpunks_shop_support_imap_cron_key'] = bin2hex(random_bytes(16));
		}

		$data['module_cyberpunks_shop_support_imap_password'] = '';
		$data['cron_url'] = HTTP_CATALOG . 'index.php?route=extension/module/cyberpunks_shop_support/cron&key=' . urlencode((string)$data['module_cyberpunks_shop_support_imap_cron_key']);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/cyberpunks_shop_support', $data));
	}

	public function fetchImap() {
		$this->load->language('extension/module/cyberpunks_shop_support');

		if (!$this->validateModify()) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('extension/module/cyberpunks_shop_support', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$this->load->model('extension/module/cyberpunks_shop_support');
		$this->model_extension_module_cyberpunks_shop_support->ensureSchema();

		if (!is_file(DIR_SYSTEM . 'library/cyberpunks_support_imap.php')) {
			$this->session->data['error_warning'] = 'IMAP library missing.';
			$this->response->redirect($this->url->link('extension/module/cyberpunks_shop_support', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		require_once(DIR_SYSTEM . 'library/cyberpunks_support_imap.php');
		$imap = new CyberpunksSupportImap($this->registry);
		$result = $imap->poll();

		if (!empty($result['ok'])) {
			$this->session->data['success'] = sprintf($this->language->get('text_imap_fetch_result'), (int)$result['imported'], (int)$result['skipped']);
		} else {
			$this->session->data['error_warning'] = !empty($result['message']) ? $result['message'] : $this->language->get('error_imap_fetch');
		}

		$this->response->redirect($this->url->link('extension/module/cyberpunks_shop_support', 'user_token=' . $this->session->data['user_token'], true));
	}

	/**
	 * Ticket list (left nav).
	 */
	public function tickets() {
		$data = $this->load->language('extension/module/cyberpunks_shop_support');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/cyberpunks_shop_support');
		$this->model_extension_module_cyberpunks_shop_support->ensureSchema();
		$this->ensureMenuEvent();
		$this->getList($data);
	}

	/**
	 * Inject top-level left menu item (no OCMOD dependency).
	 * Event: admin/view/common/column_left/before
	 */
	public function injectMenu(&$route, &$data) {
		if (empty($data['menus']) || !is_array($data['menus'])) {
			return;
		}

		if (!(int)$this->config->get('module_cyberpunks_shop_support_status')) {
			return;
		}

		if (!$this->user->hasPermission('access', 'extension/module/cyberpunks_shop_support')) {
			return;
		}

		foreach ($data['menus'] as $menu) {
			if (!empty($menu['id']) && $menu['id'] === 'menu-cyberpunks-support') {
				return;
			}
		}

		$item = array(
			'id'       => 'menu-cyberpunks-support',
			'icon'     => 'fa-life-ring',
			'name'     => 'Cyberpunks Shop Support',
			'href'     => $this->url->link('extension/module/cyberpunks_shop_support/tickets', 'user_token=' . $this->session->data['user_token'], true),
			'children' => array()
		);

		$inserted = false;
		$menus = array();
		foreach ($data['menus'] as $menu) {
			$menus[] = $menu;
			if (!$inserted && !empty($menu['id']) && $menu['id'] === 'menu-sale') {
				$menus[] = $item;
				$inserted = true;
			}
		}

		if (!$inserted) {
			$menus[] = $item;
		}

		$data['menus'] = $menus;
	}

	public function info() {
		$data = $this->load->language('extension/module/cyberpunks_shop_support');
		$this->load->model('extension/module/cyberpunks_shop_support');
		$this->model_extension_module_cyberpunks_shop_support->ensureSchema();

		$ticket_id = isset($this->request->get['ticket_id']) ? (int)$this->request->get['ticket_id'] : 0;
		$ticket = $this->model_extension_module_cyberpunks_shop_support->getTicket($ticket_id);

		if (!$ticket) {
			$this->session->data['error_warning'] = $this->language->get('error_not_found');
			$this->response->redirect($this->url->link('extension/module/cyberpunks_shop_support/tickets', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		if (empty($ticket['reason'])) {
			$messages_probe = $this->model_extension_module_cyberpunks_shop_support->getMessages($ticket_id);
			if (!empty($messages_probe[0]['message']) && preg_match('/^Reason:\s*(.+?)(?:\r?\n)+/i', $messages_probe[0]['message'], $rm)) {
				$ticket['reason'] = trim($rm[1]);
			}
		}

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateModify()) {
			if (!empty($this->request->post['status'])) {
				$this->model_extension_module_cyberpunks_shop_support->updateStatus($ticket_id, $this->request->post['status']);
			}

			$reply = isset($this->request->post['reply']) ? trim((string)$this->request->post['reply']) : '';
			$log_customer = !empty($this->request->post['log_customer_reply']);
			$log_internal = !empty($this->request->post['log_internal_note']);

			if ($reply !== '') {
				if ($log_internal) {
					$this->model_extension_module_cyberpunks_shop_support->addInternalNote($ticket_id, $reply, (int)$this->user->getId());
					$this->session->data['success'] = $this->language->get('text_success_internal');
				} elseif ($log_customer) {
					$this->model_extension_module_cyberpunks_shop_support->addCustomerMessage($ticket_id, $reply);
					$this->session->data['success'] = $this->language->get('text_success_logged');
				} else {
					$this->model_extension_module_cyberpunks_shop_support->addAdminMessage($ticket_id, $reply, (int)$this->user->getId());
					$this->sendReplyEmail($ticket, $reply);
					if (!empty($this->request->post['status_after_reply']) && $this->request->post['status_after_reply'] === 'waiting') {
						$this->model_extension_module_cyberpunks_shop_support->updateStatus($ticket_id, 'waiting');
					}
					$this->session->data['success'] = $this->language->get('text_success_reply');
				}
			} else {
				$this->session->data['success'] = $this->language->get('text_success_status');
			}

			$this->response->redirect($this->url->link('extension/module/cyberpunks_shop_support/info', 'user_token=' . $this->session->data['user_token'] . '&ticket_id=' . $ticket_id, true));
			return;
		}

		$this->document->setTitle($this->language->get('heading_title') . ' #' . $ticket['request_code']);

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/cyberpunks_shop_support/tickets', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $ticket['request_code'],
			'href' => $this->url->link('extension/module/cyberpunks_shop_support/info', 'user_token=' . $this->session->data['user_token'] . '&ticket_id=' . $ticket_id, true)
		);

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} elseif (!empty($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		} else {
			$data['error_warning'] = '';
		}

		if (!empty($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_ticket'] = sprintf($this->language->get('text_ticket'), $ticket['request_code']);
		$data['ticket'] = $ticket;
		$data['statuses'] = $this->model_extension_module_cyberpunks_shop_support->getStatuses();
		$data['status_labels'] = array();
		foreach ($data['statuses'] as $status) {
			$data['status_labels'][$status] = $this->language->get('text_status_' . $status);
		}

		$messages = $this->model_extension_module_cyberpunks_shop_support->getMessages($ticket_id);
		$data['messages'] = array();
		foreach ($messages as $message) {
			if ($message['author'] === 'admin') {
				$author_label = $this->language->get('text_author_admin');
			} elseif ($message['author'] === 'internal') {
				$author_label = $this->language->get('text_author_internal');
			} else {
				$author_label = $this->language->get('text_author_customer');
			}

			$data['messages'][] = array(
				'author' => $message['author'],
				'author_label' => $author_label,
				'message' => nl2br(htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8')),
				'date_added' => $message['date_added']
			);
		}

		$data['action'] = $this->url->link('extension/module/cyberpunks_shop_support/info', 'user_token=' . $this->session->data['user_token'] . '&ticket_id=' . $ticket_id, true);
		$data['cancel'] = $this->url->link('extension/module/cyberpunks_shop_support/tickets', 'user_token=' . $this->session->data['user_token'], true);
		$data['blacklist'] = $this->url->link('extension/module/cyberpunks_shop_support/blacklist', 'user_token=' . $this->session->data['user_token'] . '&ticket_id=' . $ticket_id, true);
		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/cyberpunks_shop_support_info', $data));
	}

	public function delete() {
		$this->load->language('extension/module/cyberpunks_shop_support');
		$this->load->model('extension/module/cyberpunks_shop_support');

		if (isset($this->request->post['selected']) && $this->validateModify()) {
			foreach ((array)$this->request->post['selected'] as $ticket_id) {
				$this->model_extension_module_cyberpunks_shop_support->deleteTicket((int)$ticket_id);
			}
			$this->session->data['success'] = $this->language->get('text_success_delete');
		}

		$this->response->redirect($this->url->link('extension/module/cyberpunks_shop_support/tickets', 'user_token=' . $this->session->data['user_token'], true));
	}

	/**
	 * Blacklist the email addresses of the tickets selected in the list,
	 * or a single ticket_id from the ticket info page.
	 */
	public function blacklist() {
		$this->load->language('extension/module/cyberpunks_shop_support');
		$this->load->model('extension/module/cyberpunks_shop_support');

		$selected = array();
		if (isset($this->request->post['selected'])) {
			$selected = (array)$this->request->post['selected'];
		} elseif (isset($this->request->get['ticket_id'])) {
			$selected = array((int)$this->request->get['ticket_id']);
		}

		$redirect = $this->url->link('extension/module/cyberpunks_shop_support/tickets', 'user_token=' . $this->session->data['user_token'], true);
		if (isset($this->request->get['ticket_id'])) {
			$redirect = $this->url->link('extension/module/cyberpunks_shop_support/info', 'user_token=' . $this->session->data['user_token'] . '&ticket_id=' . (int)$this->request->get['ticket_id'], true);
		}

		if ($selected && $this->validateModify()) {
			$result = $this->model_extension_module_cyberpunks_shop_support->blockEmailsFromTickets($selected, (int)$this->user->getId());
			$this->session->data['success'] = sprintf($this->language->get('text_success_blacklist'), (int)$result['added'], (int)$result['skipped']);
		} elseif (!empty($this->error['warning'])) {
			$this->session->data['error_warning'] = $this->error['warning'];
		}

		$this->response->redirect($redirect);
	}

	/**
	 * Blacklist management page (list + add).
	 */
	public function blocklist() {
		$data = $this->load->language('extension/module/cyberpunks_shop_support');
		$this->document->setTitle($this->language->get('heading_title_blocklist'));
		$this->load->model('extension/module/cyberpunks_shop_support');
		$this->model_extension_module_cyberpunks_shop_support->ensureSchema();
		$this->ensureMenuEvent();

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateModify()) {
			$emails = isset($this->request->post['emails']) ? (string)$this->request->post['emails'] : '';
			$note = isset($this->request->post['note']) ? (string)$this->request->post['note'] : '';

			$added = 0;
			$skipped = 0;
			foreach (preg_split('/[\s,;]+/', $emails) as $email) {
				$email = trim($email);
				if ($email === '') {
					continue;
				}
				if ($this->model_extension_module_cyberpunks_shop_support->addBlockedEmail($email, $note, (int)$this->user->getId())) {
					$added++;
				} else {
					$skipped++;
				}
			}

			$this->session->data['success'] = sprintf($this->language->get('text_success_blacklist'), $added, $skipped);
			$this->response->redirect($this->url->link('extension/module/cyberpunks_shop_support/blocklist', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$filter_email = isset($this->request->get['filter_email']) ? $this->request->get['filter_email'] : '';
		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;

		$url = '';
		if ($filter_email !== '') {
			$url .= '&filter_email=' . urlencode(html_entity_decode($filter_email, ENT_QUOTES, 'UTF-8'));
		}
		if ($page > 1) {
			$url .= '&page=' . $page;
		}

		$filter_data = array(
			'filter_email' => $filter_email,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$total = $this->model_extension_module_cyberpunks_shop_support->getTotalBlocklist($filter_data);

		$data['blocked'] = array();
		foreach ($this->model_extension_module_cyberpunks_shop_support->getBlocklist($filter_data) as $row) {
			$data['blocked'][] = array(
				'block_id' => (int)$row['block_id'],
				'email' => $row['email'],
				'note' => $row['note'],
				'date_added' => $row['date_added']
			);
		}

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/cyberpunks_shop_support/tickets', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title_blocklist'),
			'href' => $this->url->link('extension/module/cyberpunks_shop_support/blocklist', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} elseif (!empty($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		} else {
			$data['error_warning'] = '';
		}

		if (!empty($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['heading_title'] = $this->language->get('heading_title_blocklist');
		$data['user_token'] = $this->session->data['user_token'];
		$data['filter_email'] = $filter_email;
		$data['action'] = $this->url->link('extension/module/cyberpunks_shop_support/blocklist', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('extension/module/cyberpunks_shop_support/blocklistDelete', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['cancel'] = $this->url->link('extension/module/cyberpunks_shop_support/tickets', 'user_token=' . $this->session->data['user_token'], true);

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('extension/module/cyberpunks_shop_support/blocklist', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);
		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($total - $this->config->get('config_limit_admin'))) ? $total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $total, ceil($total / $this->config->get('config_limit_admin')));

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/cyberpunks_shop_support_blocklist', $data));
	}

	public function blocklistDelete() {
		$this->load->language('extension/module/cyberpunks_shop_support');
		$this->load->model('extension/module/cyberpunks_shop_support');

		if (isset($this->request->post['selected']) && $this->validateModify()) {
			foreach ((array)$this->request->post['selected'] as $block_id) {
				$this->model_extension_module_cyberpunks_shop_support->deleteBlockedEmail((int)$block_id);
			}
			$this->session->data['success'] = $this->language->get('text_success_blocklist_delete');
		} elseif (!empty($this->error['warning'])) {
			$this->session->data['error_warning'] = $this->error['warning'];
		}

		$this->response->redirect($this->url->link('extension/module/cyberpunks_shop_support/blocklist', 'user_token=' . $this->session->data['user_token'], true));
	}

	protected function getList($data = array()) {
		if (!$data) {
			$data = $this->load->language('extension/module/cyberpunks_shop_support');
		}

		$filter_request_code = isset($this->request->get['filter_request_code']) ? $this->request->get['filter_request_code'] : '';
		$filter_email = isset($this->request->get['filter_email']) ? $this->request->get['filter_email'] : '';
		$filter_status = isset($this->request->get['filter_status']) ? $this->request->get['filter_status'] : '';
		$sort = isset($this->request->get['sort']) ? $this->request->get['sort'] : 't.date_modified';
		$order = isset($this->request->get['order']) ? $this->request->get['order'] : 'DESC';
		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;

		$url = '';
		if ($filter_request_code !== '') {
			$url .= '&filter_request_code=' . urlencode(html_entity_decode($filter_request_code, ENT_QUOTES, 'UTF-8'));
		}
		if ($filter_email !== '') {
			$url .= '&filter_email=' . urlencode(html_entity_decode($filter_email, ENT_QUOTES, 'UTF-8'));
		}
		if ($filter_status !== '') {
			$url .= '&filter_status=' . urlencode($filter_status);
		}
		$url .= '&sort=' . $sort . '&order=' . $order;
		if ($page > 1) {
			$url .= '&page=' . $page;
		}

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/cyberpunks_shop_support/tickets', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$data['delete'] = $this->url->link('extension/module/cyberpunks_shop_support/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['blacklist'] = $this->url->link('extension/module/cyberpunks_shop_support/blacklist', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['blocklist'] = $this->url->link('extension/module/cyberpunks_shop_support/blocklist', 'user_token=' . $this->session->data['user_token'], true);
		$data['settings'] = $this->url->link('extension/module/cyberpunks_shop_support', 'user_token=' . $this->session->data['user_token'], true);

		$filter_data = array(
			'filter_request_code' => $filter_request_code,
			'filter_email' => $filter_email,
			'filter_status' => $filter_status,
			'sort' => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$ticket_total = $this->model_extension_module_cyberpunks_shop_support->getTotalTickets($filter_data);
		$results = $this->model_extension_module_cyberpunks_shop_support->getTickets($filter_data);

		$data['tickets'] = array();
		foreach ($results as $result) {
			$raw_preview = isset($result['first_message']) ? (string)$result['first_message'] : '';
			$reason = trim((string)$result['reason']);
			if ($reason === '' && preg_match('/^Reason:\s*(.+?)(?:\r?\n)+/i', $raw_preview, $rm)) {
				$reason = trim($rm[1]);
			}
			$preview = preg_replace('/^Reason:\s*.+?(?:\r?\n)+/i', '', $raw_preview, 1);
			$preview = trim((string)$preview);
			if (utf8_strlen($preview) > 80) {
				$preview = utf8_substr($preview, 0, 80) . '…';
			}
			$data['tickets'][] = array(
				'ticket_id' => $result['ticket_id'],
				'request_code' => $result['request_code'],
				'email' => $result['email'],
				'reason' => $reason,
				'status' => $result['status'],
				'status_label' => $this->language->get('text_status_' . $result['status']),
				'message_count' => (int)$result['message_count'],
				'preview' => $preview,
				'date_added' => $result['date_added'],
				'date_modified' => $result['date_modified'],
				'view' => $this->url->link('extension/module/cyberpunks_shop_support/info', 'user_token=' . $this->session->data['user_token'] . '&ticket_id=' . $result['ticket_id'] . $url, true)
			);
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['user_token'] = $this->session->data['user_token'];
		$data['statuses'] = $this->model_extension_module_cyberpunks_shop_support->getStatuses();
		$data['status_labels'] = array();
		foreach ($data['statuses'] as $status) {
			$data['status_labels'][$status] = $this->language->get('text_status_' . $status);
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} elseif (!empty($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		} else {
			$data['error_warning'] = '';
		}

		if (!empty($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['filter_request_code'] = $filter_request_code;
		$data['filter_email'] = $filter_email;
		$data['filter_status'] = $filter_status;
		$data['sort'] = $sort;
		$data['order'] = $order;

		$pagination = new Pagination();
		$pagination->total = $ticket_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('extension/module/cyberpunks_shop_support/tickets', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);
		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'), ($ticket_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($ticket_total - $this->config->get('config_limit_admin'))) ? $ticket_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $ticket_total, ceil($ticket_total / $this->config->get('config_limit_admin')));

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/cyberpunks_shop_support_list', $data));
	}

	private function ensureMenuEvent() {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('cyberpunks_shop_support');
		$this->model_setting_event->addEvent(
			'cyberpunks_shop_support',
			'admin/view/common/column_left/before',
			'extension/module/cyberpunks_shop_support/injectMenu',
			1,
			0
		);
	}

	private function getEmailSignature() {
		$signature = $this->config->get('module_cyberpunks_shop_support_email_signature');
		return ($signature === null) ? '' : trim((string)$signature);
	}

	private function sendReplyEmail($ticket, $reply_text) {
		$mail = new Mail($this->config->get('config_mail_engine'));
		$mail->parameter = $this->config->get('config_mail_parameter');
		$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
		$mail->smtp_username = $this->config->get('config_mail_smtp_username');
		$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
		$mail->smtp_port = $this->config->get('config_mail_smtp_port');
		$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

		$mail->setTo($ticket['email']);
		$mail->setFrom($this->config->get('config_email'));
		$mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
		$mail->setReplyTo($this->config->get('config_email'));
		$mail->setSubject(html_entity_decode(sprintf($this->language->get('email_subject_reply'), $ticket['request_code']), ENT_QUOTES, 'UTF-8'));

		$body = sprintf($this->language->get('email_text_reply_intro'), $ticket['request_code']) . "\n\n" . $reply_text;
		$signature = $this->getEmailSignature();
		if ($signature !== '') {
			$body .= "\n\n" . $signature;
		}
		$mail->setText($body);
		$mail->send();
	}

	protected function validateModify() {
		if (!$this->user->hasPermission('modify', 'extension/module/cyberpunks_shop_support')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		return !$this->error;
	}
}

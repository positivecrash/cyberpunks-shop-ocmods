<?php
class ControllerExtensionAdvertiseCyberpunksShopMarketing extends Controller {
	private $error = array();
	private $store_id = 0;

	public function __construct($registry) {
		parent::__construct($registry);

		$this->store_id = isset($this->request->get['store_id']) ? (int)$this->request->get['store_id'] : 0;
	}

	public function install() {
		$this->load->model('user/user_group');

		foreach ($this->db->query("SELECT user_group_id FROM `" . DB_PREFIX . "user_group`")->rows as $user_group) {
			$this->model_user_user_group->addPermission($user_group['user_group_id'], 'access', 'extension/advertise/cyberpunks_shop_marketing');
			$this->model_user_user_group->addPermission($user_group['user_group_id'], 'modify', 'extension/advertise/cyberpunks_shop_marketing');
		}

		$this->load->model('setting/extension');

		if (in_array('cyberpunks_shop_marketing', $this->model_setting_extension->getInstalled('module'), true)) {
			$this->model_setting_extension->uninstall('module', 'cyberpunks_shop_marketing');
		}

		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('cyberpunks_shop_marketing');
		$this->model_setting_event->deleteEventByCode('cyberpunks_shop_gtm');
		$this->model_setting_event->deleteEventByCode('analytics_matomo');
		$this->model_setting_event->addEvent('cyberpunks_shop_marketing', 'catalog/controller/checkout/success/before', 'extension/advertise/cyberpunks_shop_marketing/captureSuccess', 1, 0);
		$this->model_setting_event->addEvent('cyberpunks_shop_marketing', 'catalog/controller/checkout/success/after', 'extension/advertise/cyberpunks_shop_marketing/injectSuccess', 1, 1);
		$this->model_setting_event->addEvent('cyberpunks_shop_marketing', 'catalog/controller/product/product/before', 'extension/advertise/cyberpunks_shop_marketing/captureViewItem', 1, 0);
		$this->model_setting_event->addEvent('cyberpunks_shop_marketing', 'catalog/controller/product/product/after', 'extension/advertise/cyberpunks_shop_marketing/injectViewItem', 1, 1);

		$this->load->model('setting/setting');

		$settings = $this->defaultSettings();
		$this->migrateLegacySettings($settings);

		$this->model_setting_setting->editSetting('advertise_cyberpunks_shop_marketing', $settings, 0);

		$this->model_setting_setting->editSetting('analytics_matomo', array(
			'analytics_matomo_status' => '0',
		), 0);
	}

	public function uninstall() {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('cyberpunks_shop_marketing');
	}

	public function index() {
		$this->load->language('extension/advertise/cyberpunks_shop_marketing');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('advertise_cyberpunks_shop_marketing', $this->buildSettingsFromPost(), $this->store_id);

			if (!empty($this->request->post['advertise_cyberpunks_shop_marketing_matomo_status'])) {
				$this->model_setting_setting->editSetting('analytics_matomo', array(
					'analytics_matomo_status' => '0',
				), $this->store_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/advertise/cyberpunks_shop_marketing', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $this->store_id, true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_container_id'] = isset($this->error['container_id']) ? $this->error['container_id'] : '';
		$data['error_consent_expiry_days'] = isset($this->error['consent_expiry_days']) ? $this->error['consent_expiry_days'] : '';
		$data['error_matomo_server'] = isset($this->error['matomo_server']) ? $this->error['matomo_server'] : '';
		$data['error_matomo_site_id'] = isset($this->error['matomo_site_id']) ? $this->error['matomo_site_id'] : '';

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
			'href' => $this->url->link('extension/extension/advertise', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/advertise/cyberpunks_shop_marketing', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $this->store_id, true)
		);

		$data['action'] = $this->url->link('extension/advertise/cyberpunks_shop_marketing', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $this->store_id, true);
		$data['cancel'] = $this->url->link('extension/extension/advertise', 'user_token=' . $this->session->data['user_token'], true);

		foreach ($this->settingFields() as $field) {
			if (isset($this->request->post[$field])) {
				$data[$field] = $this->request->post[$field];
			} else {
				$value = $this->model_setting_setting->getSettingValue($field, $this->store_id);
				$data[$field] = ($value === null || $value === '') ? $this->legacySettingValue($field) : $value;
			}
		}

		if ($data['advertise_cyberpunks_shop_marketing_item_id_field'] === '') {
			$data['advertise_cyberpunks_shop_marketing_item_id_field'] = 'model';
		}

		$consent_defaults = array(
			'advertise_cyberpunks_shop_marketing_consent_status'        => 1,
			'advertise_cyberpunks_shop_marketing_consent_message'       => "We'd like to taste your cookies, if you are okay with that.",
			'advertise_cyberpunks_shop_marketing_consent_privacy_label' => 'Privacy Policy',
			'advertise_cyberpunks_shop_marketing_consent_privacy_url'   => '/privacy-policy',
			'advertise_cyberpunks_shop_marketing_consent_deny_label'    => 'No thanks',
			'advertise_cyberpunks_shop_marketing_consent_grant_label'   => 'OK',
			'advertise_cyberpunks_shop_marketing_consent_expiry_days'   => 30,
		);

		foreach ($consent_defaults as $field => $default) {
			if ($data[$field] === '' || $data[$field] === null) {
				$data[$field] = $default;
			}
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_model'] = $this->language->get('text_model');
		$data['text_sku'] = $this->language->get('text_sku');
		$data['text_gtm_section'] = $this->language->get('text_gtm_section');
		$data['text_consent_section'] = $this->language->get('text_consent_section');
		$data['text_matomo_section'] = $this->language->get('text_matomo_section');
		$data['text_shared_section'] = $this->language->get('text_shared_section');
		$data['text_setup_hint'] = $this->language->get('text_setup_hint');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_gtm_status'] = $this->language->get('entry_gtm_status');
		$data['entry_container_id'] = $this->language->get('entry_container_id');
		$data['entry_event_purchase'] = $this->language->get('entry_event_purchase');
		$data['entry_event_view_item'] = $this->language->get('entry_event_view_item');
		$data['entry_consent_status'] = $this->language->get('entry_consent_status');
		$data['entry_consent_message'] = $this->language->get('entry_consent_message');
		$data['entry_consent_privacy_label'] = $this->language->get('entry_consent_privacy_label');
		$data['entry_consent_privacy_url'] = $this->language->get('entry_consent_privacy_url');
		$data['entry_consent_deny_label'] = $this->language->get('entry_consent_deny_label');
		$data['entry_consent_grant_label'] = $this->language->get('entry_consent_grant_label');
		$data['entry_consent_expiry_days'] = $this->language->get('entry_consent_expiry_days');
		$data['entry_matomo_status'] = $this->language->get('entry_matomo_status');
		$data['entry_matomo_server'] = $this->language->get('entry_matomo_server');
		$data['entry_matomo_site_id'] = $this->language->get('entry_matomo_site_id');
		$data['entry_matomo_ecommerce'] = $this->language->get('entry_matomo_ecommerce');
		$data['entry_matomo_disable_cookies'] = $this->language->get('entry_matomo_disable_cookies');
		$data['entry_matomo_respect_dnt'] = $this->language->get('entry_matomo_respect_dnt');
		$data['entry_item_id_field'] = $this->language->get('entry_item_id_field');
		$data['help_container'] = $this->language->get('help_container');
		$data['help_events'] = $this->language->get('help_events');
		$data['help_no_gtag'] = $this->language->get('help_no_gtag');
		$data['help_consent'] = $this->language->get('help_consent');
		$data['help_consent_message'] = $this->language->get('help_consent_message');
		$data['help_consent_privacy'] = $this->language->get('help_consent_privacy');
		$data['help_consent_expiry_days'] = $this->language->get('help_consent_expiry_days');
		$data['help_matomo_server'] = $this->language->get('help_matomo_server');
		$data['help_matomo_site_id'] = $this->language->get('help_matomo_site_id');
		$data['help_matomo_ecommerce'] = $this->language->get('help_matomo_ecommerce');
		$data['help_matomo_disable_cookies'] = $this->language->get('help_matomo_disable_cookies');
		$data['help_matomo_dnt'] = $this->language->get('help_matomo_dnt');
		$data['help_item_id'] = $this->language->get('help_item_id');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/advertise/cyberpunks_shop_marketing', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/advertise/cyberpunks_shop_marketing')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!empty($this->request->post['advertise_cyberpunks_shop_marketing_status']) && !empty($this->request->post['advertise_cyberpunks_shop_marketing_gtm_status'])) {
			$container_id = isset($this->request->post['advertise_cyberpunks_shop_marketing_gtm_container_id']) ? trim((string)$this->request->post['advertise_cyberpunks_shop_marketing_gtm_container_id']) : '';

			if ($container_id === '' || !preg_match('/^GTM-[A-Z0-9]+$/i', $container_id)) {
				$this->error['container_id'] = $this->language->get('error_container_id');
			}
		}

		if (!empty($this->request->post['advertise_cyberpunks_shop_marketing_status'])
			&& !empty($this->request->post['advertise_cyberpunks_shop_marketing_gtm_status'])
			&& !empty($this->request->post['advertise_cyberpunks_shop_marketing_consent_status'])) {
			$expiry_days = isset($this->request->post['advertise_cyberpunks_shop_marketing_consent_expiry_days'])
				? (int)$this->request->post['advertise_cyberpunks_shop_marketing_consent_expiry_days']
				: 0;

			if ($expiry_days < 1 || $expiry_days > 3650) {
				$this->error['consent_expiry_days'] = $this->language->get('error_consent_expiry_days');
			}
		}

		if (!empty($this->request->post['advertise_cyberpunks_shop_marketing_status']) && !empty($this->request->post['advertise_cyberpunks_shop_marketing_matomo_status'])) {
			$server = isset($this->request->post['advertise_cyberpunks_shop_marketing_matomo_server']) ? trim((string)$this->request->post['advertise_cyberpunks_shop_marketing_matomo_server']) : '';

			if ($server === '') {
				$this->error['matomo_server'] = $this->language->get('error_matomo_server');
			} elseif (!filter_var($server, FILTER_VALIDATE_URL)) {
				$this->error['matomo_server'] = $this->language->get('error_matomo_server_url');
			}

			$site_id = isset($this->request->post['advertise_cyberpunks_shop_marketing_matomo_site_id']) ? (int)$this->request->post['advertise_cyberpunks_shop_marketing_matomo_site_id'] : 0;

			if ($site_id < 1) {
				$this->error['matomo_site_id'] = $this->language->get('error_matomo_site_id');
			}
		}

		return !$this->error;
	}

	private function settingFields() {
		return array(
			'advertise_cyberpunks_shop_marketing_status',
			'advertise_cyberpunks_shop_marketing_gtm_status',
			'advertise_cyberpunks_shop_marketing_gtm_container_id',
			'advertise_cyberpunks_shop_marketing_gtm_event_purchase',
			'advertise_cyberpunks_shop_marketing_gtm_event_view_item',
			'advertise_cyberpunks_shop_marketing_consent_status',
			'advertise_cyberpunks_shop_marketing_consent_message',
			'advertise_cyberpunks_shop_marketing_consent_privacy_label',
			'advertise_cyberpunks_shop_marketing_consent_privacy_url',
			'advertise_cyberpunks_shop_marketing_consent_deny_label',
			'advertise_cyberpunks_shop_marketing_consent_grant_label',
			'advertise_cyberpunks_shop_marketing_consent_expiry_days',
			'advertise_cyberpunks_shop_marketing_matomo_status',
			'advertise_cyberpunks_shop_marketing_matomo_server',
			'advertise_cyberpunks_shop_marketing_matomo_site_id',
			'advertise_cyberpunks_shop_marketing_matomo_ecommerce',
			'advertise_cyberpunks_shop_marketing_matomo_disable_cookies',
			'advertise_cyberpunks_shop_marketing_matomo_respect_dnt',
			'advertise_cyberpunks_shop_marketing_item_id_field',
		);
	}

	private function defaultSettings() {
		return array(
			'advertise_cyberpunks_shop_marketing_status'                    => 1,
			'advertise_cyberpunks_shop_marketing_gtm_status'                => 1,
			'advertise_cyberpunks_shop_marketing_gtm_container_id'          => 'GTM-K79KW7T2',
			'advertise_cyberpunks_shop_marketing_gtm_event_purchase'        => 1,
			'advertise_cyberpunks_shop_marketing_gtm_event_view_item'       => 1,
			'advertise_cyberpunks_shop_marketing_consent_status'           => 1,
			'advertise_cyberpunks_shop_marketing_consent_message'           => "We'd like to taste your cookies, if you are okay with that.",
			'advertise_cyberpunks_shop_marketing_consent_privacy_label'    => 'Privacy Policy',
			'advertise_cyberpunks_shop_marketing_consent_privacy_url'      => '/privacy-policy',
			'advertise_cyberpunks_shop_marketing_consent_deny_label'      => 'No thanks',
			'advertise_cyberpunks_shop_marketing_consent_grant_label'       => 'OK',
			'advertise_cyberpunks_shop_marketing_consent_expiry_days'      => 30,
			'advertise_cyberpunks_shop_marketing_matomo_status'             => 0,
			'advertise_cyberpunks_shop_marketing_matomo_server'             => '',
			'advertise_cyberpunks_shop_marketing_matomo_site_id'            => 1,
			'advertise_cyberpunks_shop_marketing_matomo_ecommerce'          => 1,
			'advertise_cyberpunks_shop_marketing_matomo_disable_cookies'    => 1,
			'advertise_cyberpunks_shop_marketing_matomo_respect_dnt'        => 0,
			'advertise_cyberpunks_shop_marketing_item_id_field'             => 'model',
		);
	}

	private function buildSettingsFromPost() {
		return array(
			'advertise_cyberpunks_shop_marketing_status'                    => !empty($this->request->post['advertise_cyberpunks_shop_marketing_status']) ? 1 : 0,
			'advertise_cyberpunks_shop_marketing_gtm_status'                => !empty($this->request->post['advertise_cyberpunks_shop_marketing_gtm_status']) ? 1 : 0,
			'advertise_cyberpunks_shop_marketing_gtm_container_id'          => isset($this->request->post['advertise_cyberpunks_shop_marketing_gtm_container_id']) ? trim((string)$this->request->post['advertise_cyberpunks_shop_marketing_gtm_container_id']) : '',
			'advertise_cyberpunks_shop_marketing_gtm_event_purchase'        => !empty($this->request->post['advertise_cyberpunks_shop_marketing_gtm_event_purchase']) ? 1 : 0,
			'advertise_cyberpunks_shop_marketing_gtm_event_view_item'       => !empty($this->request->post['advertise_cyberpunks_shop_marketing_gtm_event_view_item']) ? 1 : 0,
			'advertise_cyberpunks_shop_marketing_consent_status'            => !empty($this->request->post['advertise_cyberpunks_shop_marketing_consent_status']) ? 1 : 0,
			'advertise_cyberpunks_shop_marketing_consent_message'           => isset($this->request->post['advertise_cyberpunks_shop_marketing_consent_message']) ? trim((string)$this->request->post['advertise_cyberpunks_shop_marketing_consent_message']) : '',
			'advertise_cyberpunks_shop_marketing_consent_privacy_label'    => isset($this->request->post['advertise_cyberpunks_shop_marketing_consent_privacy_label']) ? trim((string)$this->request->post['advertise_cyberpunks_shop_marketing_consent_privacy_label']) : '',
			'advertise_cyberpunks_shop_marketing_consent_privacy_url'      => isset($this->request->post['advertise_cyberpunks_shop_marketing_consent_privacy_url']) ? trim((string)$this->request->post['advertise_cyberpunks_shop_marketing_consent_privacy_url']) : '',
			'advertise_cyberpunks_shop_marketing_consent_deny_label'       => isset($this->request->post['advertise_cyberpunks_shop_marketing_consent_deny_label']) ? trim((string)$this->request->post['advertise_cyberpunks_shop_marketing_consent_deny_label']) : '',
			'advertise_cyberpunks_shop_marketing_consent_grant_label'       => isset($this->request->post['advertise_cyberpunks_shop_marketing_consent_grant_label']) ? trim((string)$this->request->post['advertise_cyberpunks_shop_marketing_consent_grant_label']) : '',
			'advertise_cyberpunks_shop_marketing_consent_expiry_days'      => isset($this->request->post['advertise_cyberpunks_shop_marketing_consent_expiry_days']) ? (int)$this->request->post['advertise_cyberpunks_shop_marketing_consent_expiry_days'] : 30,
			'advertise_cyberpunks_shop_marketing_matomo_status'             => !empty($this->request->post['advertise_cyberpunks_shop_marketing_matomo_status']) ? 1 : 0,
			'advertise_cyberpunks_shop_marketing_matomo_server'             => isset($this->request->post['advertise_cyberpunks_shop_marketing_matomo_server']) ? trim((string)$this->request->post['advertise_cyberpunks_shop_marketing_matomo_server']) : '',
			'advertise_cyberpunks_shop_marketing_matomo_site_id'            => isset($this->request->post['advertise_cyberpunks_shop_marketing_matomo_site_id']) ? (int)$this->request->post['advertise_cyberpunks_shop_marketing_matomo_site_id'] : 0,
			'advertise_cyberpunks_shop_marketing_matomo_ecommerce'          => !empty($this->request->post['advertise_cyberpunks_shop_marketing_matomo_ecommerce']) ? 1 : 0,
			'advertise_cyberpunks_shop_marketing_matomo_disable_cookies'      => !empty($this->request->post['advertise_cyberpunks_shop_marketing_matomo_disable_cookies']) ? 1 : 0,
			'advertise_cyberpunks_shop_marketing_matomo_respect_dnt'          => !empty($this->request->post['advertise_cyberpunks_shop_marketing_matomo_respect_dnt']) ? 1 : 0,
			'advertise_cyberpunks_shop_marketing_item_id_field'               => (isset($this->request->post['advertise_cyberpunks_shop_marketing_item_id_field']) && $this->request->post['advertise_cyberpunks_shop_marketing_item_id_field'] === 'sku') ? 'sku' : 'model',
		);
	}

	private function legacySettingValue($field) {
		$legacy = str_replace('advertise_cyberpunks_shop_marketing_', 'module_cyberpunks_shop_marketing_', $field);
		$value = $this->config->get($legacy);

		if ($value !== null && $value !== '') {
			return $value;
		}

		return '';
	}

	private function migrateLegacySettings(array &$settings) {
		$module_map = array(
			'advertise_cyberpunks_shop_marketing_status'                 => 'module_cyberpunks_shop_marketing_status',
			'advertise_cyberpunks_shop_marketing_gtm_status'             => 'module_cyberpunks_shop_marketing_gtm_status',
			'advertise_cyberpunks_shop_marketing_gtm_container_id'       => 'module_cyberpunks_shop_marketing_gtm_container_id',
			'advertise_cyberpunks_shop_marketing_gtm_event_purchase'   => 'module_cyberpunks_shop_marketing_gtm_event_purchase',
			'advertise_cyberpunks_shop_marketing_gtm_event_view_item'  => 'module_cyberpunks_shop_marketing_gtm_event_view_item',
			'advertise_cyberpunks_shop_marketing_matomo_status'        => 'module_cyberpunks_shop_marketing_matomo_status',
			'advertise_cyberpunks_shop_marketing_matomo_server'        => 'module_cyberpunks_shop_marketing_matomo_server',
			'advertise_cyberpunks_shop_marketing_matomo_site_id'       => 'module_cyberpunks_shop_marketing_matomo_site_id',
			'advertise_cyberpunks_shop_marketing_matomo_ecommerce'     => 'module_cyberpunks_shop_marketing_matomo_ecommerce',
			'advertise_cyberpunks_shop_marketing_matomo_disable_cookies' => 'module_cyberpunks_shop_marketing_matomo_disable_cookies',
			'advertise_cyberpunks_shop_marketing_matomo_respect_dnt'   => 'module_cyberpunks_shop_marketing_matomo_respect_dnt',
			'advertise_cyberpunks_shop_marketing_item_id_field'        => 'module_cyberpunks_shop_marketing_item_id_field',
		);

		foreach ($module_map as $new_key => $old_key) {
			$value = $this->config->get($old_key);
			if ($value !== null && $value !== '') {
				$settings[$new_key] = $value;
			}
		}

		$gtm_container = $this->config->get('module_cyberpunks_shop_gtm_container_id');
		if ($gtm_container !== null && trim((string)$gtm_container) !== '') {
			$settings['advertise_cyberpunks_shop_marketing_gtm_container_id'] = trim((string)$gtm_container);
			$settings['advertise_cyberpunks_shop_marketing_gtm_status'] = (int)$this->config->get('module_cyberpunks_shop_gtm_status');
			$settings['advertise_cyberpunks_shop_marketing_gtm_event_purchase'] = (int)$this->config->get('module_cyberpunks_shop_gtm_event_purchase');
			$settings['advertise_cyberpunks_shop_marketing_gtm_event_view_item'] = (int)$this->config->get('module_cyberpunks_shop_gtm_event_view_item');
			if ($this->config->get('module_cyberpunks_shop_gtm_item_id_field') === 'sku') {
				$settings['advertise_cyberpunks_shop_marketing_item_id_field'] = 'sku';
			}
		}

		$matomo_server = $this->config->get('analytics_matomo_server');
		if ($matomo_server !== null && trim((string)$matomo_server) !== '') {
			$settings['advertise_cyberpunks_shop_marketing_matomo_server'] = trim((string)$matomo_server);
			$settings['advertise_cyberpunks_shop_marketing_matomo_site_id'] = (int)$this->config->get('analytics_matomo_site_id');
			$settings['advertise_cyberpunks_shop_marketing_matomo_status'] = (int)$this->config->get('analytics_matomo_status');
			$settings['advertise_cyberpunks_shop_marketing_matomo_ecommerce'] = (int)$this->config->get('analytics_matomo_ecommerce');
			$settings['advertise_cyberpunks_shop_marketing_matomo_disable_cookies'] = (int)$this->config->get('analytics_matomo_disable_cookies');
			$settings['advertise_cyberpunks_shop_marketing_matomo_respect_dnt'] = (int)$this->config->get('analytics_matomo_respect_dnt');
			if ($this->config->get('analytics_matomo_sku_field') === 'sku') {
				$settings['advertise_cyberpunks_shop_marketing_item_id_field'] = 'sku';
			}
		}
	}
}

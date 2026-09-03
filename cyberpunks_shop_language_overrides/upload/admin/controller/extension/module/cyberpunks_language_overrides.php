<?php
class ControllerExtensionModuleCyberpunksLanguageOverrides extends Controller {
	private $error = array();

	public function install() {
		$this->load->model('user/user_group');

		foreach ($this->db->query("SELECT user_group_id FROM `" . DB_PREFIX . "user_group`")->rows as $user_group) {
			$this->model_user_user_group->addPermission($user_group['user_group_id'], 'access', 'extension/module/cyberpunks_language_overrides');
			$this->model_user_user_group->addPermission($user_group['user_group_id'], 'modify', 'extension/module/cyberpunks_language_overrides');
		}

		$this->load->model('extension/module/cyberpunks_language_overrides');
		$this->model_extension_module_cyberpunks_language_overrides->install();

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('module_cyberpunks_language_overrides', array(
			'module_cyberpunks_language_overrides_status' => 1,
			'module_cyberpunks_language_overrides_map' => array(),
			'module_cyberpunks_language_overrides_total_labels' => array(),
			'module_cyberpunks_language_overrides_thousand_point' => ''
		));
	}

	public function index() {
		$this->load->language('extension/module/cyberpunks_language_overrides');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/cyberpunks_language_overrides');
		$this->load->model('setting/setting');

		$this->model_extension_module_cyberpunks_language_overrides->ensureSchema();

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate() && isset($this->request->post['save_settings'])) {
			$posted = isset($this->request->post['module_cyberpunks_language_overrides_map']) && is_array($this->request->post['module_cyberpunks_language_overrides_map'])
				? $this->request->post['module_cyberpunks_language_overrides_map']
				: array();

			$clean = array();
			foreach ($posted as $key => $value) {
				$key = trim((string)$key);
				$value = trim((string)$value);
				if ($key !== '' && $value !== '') {
					$clean[$key] = $value;
				}
			}

			$posted_total_labels = isset($this->request->post['module_cyberpunks_language_overrides_total_labels']) && is_array($this->request->post['module_cyberpunks_language_overrides_total_labels'])
				? $this->request->post['module_cyberpunks_language_overrides_total_labels']
				: array();

			$clean_total_labels = array();
			foreach ($posted_total_labels as $code => $label) {
				$code = trim((string)$code);
				$label = trim((string)$label);
				if ($code !== '' && $label !== '') {
					$clean_total_labels[$code] = $label;
				}
			}

			$thousand_point = isset($this->request->post['module_cyberpunks_language_overrides_thousand_point'])
				? trim((string)$this->request->post['module_cyberpunks_language_overrides_thousand_point'])
				: '';
			$allowed_thousand = array('space', 'comma', 'dot', 'nbsp', 'none');
			if (!in_array($thousand_point, $allowed_thousand, true)) {
				$thousand_point = '';
			}

			$this->model_setting_setting->editSetting('module_cyberpunks_language_overrides', array(
				'module_cyberpunks_language_overrides_status' => 1,
				'module_cyberpunks_language_overrides_map' => $clean,
				'module_cyberpunks_language_overrides_total_labels' => $clean_total_labels,
				'module_cyberpunks_language_overrides_thousand_point' => $thousand_point
			));

			$this->session->data['success'] = $this->language->get('text_success_settings');
			$this->response->redirect($this->url->link('extension/module/cyberpunks_language_overrides', 'user_token=' . $this->session->data['user_token'], true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
		unset($this->session->data['success']);

		$data['missing_files'] = $this->findMissingRuntimeFiles();

		$data['breadcrumbs'] = $this->breadcrumbs();
		$data['add'] = $this->url->link('extension/module/cyberpunks_language_overrides/add', 'user_token=' . $this->session->data['user_token'], true);
		$data['action_settings'] = $this->url->link('extension/module/cyberpunks_language_overrides', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		$languages = $this->filterTranslationLanguages($this->model_extension_module_cyberpunks_language_overrides->getLanguages());
		$data['languages'] = $languages;

		$strings = $this->model_extension_module_cyberpunks_language_overrides->getStrings();
		$data['strings'] = array();

		foreach ($strings as $row) {
			$missing = array();
			foreach ($languages as $language) {
				$lid = (int)$language['language_id'];
				if (empty($row['translations'][$lid])) {
					$missing[] = $language['code'];
				}
			}

			$data['strings'][] = array(
				'string_id' => $row['string_id'],
				'source_text' => $row['source_text'],
				'comment' => $row['comment'],
				'missing' => $missing,
				'edit' => $this->url->link('extension/module/cyberpunks_language_overrides/edit', 'user_token=' . $this->session->data['user_token'] . '&string_id=' . (int)$row['string_id'], true),
				'delete' => $this->url->link('extension/module/cyberpunks_language_overrides/delete', 'user_token=' . $this->session->data['user_token'] . '&string_id=' . (int)$row['string_id'], true)
			);
		}

		$total_label_defaults = $this->getCartTotalLabelDefaults();
		$total_label_overrides = $this->config->get('module_cyberpunks_language_overrides_total_labels');
		if (!is_array($total_label_overrides)) {
			$total_label_overrides = array();
		}

		$data['total_labels'] = array();
		foreach ($total_label_defaults as $total_code => $total_default_label) {
			$override = isset($total_label_overrides[$total_code]) ? $total_label_overrides[$total_code] : '';
			$data['total_labels'][] = array(
				'code' => $total_code,
				'default' => $total_default_label,
				'override' => $override,
				'has_override' => $override !== ''
			);
		}

		$thousand_point = $this->config->get('module_cyberpunks_language_overrides_thousand_point');
		$data['thousand_point'] = is_string($thousand_point) ? $thousand_point : '';
		$data['thousand_point_options'] = array(
			'' => $this->language->get('text_thousand_default'),
			'space' => $this->language->get('text_thousand_space'),
			'comma' => $this->language->get('text_thousand_comma'),
			'dot' => $this->language->get('text_thousand_dot'),
			'nbsp' => $this->language->get('text_thousand_nbsp'),
			'none' => $this->language->get('text_thousand_none')
		);

		$overrides = $this->config->get('module_cyberpunks_language_overrides_map');
		if (!is_array($overrides)) {
			$overrides = array();
		}
		$data['language_groups'] = $this->buildLanguageGroups($overrides);

		$this->assignCommon($data);
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/cyberpunks_language_overrides', $data));
	}

	public function add() {
		$this->load->language('extension/module/cyberpunks_language_overrides');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/cyberpunks_language_overrides');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$result = $this->model_extension_module_cyberpunks_language_overrides->saveString($this->request->post);

			if ($result === 'duplicate') {
				$this->error['warning'] = $this->language->get('error_duplicate');
			} elseif ($result === false) {
				$this->error['warning'] = $this->language->get('error_source');
			} else {
				$this->session->data['success'] = $this->language->get('text_success');
				$this->response->redirect($this->url->link('extension/module/cyberpunks_language_overrides', 'user_token=' . $this->session->data['user_token'], true));
			}
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('extension/module/cyberpunks_language_overrides');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/cyberpunks_language_overrides');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$result = $this->model_extension_module_cyberpunks_language_overrides->saveString($this->request->post);

			if ($result === 'duplicate') {
				$this->error['warning'] = $this->language->get('error_duplicate');
			} elseif ($result === false) {
				$this->error['warning'] = $this->language->get('error_source');
			} else {
				$this->session->data['success'] = $this->language->get('text_success');
				$this->response->redirect($this->url->link('extension/module/cyberpunks_language_overrides', 'user_token=' . $this->session->data['user_token'], true));
			}
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('extension/module/cyberpunks_language_overrides');
		$this->load->model('extension/module/cyberpunks_language_overrides');

		if (isset($this->request->get['string_id']) && $this->validate()) {
			$this->model_extension_module_cyberpunks_language_overrides->deleteString((int)$this->request->get['string_id']);
			$this->session->data['success'] = $this->language->get('text_success_delete');
		}

		$this->response->redirect($this->url->link('extension/module/cyberpunks_language_overrides', 'user_token=' . $this->session->data['user_token'], true));
	}

	protected function getForm() {
		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['breadcrumbs'] = $this->breadcrumbs();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_form'),
			'href' => ''
		);

		$string_id = isset($this->request->get['string_id']) ? (int)$this->request->get['string_id'] : 0;

		if ($string_id) {
			$data['action'] = $this->url->link('extension/module/cyberpunks_language_overrides/edit', 'user_token=' . $this->session->data['user_token'] . '&string_id=' . $string_id, true);
		} else {
			$data['action'] = $this->url->link('extension/module/cyberpunks_language_overrides/add', 'user_token=' . $this->session->data['user_token'], true);
		}

		$data['cancel'] = $this->url->link('extension/module/cyberpunks_language_overrides', 'user_token=' . $this->session->data['user_token'], true);

		$info = $string_id ? $this->model_extension_module_cyberpunks_language_overrides->getString($string_id) : null;

		if (isset($this->request->post['source_text'])) {
			$data['source_text'] = $this->request->post['source_text'];
		} elseif ($info) {
			$data['source_text'] = $info['source_text'];
		} else {
			$data['source_text'] = '';
		}

		if (isset($this->request->post['comment'])) {
			$data['comment'] = $this->request->post['comment'];
		} elseif ($info) {
			$data['comment'] = $info['comment'];
		} else {
			$data['comment'] = '';
		}

		$data['string_id'] = $string_id;
		$data['languages'] = $this->filterTranslationLanguages($this->model_extension_module_cyberpunks_language_overrides->getLanguages());
		$data['translations'] = array();

		if (isset($this->request->post['translations']) && is_array($this->request->post['translations'])) {
			$data['translations'] = $this->request->post['translations'];
		} elseif ($info && isset($info['translations'])) {
			$data['translations'] = $info['translations'];
		}

		$this->assignCommon($data);
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/cyberpunks_language_overrides_form', $data));
	}

	private function breadcrumbs() {
		return array(
			array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
			),
			array(
				'text' => $this->language->get('text_extension'),
				'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
			),
			array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/cyberpunks_language_overrides', 'user_token=' . $this->session->data['user_token'], true)
			)
		);
	}

	private function assignCommon(&$data) {
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_form'] = $this->language->get('text_form');
		$data['text_cb_lang'] = $this->language->get('text_cb_lang');
		$data['text_cb_lang_help'] = $this->language->get('text_cb_lang_help');
		$data['text_source'] = $this->language->get('text_source');
		$data['text_comment'] = $this->language->get('text_comment');
		$data['text_translations'] = $this->language->get('text_translations');
		$data['text_translations_help'] = $this->language->get('text_translations_help');
		$data['text_missing'] = $this->language->get('text_missing');
		$data['text_confirm'] = $this->language->get('text_confirm');
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['text_total_labels'] = $this->language->get('text_total_labels');
		$data['text_total_labels_help'] = $this->language->get('text_total_labels_help');
		$data['text_total_code'] = $this->language->get('text_total_code');
		$data['text_original'] = $this->language->get('text_original');
		$data['text_override'] = $this->language->get('text_override');
		$data['text_price_format'] = $this->language->get('text_price_format');
		$data['text_price_format_help'] = $this->language->get('text_price_format_help');
		$data['text_no_translation_languages'] = $this->language->get('text_no_translation_languages');
		$data['entry_thousand_point'] = $this->language->get('entry_thousand_point');
		$data['entry_source'] = $this->language->get('entry_source');
		$data['entry_comment'] = $this->language->get('entry_comment');
		$data['entry_translation'] = $this->language->get('entry_translation');
		$data['help_source'] = $this->language->get('help_source');
		$data['help_comment'] = $this->language->get('help_comment');
		$data['text_key'] = $this->language->get('text_key');
		$data['text_language_strings'] = $this->language->get('text_language_strings');
		$data['text_language_strings_help'] = $this->language->get('text_language_strings_help');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_add'] = $this->language->get('button_add');
		$data['button_edit'] = $this->language->get('button_edit');
		$data['button_delete'] = $this->language->get('button_delete');
	}

	// OpenCart's installer copies upload/ with rename() and ignores failures, so a "successful"
	// install can still leave the runtime files missing. Surface that instead of failing silently.
	private function findMissingRuntimeFiles() {
		$required = array(
			DIR_SYSTEM . 'library/cyberpunks_cb_lang.php',
			DIR_SYSTEM . 'library/cyberpunks_language_overrides.php',
			DIR_SYSTEM . 'library/cyberpunks_language_switcher.php',
			DIR_SYSTEM . 'library/cyberpunks_url_locale.php',
			DIR_CATALOG . 'controller/extension/module/cyberpunks_locale.php'
		);

		$missing = array();

		foreach ($required as $file) {
			if (!is_file($file)) {
				$missing[] = $file;
			}
		}

		return $missing;
	}

	private function getCatalogLanguageFiles() {
		return array(
			'checkout/cart' => 'Checkout — Cart',
			'checkout/checkout' => 'Checkout',
			'error/not_found' => 'Errors — Page not found',
			'extension/module/cyberpunks_checkout_facade' => 'Checkout Facade module'
		);
	}

	private function buildLanguageGroups($overrides) {
		$groups = array();
		$assigned_keys = array();

		foreach ($this->getCatalogLanguageFiles() as $route => $title) {
			$strings = $this->readLanguageFileStrings($route);
			$rows = array();

			foreach ($strings as $row) {
				$map_key = $route . ':' . $row['key'];
				$override = '';
				$has_override = false;

				if (isset($overrides[$map_key]) && $overrides[$map_key] !== '') {
					$override = $overrides[$map_key];
					$has_override = true;
					$assigned_keys[$map_key] = true;
				} elseif (isset($overrides[$row['key']]) && $overrides[$row['key']] !== '' && strpos($row['key'], ':') === false) {
					$override = $overrides[$row['key']];
					$has_override = true;
					$assigned_keys[$row['key']] = true;
				}

				$rows[] = array(
					'map_key' => $map_key,
					'key' => $row['key'],
					'original' => $row['value'],
					'override' => $override,
					'has_override' => $has_override
				);
			}

			usort($rows, function ($a, $b) {
				if ($a['has_override'] !== $b['has_override']) {
					return $b['has_override'] - $a['has_override'];
				}

				return strcmp($a['key'], $b['key']);
			});

			$groups[] = array(
				'route' => $route,
				'title' => $title,
				'strings' => $rows
			);
		}

		$legacy_rows = array();
		foreach ($overrides as $key => $value) {
			if ($value === '' || isset($assigned_keys[$key])) {
				continue;
			}

			if (strpos($key, ':') !== false) {
				continue;
			}

			$legacy_rows[] = array(
				'map_key' => $key,
				'key' => $key,
				'original' => '',
				'override' => $value,
				'has_override' => true
			);
		}

		if ($legacy_rows) {
			usort($legacy_rows, function ($a, $b) {
				return strcmp($a['key'], $b['key']);
			});

			$groups[] = array(
				'route' => '',
				'title' => 'Legacy overrides (migrate to namespaced keys by re-saving)',
				'strings' => $legacy_rows
			);
		}

		return $groups;
	}

	private function readLanguageFileStrings($route) {
		$file = DIR_CATALOG . 'language/en-gb/' . $route . '.php';
		$result = array();

		if (!is_file($file)) {
			return $result;
		}

		$lines = file($file);
		if (!is_array($lines)) {
			return $result;
		}

		foreach ($lines as $line) {
			$matches = array();
			if (preg_match('/^\$_\[\'([^\']+)\'\]\s*=\s*\'(.*)\';\s*$/', trim($line), $matches) === 1) {
				$result[] = array(
					'key' => $matches[1],
					'value' => stripcslashes($matches[2])
				);
			}
		}

		return $result;
	}

	private function filterTranslationLanguages($languages) {
		$out = array();

		foreach ((array)$languages as $language) {
			$code = strtolower(isset($language['code']) ? (string)$language['code'] : '');

			// Original text IS English — do not show EN again in translation fields.
			if ($code === 'en-gb' || $code === 'en' || strpos($code, 'en-') === 0) {
				continue;
			}

			$out[] = $language;
		}

		return $out;
	}

	private function getCartTotalLabelDefaults() {
		return array(
			'sub_total' => 'Sub-Total',
			'shipping' => 'Shipping',
			'tax' => 'Tax',
			'total' => 'Total',
			'coupon' => 'Coupon',
			'voucher' => 'Gift Certificate',
			'reward' => 'Reward Points',
			'credit' => 'Store Credit'
		);
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/cyberpunks_language_overrides')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}

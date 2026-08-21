<?php
class ControllerExtensionModuleCyberpunksShopMenu extends Controller {
	private $error = array();

	public function install() {
		$this->load->model('user/user_group');

		foreach ($this->db->query("SELECT user_group_id FROM `" . DB_PREFIX . "user_group`")->rows as $user_group) {
			$this->model_user_user_group->addPermission($user_group['user_group_id'], 'access', 'extension/module/cyberpunks_shop_menu');
			$this->model_user_user_group->addPermission($user_group['user_group_id'], 'modify', 'extension/module/cyberpunks_shop_menu');
		}

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('module_cyberpunks_shop_menu', array(
			'module_cyberpunks_shop_menu_status' => 1,
			'module_cyberpunks_shop_menu_items'  => array()
		));
	}

	public function index() {
		$this->load->language('extension/module/cyberpunks_shop_menu');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');
		$this->load->model('catalog/category');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$items = $this->normalizeItems(isset($this->request->post['module_cyberpunks_shop_menu_items']) ? $this->request->post['module_cyberpunks_shop_menu_items'] : array());

			$this->model_setting_setting->editSetting('module_cyberpunks_shop_menu', array(
				'module_cyberpunks_shop_menu_status' => !empty($this->request->post['module_cyberpunks_shop_menu_status']) ? 1 : 0,
				'module_cyberpunks_shop_menu_items'  => $items
			));

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/cyberpunks_shop_menu', 'user_token=' . $this->session->data['user_token'], true));
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
			'href' => $this->url->link('extension/module/cyberpunks_shop_menu', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/cyberpunks_shop_menu', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if (isset($this->request->post['module_cyberpunks_shop_menu_status'])) {
			$data['module_cyberpunks_shop_menu_status'] = (int)$this->request->post['module_cyberpunks_shop_menu_status'];
		} else {
			$st = $this->config->get('module_cyberpunks_shop_menu_status');
			$data['module_cyberpunks_shop_menu_status'] = ($st === null) ? 1 : (int)$st;
		}

		if (isset($this->request->post['module_cyberpunks_shop_menu_items'])) {
			$data['items'] = $this->request->post['module_cyberpunks_shop_menu_items'];
		} else {
			$items = $this->config->get('module_cyberpunks_shop_menu_items');
			$data['items'] = is_array($items) ? $items : array();
		}

		$data['categories'] = array();
		$categories = $this->model_catalog_category->getCategories(array('sort' => 'name'));

		foreach ($categories as $category) {
			$category_id = (int)$category['category_id'];
			$info = $this->model_catalog_category->getCategory($category_id);
			$title = ($info && !empty($info['name'])) ? $info['name'] : html_entity_decode(strip_tags($category['name']), ENT_QUOTES, 'UTF-8');

			$data['categories'][] = array(
				'category_id' => $category_id,
				'name'        => $category['name'],
				'title'       => $title,
				'href'        => $this->getCategoryCatalogHref($category_id)
			);
		}

		$data['categories_json'] = json_encode($data['categories']);

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_none'] = $this->language->get('text_none');
		$data['text_panel_none'] = $this->language->get('text_panel_none');
		$data['text_panel_products'] = $this->language->get('text_panel_products');
		$data['text_panel_links'] = $this->language->get('text_panel_links');
		$data['text_item'] = $this->language->get('text_item');
		$data['text_links'] = $this->language->get('text_links');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_name'] = $this->language->get('entry_name');
		$data['entry_href'] = $this->language->get('entry_href');
		$data['entry_panel'] = $this->language->get('entry_panel');
		$data['entry_category'] = $this->language->get('entry_category');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['entry_item_status'] = $this->language->get('entry_item_status');
		$data['help_href'] = $this->language->get('help_href');
		$data['help_products'] = $this->language->get('help_products');
		$data['button_add_item'] = $this->language->get('button_add_item');
		$data['button_add_link'] = $this->language->get('button_add_link');
		$data['button_remove'] = $this->language->get('button_remove');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/cyberpunks_shop_menu', $data));
	}

	private function getCategoryCatalogHref($category_id) {
		$category_id = (int)$category_id;

		if ($category_id < 1) {
			return '';
		}

		$seo = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "seo_url` WHERE `query` = 'category_id=" . $category_id . "' AND store_id = '0' AND language_id = '" . (int)$this->config->get('config_language_id') . "' LIMIT 1");

		if ($seo->num_rows && !empty($seo->row['keyword'])) {
			return '/' . ltrim($seo->row['keyword'], '/');
		}

		return 'index.php?route=product/category&path=' . $category_id;
	}

	private function normalizeItems($raw) {
		$items = array();

		if (!is_array($raw)) {
			return $items;
		}

		foreach ($raw as $row) {
			if (!is_array($row)) {
				continue;
			}

			$name = isset($row['name']) ? trim(strip_tags((string)$row['name'])) : '';

			if ($name === '') {
				continue;
			}

			$panel = isset($row['panel']) ? (string)$row['panel'] : 'none';

			if (!in_array($panel, array('none', 'products', 'links'), true)) {
				$panel = 'none';
			}

			$links = array();

			if (!empty($row['links']) && is_array($row['links'])) {
				foreach ($row['links'] as $link) {
					if (!is_array($link)) {
						continue;
					}

					$link_name = isset($link['name']) ? trim(strip_tags((string)$link['name'])) : '';

					if ($link_name === '') {
						continue;
					}

					$links[] = array(
						'name' => $link_name,
						'href' => isset($link['href']) ? trim((string)$link['href']) : ''
					);
				}
			}

			$items[] = array(
				'name'        => $name,
				'href'        => isset($row['href']) ? trim((string)$row['href']) : '',
				'panel'       => $panel,
				'category_id' => isset($row['category_id']) ? (int)$row['category_id'] : 0,
				'links'       => $links,
				'sort_order'  => isset($row['sort_order']) ? (int)$row['sort_order'] : 0,
				'status'      => !empty($row['status']) ? 1 : 0
			);
		}

		usort($items, function ($a, $b) {
			if ($a['sort_order'] === $b['sort_order']) {
				return strcmp($a['name'], $b['name']);
			}

			return $a['sort_order'] - $b['sort_order'];
		});

		return $items;
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/cyberpunks_shop_menu')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}

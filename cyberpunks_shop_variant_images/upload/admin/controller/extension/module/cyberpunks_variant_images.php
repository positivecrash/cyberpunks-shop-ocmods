<?php
class ControllerExtensionModuleCyberpunksVariantImages extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/cyberpunks_variant_images');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/module');
		$this->load->model('setting/setting');
		$this->load->model('catalog/product');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$existing_mappings = $this->normalizeMappings((array)$this->config->get('module_cyberpunks_variant_images_mappings'));
			$incoming_mappings = $this->normalizeMappings(isset($this->request->post['module_cyberpunks_variant_images_mappings']) ? $this->request->post['module_cyberpunks_variant_images_mappings'] : array());
			$active_product_id = isset($this->request->post['active_product_id']) ? (int)$this->request->post['active_product_id'] : 0;

			$merged_mappings = $existing_mappings;

			if ($active_product_id > 0) {
				$merged_mappings = array();

				foreach ($existing_mappings as $mapping) {
					if ((int)$mapping['product_id'] !== $active_product_id) {
						$merged_mappings[] = $mapping;
					}
				}

				foreach ($incoming_mappings as $mapping) {
					$mapping['product_id'] = $active_product_id;
					$merged_mappings[] = $mapping;
				}
			}

			$save_data = array(
				'module_cyberpunks_variant_images_status' => isset($this->request->post['module_cyberpunks_variant_images_status']) ? (int)$this->request->post['module_cyberpunks_variant_images_status'] : (int)$this->config->get('module_cyberpunks_variant_images_status'),
				'module_cyberpunks_variant_images_mappings' => $merged_mappings
			);

			$this->model_setting_setting->editSetting('module_cyberpunks_variant_images', $save_data);

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/cyberpunks_variant_images', 'user_token=' . $this->session->data['user_token'], true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
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
			'href' => $this->url->link('extension/module/cyberpunks_variant_images', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/cyberpunks_variant_images', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if (isset($this->request->post['module_cyberpunks_variant_images_status'])) {
			$data['module_cyberpunks_variant_images_status'] = $this->request->post['module_cyberpunks_variant_images_status'];
		} else {
			$data['module_cyberpunks_variant_images_status'] = $this->config->get('module_cyberpunks_variant_images_status');
		}

		$stored = $this->config->get('module_cyberpunks_variant_images_mappings');
		$data['mappings'] = $this->normalizeMappings(is_array($stored) ? $stored : array());

		$data['products'] = $this->model_catalog_product->getProducts(array(
			'sort' => 'pd.name',
			'order' => 'ASC',
			'start' => 0,
			'limit' => 10000
		));
		$data['option_data_url'] = $this->url->link('extension/module/cyberpunks_variant_images/options', 'user_token=' . $this->session->data['user_token'], true);
		$data['product_name_map'] = array();
		foreach ($data['products'] as $product) {
			$data['product_name_map'][(int)$product['product_id']] = $product['name'];
		}

		$data['mapping_groups'] = array();
		foreach ($data['mappings'] as $mapping) {
			$product_id = (int)$mapping['product_id'];

			if (!isset($data['mapping_groups'][$product_id])) {
				$data['mapping_groups'][$product_id] = array(
					'product_id' => $product_id,
					'product_name' => isset($data['product_name_map'][$product_id]) ? $data['product_name_map'][$product_id] : ('#' . $product_id),
					'mappings' => array()
				);
			}

			$data['mapping_groups'][$product_id]['mappings'][] = $mapping;
		}

		$data['mapping_groups'] = array_values($data['mapping_groups']);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/cyberpunks_variant_images', $data));
	}

	private function normalizeMappings($mappings) {
		$result = array();

		if (!is_array($mappings)) {
			return $result;
		}

		foreach ($mappings as $mapping) {
			$product_id = isset($mapping['product_id']) ? (int)$mapping['product_id'] : 0;
			$signature = '';
			$image = isset($mapping['image']) ? trim((string)$mapping['image']) : '';
			$status = !empty($mapping['status']) ? 1 : 0;
			$pairs = array();
			$option_value_ids = array();

			$pairs_json = isset($mapping['pairs_json']) ? html_entity_decode((string)$mapping['pairs_json'], ENT_QUOTES, 'UTF-8') : '';
			$decoded_pairs = json_decode($pairs_json, true);

			if (is_array($decoded_pairs)) {
				foreach ($decoded_pairs as $pair) {
					$product_option_id = isset($pair['product_option_id']) ? (int)$pair['product_option_id'] : 0;
					$product_option_value_id = isset($pair['product_option_value_id']) ? (int)$pair['product_option_value_id'] : 0;
					$option_value_id = isset($pair['option_value_id']) ? (int)$pair['option_value_id'] : 0;

					if (!$product_option_id || !$product_option_value_id) {
						continue;
					}

					if (!$option_value_id && $product_id > 0) {
						$option_value = $this->model_catalog_product->getProductOptionValue($product_id, $product_option_value_id);
						if (!empty($option_value['option_value_id'])) {
							$option_value_id = (int)$option_value['option_value_id'];
						}
					}

					if (!$option_value_id) {
						continue;
					}

					$pairs[] = array(
						'product_option_id' => $product_option_id,
						'product_option_value_id' => $product_option_value_id,
						'option_value_id' => $option_value_id
					);
					$option_value_ids[] = $option_value_id;
				}
			}

			if ($option_value_ids) {
				$option_value_ids = array_values(array_unique($option_value_ids));
				sort($option_value_ids, SORT_NUMERIC);
				$signature = implode('-', $option_value_ids);
			} elseif (isset($mapping['option_value_signature'])) {
				$signature = trim((string)$mapping['option_value_signature']);
			}

			if ($product_id <= 0 && $signature === '' && $image === '') {
				continue;
			}

			$result[] = array(
				'product_id' => $product_id,
				'option_value_signature' => $signature,
				'pairs' => $pairs,
				'pairs_json' => json_encode($pairs),
				'image' => $image,
				'status' => $status
			);
		}

		return $result;
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/cyberpunks_variant_images')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function options() {
		$json = array(
			'options' => array()
		);

		$product_id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;

		if ($product_id > 0) {
			$this->load->model('catalog/product');
			$product_options = $this->model_catalog_product->getProductOptions($product_id);

			foreach ($product_options as $product_option) {
				if (!in_array($product_option['type'], array('select', 'radio', 'checkbox', 'image'))) {
					continue;
				}

				$values = array();
				foreach ($product_option['product_option_value'] as $product_option_value) {
					$value_info = $this->model_catalog_product->getProductOptionValue($product_id, $product_option_value['product_option_value_id']);

					$values[] = array(
						'product_option_value_id' => (int)$product_option_value['product_option_value_id'],
						'option_value_id' => (int)$product_option_value['option_value_id'],
						'name' => $value_info ? $value_info['name'] : ('#' . (int)$product_option_value['option_value_id'])
					);
				}

				$json['options'][] = array(
					'product_option_id' => (int)$product_option['product_option_id'],
					'name' => $product_option['name'],
					'values' => $values
				);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}

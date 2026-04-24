<?php
class ControllerExtensionModuleCyberpunksVariantImages extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/cyberpunks_variant_images');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/module');
		$this->load->model('setting/setting');
		$this->load->model('catalog/product');

		if (isset($this->request->get['export_product_id'])) {
			$this->handleExportRequest((int)$this->request->get['export_product_id']);
			return;
		}

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			if (!empty($this->request->post['import_action'])) {
				$this->handleImportRequest();
				$this->response->redirect($this->url->link('extension/module/cyberpunks_variant_images', 'user_token=' . $this->session->data['user_token'], true));
				return;
			}
			if (!empty($this->request->post['delete_tab_action'])) {
				$this->handleDeleteTabRequest();
				$this->response->redirect($this->url->link('extension/module/cyberpunks_variant_images', 'user_token=' . $this->session->data['user_token'], true));
				return;
			}
			if (!empty($this->request->post['export_action'])) {
				$this->handleExportRequest();
				return;
			}

			$existing_mappings = $this->normalizeMappings($this->getStoredMappingsRaw());
			$incoming_mappings = array();
			if (!empty($this->request->post['module_cyberpunks_variant_images_payload'])) {
				$payload = json_decode(html_entity_decode((string)$this->request->post['module_cyberpunks_variant_images_payload'], ENT_QUOTES, 'UTF-8'), true);
				$incoming_mappings = $this->normalizeMappings(is_array($payload) ? $payload : array());
			} else {
				$incoming_mappings = $this->normalizeMappings(isset($this->request->post['module_cyberpunks_variant_images_mappings']) ? $this->request->post['module_cyberpunks_variant_images_mappings'] : array());
			}
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
				'module_cyberpunks_variant_images_mappings' => $this->compactMappingsForStorage($merged_mappings)
			);

			$this->model_setting_setting->editSetting('module_cyberpunks_variant_images', $save_data);

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/cyberpunks_variant_images', 'user_token=' . $this->session->data['user_token'], true));
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
		$data['import_action'] = $this->url->link('extension/module/cyberpunks_variant_images', 'user_token=' . $this->session->data['user_token'], true);
		$data['export_url_base'] = $this->url->link('extension/module/cyberpunks_variant_images', 'user_token=' . $this->session->data['user_token'] . '&export_product_id=', true);

		if (isset($this->request->post['module_cyberpunks_variant_images_status'])) {
			$data['module_cyberpunks_variant_images_status'] = $this->request->post['module_cyberpunks_variant_images_status'];
		} else {
			$data['module_cyberpunks_variant_images_status'] = $this->config->get('module_cyberpunks_variant_images_status');
		}

		$data['mappings'] = $this->normalizeMappings($this->getStoredMappingsRaw());

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
			$mappings = $this->decodeMappingsValue($mappings);
		}

		if (!is_array($mappings)) {
			return array();
		}

		foreach ($mappings as $mapping) {
			$product_id = isset($mapping['product_id']) ? (int)$mapping['product_id'] : (isset($mapping['p']) ? (int)$mapping['p'] : 0);
			$signature = '';
			$image = isset($mapping['image']) ? trim((string)$mapping['image']) : (isset($mapping['i']) ? trim((string)$mapping['i']) : '');
			$status = isset($mapping['status']) ? (!empty($mapping['status']) ? 1 : 0) : (isset($mapping['t']) ? (!empty($mapping['t']) ? 1 : 0) : 1);
			$pairs = array();
			$signature_ids = array();

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
					$signature_ids[] = (int)$product_option_value_id;
				}
			}

			if ($signature_ids) {
				$signature_ids = array_values(array_unique($signature_ids));
				sort($signature_ids, SORT_NUMERIC);
				$signature = implode('-', $signature_ids);
			} elseif (isset($mapping['option_value_signature'])) {
				$signature = trim((string)$mapping['option_value_signature']);
			} elseif (isset($mapping['s'])) {
				$signature = trim((string)$mapping['s']);
			}

			// Rebuild option pairs from signature for compactly stored mappings.
			if (!$pairs && $product_id > 0 && $signature !== '') {
				$pairs = $this->buildPairsFromSignature($product_id, $signature);
			}

			if ($product_id <= 0 && $signature === '' && $image === '') {
				continue;
			}

			if (strpos($image, '/') === false) {
				$image = 'catalog/view/theme/cybershops/media/altruist-bundle/product-previews/' . $image;
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

	private function getStoredMappingsRaw() {
		$from_config = $this->config->get('module_cyberpunks_variant_images_mappings');

		if (is_array($from_config)) {
			return $from_config;
		}

		$from_config_decoded = $this->decodeMappingsValue($from_config);
		if (is_array($from_config_decoded)) {
			return $from_config_decoded;
		}

		$from_setting_model = $this->model_setting_setting->getSetting('module_cyberpunks_variant_images');
		if (isset($from_setting_model['module_cyberpunks_variant_images_mappings'])) {
			$model_value = $from_setting_model['module_cyberpunks_variant_images_mappings'];
			if (is_array($model_value)) {
				return $model_value;
			}

			$model_decoded = $this->decodeMappingsValue($model_value);
			if (is_array($model_decoded)) {
				return $model_decoded;
			}
		}

		$query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `key` = 'module_cyberpunks_variant_images_mappings' AND store_id = '0' ORDER BY setting_id DESC");

		foreach ($query->rows as $row) {
			$decoded = $this->decodeMappingsValue($row['value']);
			if (is_array($decoded)) {
				return $decoded;
			}
		}

		return array();
	}

	private function decodeMappingsValue($raw) {
		if (is_array($raw)) {
			return $raw;
		}

		if (!is_string($raw) || $raw === '') {
			return array();
		}

		$candidates = array($raw);
		$tmp = $raw;
		for ($i = 0; $i < 3; $i++) {
			$next = stripcslashes($tmp);
			if ($next === $tmp) {
				break;
			}
			$candidates[] = $next;
			$tmp = $next;
		}

		foreach ($candidates as $candidate) {
			$json = json_decode($candidate, true);
			if (is_array($json)) {
				return $json;
			}

			$php = @unserialize($candidate);
			if (is_array($php)) {
				return $php;
			}
		}

		return array();
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/cyberpunks_variant_images')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	private function handleImportRequest() {
		if (empty($this->request->files['import_file']['tmp_name']) || !is_uploaded_file($this->request->files['import_file']['tmp_name'])) {
			$this->session->data['error_warning'] = $this->language->get('error_import_file_required');
			return;
		}

		$raw = file_get_contents($this->request->files['import_file']['tmp_name']);
		if (!is_string($raw) || trim($raw) === '') {
			$this->session->data['error_warning'] = $this->language->get('error_import_file_empty');
			return;
		}

		$entries = $this->parseImportEntries($raw);
		if (!$entries) {
			$this->session->data['error_warning'] = $this->language->get('error_import_invalid_format');
			return;
		}

		$import_mappings = $this->buildMappingsFromImportEntries($entries);
		if (!$import_mappings) {
			$this->session->data['error_warning'] = $this->language->get('error_import_no_rows');
			return;
		}

		$existing_mappings = $this->normalizeMappings($this->getStoredMappingsRaw());
		$import_product_ids = array();
		foreach ($import_mappings as $mapping) {
			$import_product_ids[(int)$mapping['product_id']] = (int)$mapping['product_id'];
		}

		$merged_mappings = array();
		foreach ($existing_mappings as $mapping) {
			$product_id = isset($mapping['product_id']) ? (int)$mapping['product_id'] : 0;
			if ($product_id > 0 && isset($import_product_ids[$product_id])) {
				continue;
			}
			$merged_mappings[] = $mapping;
		}
		foreach ($import_mappings as $mapping) {
			$merged_mappings[] = $mapping;
		}

		$save_data = array(
			'module_cyberpunks_variant_images_status' => (int)$this->config->get('module_cyberpunks_variant_images_status'),
			'module_cyberpunks_variant_images_mappings' => $this->compactMappingsForStorage($merged_mappings)
		);

		$this->model_setting_setting->editSetting('module_cyberpunks_variant_images', $save_data);
		$this->session->data['success'] = sprintf($this->language->get('text_import_success_count'), count($import_mappings));
	}

	private function handleDeleteTabRequest() {
		$product_id = isset($this->request->post['active_product_id']) ? (int)$this->request->post['active_product_id'] : 0;
		if ($product_id <= 0) {
			$this->session->data['error_warning'] = $this->language->get('error_delete_tab_product_required');
			return;
		}

		$existing_mappings = $this->normalizeMappings($this->getStoredMappingsRaw());
		$filtered = array();
		foreach ($existing_mappings as $mapping) {
			if ((int)$mapping['product_id'] !== $product_id) {
				$filtered[] = $mapping;
			}
		}

		$save_data = array(
			'module_cyberpunks_variant_images_status' => (int)$this->config->get('module_cyberpunks_variant_images_status'),
			'module_cyberpunks_variant_images_mappings' => $this->compactMappingsForStorage($filtered)
		);

		$this->model_setting_setting->editSetting('module_cyberpunks_variant_images', $save_data);
		$this->session->data['success'] = sprintf($this->language->get('text_delete_tab_success'), $product_id);
	}

	private function handleExportRequest($product_id = 0) {
		if (!$product_id) {
			$product_id = isset($this->request->post['active_product_id']) ? (int)$this->request->post['active_product_id'] : 0;
		}
		if ($product_id <= 0) {
			$this->session->data['error_warning'] = $this->language->get('error_export_product_required');
			$this->response->redirect($this->url->link('extension/module/cyberpunks_variant_images', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$all = $this->normalizeMappings($this->getStoredMappingsRaw());
		$rows = array();
		foreach ($all as $mapping) {
			if ((int)$mapping['product_id'] === $product_id) {
				$rows[] = $mapping;
			}
		}

		$yaml = $this->buildYamlExport($product_id, $rows);
		$filename = 'variant_images_product_' . $product_id . '.yaml';

		$this->response->addHeader('Content-Type: text/yaml; charset=utf-8');
		$this->response->addHeader('Content-Disposition: attachment; filename="' . $filename . '"');
		$this->response->setOutput($yaml);
	}

	private function buildYamlExport($product_id, $rows) {
		$lines = array();
		$lines[] = 'product_id: ' . (int)$product_id;
		$lines[] = 'items:';

		$product_options = $this->model_catalog_product->getProductOptions((int)$product_id);
		$option_name_by_id = array();
		foreach ($product_options as $product_option) {
			$option_name_by_id[(int)$product_option['product_option_id']] = $this->canonicalOptionKey($product_option['name']);
		}

		foreach ($rows as $row) {
			$pairs = array();
			$pairs_json = isset($row['pairs_json']) ? json_decode(html_entity_decode((string)$row['pairs_json'], ENT_QUOTES, 'UTF-8'), true) : array();
			if (is_array($pairs_json) && $pairs_json) {
				$pairs = $pairs_json;
			} elseif (!empty($row['option_value_signature'])) {
				$pairs = $this->buildPairsFromSignature($product_id, $row['option_value_signature']);
			}

			$options_map = array();
			foreach ($pairs as $pair) {
				$product_option_id = isset($pair['product_option_id']) ? (int)$pair['product_option_id'] : 0;
				$product_option_value_id = isset($pair['product_option_value_id']) ? (int)$pair['product_option_value_id'] : 0;
				if (!$product_option_id || !$product_option_value_id) {
					continue;
				}

				$key = isset($option_name_by_id[$product_option_id]) ? $option_name_by_id[$product_option_id] : ('option-' . $product_option_id);
				$value_info = $this->model_catalog_product->getProductOptionValue((int)$product_id, $product_option_value_id);
				if (!$value_info || empty($value_info['name'])) {
					continue;
				}

				$options_map[$key] = $value_info['name'];
			}

			if (!$options_map) {
				continue;
			}

			$image = isset($row['image']) ? (string)$row['image'] : '';
			$lines[] = '  - options:';
			foreach ($options_map as $key => $value) {
				$lines[] = '      ' . $key . ': ' . $this->yamlScalar($value);
			}
			$lines[] = '    image: ' . $this->yamlScalar($image);
		}

		return implode("\n", $lines) . "\n";
	}

	private function canonicalOptionKey($option_name) {
		$key = $this->normalizeSlug($option_name);
		$aliases = $this->buildOptionAliases($key);
		return $aliases ? $aliases[0] : $key;
	}

	private function yamlScalar($value) {
		$value = (string)$value;
		$value = str_replace('"', '\"', $value);
		return '"' . $value . '"';
	}

	private function parseImportEntries($raw) {
		$entries = array();
		$parsed_json = json_decode($raw, true);

		if (is_array($parsed_json)) {
			$is_assoc = array_keys($parsed_json) !== range(0, count($parsed_json) - 1);
			$entries = $is_assoc ? array($parsed_json) : $parsed_json;
		}

		if ($entries) {
			return $entries;
		}

		$yaml_entries = $this->parseYamlLikeEntries($raw);
		if ($yaml_entries) {
			return $yaml_entries;
		}

		$normalized = str_replace(array("\r\n", "\r"), "\n", $raw);
		$blocks = preg_split("/\n\s*\n/", trim($normalized));
		$result = array();

		foreach ($blocks as $block) {
			$product_id = 0;
			$image = '';
			$options = array();

			if (preg_match('/product_id\s*:\s*(\d+)/i', $block, $m)) {
				$product_id = (int)$m[1];
			}

			if (preg_match('/image\s*:\s*["\']?(.+?)["\']?\s*$/im', $block, $m)) {
				$image = trim($m[1]);
			}

			if (preg_match('/options\s*:\s*\{(.*?)\}/is', $block, $m)) {
				$options = $this->parseInlineOptions($m[1]);
			}

			if ($product_id > 0 && $image !== '' && $options) {
				$result[] = array(
					'product_id' => $product_id,
					'options' => $options,
					'image' => $image
				);
			}
		}

		return $result;
	}

	private function parseYamlLikeEntries($raw) {
		$normalized = str_replace(array("\r\n", "\r"), "\n", $raw);
		$lines = explode("\n", $normalized);
		$product_id = 0;
		$entries = array();
		$current = array();
		$in_options_block = false;

		foreach ($lines as $line) {
			$trimmed = trim($line);
			if ($trimmed === '' || $trimmed === '{' || $trimmed === '}') {
				continue;
			}

			if (preg_match('/^product_id\s*:\s*(\d+)$/i', $trimmed, $m)) {
				$product_id = (int)$m[1];
				continue;
			}

			if (preg_match('/^-?\s*options\s*:\s*\{(.*)\}\s*$/i', $trimmed, $m)) {
				if (!isset($current['options'])) {
					$current['options'] = array();
				}
				$current['options'] = $this->parseInlineOptions($m[1]);
				$in_options_block = false;
				continue;
			}

			if (preg_match('/^-?\s*options\s*:\s*$/i', $trimmed)) {
				if (!isset($current['options'])) {
					$current['options'] = array();
				}
				$in_options_block = true;
				continue;
			}

			if (preg_match('/^-?\s*image\s*:\s*["\']?(.+?)["\']?\s*$/i', $trimmed, $m)) {
				$current['image'] = trim($m[1]);
				$in_options_block = false;

				if ($product_id > 0 && !empty($current['image']) && !empty($current['options'])) {
					$entries[] = array(
						'product_id' => $product_id,
						'options' => $current['options'],
						'image' => $current['image']
					);
				}

				$current = array();
				continue;
			}

			if ($in_options_block && preg_match('/^([a-z0-9\-_]+)\s*:\s*(.+)$/i', $trimmed, $m)) {
				$key = trim($m[1], " \t\n\r\0\x0B\"'");
				$value = trim($m[2], " \t\n\r\0\x0B\"',");
				if ($key !== '' && $value !== '') {
					$current['options'][$key] = $value;
				}
			}
		}

		return $entries;
	}

	private function parseInlineOptions($blob) {
		$result = array();
		$pairs = preg_split('/\s*,\s*/', trim((string)$blob));

		foreach ($pairs as $pair) {
			if (strpos($pair, ':') === false) {
				continue;
			}
			list($key, $value) = array_map('trim', explode(':', $pair, 2));
			$key = trim($key, " \t\n\r\0\x0B\"'");
			$value = trim($value, " \t\n\r\0\x0B\"'");

			if ($key !== '' && $value !== '') {
				$result[$key] = $value;
			}
		}

		return $result;
	}

	private function buildMappingsFromImportEntries($entries) {
		$result = array();

		foreach ($entries as $entry) {
			$product_id = isset($entry['product_id']) ? (int)$entry['product_id'] : 0;
			$image = isset($entry['image']) ? trim((string)$entry['image']) : '';
			$options = isset($entry['options']) && is_array($entry['options']) ? $entry['options'] : array();

			if ($product_id <= 0 || $image === '' || !$options) {
				continue;
			}

			$product_options = $this->model_catalog_product->getProductOptions($product_id);
			if (!$product_options) {
				continue;
			}

			$index = $this->indexProductOptions($product_id, $product_options);
			$pairs = array();
			$signature_ids = array();

			foreach ($options as $raw_option_name => $raw_value_name) {
				$option_key = $this->normalizeSlug($raw_option_name);
				$value_key = $this->normalizeSlug($raw_value_name);

				if (!isset($index[$option_key]) || !isset($index[$option_key]['values'][$value_key])) {
					continue;
				}

				$value_item = $index[$option_key]['values'][$value_key];
				$pairs[] = array(
					'product_option_id' => (int)$index[$option_key]['product_option_id'],
					'product_option_value_id' => (int)$value_item['product_option_value_id'],
					'option_value_id' => (int)$value_item['option_value_id']
				);
				$signature_ids[] = (int)$value_item['product_option_value_id'];
			}

			if (!$signature_ids) {
				continue;
			}

			$signature_ids = array_values(array_unique($signature_ids));
			sort($signature_ids, SORT_NUMERIC);

			$result[] = array(
				'product_id' => $product_id,
				'option_value_signature' => implode('-', $signature_ids),
				'image' => $image,
				'status' => 1
			);
		}

		return $result;
	}

	private function compactMappingsForStorage($mappings) {
		$result = array();

		if (!is_array($mappings)) {
			return $result;
		}

		foreach ($mappings as $mapping) {
			$product_id = isset($mapping['product_id']) ? (int)$mapping['product_id'] : (isset($mapping['p']) ? (int)$mapping['p'] : 0);
			$signature = isset($mapping['option_value_signature']) ? trim((string)$mapping['option_value_signature']) : (isset($mapping['s']) ? trim((string)$mapping['s']) : '');
			$image = isset($mapping['image']) ? trim((string)$mapping['image']) : (isset($mapping['i']) ? trim((string)$mapping['i']) : '');
			$status = isset($mapping['status']) ? (int)!empty($mapping['status']) : (isset($mapping['t']) ? (int)!empty($mapping['t']) : 1);

			if ($product_id <= 0 || $signature === '' || $image === '') {
				continue;
			}

			$image = preg_replace('#^/?catalog/view/theme/cybershops/media/altruist-bundle/product-previews/#', '', $image);

			$result[] = array(
				'p' => $product_id,
				's' => $signature,
				'i' => $image,
				't' => $status
			);
		}

		return $result;
	}

	private function buildPairsFromSignature($product_id, $signature) {
		$result = array();
		$ids = array_filter(array_map('intval', explode('-', (string)$signature)));
		if (!$ids) {
			return $result;
		}

		$target_lookup = array_flip($ids);
		$product_options = $this->model_catalog_product->getProductOptions((int)$product_id);
		if (!$product_options) {
			return $result;
		}

		foreach ($product_options as $product_option) {
			if (empty($product_option['product_option_value']) || !is_array($product_option['product_option_value'])) {
				continue;
			}

			foreach ($product_option['product_option_value'] as $product_option_value) {
				$product_option_value_id = isset($product_option_value['product_option_value_id']) ? (int)$product_option_value['product_option_value_id'] : 0;
				if (!$product_option_value_id || !isset($target_lookup[$product_option_value_id])) {
					continue;
				}

				$result[] = array(
					'product_option_id' => (int)$product_option['product_option_id'],
					'product_option_value_id' => $product_option_value_id,
					'option_value_id' => isset($product_option_value['option_value_id']) ? (int)$product_option_value['option_value_id'] : 0
				);
			}
		}

		return $result;
	}

	private function indexProductOptions($product_id, $product_options) {
		$result = array();

		foreach ($product_options as $product_option) {
			$option_key = $this->normalizeSlug($product_option['name']);

			$option_payload = array(
				'product_option_id' => (int)$product_option['product_option_id'],
				'values' => array()
			);
			$result[$option_key] = $option_payload;
			foreach ($this->buildOptionAliases($option_key) as $alias) {
				$result[$alias] = $option_payload;
			}

			if (empty($product_option['product_option_value']) || !is_array($product_option['product_option_value'])) {
				continue;
			}

			foreach ($product_option['product_option_value'] as $product_option_value) {
				$value_info = $this->model_catalog_product->getProductOptionValue((int)$product_id, (int)$product_option_value['product_option_value_id']);
				$value_name = !empty($value_info['name']) ? $value_info['name'] : '';
				$value_key = $this->normalizeSlug($value_name);

				if ($value_key === '') {
					continue;
				}

				$result[$option_key]['values'][$value_key] = array(
					'product_option_value_id' => (int)$product_option_value['product_option_value_id'],
					'option_value_id' => (int)$product_option_value['option_value_id']
				);
				foreach ($this->buildValueAliases($value_key) as $value_alias) {
					$result[$option_key]['values'][$value_alias] = array(
						'product_option_value_id' => (int)$product_option_value['product_option_value_id'],
						'option_value_id' => (int)$product_option_value['option_value_id']
					);
				}
			}
		}

		return $result;
	}

	private function buildOptionAliases($option_key) {
		$aliases = array();
		if (strpos($option_key, 'emotion') !== false && strpos($option_key, 'urban') !== false) {
			$aliases[] = 'urban-emotion';
		}
		if (strpos($option_key, 'color') !== false && strpos($option_key, 'urban') !== false && strpos($option_key, 'hood') === false) {
			$aliases[] = 'urban-color';
		}
		if (strpos($option_key, 'color') !== false && strpos($option_key, 'insight') !== false) {
			$aliases[] = 'insight-color';
		}
		if (strpos($option_key, 'hood') !== false && strpos($option_key, 'color') !== false) {
			$aliases[] = 'urban-hood-color';
		}
		return array_values(array_unique($aliases));
	}

	private function buildValueAliases($value_key) {
		$aliases = array($value_key);
		if ($value_key === 'rnd') {
			$aliases[] = 'random';
		}
		return array_values(array_unique($aliases));
	}

	private function normalizeSlug($value) {
		$value = trim((string)$value);
		if (function_exists('mb_strtolower')) {
			$value = mb_strtolower($value, 'UTF-8');
		} else {
			$value = strtolower($value);
		}
		$value = preg_replace('/[\s_]+/u', '-', $value);
		$value = preg_replace('/[^a-z0-9\-]+/u', '', $value);
		$value = preg_replace('/-+/', '-', $value);
		return trim($value, '-');
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

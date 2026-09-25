<?php
class ControllerExtensionModuleCyberpunksVariantImages extends Controller {
	private $error = array();
	private $product_option_index_cache = array();
	private $product_options_cache = array();

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
				$redirect_product_id = isset($this->request->post['redirect_product_id']) ? (int)$this->request->post['redirect_product_id'] : 0;
				if ($redirect_product_id > 0) {
					$this->response->redirect($this->url->link('catalog/product/edit', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $redirect_product_id, true));
				} else {
					$this->response->redirect($this->url->link('extension/module/cyberpunks_variant_images', 'user_token=' . $this->session->data['user_token'], true));
				}
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

			// Dedicated status save (top of page) — no mapping payload required.
			if (!empty($this->request->post['save_module_status'])) {
				$this->saveModuleStatus(isset($this->request->post['module_cyberpunks_variant_images_status']) ? (int)$this->request->post['module_cyberpunks_variant_images_status'] : 0);
				$this->session->data['success'] = $this->language->get('text_success');
				$this->response->redirect($this->url->link('extension/module/cyberpunks_variant_images', 'user_token=' . $this->session->data['user_token'], true));
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

			$status = isset($this->request->post['module_cyberpunks_variant_images_status'])
				? (int)$this->request->post['module_cyberpunks_variant_images_status']
				: null;

			$this->saveMappingsToSettings($merged_mappings, $status, $active_product_id > 0 ? array($active_product_id) : array());

			if ($active_product_id > 0 && array_key_exists('module_cyberpunks_variant_images_media_path', $this->request->post)) {
				$this->saveProductMediaPath(
					$active_product_id,
					$this->request->post['module_cyberpunks_variant_images_media_path']
				);
			}

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

		$this->maybeMigrateLegacyMappingsShard();
		// Skip pair hydration on page load — Variant Builder expands lazily in JS.
		$data['mappings'] = $this->normalizeMappings($this->getStoredMappingsRaw(), false);

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
		$media_paths = $this->loadAllMediaPaths();

		foreach ($data['mappings'] as $mapping) {
			$product_id = (int)$mapping['product_id'];

			if (!isset($data['mapping_groups'][$product_id])) {
				$data['mapping_groups'][$product_id] = array(
					'product_id' => $product_id,
					'product_name' => isset($data['product_name_map'][$product_id]) ? $data['product_name_map'][$product_id] : ('#' . $product_id),
					'media_path' => isset($media_paths[$product_id]) ? $media_paths[$product_id] : '',
					'mappings' => array()
				);
			}

			$data['mapping_groups'][$product_id]['mappings'][] = $mapping;
		}

		$data['mapping_groups'] = array_values($data['mapping_groups']);

		foreach ($this->language->all() as $key => $value) {
			if (!isset($data[$key])) {
				$data[$key] = $value;
			}
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/cyberpunks_variant_images', $data));
	}

	private function normalizeMappings($mappings, $resolve_pairs = true) {
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

			if ($resolve_pairs) {
				// Rebuild option pairs from named `o` map (legacy compact rows).
				if (!$pairs && $product_id > 0 && !empty($mapping['o']) && is_array($mapping['o'])) {
					$named_parts = array();
					foreach ($mapping['o'] as $raw_option_name => $raw_value_name) {
						$option_key = $this->canonicalNamedOptionKey($this->normalizeSlug($raw_option_name));
						$value_key = $this->normalizeSlug($raw_value_name);
						if ($option_key !== '' && $value_key !== '') {
							$named_parts[] = $option_key . '=' . $value_key;
						}
					}
					if ($named_parts) {
						if ($signature === '') {
							$signature = 'n:' . implode('|', $named_parts);
						}
						$pairs = $this->buildPairsFromNamedSignature($product_id, 'n:' . implode('|', $named_parts));
					}
				}

				// Rebuild option pairs from signature for compactly stored mappings.
				if (!$pairs && $product_id > 0 && $signature !== '') {
					$pairs = $this->buildPairsFromSignature($product_id, $signature);
				}
			} elseif ($signature === '' && $product_id > 0 && !empty($mapping['o']) && is_array($mapping['o'])) {
				$named_parts = array();
				foreach ($mapping['o'] as $raw_option_name => $raw_value_name) {
					$option_key = $this->canonicalNamedOptionKey($this->normalizeSlug($raw_option_name));
					$value_key = $this->normalizeSlug($raw_value_name);
					if ($option_key !== '' && $value_key !== '') {
						$named_parts[] = $option_key . '=' . $value_key;
					}
				}
				if ($named_parts) {
					$signature = 'n:' . implode('|', $named_parts);
				}
			}

			if ($product_id <= 0 && $signature === '' && $image === '') {
				continue;
			}

			// Admin UI stores/shows filename only; cart expands via media_path at runtime.
			$image = $this->imageFilenameOnly($image);

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
		if (!class_exists('CyberpunksShopVariantImagesStorage')) {
			require_once(DIR_SYSTEM . 'library/cyberpunks_shop_variant_images_storage.php');
		}

		return CyberpunksShopVariantImagesStorage::loadAll($this->registry);
	}

	private function maybeMigrateLegacyMappingsShard() {
		if (!class_exists('CyberpunksShopVariantImagesStorage')) {
			require_once(DIR_SYSTEM . 'library/cyberpunks_shop_variant_images_storage.php');
		}

		$this->load->model('setting/setting');
		$settings = $this->model_setting_setting->getSetting('module_cyberpunks_variant_images');
		$has_shards = false;

		foreach ($settings as $key => $value) {
			if (CyberpunksShopVariantImagesStorage::productIdFromMappingsKey($key) > 0) {
				$has_shards = true;
				break;
			}
		}

		if ($has_shards) {
			return;
		}

		if (empty($settings[CyberpunksShopVariantImagesStorage::LEGACY_MAPPINGS_KEY])) {
			return;
		}

		$mappings = $this->normalizeMappings($settings[CyberpunksShopVariantImagesStorage::LEGACY_MAPPINGS_KEY]);
		if ($mappings) {
			$this->saveMappingsToSettings($mappings);
		}
	}

	private function saveMappingsToSettings($mappings, $status = null, $ensure_product_ids = array()) {
		if (!class_exists('CyberpunksShopVariantImagesStorage')) {
			require_once(DIR_SYSTEM . 'library/cyberpunks_shop_variant_images_storage.php');
		}

		$grouped = array();
		foreach ($this->compactMappingsForStorage($mappings) as $row) {
			$product_id = isset($row['p']) ? (int)$row['p'] : 0;
			if ($product_id <= 0) {
				continue;
			}
			if (!isset($grouped[$product_id])) {
				$grouped[$product_id] = array();
			}
			$grouped[$product_id][] = $row;
		}

		foreach ((array)$ensure_product_ids as $product_id) {
			$product_id = (int)$product_id;
			if ($product_id > 0 && !isset($grouped[$product_id])) {
				$grouped[$product_id] = array();
			}
		}

		CyberpunksShopVariantImagesStorage::saveGrouped($this->registry, $grouped, $status);
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
		$can_module = $this->user->hasPermission('modify', 'extension/module/cyberpunks_variant_images');
		$can_product = $this->user->hasPermission('modify', 'catalog/product');

		if (!$can_module && !$can_product) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	/**
	 * Product edit tab HTML. Called via load->controller(..., $product_id).
	 */
	public function productForm($product_id = 0) {
		$this->load->language('extension/module/cyberpunks_variant_images');
		$this->load->model('catalog/product');

		$product_id = is_array($product_id)
			? (isset($product_id['product_id']) ? (int)$product_id['product_id'] : 0)
			: (int)$product_id;

		$data = array();
		foreach ($this->language->all() as $key => $value) {
			$data[$key] = $value;
		}

		$data['product_id'] = $product_id;
		$data['product_model'] = '';
		$data['media_path'] = '';
		$data['mappings'] = array();
		$data['vi_success'] = '';
		$data['vi_error'] = '';

		if (!empty($this->session->data['success']) && $product_id > 0) {
			$data['vi_success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		}
		if (!empty($this->session->data['error_warning']) && $product_id > 0) {
			$data['vi_error'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		}

		if ($product_id > 0) {
			$product_info = $this->model_catalog_product->getProduct($product_id);
			if ($product_info) {
				$data['product_model'] = isset($product_info['model']) ? (string)$product_info['model'] : '';
			}

			$this->maybeMigrateLegacyMappingsShard();
			$data['media_path'] = $this->getStoredMediaPath($product_id);
			$all = $this->normalizeMappings($this->getStoredMappingsRaw(), false);
			foreach ($all as $mapping) {
				if ((int)$mapping['product_id'] === $product_id) {
					$data['mappings'][] = $mapping;
				}
			}
		}

		$data['option_data_url'] = $this->url->link('extension/module/cyberpunks_variant_images/options', 'user_token=' . $this->session->data['user_token'], true);
		$data['export_url'] = $product_id > 0
			? $this->url->link('extension/module/cyberpunks_variant_images', 'user_token=' . $this->session->data['user_token'] . '&export_product_id=' . $product_id, true)
			: '';
		$data['import_action'] = $this->url->link('extension/module/cyberpunks_variant_images', 'user_token=' . $this->session->data['user_token'], true);

		return $this->load->view('extension/module/cyberpunks_variant_images_product_form', $data);
	}

	/**
	 * Persist mappings posted from Catalog → Product form.
	 */
	public function saveFromProductForm($product_id = 0) {
		$product_id = is_array($product_id)
			? (isset($product_id['product_id']) ? (int)$product_id['product_id'] : 0)
			: (int)$product_id;

		if ($product_id <= 0) {
			return;
		}

		if (!$this->user->hasPermission('modify', 'catalog/product')
			&& !$this->user->hasPermission('modify', 'extension/module/cyberpunks_variant_images')) {
			return;
		}

		$this->load->model('catalog/product');
		$this->load->language('extension/module/cyberpunks_variant_images');

		$has_media_path = array_key_exists('module_cyberpunks_variant_images_media_path', $this->request->post);
		$has_mappings = isset($this->request->post['module_cyberpunks_variant_images_payload'])
			|| !empty($this->request->post['module_cyberpunks_variant_images_mappings']);

		if (!$has_media_path && !$has_mappings) {
			return;
		}

		if ($has_media_path) {
			$this->saveProductMediaPath(
				$product_id,
				isset($this->request->post['module_cyberpunks_variant_images_media_path'])
					? $this->request->post['module_cyberpunks_variant_images_media_path']
					: ''
			);
		}

		if (!$has_mappings) {
			return;
		}

		$existing_mappings = $this->normalizeMappings($this->getStoredMappingsRaw());
		$incoming_mappings = array();

		if (!empty($this->request->post['module_cyberpunks_variant_images_payload'])) {
			$payload = json_decode(html_entity_decode((string)$this->request->post['module_cyberpunks_variant_images_payload'], ENT_QUOTES, 'UTF-8'), true);
			$incoming_mappings = $this->normalizeMappings(is_array($payload) ? $payload : array());
		} else {
			$incoming_mappings = $this->normalizeMappings(
				isset($this->request->post['module_cyberpunks_variant_images_mappings'])
					? $this->request->post['module_cyberpunks_variant_images_mappings']
					: array()
			);
		}

		$merged_mappings = array();
		foreach ($existing_mappings as $mapping) {
			if ((int)$mapping['product_id'] !== $product_id) {
				$merged_mappings[] = $mapping;
			}
		}

		foreach ($incoming_mappings as $mapping) {
			$mapping['product_id'] = $product_id;
			$merged_mappings[] = $mapping;
		}

		$this->saveMappingsToSettings($merged_mappings, null, array($product_id));
	}

	private function saveModuleStatus($status) {
		if (!class_exists('CyberpunksShopVariantImagesStorage')) {
			require_once(DIR_SYSTEM . 'library/cyberpunks_shop_variant_images_storage.php');
		}

		$this->load->model('setting/setting');
		$existing = $this->model_setting_setting->getSetting(CyberpunksShopVariantImagesStorage::SETTING_CODE);
		$existing['module_cyberpunks_variant_images_status'] = (int)$status ? 1 : 0;
		$this->model_setting_setting->editSetting(CyberpunksShopVariantImagesStorage::SETTING_CODE, $existing);
	}

	public function install() {
		$this->load->model('setting/setting');
		$existing = $this->model_setting_setting->getSetting('module_cyberpunks_variant_images');
		if (!is_array($existing)) {
			$existing = array();
		}
		$existing['module_cyberpunks_variant_images_status'] = 1;
		$this->model_setting_setting->editSetting('module_cyberpunks_variant_images', $existing);
	}

	private function getStoredMediaPath($product_id) {
		$paths = $this->loadAllMediaPaths();
		$product_id = (int)$product_id;

		return isset($paths[$product_id]) ? $paths[$product_id] : '';
	}

	private function loadAllMediaPaths() {
		if (!class_exists('CyberpunksShopVariantImagesStorage')) {
			require_once(DIR_SYSTEM . 'library/cyberpunks_shop_variant_images_storage.php');
		}

		return CyberpunksShopVariantImagesStorage::loadMediaPaths($this->registry);
	}

	private function saveProductMediaPath($product_id, $path) {
		if (!class_exists('CyberpunksShopVariantImagesStorage')) {
			require_once(DIR_SYSTEM . 'library/cyberpunks_shop_variant_images_storage.php');
		}

		CyberpunksShopVariantImagesStorage::saveMediaPaths($this->registry, array(
			(int)$product_id => $path
		));
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
		$merged_mappings = $this->mergeImportMappings($existing_mappings, $import_mappings);

		$import_product_ids = array();
		foreach ($import_mappings as $mapping) {
			$import_product_ids[(int)$mapping['product_id']] = (int)$mapping['product_id'];
		}

		$this->saveMappingsToSettings($merged_mappings, null, array_values($import_product_ids));

		$media_path = $this->extractImportMediaPath($raw);
		if ($media_path !== '') {
			$force_product_id = isset($this->request->post['import_target_product_id'])
				? (int)$this->request->post['import_target_product_id']
				: 0;
			$path_updates = array();
			if ($force_product_id > 0) {
				$path_updates[$force_product_id] = $media_path;
			} else {
				foreach ($import_product_ids as $product_id) {
					$path_updates[$product_id] = $media_path;
				}
			}
			if ($path_updates) {
				$this->saveProductMediaPaths($path_updates);
			}
		}

		$this->session->data['success'] = sprintf($this->language->get('text_import_success_count'), count($import_mappings));
	}

	/**
	 * Upsert import rows: same product + same option combination → rewrite image (filename).
	 * Other existing rows for that product are kept.
	 */
	private function mergeImportMappings(array $existing_mappings, array $import_mappings) {
		$merged = array();
		$index_by_key = array();

		foreach ($existing_mappings as $mapping) {
			$key = $this->mappingComboKey($mapping);
			if ($key === '') {
				$merged[] = $mapping;
				continue;
			}
			$index_by_key[$key] = count($merged);
			$merged[] = $mapping;
		}

		foreach ($import_mappings as $incoming) {
			$incoming['image'] = $this->imageFilenameOnly(isset($incoming['image']) ? $incoming['image'] : '');
			if ($incoming['image'] === '') {
				continue;
			}

			// Stable named signature (sorted pairs).
			if (!empty($incoming['option_value_signature']) && strpos($incoming['option_value_signature'], 'n:') === 0) {
				$incoming['option_value_signature'] = $this->normalizeNamedSignature($incoming['option_value_signature']);
			}

			$key = $this->mappingComboKey($incoming);
			if ($key === '') {
				$merged[] = $incoming;
				continue;
			}

			if (isset($index_by_key[$key])) {
				$idx = $index_by_key[$key];
				$merged[$idx]['image'] = $incoming['image'];
				if (isset($incoming['status'])) {
					$merged[$idx]['status'] = !empty($incoming['status']) ? 1 : 0;
				}
				// Prefer named signature when updating a legacy numeric row.
				if (!empty($incoming['option_value_signature']) && strpos($incoming['option_value_signature'], 'n:') === 0) {
					$merged[$idx]['option_value_signature'] = $incoming['option_value_signature'];
				}
			} else {
				$index_by_key[$key] = count($merged);
				$merged[] = $incoming;
			}
		}

		return $merged;
	}

	private function imageFilenameOnly($image) {
		$image = trim((string)$image);
		if ($image === '') {
			return '';
		}
		$image = str_replace('\\', '/', $image);
		$base = basename($image);
		return $base !== '' ? $base : $image;
	}

	private function normalizeNamedSignature($signature) {
		$signature = trim((string)$signature);
		if (strpos($signature, 'n:') !== 0) {
			return $signature;
		}
		$parts = array_filter(explode('|', substr($signature, 2)), 'strlen');
		sort($parts, SORT_STRING);
		return 'n:' . implode('|', $parts);
	}

	/**
	 * Comparable key: product_id + sorted option pairs (named) or numeric signature.
	 */
	private function mappingComboKey(array $mapping) {
		$product_id = isset($mapping['product_id']) ? (int)$mapping['product_id'] : (isset($mapping['p']) ? (int)$mapping['p'] : 0);
		if ($product_id <= 0) {
			return '';
		}

		$signature = isset($mapping['option_value_signature']) ? trim((string)$mapping['option_value_signature']) : (isset($mapping['s']) ? trim((string)$mapping['s']) : '');
		if ($signature === '') {
			return '';
		}

		if (strpos($signature, 'n:') === 0) {
			return $product_id . '|' . $this->normalizeNamedSignature($signature);
		}

		// Legacy numeric id signature — also expose a fingerprint via pairs when present.
		$pairs_fp = $this->fingerprintFromPairsJson(isset($mapping['pairs_json']) ? $mapping['pairs_json'] : '');
		if ($pairs_fp !== '') {
			return $product_id . '|pairs:' . $pairs_fp;
		}

		$ids = array_filter(array_map('intval', explode('-', $signature)));
		sort($ids, SORT_NUMERIC);
		return $product_id . '|ids:' . implode('-', $ids);
	}

	private function fingerprintFromPairsJson($pairs_json) {
		$raw = is_string($pairs_json) ? html_entity_decode($pairs_json, ENT_QUOTES, 'UTF-8') : '';
		$decoded = json_decode($raw, true);
		if (!is_array($decoded) || !$decoded) {
			return '';
		}
		$parts = array();
		foreach ($decoded as $pair) {
			$po = isset($pair['product_option_id']) ? (int)$pair['product_option_id'] : 0;
			$pov = isset($pair['product_option_value_id']) ? (int)$pair['product_option_value_id'] : 0;
			if ($po > 0 && $pov > 0) {
				$parts[] = $po . '=' . $pov;
			}
		}
		if (!$parts) {
			return '';
		}
		sort($parts, SORT_STRING);
		return implode('|', $parts);
	}

	private function extractImportMediaPath($raw) {
		if (!is_string($raw) || $raw === '') {
			return '';
		}

		if (preg_match('/^media_path\s*:\s*(.+)$/im', $raw, $m)) {
			return $this->unquoteYamlScalar($m[1]);
		}

		return '';
	}

	private function saveProductMediaPaths(array $paths_by_product) {
		if (!class_exists('CyberpunksShopVariantImagesStorage')) {
			require_once(DIR_SYSTEM . 'library/cyberpunks_shop_variant_images_storage.php');
		}

		CyberpunksShopVariantImagesStorage::saveMediaPaths($this->registry, $paths_by_product);
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

		$this->saveMappingsToSettings($filtered, null, array($product_id));
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
		$model = $this->getProductModel($product_id);
		$slug = $model !== '' ? $this->sanitizeFilename($model) : ('product_' . $product_id);
		$filename = 'variant_images_' . $slug . '.yaml';

		$this->response->addHeader('Content-Type: text/yaml; charset=utf-8');
		$this->response->addHeader('Content-Disposition: attachment; filename="' . $filename . '"');
		$this->response->setOutput($yaml);
	}

	private function getProductModel($product_id) {
		$product_info = $this->model_catalog_product->getProduct((int)$product_id);
		if (!$product_info || !isset($product_info['model'])) {
			return '';
		}
		return trim((string)$product_info['model']);
	}

	private function sanitizeFilename($value) {
		$value = strtolower(trim((string)$value));
		$value = preg_replace('/[^a-z0-9\-]+/', '-', $value);
		$value = trim($value, '-');
		return $value !== '' ? $value : 'product';
	}

	private function findProductIdByModel($model) {
		$model = trim((string)$model);
		if ($model === '') {
			return 0;
		}

		$query = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE LCASE(model) = '" . $this->db->escape(utf8_strtolower($model)) . "' LIMIT 2");
		if ($query->num_rows === 1) {
			return (int)$query->row['product_id'];
		}

		return 0;
	}

	private function buildYamlExport($product_id, $rows) {
		$lines = array();
		$model = $this->getProductModel($product_id);
		if ($model !== '') {
			$lines[] = 'model: ' . $this->yamlScalar($model);
		} else {
			$lines[] = 'product_id: ' . (int)$product_id;
		}

		$media_path = $this->getStoredMediaPath($product_id);
		if ($media_path !== '') {
			$lines[] = 'media_path: ' . $this->yamlScalar($media_path);
		}

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
			if ($media_path !== '' && $image !== '') {
				$image_norm = ltrim(str_replace('\\', '/', $image), '/');
				$prefix = $media_path . '/';
				if (stripos($image_norm, $prefix) === 0) {
					$image = substr($image_norm, strlen($prefix));
				} elseif (strcasecmp($image_norm, $media_path) === 0) {
					$image = '';
				}
			}
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
			$model = '';
			$image = '';
			$options = array();

			if (preg_match('/product_id\s*:\s*(\d+)/i', $block, $m)) {
				$product_id = (int)$m[1];
			}

			if (preg_match('/model\s*:\s*["\']?(.+?)["\']?\s*$/im', $block, $m)) {
				$model = trim($m[1], " \t\"'");
			}

			if (preg_match('/image\s*:\s*["\']?(.+?)["\']?\s*$/im', $block, $m)) {
				$image = trim($m[1]);
			}

			if (preg_match('/options\s*:\s*\{(.*?)\}/is', $block, $m)) {
				$options = $this->parseInlineOptions($m[1]);
			}

			if (($model !== '' || $product_id > 0) && $image !== '' && $options) {
				$result[] = array(
					'product_id' => $product_id,
					'model' => $model,
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
		$product_model = '';
		$entries = array();
		$current = array();
		$in_options_block = false;

		// Product-tab import posts a target id — allow rows even if YAML omits model/product_id.
		$force_product_id = isset($this->request->post['import_target_product_id'])
			? (int)$this->request->post['import_target_product_id']
			: 0;

		if ($force_product_id > 0) {
			$product_id = $force_product_id;
		}

		foreach ($lines as $line) {
			$trimmed = trim($line);
			if ($trimmed === '' || $trimmed === '{' || $trimmed === '}' || $trimmed === '---') {
				continue;
			}

			// Top-level YAML keys that are not mapping rows.
			if (preg_match('/^(media_path|items)\s*:/i', $trimmed)) {
				if (preg_match('/^media_path\s*:\s*(.+)$/i', $trimmed, $m)) {
					// Keep parser focused; extractImportMediaPath reads the raw file.
				}
				continue;
			}

			if (preg_match('/^product_id\s*:\s*(\d+)$/i', $trimmed, $m)) {
				$product_id = (int)$m[1];
				continue;
			}

			if (preg_match('/^model\s*:\s*(.+)$/i', $trimmed, $m)) {
				$product_model = $this->unquoteYamlScalar($m[1]);
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

			if (preg_match('/^-?\s*image\s*:\s*(.+)$/i', $trimmed, $m)) {
				$current['image'] = $this->unquoteYamlScalar($m[1]);
				$in_options_block = false;

				$row_product_id = $product_id > 0 ? $product_id : $force_product_id;

				if (($product_model !== '' || $row_product_id > 0) && !empty($current['image']) && !empty($current['options'])) {
					$entries[] = array(
						'product_id' => $row_product_id,
						'model' => $product_model,
						'options' => $current['options'],
						'image' => $current['image']
					);
				}

				$current = array();
				continue;
			}

			if ($in_options_block && preg_match('/^([a-z0-9\-_]+)\s*:\s*(.+)$/i', $trimmed, $m)) {
				$key = trim($m[1], " \t\n\r\0\x0B\"'");
				$value = $this->unquoteYamlScalar($m[2]);
				if ($key !== '' && $value !== '') {
					$current['options'][$key] = $value;
				}
			}
		}

		return $entries;
	}

	private function unquoteYamlScalar($value) {
		$value = trim((string)$value);
		$value = trim($value, " \t\n\r\0\x0B\"'");
		return $value;
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
		$force_product_id = isset($this->request->post['import_target_product_id'])
			? (int)$this->request->post['import_target_product_id']
			: 0;

		foreach ($entries as $entry) {
			$product_id = isset($entry['product_id']) ? (int)$entry['product_id'] : 0;
			$model = isset($entry['model']) ? trim((string)$entry['model']) : '';
			$image = isset($entry['image']) ? trim((string)$entry['image']) : '';
			$options = isset($entry['options']) && is_array($entry['options']) ? $entry['options'] : array();

			if ($force_product_id > 0) {
				$product_id = $force_product_id;
			} elseif ($model !== '') {
				$resolved = $this->findProductIdByModel($model);
				if ($resolved > 0) {
					$product_id = $resolved;
				}
			}

			if ($product_id <= 0 || $image === '' || !$options) {
				continue;
			}

			$product_options = $this->getCachedProductOptions($product_id);
			if (!$product_options) {
				continue;
			}

			$index = $this->getCachedProductOptionIndex($product_id);
			$named_parts = array();
			$resolved_count = 0;
			$declared_count = 0;

			foreach ($options as $raw_option_name => $raw_value_name) {
				$declared_count++;
				$option_key = $this->canonicalNamedOptionKey($this->normalizeSlug($raw_option_name));
				$value_key = $this->normalizeSlug($raw_value_name);
				$value_slug = '';

				if (isset($index[$option_key]['values'][$value_key])) {
					$value_slug = $value_key;
				} else {
					$value_compact = str_replace('-', '', $value_key);
					if (isset($index[$option_key]['values'][$value_compact])) {
						$value_slug = $value_compact;
					}
				}

				// Skip the whole row if any YAML option fails — otherwise a Hood-* image can
				// be stored under a shorter signature and win for "no hood" carts.
				if ($option_key === '' || $value_slug === '') {
					$named_parts = array();
					break;
				}

				$named_parts[] = $option_key . '=' . $value_slug;
				$resolved_count++;
			}

			if (!$named_parts || $resolved_count !== $declared_count) {
				continue;
			}

			sort($named_parts, SORT_STRING);

			$result[] = array(
				'product_id' => $product_id,
				'option_value_signature' => 'n:' . implode('|', $named_parts),
				'image' => $this->imageFilenameOnly($image),
				'status' => 1
			);
		}

		return $result;
	}

	private function compactMappingsForStorage($mappings) {
		if (!class_exists('CyberpunksShopVariantImagesStorage')) {
			require_once(DIR_SYSTEM . 'library/cyberpunks_shop_variant_images_storage.php');
		}

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

			$image = CyberpunksShopVariantImagesStorage::compactImagePathForStorage($image);
			// Prefer bare filename when a full theme path was pasted/imported.
			if (strpos($image, '/') !== false) {
				$base = basename($image);
				if ($base !== '') {
					$image = $base;
				}
			}

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
		$signature = trim((string)$signature);
		if ($signature === '') {
			return array();
		}

		if (strpos($signature, 'n:') === 0) {
			return $this->buildPairsFromNamedSignature((int)$product_id, $signature);
		}

		$result = array();
		$ids = array_filter(array_map('intval', explode('-', $signature)));
		if (!$ids) {
			return $result;
		}

		$target_lookup = array_flip($ids);
		$product_options = $this->getCachedProductOptions((int)$product_id);
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

	private function buildPairsFromNamedSignature($product_id, $signature) {
		$result = array();
		$wanted = array();

		$named = substr(trim((string)$signature), 2);
		foreach (explode('|', $named) as $part) {
			$part = trim($part);
			if ($part === '' || strpos($part, '=') === false) {
				continue;
			}

			list($raw_key, $raw_value) = explode('=', $part, 2);
			$option_key = $this->canonicalNamedOptionKey($this->normalizeSlug($raw_key));
			$value_key = $this->normalizeSlug($raw_value);
			$value_compact = str_replace('-', '', $value_key);

			if ($option_key === '' || ($value_key === '' && $value_compact === '')) {
				continue;
			}

			$wanted[] = array(
				'option_key' => $option_key,
				'value_key' => $value_key,
				'value_compact' => $value_compact
			);
		}

		if (!$wanted) {
			return $result;
		}

		$index = $this->getCachedProductOptionIndex((int)$product_id);
		if (!$index) {
			return $result;
		}

		foreach ($wanted as $item) {
			if (!isset($index[$item['option_key']])) {
				continue;
			}

			$values = isset($index[$item['option_key']]['values']) && is_array($index[$item['option_key']]['values'])
				? $index[$item['option_key']]['values']
				: array();
			$value_item = null;

			if ($item['value_key'] !== '' && isset($values[$item['value_key']])) {
				$value_item = $values[$item['value_key']];
			} else {
				foreach ($values as $vk => $payload) {
					if (str_replace('-', '', (string)$vk) === $item['value_compact']) {
						$value_item = $payload;
						break;
					}
				}
			}

			if (!$value_item) {
				continue;
			}

			$result[] = array(
				'product_option_id' => (int)$index[$item['option_key']]['product_option_id'],
				'product_option_value_id' => (int)$value_item['product_option_value_id'],
				'option_value_id' => (int)$value_item['option_value_id']
			);
		}

		return $result;
	}

	private function getCachedProductOptions($product_id) {
		$product_id = (int)$product_id;
		if ($product_id <= 0) {
			return array();
		}

		if (isset($this->product_options_cache[$product_id])) {
			return $this->product_options_cache[$product_id];
		}

		$product_options = $this->model_catalog_product->getProductOptions($product_id);
		$this->product_options_cache[$product_id] = is_array($product_options) ? $product_options : array();
		return $this->product_options_cache[$product_id];
	}

	private function getCachedProductOptionIndex($product_id) {
		$product_id = (int)$product_id;
		if ($product_id <= 0) {
			return array();
		}

		if (isset($this->product_option_index_cache[$product_id])) {
			return $this->product_option_index_cache[$product_id];
		}

		$product_options = $this->getCachedProductOptions($product_id);
		if (!$product_options) {
			$this->product_option_index_cache[$product_id] = array();
			return array();
		}

		$this->product_option_index_cache[$product_id] = $this->indexProductOptions($product_id, $product_options);
		return $this->product_option_index_cache[$product_id];
	}

	private function canonicalNamedOptionKey($option_key) {
		$option_key = trim((string)$option_key);
		$compact = str_replace('-', '', $option_key);
		$aliases = array(
			'urbanemotion' => 'urban-emotion',
			'urbancolor' => 'urban-color',
			'insightcolor' => 'insight-color',
			'urbanhoodcolor' => 'urban-hood-color',
			'urbanwallmount' => 'urban-wallmount'
		);

		return isset($aliases[$compact]) ? $aliases[$compact] : $option_key;
	}

	private function indexProductOptions($product_id, $product_options) {
		$result = array();

		foreach ($product_options as $product_option) {
			$option_key = $this->normalizeSlug($product_option['name']);
			$values = array();

			if (!empty($product_option['product_option_value']) && is_array($product_option['product_option_value'])) {
				foreach ($product_option['product_option_value'] as $product_option_value) {
					$value_info = $this->model_catalog_product->getProductOptionValue((int)$product_id, (int)$product_option_value['product_option_value_id']);
					$value_name = !empty($value_info['name']) ? $value_info['name'] : '';
					$value_key = $this->normalizeSlug($value_name);

					if ($value_key === '') {
						continue;
					}

					$value_payload = array(
						'product_option_value_id' => (int)$product_option_value['product_option_value_id'],
						'option_value_id' => (int)$product_option_value['option_value_id']
					);

					foreach ($this->buildValueAliases($value_key) as $value_alias) {
						$values[$value_alias] = $value_payload;
					}

					// Match storage-style value slugs without hyphens (e.g. brightgreen).
					$values[str_replace('-', '', $value_key)] = $value_payload;
				}
			}

			$option_payload = array(
				'product_option_id' => (int)$product_option['product_option_id'],
				'values' => $values
			);

			$keys = array_merge(array($option_key), $this->buildOptionAliases($option_key));
			foreach (array_values(array_unique($keys)) as $key) {
				$result[$key] = $option_payload;
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
		if (strpos($option_key, 'wall') !== false || strpos($option_key, 'mount') !== false) {
			$aliases[] = 'urban-wallmount';
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

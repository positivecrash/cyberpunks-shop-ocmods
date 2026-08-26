<?php
class ModelExtensionModuleCyberpunksShopProductFields extends Model {
	public function getProductFieldsMap($product_id) {
		$table = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_product_field") . "'");
		$value_table = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_product_field_value") . "'");

		if (!$table->num_rows || !$value_table->num_rows) {
			return array();
		}

		$data = array();
		$query = $this->db->query("SELECT f.field_key, f.field_type, v.value FROM `" . DB_PREFIX . "cyberpunks_product_field_value` v LEFT JOIN `" . DB_PREFIX . "cyberpunks_product_field` f ON (v.field_id = f.field_id) WHERE v.product_id = '" . (int)$product_id . "' AND f.status = '1'");

		foreach ($query->rows as $row) {
			if (empty($row['field_key'])) {
				continue;
			}

			$value = $row['value'];
			$field_type = isset($row['field_type']) ? $row['field_type'] : 'text';

			if ($field_type === 'html' || $field_type === 'textarea') {
				$value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
				$value = $this->expandIconShortcodes($value);
				$value = $this->expandOptionShortcodes($value);
			} elseif ($field_type === 'image') {
				$value = $this->resolveImageFieldValue($value);
			} elseif ($field_type === 'checkboxes') {
				$value = $this->decodeCheckboxListValue($value);
			} elseif ($field_type === 'repeater') {
				$value = $this->decodeRepeaterValue($value);
			}

			$data[$row['field_key']] = $value;
		}

		return $data;
	}

	public function getHomeFeaturedCategories($limit = 3, $flag_key = 'featured') {
		$limit = max(1, (int)$limit);
		$flag_key = preg_replace('/[^a-z0-9_]/', '', (string)$flag_key);

		if ($flag_key === '') {
			return array();
		}

		$table = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_product_field") . "'");
		$value_table = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_product_field_value") . "'");

		if (!$table->num_rows || !$value_table->num_rows) {
			return array();
		}

		$flag_field = $this->db->query("SELECT field_id FROM `" . DB_PREFIX . "cyberpunks_product_field` WHERE field_key = '" . $this->db->escape($flag_key) . "' AND status = '1' LIMIT 1");

		if (!$flag_field->num_rows) {
			return array();
		}

		$flag_field_id = (int)$flag_field->row['field_id'];

		$categories = $this->db->query("SELECT DISTINCT c.category_id, cd.name, c.sort_order
			FROM `" . DB_PREFIX . "category` c
			LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id)
			LEFT JOIN `" . DB_PREFIX . "category_to_store` c2s ON (c.category_id = c2s.category_id)
			INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (c.category_id = p2c.category_id)
			INNER JOIN `" . DB_PREFIX . "product` p ON (p2c.product_id = p.product_id)
			INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
			INNER JOIN `" . DB_PREFIX . "cyberpunks_product_field_value` fv ON (fv.product_id = p.product_id AND fv.field_id = '" . $flag_field_id . "' AND fv.value = '1')
			WHERE cd.language_id = '" . (int)$this->config->get('config_language_id') . "'
				AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
				AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
				AND c.status = '1'
				AND p.status = '1'
				AND p.date_available <= NOW()
			ORDER BY c.sort_order ASC, LCASE(cd.name) ASC")->rows;

		if (!$categories) {
			return array();
		}

		$this->load->model('tool/image');

		$result = array();

		foreach ($categories as $category) {
			$category_id = (int)$category['category_id'];

			$products_query = $this->db->query("SELECT p.product_id, pd.name, p.image, p.price, p.tax_class_id, p.date_added
				FROM `" . DB_PREFIX . "product` p
				LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id)
				LEFT JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
				INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p.product_id = p2c.product_id)
				INNER JOIN `" . DB_PREFIX . "cyberpunks_product_field_value` fv ON (fv.product_id = p.product_id AND fv.field_id = '" . $flag_field_id . "' AND fv.value = '1')
				WHERE p2c.category_id = '" . $category_id . "'
					AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
					AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
					AND p.status = '1'
					AND p.date_available <= NOW()
				ORDER BY p.date_added DESC
				LIMIT " . (int)$limit);

			if (!$products_query->num_rows) {
				continue;
			}

			$products = array();

			foreach ($products_query->rows as $product) {
				$product_id = (int)$product['product_id'];

				$products[] = array(
					'product_id' => $product_id,
					'name'       => $product['name'],
					'href'       => $this->url->link('product/product', 'product_id=' . $product_id),
					'image'      => $this->resolveImageFieldValue($product['image']),
					'price'      => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
					'fields'     => $this->getProductFieldsMap($product_id)
				);
			}

			$result[] = array(
				'category_id' => $category_id,
				'name'        => $category['name'],
				'href'        => $this->url->link('product/category', 'path=' . $category_id),
				'products'    => $products
			);
		}

		return $result;
	}

	/**
	 * Attach generic fields map for listing / cart templates.
	 * Returns the product array (must assign — OC model Proxy breaks by-ref args).
	 */
	public function attachProductFields(array $product) {
		$product['fields'] = $this->getProductFieldsMap((int)$product['product_id']);

		if (empty($product['image']) && !empty($product['thumb'])) {
			$product['image'] = $product['thumb'];
		}

		return $product;
	}

	/**
	 * Register product checkbox-list scripts (field `3d_scripts`) once in Document header.
	 * Do not also output these paths as <script> in product twig (avoids THREE double-init warning).
	 */
	public function registerProductScripts($document, array $fields_map, $field_key = '3d_scripts') {
		if (!is_object($document) || !method_exists($document, 'addScript')) {
			return;
		}

		if (empty($fields_map[$field_key]) || !is_array($fields_map[$field_key])) {
			return;
		}

		$script_types = array();

		if ($this->registry->has('cyberpunks_shop_head_includes_script_types')) {
			$script_types = (array)$this->registry->get('cyberpunks_shop_head_includes_script_types');
		}

		$seen = array();

		foreach ($fields_map[$field_key] as $script_path) {
			$script_path = trim((string)$script_path);

			if ($script_path === '') {
				continue;
			}

			$is_module = false;

			if (stripos($script_path, 'module:') === 0) {
				$is_module = true;
				$script_path = trim(substr($script_path, 7));
			}

			if ($script_path === '' || isset($seen[$script_path])) {
				continue;
			}

			$seen[$script_path] = true;
			$document->addScript($script_path, 'header');

			if ($is_module) {
				$script_types[$script_path] = 'module';
			}
		}

		if ($script_types) {
			$this->registry->set('cyberpunks_shop_head_includes_script_types', $script_types);
		}
	}

	private function resolveImageFieldValue($path) {
		$path = trim((string)$path);

		if ($path === '') {
			return '';
		}

		if (strpos($path, 'catalog/view/') === 0 || strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0 || strpos($path, '//') === 0) {
			return $path;
		}

		$this->load->model('tool/image');

		if (!is_file(DIR_IMAGE . $path)) {
			return '';
		}

		$theme = $this->config->get('config_theme');
		$width = (int)$this->config->get('theme_' . $theme . '_image_product_width');
		$height = (int)$this->config->get('theme_' . $theme . '_image_product_height');

		if ($width < 1) {
			$width = 400;
		}

		if ($height < 1) {
			$height = 400;
		}

		return $this->model_tool_image->resize($path, $width, $height);
	}

	/**
	 * Expand [[icon:name]] and [[icon:name attr="value" ...]] into SVG sprite icons.
	 */
	private function expandIconShortcodes($text) {
		if ($text === '' || strpos($text, '[[icon:') === false) {
			return $text;
		}

		return preg_replace_callback(
			'/\[\[\s*icon:([a-z0-9_-]+)((?:\s+[a-zA-Z_:][\w:.-]*="[^"]*")*)\s*\]\]/i',
			function ($matches) {
				$name = strtolower($matches[1]);
				$extra = isset($matches[2]) ? trim($matches[2]) : '';
				$aria_hidden = 'true';

				if (preg_match('/\baria-hidden="(true|false)"/i', $extra, $aria_match)) {
					$aria_hidden = strtolower($aria_match[1]);
					$extra = trim(preg_replace('/\baria-hidden="(true|false)"/i', '', $extra));
				}

				$svg = '<svg class="icon icon-' . $name . '" aria-hidden="' . $aria_hidden . '" focusable="false"';

				if ($extra !== '') {
					$svg .= ' ' . $extra;
				}

				$svg .= '><use href="catalog/view/theme/cybershops/media/icons-sprite.svg#icon-' . $name . '"></use></svg>';

				return $svg;
			},
			$text
		);
	}

	/**
	 * Expand [[option:key]] into Cyberpunks Shop Common Options values.
	 * Text/url/image values are HTML-escaped; html/textarea values are inserted raw.
	 */
	private function expandOptionShortcodes($text) {
		if ($text === '' || strpos($text, '[[option:') === false) {
			return $text;
		}

		$options = array();
		$html_keys = array();

		if (is_file(DIR_APPLICATION . 'model/extension/module/cyberpunks_shop_common_options.php')) {
			$this->load->model('extension/module/cyberpunks_shop_common_options');
			$options = $this->model_extension_module_cyberpunks_shop_common_options->getOptionsMap();
			$html_keys = $this->model_extension_module_cyberpunks_shop_common_options->getHtmlFieldKeys();
		}

		return preg_replace_callback(
			'/\[\[\s*option:([a-z0-9_]+)\s*\]\]/i',
			function ($matches) use ($options, $html_keys) {
				$key = strtolower($matches[1]);

				if (!isset($options[$key])) {
					return '';
				}

				$value = (string)$options[$key];

				if (in_array($key, $html_keys, true)) {
					return $value;
				}

				return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
			},
			$text
		);
	}

	private function decodeRepeaterValue($raw) {
		$raw = trim((string)$raw);

		if ($raw === '') {
			return array();
		}

		$decoded = json_decode($raw, true);

		if (!is_array($decoded)) {
			return array();
		}

		$rows = array();

		foreach ($decoded as $row) {
			if (!is_array($row)) {
				continue;
			}

			$clean_row = array();

			foreach ($row as $sub_key => $sub_value) {
				$sub_key = trim((string)$sub_key);

				if ($sub_key === '') {
					continue;
				}

				if (is_array($sub_value)) {
					$clean_list = array();

					foreach ($sub_value as $item) {
						$item = trim((string)$item);

						if ($item !== '') {
							$clean_list[] = $item;
						}
					}

					$clean_row[$sub_key] = $clean_list;
				} else {
					$clean_row[$sub_key] = (string)$sub_value;
				}
			}

			if ($clean_row) {
				$rows[] = $clean_row;
			}
		}

		return $rows;
	}

	private function decodeCheckboxListValue($raw) {
		$raw = trim((string)$raw);

		if ($raw === '') {
			return array();
		}

		$decoded = json_decode($raw, true);

		if (is_array($decoded)) {
			$values = array();

			foreach ($decoded as $item) {
				$item = trim((string)$item);

				if ($item !== '') {
					$values[] = $item;
				}
			}

			return $values;
		}

		$lines = preg_split('/\R/', $raw);
		$values = array();

		foreach ($lines as $line) {
			$line = trim($line);

			if ($line !== '') {
				$values[] = $line;
			}
		}

		return $values;
	}
}

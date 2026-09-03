<?php
class ModelExtensionModuleCyberpunksShopMenu extends Model {
	public function getMenuItems() {
		if (!(int)$this->config->get('module_cyberpunks_shop_menu_status')) {
			return array();
		}

		$raw = $this->config->get('module_cyberpunks_shop_menu_items');

		if (!is_array($raw) || !$raw) {
			return array();
		}

		$items = array();

		foreach ($raw as $row) {
			$category_id = isset($row['category_id']) ? (int)$row['category_id'] : 0;
			$name = isset($row['name']) ? trim((string)$row['name']) : '';

			// Prefer live category name for current storefront language (admin Name is only a fallback).
			if ($category_id > 0) {
				$translated = $this->getCategoryName($category_id);

				if ($translated !== '') {
					$name = $translated;
				}
			}

			if (empty($row['status']) || $name === '') {
				continue;
			}

			$panel = isset($row['panel']) ? (string)$row['panel'] : 'none';

			if (!in_array($panel, array('none', 'products', 'links'), true)) {
				$panel = 'none';
			}

			if ($category_id > 0) {
				$href = $this->url->link('product/category', 'path=' . $category_id);
			} else {
				$href = $this->resolveHref(isset($row['href']) ? $row['href'] : '');
			}

			$item = array(
				'name'     => $name,
				'href'     => $href,
				'panel'    => $panel,
				'products' => array(),
				'links'    => array()
			);

			if ($panel === 'products') {
				if ($category_id > 0) {
					$item['products'] = $this->getFeaturedProductsByCategory($category_id);
				}
			} elseif ($panel === 'links' && !empty($row['links']) && is_array($row['links'])) {
				foreach ($row['links'] as $link) {
					if (empty($link['name'])) {
						continue;
					}

					$item['links'][] = array(
						'name' => (string)$link['name'],
						'href' => $this->resolveHref(isset($link['href']) ? $link['href'] : '')
					);
				}
			}

			$items[] = $item;
		}

		return $items;
	}

	private function getCategoryName($category_id) {
		$category_id = (int)$category_id;

		if ($category_id < 1) {
			return '';
		}

		$query = $this->db->query("SELECT name FROM `" . DB_PREFIX . "category_description`
			WHERE category_id = '" . (int)$category_id . "'
				AND language_id = '" . (int)$this->config->get('config_language_id') . "'
			LIMIT 1");

		if (!$query->num_rows) {
			return '';
		}

		return html_entity_decode((string)$query->row['name'], ENT_QUOTES, 'UTF-8');
	}

	private function resolveHref($href) {
		$href = trim((string)$href);

		if ($href === '') {
			return '#';
		}

		return $href;
	}

	/**
	 * Featured products in a category (custom field featured=1), with fields map for theme cards.
	 */
	private function getFeaturedProductsByCategory($category_id, $limit = 24) {
		$category_id = (int)$category_id;
		$limit = max(1, (int)$limit);

		if ($category_id < 1) {
			return array();
		}

		$table = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_product_field") . "'");
		$value_table = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_product_field_value") . "'");

		if (!$table->num_rows || !$value_table->num_rows) {
			return array();
		}

		$featured_field = $this->db->query("SELECT field_id FROM `" . DB_PREFIX . "cyberpunks_product_field` WHERE field_key = 'featured' AND status = '1' LIMIT 1");

		if (!$featured_field->num_rows) {
			return array();
		}

		$featured_field_id = (int)$featured_field->row['field_id'];

		$query = $this->db->query("SELECT p.product_id, pd.name, p.image, p.price, p.tax_class_id, p.date_added
			FROM `" . DB_PREFIX . "product` p
			LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id)
			LEFT JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
			INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p.product_id = p2c.product_id)
			INNER JOIN `" . DB_PREFIX . "cyberpunks_product_field_value` fv ON (fv.product_id = p.product_id AND fv.field_id = '" . $featured_field_id . "' AND fv.value = '1')
			WHERE p2c.category_id = '" . $category_id . "'
				AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
				AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
				AND p.status = '1'
				AND p.date_available <= NOW()
			ORDER BY p.date_added DESC
			LIMIT " . (int)$limit);

		if (!$query->num_rows) {
			return array();
		}

		$this->load->model('tool/image');

		$has_fields_model = is_file(DIR_APPLICATION . 'model/extension/module/cyberpunks_shop_product_fields.php');

		if ($has_fields_model) {
			$this->load->model('extension/module/cyberpunks_shop_product_fields');
		}

		$products = array();

		foreach ($query->rows as $product) {
			$product_id = (int)$product['product_id'];
			$image = '';

			if (!empty($product['image']) && is_file(DIR_IMAGE . $product['image'])) {
				$image = $this->model_tool_image->resize($product['image'], 400, 400);
			} else {
				$image = $this->model_tool_image->resize('placeholder.png', 400, 400);
			}

			$fields = array();

			if ($has_fields_model) {
				$fields = $this->model_extension_module_cyberpunks_shop_product_fields->getProductFieldsMap($product_id);
			}

			$products[] = array(
				'product_id' => $product_id,
				'name'       => $product['name'],
				'href'       => $this->url->link('product/product', 'product_id=' . $product_id),
				'image'      => $image,
				'price'      => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
				'fields'     => $fields
			);
		}

		return $products;
	}
}

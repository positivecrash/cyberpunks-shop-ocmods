<?php
class ModelExtensionModuleCyberpunksShopOptionFields extends Model {
	public function getOptionMode($option_id) {
		$option_id = (int)$option_id;
		$defaults = array(
			'show_image' => 0,
			'enabled_field_ids' => array(),
			'values_mode' => 'default',
			'filament_type' => ''
		);

		if ($option_id <= 0 || !$this->displayModeTableExists()) {
			return $defaults;
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cyberpunks_option_display_mode` WHERE option_id = '" . $option_id . "'");

		if (!$query->num_rows) {
			return $defaults;
		}

		$enabled_field_ids = array();

		if (!empty($query->row['enabled_field_ids'])) {
			$enabled_field_ids = json_decode($query->row['enabled_field_ids'], true);

			if (!is_array($enabled_field_ids)) {
				$enabled_field_ids = array();
			}
		}

		$values_mode = !empty($query->row['values_mode']) ? (string)$query->row['values_mode'] : 'default';

		if ($values_mode !== 'color_palette') {
			$values_mode = 'default';
		}

		$filament_type = isset($query->row['filament_type']) ? strtoupper(trim((string)$query->row['filament_type'])) : '';

		if (!in_array($filament_type, array('PLA', 'ASA', 'ABS'), true)) {
			$filament_type = '';
		}

		return array(
			'show_image' => (int)$query->row['show_image'],
			'enabled_field_ids' => $enabled_field_ids,
			'values_mode' => $values_mode,
			'filament_type' => $filament_type
		);
	}

	public function paletteHasInStockColor($palette_id) {
		require_once(DIR_SYSTEM . 'library/cyberpunks_palette_stock.php');

		return CyberpunksPaletteStock::paletteHasInStockColor($this->db, $palette_id);
	}

	public function isOptionValueInStock($option_value_id) {
		require_once(DIR_SYSTEM . 'library/cyberpunks_palette_stock.php');

		return CyberpunksPaletteStock::isOptionValueInStock($this->db, $option_value_id);
	}

	public function isProductOptionValueInStock($product_option_value_id) {
		require_once(DIR_SYSTEM . 'library/cyberpunks_palette_stock.php');

		return CyberpunksPaletteStock::isProductOptionValueInStock($this->db, $product_option_value_id);
	}

	/**
	 * @param int   $product_id
	 * @param array $option POST-style map: product_option_id => value or array of values
	 * @return string Empty when OK, otherwise error message
	 */
	public function validateSelectedOptionsPaletteStock($product_id, array $option) {
		$product_id = (int)$product_id;

		if ($product_id <= 0 || !$option || !$this->tablesExist()) {
			return '';
		}

		$this->load->model('catalog/product');
		$product_options = $this->model_catalog_product->getProductOptions($product_id);

		foreach ($product_options as $product_option) {
			$product_option_id = (int)$product_option['product_option_id'];

			if (!isset($option[$product_option_id]) || $option[$product_option_id] === '' || $option[$product_option_id] === array()) {
				continue;
			}

			$selected = $option[$product_option_id];
			$selected_ids = is_array($selected) ? $selected : array($selected);

			foreach ($selected_ids as $selected_value) {
				$product_option_value_id = (int)$selected_value;

				if ($product_option_value_id <= 0) {
					continue;
				}

				if (!$this->isProductOptionValueInStock($product_option_value_id)) {
					$color_name = $this->getProductOptionValueLabel($product_option_value_id, isset($product_option['name']) ? $product_option['name'] : '');

					return $color_name !== ''
						? sprintf('The selected color "%s" is currently out of stock.', $color_name)
						: 'One of the selected colors is currently out of stock.';
				}
			}
		}

		return '';
	}

	/**
	 * Validate palette stock for a cart line item (option rows from Cart::getProducts()).
	 */
	public function validateCartProductPaletteStock(array $cart_product) {
		require_once(DIR_SYSTEM . 'library/cyberpunks_palette_stock.php');

		return CyberpunksPaletteStock::validateCartProductPaletteStock($this->db, $cart_product);
	}

	private function getProductOptionValueLabel($product_option_value_id, $fallback = '') {
		$query = $this->db->query("SELECT ovd.name
			FROM `" . DB_PREFIX . "product_option_value` pov
			LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd ON (pov.option_value_id = ovd.option_value_id)
			WHERE pov.product_option_value_id = '" . (int)$product_option_value_id . "'
			AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'
			LIMIT 1");

		if ($query->num_rows && trim((string)$query->row['name']) !== '') {
			return trim((string)$query->row['name']);
		}

		return trim((string)$fallback);
	}

	public function getColorFieldsForOptionValue($option_value_id) {
		$option_value_id = (int)$option_value_id;

		if ($option_value_id <= 0) {
			return array();
		}

		if (!$this->tablesExist()) {
			return array();
		}

		$in_stock_sql = $this->paletteColorTableHasInStockColumn() ? ', c.in_stock, c.palette_id' : '';

		$query = $this->db->query("SELECT c.name, c.swatch_color, c.model_color, c.is_random" . $in_stock_sql . "
			FROM `" . DB_PREFIX . "cyberpunks_option_value_palette_color` l
			LEFT JOIN `" . DB_PREFIX . "cyberpunks_color_palette_color` c ON (l.color_id = c.color_id)
			LEFT JOIN `" . DB_PREFIX . "cyberpunks_color_palette` p ON (c.palette_id = p.palette_id)
			WHERE l.option_value_id = '" . $option_value_id . "'
			AND p.status = '1'
			LIMIT 1");

		if (!$query->num_rows) {
			return array();
		}

		$row = $query->row;
		$is_random = !empty($row['is_random']) || strtolower((string)$row['swatch_color']) === 'random' || strtolower((string)$row['model_color']) === 'random';

		if ($is_random) {
			$palette_in_stock = $this->paletteHasInStockColor((int)$row['palette_id']);

			return array(
				'color' => 'random',
				'swatch_color' => 'random',
				'model_color' => 'random',
				'palette_in_stock' => $palette_in_stock
			);
		}

		$swatch_color = trim((string)$row['swatch_color']);
		$model_color = trim((string)$row['model_color']);

		if ($model_color === '') {
			$model_color = $swatch_color;
		}

		$palette_in_stock = true;

		if ($this->paletteColorTableHasInStockColumn()) {
			$palette_in_stock = !empty($row['in_stock']);
		}

		return array(
			'color' => $swatch_color,
			'swatch_color' => $swatch_color,
			'model_color' => $model_color,
			'palette_in_stock' => $palette_in_stock
		);
	}

	public function mergeColorFields(array $cyberpunks_fields, $option_value_id) {
		$palette_fields = $this->getColorFieldsForOptionValue($option_value_id);

		if (!$palette_fields) {
			return $cyberpunks_fields;
		}

		foreach ($palette_fields as $key => $value) {
			$cyberpunks_fields[$key] = $value;
		}

		return $cyberpunks_fields;
	}

	/**
	 * Per-product gallery slide index (Product → Option → Slide #).
	 * Merges as gallery_banner_index when set (e.g. "5" or "5,6").
	 */
	public function mergeGalleryBannerIndex(array $cyberpunks_fields, $product_id, $option_value_id) {
		$product_id = (int)$product_id;
		$option_value_id = (int)$option_value_id;

		if ($product_id <= 0 || $option_value_id <= 0 || !$this->galleryTableExists()) {
			return $cyberpunks_fields;
		}

		$query = $this->db->query("SELECT gallery_banner_index FROM `" . DB_PREFIX . "cyberpunks_product_option_gallery`
			WHERE product_id = '" . $product_id . "'
			AND option_value_id = '" . $option_value_id . "'
			LIMIT 1");

		if (!$query->num_rows) {
			return $cyberpunks_fields;
		}

		$index = trim((string)$query->row['gallery_banner_index']);

		if ($index !== '' && $index !== '0') {
			$cyberpunks_fields['gallery_banner_index'] = $index;
		}

		return $cyberpunks_fields;
	}

	/**
	 * Per-product Display Name / Pick Display Name (Product → Option, next to Required).
	 * Overrides Catalog → Options defaults when set.
	 */
	public function mergeOptionLabels(array $cyberpunks_fields, $product_id, $option_id) {
		$product_id = (int)$product_id;
		$option_id = (int)$option_id;

		if ($product_id <= 0 || $option_id <= 0 || !$this->labelTableExists()) {
			return $cyberpunks_fields;
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cyberpunks_product_option_label`
			WHERE product_id = '" . $product_id . "'
			AND option_id = '" . $option_id . "'
			LIMIT 1");

		if (!$query->num_rows) {
			return $cyberpunks_fields;
		}

		$display_name = isset($query->row['display_name']) ? trim((string)$query->row['display_name']) : '';
		$pick_display_name = isset($query->row['pick_display_name']) ? trim((string)$query->row['pick_display_name']) : '';

		if ($display_name !== '') {
			$cyberpunks_fields['display_name'] = $display_name;
		}

		if ($pick_display_name !== '') {
			$cyberpunks_fields['pick_display_name'] = $pick_display_name;
		}

		return $cyberpunks_fields;
	}

	/** @deprecated use mergeOptionLabels */
	public function mergePickDisplayName(array $cyberpunks_fields, $product_id, $option_id) {
		return $this->mergeOptionLabels($cyberpunks_fields, $product_id, $option_id);
	}

	/**
	 * Cart / checkout label: product Display Name → option Display Name → option Pick Display Name.
	 * Returns empty string when nothing is configured (caller applies its own fallback).
	 */
	public function resolveDisplayName($product_id, $option_id, $fallback_name = '') {
		$product_id = (int)$product_id;
		$option_id = (int)$option_id;

		if ($option_id <= 0) {
			return '';
		}

		if ($product_id > 0 && $this->labelTableExists()) {
			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cyberpunks_product_option_label`
				WHERE product_id = '" . $product_id . "'
				AND option_id = '" . $option_id . "'
				LIMIT 1");

			if ($query->num_rows) {
				$value = isset($query->row['display_name']) ? trim((string)$query->row['display_name']) : '';

				if ($value !== '') {
					return $value;
				}
			}
		}

		$fields_table = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_option_custom_field_value") . "'");

		if ($fields_table->num_rows) {
			$query = $this->db->query("SELECT f.field_key, v.value FROM `" . DB_PREFIX . "cyberpunks_option_custom_field_value` v LEFT JOIN `" . DB_PREFIX . "cyberpunks_option_custom_field` f ON (v.field_id = f.field_id) WHERE v.option_id = '" . $option_id . "' AND v.option_value_id = '0' AND f.status = '1' AND f.field_key IN ('display_name', 'pick_display_name')");

			$values = array();

			foreach ($query->rows as $row) {
				if (!empty($row['field_key']) && trim((string)$row['value']) !== '') {
					$values[$row['field_key']] = trim((string)$row['value']);
				}
			}

			if (!empty($values['display_name'])) {
				return $values['display_name'];
			}

			if (!empty($values['pick_display_name'])) {
				return $values['pick_display_name'];
			}
		}

		return '';
	}

	private function paletteColorTableHasInStockColumn() {
		static $has_column = null;

		if ($has_column !== null) {
			return $has_column;
		}

		if (!$this->tablesExist()) {
			$has_column = false;
			return $has_column;
		}

		$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "cyberpunks_color_palette_color` LIKE 'in_stock'");
		$has_column = (bool)$query->num_rows;

		return $has_column;
	}

	private function displayModeTableExists() {
		$query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_option_display_mode") . "'");

		return (bool)$query->num_rows;
	}

	private function tablesExist() {
		$query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_option_value_palette_color") . "'");

		return (bool)$query->num_rows;
	}

	private function galleryTableExists() {
		$query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_product_option_gallery") . "'");

		return (bool)$query->num_rows;
	}

	private function labelTableExists() {
		$query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_product_option_label") . "'");

		return (bool)$query->num_rows;
	}
}

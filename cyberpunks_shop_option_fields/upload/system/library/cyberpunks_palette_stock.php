<?php
/**
 * Palette color in-stock checks (usable from Cart\Cart without Loader).
 */
class CyberpunksPaletteStock {
	public static function tablesExist($db) {
		$query = $db->query("SHOW TABLES LIKE '" . $db->escape(DB_PREFIX . "cyberpunks_option_value_palette_color") . "'");

		return (bool)$query->num_rows;
	}

	public static function paletteColorTableHasInStockColumn($db) {
		if (!self::tablesExist($db)) {
			return false;
		}

		$query = $db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "cyberpunks_color_palette_color` LIKE 'in_stock'");

		return (bool)$query->num_rows;
	}

	public static function paletteHasInStockColor($db, $palette_id) {
		$palette_id = (int)$palette_id;

		if ($palette_id <= 0 || !self::paletteColorTableHasInStockColumn($db)) {
			return true;
		}

		$query = $db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "cyberpunks_color_palette_color` WHERE palette_id = '" . $palette_id . "' AND in_stock = '1'");

		return !empty($query->row['total']);
	}

	public static function isOptionValueInStock($db, $option_value_id) {
		$option_value_id = (int)$option_value_id;

		if ($option_value_id <= 0 || !self::tablesExist($db) || !self::paletteColorTableHasInStockColumn($db)) {
			return true;
		}

		$query = $db->query("SELECT c.is_random, c.in_stock, c.palette_id
			FROM `" . DB_PREFIX . "cyberpunks_option_value_palette_color` l
			LEFT JOIN `" . DB_PREFIX . "cyberpunks_color_palette_color` c ON (l.color_id = c.color_id)
			LEFT JOIN `" . DB_PREFIX . "cyberpunks_color_palette` p ON (c.palette_id = p.palette_id)
			WHERE l.option_value_id = '" . $option_value_id . "'
			AND p.status = '1'
			LIMIT 1");

		if (!$query->num_rows) {
			return true;
		}

		if (!empty($query->row['is_random'])) {
			return self::paletteHasInStockColor($db, (int)$query->row['palette_id']);
		}

		return !empty($query->row['in_stock']);
	}

	public static function isProductOptionValueInStock($db, $product_option_value_id) {
		$product_option_value_id = (int)$product_option_value_id;

		if ($product_option_value_id <= 0) {
			return true;
		}

		$query = $db->query("SELECT option_value_id FROM `" . DB_PREFIX . "product_option_value` WHERE product_option_value_id = '" . $product_option_value_id . "' LIMIT 1");

		if (!$query->num_rows) {
			return true;
		}

		return self::isOptionValueInStock($db, (int)$query->row['option_value_id']);
	}

	public static function validateCartProductPaletteStock($db, array $cart_product) {
		if (empty($cart_product['option']) || !is_array($cart_product['option']) || !self::tablesExist($db)) {
			return '';
		}

		foreach ($cart_product['option'] as $option_row) {
			$product_option_value_id = isset($option_row['product_option_value_id']) ? (int)$option_row['product_option_value_id'] : 0;

			if ($product_option_value_id <= 0) {
				continue;
			}

			if (!self::isProductOptionValueInStock($db, $product_option_value_id)) {
				$color_name = self::getProductOptionValueLabel($db, $product_option_value_id, isset($option_row['value']) ? $option_row['value'] : '');

				return $color_name !== ''
					? sprintf('The selected color "%s" is currently out of stock.', $color_name)
					: 'One of the selected colors is currently out of stock.';
			}
		}

		return '';
	}

	public static function getCartProductPaletteAvailability($db, array $cart_product) {
		$rows = array();

		if (empty($cart_product['option']) || !is_array($cart_product['option']) || !self::tablesExist($db) || !self::paletteColorTableHasInStockColumn($db)) {
			return $rows;
		}

		$product_id = isset($cart_product['product_id']) ? (int)$cart_product['product_id'] : 0;

		foreach ($cart_product['option'] as $option_row) {
			$product_option_value_id = isset($option_row['product_option_value_id']) ? (int)$option_row['product_option_value_id'] : 0;

			if ($product_option_value_id <= 0 || self::isProductOptionValueInStock($db, $product_option_value_id)) {
				continue;
			}

			$option_id = isset($option_row['option_id']) ? (int)$option_row['option_id'] : 0;
			$fallback_name = isset($option_row['name']) ? trim((string)$option_row['name']) : '';
			$option_name = self::resolveOptionDisplayName($db, $product_id, $option_id, $fallback_name);
			$color_name = self::getProductOptionValueLabel($db, $product_option_value_id, isset($option_row['value']) ? $option_row['value'] : '');

			if ($option_name === '') {
				$option_name = $fallback_name !== '' ? $fallback_name : 'Color';
			}

			if ($color_name === '') {
				$color_name = 'Option';
			}

			$rows[] = array(
				'option_name' => $option_name,
				'color_name'  => $color_name,
				'quantity'    => 0,
				'label'       => 'Availability (' . $option_name . ' - ' . $color_name . '): 0'
			);
		}

		return $rows;
	}

	public static function cartHasPaletteStock($db, array $cart_products) {
		foreach ($cart_products as $cart_product) {
			if (self::validateCartProductPaletteStock($db, $cart_product) !== '') {
				return false;
			}
		}

		return true;
	}

	public static function getProductOptionValueLabel($db, $product_option_value_id, $fallback = '') {
		$query = $db->query("SELECT ovd.name
			FROM `" . DB_PREFIX . "product_option_value` pov
			LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd ON (pov.option_value_id = ovd.option_value_id)
			WHERE pov.product_option_value_id = '" . (int)$product_option_value_id . "'
			LIMIT 1");

		if ($query->num_rows && trim((string)$query->row['name']) !== '') {
			return trim((string)$query->row['name']);
		}

		return trim((string)$fallback);
	}

	/**
	 * Product Display Name → option Display Name → option Pick Display Name (same order as catalog model).
	 */
	public static function resolveOptionDisplayName($db, $product_id, $option_id, $fallback_name = '') {
		$product_id = (int)$product_id;
		$option_id = (int)$option_id;

		if ($option_id <= 0) {
			return trim((string)$fallback_name);
		}

		$label_table = $db->query("SHOW TABLES LIKE '" . $db->escape(DB_PREFIX . "cyberpunks_product_option_label") . "'");

		if ($product_id > 0 && $label_table->num_rows) {
			$query = $db->query("SELECT display_name, pick_display_name FROM `" . DB_PREFIX . "cyberpunks_product_option_label`
				WHERE product_id = '" . $product_id . "'
				AND option_id = '" . $option_id . "'
				LIMIT 1");

			if ($query->num_rows) {
				$display_name = isset($query->row['display_name']) ? trim((string)$query->row['display_name']) : '';

				if ($display_name !== '') {
					return $display_name;
				}
			}
		}

		$fields_table = $db->query("SHOW TABLES LIKE '" . $db->escape(DB_PREFIX . "cyberpunks_option_custom_field_value") . "'");

		if ($fields_table->num_rows) {
			$query = $db->query("SELECT f.field_key, v.value
				FROM `" . DB_PREFIX . "cyberpunks_option_custom_field_value` v
				LEFT JOIN `" . DB_PREFIX . "cyberpunks_option_custom_field` f ON (v.field_id = f.field_id)
				WHERE v.option_id = '" . $option_id . "'
				AND v.option_value_id = '0'
				AND f.status = '1'
				AND f.field_key IN ('display_name', 'pick_display_name')");

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

		return trim((string)$fallback_name);
	}
}

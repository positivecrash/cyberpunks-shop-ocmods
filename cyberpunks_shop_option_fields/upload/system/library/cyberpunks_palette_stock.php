<?php
/**
 * Palette color in-stock checks (usable from Cart\Cart without Loader).
 */
class CyberpunksPaletteStock {
	public static function isLocalizedLabelKey($field_key) {
		return in_array((string)$field_key, array('display_name', 'pick_display_name'), true);
	}

	/**
	 * Decode stored value: plain string (legacy) or JSON {"1":"EN","2":"NL"}.
	 *
	 * @return array language_id => string (may include 0 for legacy)
	 */
	public static function decodeLocalizedText($raw) {
		$raw = trim((string)$raw);

		if ($raw === '') {
			return array();
		}

		if (isset($raw[0]) && $raw[0] === '{') {
			$decoded = json_decode($raw, true);

			if (is_array($decoded)) {
				$out = array();

				foreach ($decoded as $language_id => $text) {
					if (is_array($text)) {
						continue;
					}

					$text = trim((string)$text);

					if ($text === '') {
						continue;
					}

					$out[(int)$language_id] = $text;
				}

				if ($out) {
					return $out;
				}
			}
		}

		return array(0 => $raw);
	}

	/**
	 * @param array|string $map
	 * @return string JSON map or plain string when empty
	 */
	public static function encodeLocalizedText($map) {
		if (!is_array($map)) {
			return trim((string)$map);
		}

		$clean = array();

		foreach ($map as $language_id => $text) {
			$language_id = (int)$language_id;

			if ($language_id < 1 || is_array($text)) {
				continue;
			}

			$text = trim((string)$text);

			if ($text === '') {
				continue;
			}

			$clean[(string)$language_id] = $text;
		}

		if (!$clean) {
			return '';
		}

		return json_encode($clean, JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Pick best text for language_id (current → 0/legacy → first non-empty).
	 */
	public static function pickLocalizedText($raw, $language_id = 0) {
		if (is_array($raw)) {
			$map = array();

			foreach ($raw as $lid => $text) {
				if (is_array($text)) {
					continue;
				}

				$text = trim((string)$text);

				if ($text !== '') {
					$map[(int)$lid] = $text;
				}
			}
		} else {
			$map = self::decodeLocalizedText($raw);
		}

		if (!$map) {
			return '';
		}

		$language_id = (int)$language_id;

		if ($language_id > 0 && !empty($map[$language_id])) {
			return $map[$language_id];
		}

		if (!empty($map[0])) {
			return $map[0];
		}

		return (string)reset($map);
	}

	/**
	 * Expand legacy/single values so admin shows a field per active language.
	 *
	 * @param array $map
	 * @param array $language_ids list of int
	 * @return array
	 */
	public static function expandLocalizedTextForAdmin(array $map, array $language_ids) {
		$legacy = isset($map[0]) ? trim((string)$map[0]) : '';
		$out = array();

		foreach ($language_ids as $language_id) {
			$language_id = (int)$language_id;

			if ($language_id < 1) {
				continue;
			}

			if (isset($map[$language_id]) && trim((string)$map[$language_id]) !== '') {
				$out[$language_id] = trim((string)$map[$language_id]);
			} else {
				$out[$language_id] = $legacy;
			}
		}

		return $out;
	}

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

	public static function getCartProductPaletteAvailability($db, array $cart_product, $language_id = 0) {
		$rows = array();

		if (empty($cart_product['option']) || !is_array($cart_product['option']) || !self::tablesExist($db) || !self::paletteColorTableHasInStockColumn($db)) {
			return $rows;
		}

		$product_id = isset($cart_product['product_id']) ? (int)$cart_product['product_id'] : 0;
		$language_id = (int)$language_id;

		foreach ($cart_product['option'] as $option_row) {
			$product_option_value_id = isset($option_row['product_option_value_id']) ? (int)$option_row['product_option_value_id'] : 0;

			if ($product_option_value_id <= 0 || self::isProductOptionValueInStock($db, $product_option_value_id)) {
				continue;
			}

			$option_id = isset($option_row['option_id']) ? (int)$option_row['option_id'] : 0;
			$fallback_name = isset($option_row['name']) ? trim((string)$option_row['name']) : '';
			$option_name = self::resolveOptionDisplayName($db, $product_id, $option_id, $fallback_name, $language_id);
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
	public static function resolveOptionDisplayName($db, $product_id, $option_id, $fallback_name = '', $language_id = 0) {
		$product_id = (int)$product_id;
		$option_id = (int)$option_id;
		$language_id = (int)$language_id;

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
				$display_name = self::pickLocalizedText(isset($query->row['display_name']) ? $query->row['display_name'] : '', $language_id);

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
					$values[$row['field_key']] = $row['value'];
				}
			}

			if (!empty($values['display_name'])) {
				$picked = self::pickLocalizedText($values['display_name'], $language_id);

				if ($picked !== '') {
					return $picked;
				}
			}

			if (!empty($values['pick_display_name'])) {
				$picked = self::pickLocalizedText($values['pick_display_name'], $language_id);

				if ($picked !== '') {
					return $picked;
				}
			}
		}

		return trim((string)$fallback_name);
	}
}

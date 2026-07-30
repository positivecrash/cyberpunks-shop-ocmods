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
			}

			$data[$row['field_key']] = $this->expandIconShortcodes($value);
		}

		return $data;
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
}

<?php
class ModelExtensionModuleCyberpunksShopCommonOptions extends Model {
	public function getOptionsMap() {
		$table = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_common_option_field") . "'");
		$value_table = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_common_option_value") . "'");

		if (!$table->num_rows || !$value_table->num_rows) {
			return array();
		}

		$data = array();
		$query = $this->db->query("SELECT f.field_key, f.field_type, v.value FROM `" . DB_PREFIX . "cyberpunks_common_option_value` v LEFT JOIN `" . DB_PREFIX . "cyberpunks_common_option_field` f ON (v.field_id = f.field_id) WHERE f.status = '1'");

		foreach ($query->rows as $row) {
			if (empty($row['field_key'])) {
				continue;
			}

			$value = $row['value'];
			$field_type = isset($row['field_type']) ? $row['field_type'] : 'text';

			if ($field_type === 'html' || $field_type === 'textarea') {
				$value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
				$value = $this->expandIconShortcodes($value);
			} elseif ($field_type === 'image') {
				$value = $this->resolveImageFieldValue($value);
			}

			$data[$row['field_key']] = $value;
		}

		return $data;
	}

	/**
	 * Active field keys whose values are inserted raw into HTML (html/textarea).
	 */
	public function getHtmlFieldKeys() {
		$table = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_common_option_field") . "'");

		if (!$table->num_rows) {
			return array();
		}

		$keys = array();
		$query = $this->db->query("SELECT field_key FROM `" . DB_PREFIX . "cyberpunks_common_option_field` WHERE status = '1' AND field_type IN ('html', 'textarea')");

		foreach ($query->rows as $row) {
			if (!empty($row['field_key'])) {
				$keys[] = $row['field_key'];
			}
		}

		return $keys;
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

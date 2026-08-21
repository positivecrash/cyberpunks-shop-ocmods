<?php
class ModelExtensionModuleCyberpunksShopColorPalettes extends Model {
	public function getColorFieldsForOptionValue($option_value_id) {
		$option_value_id = (int)$option_value_id;

		if ($option_value_id <= 0) {
			return array();
		}

		if (!$this->tablesExist()) {
			return array();
		}

		$query = $this->db->query("SELECT c.name, c.swatch_color, c.model_color, c.is_random
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
			return array(
				'color' => 'random',
				'swatch_color' => 'random',
				'model_color' => 'random'
			);
		}

		$swatch_color = trim((string)$row['swatch_color']);
		$model_color = trim((string)$row['model_color']);

		if ($model_color === '') {
			$model_color = $swatch_color;
		}

		return array(
			'color' => $swatch_color,
			'swatch_color' => $swatch_color,
			'model_color' => $model_color
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

	private function tablesExist() {
		$query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_option_value_palette_color") . "'");

		return (bool)$query->num_rows;
	}
}

<?php
class ModelExtensionModuleCyberpunksShopOptionFields extends Model {
	private function ensureSchema() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_option_display_mode` (
			`option_id` INT(11) NOT NULL,
			`show_image` TINYINT(1) NOT NULL DEFAULT '1',
			`enabled_field_ids` TEXT NOT NULL,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`option_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");

		$display_mode_column = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "cyberpunks_option_display_mode` LIKE 'enabled_field_ids'");
		if (!$display_mode_column->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "cyberpunks_option_display_mode` ADD `enabled_field_ids` TEXT NOT NULL AFTER `show_image`");
		}

		$values_mode_column = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "cyberpunks_option_display_mode` LIKE 'values_mode'");
		if (!$values_mode_column->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "cyberpunks_option_display_mode` ADD `values_mode` VARCHAR(32) NOT NULL DEFAULT 'default' AFTER `enabled_field_ids`");
		}

		$filament_type_column = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "cyberpunks_option_display_mode` LIKE 'filament_type'");
		if (!$filament_type_column->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "cyberpunks_option_display_mode` ADD `filament_type` VARCHAR(16) NOT NULL DEFAULT '' AFTER `values_mode`");
		}

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_option_custom_field` (
			`field_id` INT(11) NOT NULL AUTO_INCREMENT,
			`field_key` VARCHAR(64) NOT NULL,
			`label` VARCHAR(128) NOT NULL,
			`field_type` VARCHAR(32) NOT NULL DEFAULT 'text',
			`scope` VARCHAR(16) NOT NULL DEFAULT 'option_value',
			`sort_order` INT(11) NOT NULL DEFAULT '0',
			`status` TINYINT(1) NOT NULL DEFAULT '1',
			`date_added` DATETIME NOT NULL,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`field_id`),
			UNIQUE KEY `field_key` (`field_key`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_option_custom_field_value` (
			`option_id` INT(11) NOT NULL,
			`option_value_id` INT(11) NOT NULL,
			`field_id` INT(11) NOT NULL,
			`value` TEXT NOT NULL,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`option_id`,`option_value_id`,`field_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");

		$option_value_column = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "cyberpunks_option_custom_field_value` LIKE 'option_value_id'");
		if (!$option_value_column->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "cyberpunks_option_custom_field_value` ADD `option_value_id` INT(11) NOT NULL DEFAULT '0' AFTER `option_id`");
		}

		$pk_info = $this->db->query("SHOW INDEX FROM `" . DB_PREFIX . "cyberpunks_option_custom_field_value` WHERE Key_name = 'PRIMARY'");
		$pk_columns = array();
		foreach ($pk_info->rows as $pk_row) {
			$pk_columns[(int)$pk_row['Seq_in_index']] = $pk_row['Column_name'];
		}
		ksort($pk_columns);
		$pk_columns = array_values($pk_columns);
		if ($pk_columns !== array('option_id', 'option_value_id', 'field_id')) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "cyberpunks_option_custom_field_value` DROP PRIMARY KEY, ADD PRIMARY KEY (`option_id`,`option_value_id`,`field_id`)");
		}

		$scope_column = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "cyberpunks_option_custom_field` LIKE 'scope'");
		if (!$scope_column->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "cyberpunks_option_custom_field` ADD `scope` VARCHAR(16) NOT NULL DEFAULT 'option_value' AFTER `field_type`");
		}

		$this->ensurePaletteSchema();
	}

	private function ensurePaletteSchema() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_color_palette` (
			`palette_id` INT(11) NOT NULL AUTO_INCREMENT,
			`name` VARCHAR(128) NOT NULL,
			`sort_order` INT(11) NOT NULL DEFAULT '0',
			`status` TINYINT(1) NOT NULL DEFAULT '1',
			`date_added` DATETIME NOT NULL,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`palette_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_color_palette_color` (
			`color_id` INT(11) NOT NULL AUTO_INCREMENT,
			`palette_id` INT(11) NOT NULL,
			`name` VARCHAR(128) NOT NULL,
			`swatch_color` VARCHAR(32) NOT NULL,
			`model_color` VARCHAR(32) NOT NULL,
			`is_random` TINYINT(1) NOT NULL DEFAULT '0',
			`sort_order` INT(11) NOT NULL DEFAULT '0',
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`color_id`),
			KEY `palette_id` (`palette_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_option_color_palette` (
			`option_id` INT(11) NOT NULL,
			`palette_id` INT(11) NOT NULL,
			PRIMARY KEY (`option_id`, `palette_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_option_value_palette_color` (
			`option_id` INT(11) NOT NULL,
			`option_value_id` INT(11) NOT NULL,
			`color_id` INT(11) NOT NULL,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`option_value_id`),
			KEY `option_id` (`option_id`),
			KEY `color_id` (`color_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_product_option_gallery` (
			`product_id` INT(11) NOT NULL,
			`option_value_id` INT(11) NOT NULL,
			`gallery_banner_index` VARCHAR(32) NOT NULL DEFAULT '',
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`product_id`, `option_value_id`),
			KEY `option_value_id` (`option_value_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");

		$gallery_col = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "cyberpunks_product_option_gallery` LIKE 'gallery_banner_index'");
		if ($gallery_col->num_rows && stripos($gallery_col->row['Type'], 'int') !== false) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "cyberpunks_product_option_gallery`
				MODIFY `gallery_banner_index` VARCHAR(32) NOT NULL DEFAULT ''");
		}

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_product_option_label` (
			`product_id` INT(11) NOT NULL,
			`option_id` INT(11) NOT NULL,
			`display_name` VARCHAR(255) NOT NULL DEFAULT '',
			`pick_display_name` VARCHAR(255) NOT NULL DEFAULT '',
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`product_id`, `option_id`),
			KEY `option_id` (`option_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");

		$label_display_col = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "cyberpunks_product_option_label` LIKE 'display_name'");
		if (!$label_display_col->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "cyberpunks_product_option_label` ADD `display_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `option_id`");
		}
	}

	/**
	 * Map option_value_id => gallery_banner_index for a product (Product → Option tab).
	 */
	public function getProductOptionGalleryMap($product_id) {
		$this->ensureSchema();

		$product_id = (int)$product_id;
		$map = array();

		if ($product_id <= 0) {
			return $map;
		}

		$query = $this->db->query("SELECT option_value_id, gallery_banner_index FROM `" . DB_PREFIX . "cyberpunks_product_option_gallery` WHERE product_id = '" . $product_id . "'");

		foreach ($query->rows as $row) {
			$index = $this->normalizeGallerySlideList(isset($row['gallery_banner_index']) ? $row['gallery_banner_index'] : '');

			if ($index !== '') {
				$map[(int)$row['option_value_id']] = $index;
			}
		}

		return $map;
	}

	/**
	 * Persist gallery slide indices from product form POST (keyed by option_value_id).
	 */
	public function saveProductOptionGallery($product_id, array $post) {
		$this->ensureSchema();

		$product_id = (int)$product_id;

		if ($product_id <= 0) {
			return;
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_option_gallery` WHERE product_id = '" . $product_id . "'");

		if (empty($post['product_option']) || !is_array($post['product_option'])) {
			return;
		}

		$seen = array();

		foreach ($post['product_option'] as $product_option) {
			if (empty($product_option['product_option_value']) || !is_array($product_option['product_option_value'])) {
				continue;
			}

			foreach ($product_option['product_option_value'] as $product_option_value) {
				$option_value_id = isset($product_option_value['option_value_id']) ? (int)$product_option_value['option_value_id'] : 0;
				$index = isset($product_option_value['cyberpunks_gallery_banner_index'])
					? $this->normalizeGallerySlideList($product_option_value['cyberpunks_gallery_banner_index'])
					: '';

				if ($option_value_id <= 0 || $index === '' || isset($seen[$option_value_id])) {
					continue;
				}

				$seen[$option_value_id] = true;

				$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_product_option_gallery` SET
					product_id = '" . $product_id . "',
					option_value_id = '" . $option_value_id . "',
					gallery_banner_index = '" . $this->db->escape($index) . "',
					date_modified = NOW()");
			}
		}
	}

	public function normalizeGallerySlideList($raw) {
		$raw = trim((string)$raw);

		if ($raw === '' || $raw === '0') {
			return '';
		}

		$parts = preg_split('/\s*,\s*/', $raw);
		$slides = array();

		foreach ($parts as $part) {
			$part = trim($part);

			if ($part !== '' && preg_match('/^\d+$/', $part)) {
				$slides[] = $part;
			}
		}

		$slides = array_values(array_unique($slides));

		return $slides ? implode(',', $slides) : '';
	}

	public function deleteProductOptionGallery($product_id) {
		$product_id = (int)$product_id;

		if ($product_id <= 0) {
			return;
		}

		$table = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_product_option_gallery") . "'");

		if ($table->num_rows) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_option_gallery` WHERE product_id = '" . $product_id . "'");
		}
	}

	/**
	 * option_id => default label from Catalog → Options (field_key: display_name | pick_display_name).
	 */
	public function getOptionLabelDefaults($field_key) {
		$this->ensureSchema();

		$field_key = (string)$field_key;
		$map = array();

		if ($field_key === '') {
			return $map;
		}

		$query = $this->db->query("SELECT v.option_id, v.value FROM `" . DB_PREFIX . "cyberpunks_option_custom_field_value` v LEFT JOIN `" . DB_PREFIX . "cyberpunks_option_custom_field` f ON (v.field_id = f.field_id) WHERE v.option_value_id = '0' AND f.field_key = '" . $this->db->escape($field_key) . "' AND f.status = '1'");

		foreach ($query->rows as $row) {
			$value = trim((string)$row['value']);

			if ($value !== '') {
				$map[(int)$row['option_id']] = $value;
			}
		}

		return $map;
	}

	/** @deprecated use getOptionLabelDefaults('pick_display_name') */
	public function getPickDisplayNameDefaults() {
		return $this->getOptionLabelDefaults('pick_display_name');
	}

	/**
	 * option_id => ['display_name' => '', 'pick_display_name' => ''] per-product overrides.
	 */
	public function getProductOptionLabelMap($product_id) {
		$this->ensureSchema();

		$product_id = (int)$product_id;
		$map = array();

		if ($product_id <= 0) {
			return $map;
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cyberpunks_product_option_label` WHERE product_id = '" . $product_id . "'");

		foreach ($query->rows as $row) {
			$display_name = isset($row['display_name']) ? trim((string)$row['display_name']) : '';
			$pick_display_name = isset($row['pick_display_name']) ? trim((string)$row['pick_display_name']) : '';

			if ($display_name === '' && $pick_display_name === '') {
				continue;
			}

			$map[(int)$row['option_id']] = array(
				'display_name' => $display_name,
				'pick_display_name' => $pick_display_name
			);
		}

		return $map;
	}

	/** @deprecated use getProductOptionLabelMap */
	public function getProductOptionPickDisplayNameMap($product_id) {
		$map = array();

		foreach ($this->getProductOptionLabelMap($product_id) as $option_id => $labels) {
			if (!empty($labels['pick_display_name'])) {
				$map[(int)$option_id] = $labels['pick_display_name'];
			}
		}

		return $map;
	}

	/**
	 * Persist Display Name / Pick Display Name overrides from product form POST.
	 * Empty values fall back to Catalog → Options defaults and are not stored.
	 */
	public function saveProductOptionLabels($product_id, array $post) {
		$this->ensureSchema();

		$product_id = (int)$product_id;

		if ($product_id <= 0) {
			return;
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_option_label` WHERE product_id = '" . $product_id . "'");

		if (empty($post['product_option']) || !is_array($post['product_option'])) {
			return;
		}

		$seen = array();

		foreach ($post['product_option'] as $product_option) {
			$option_id = isset($product_option['option_id']) ? (int)$product_option['option_id'] : 0;

			if ($option_id <= 0 || isset($seen[$option_id])) {
				continue;
			}

			$seen[$option_id] = true;
			$display_name = isset($product_option['cyberpunks_display_name']) ? trim((string)$product_option['cyberpunks_display_name']) : '';
			$pick_display_name = isset($product_option['cyberpunks_pick_display_name']) ? trim((string)$product_option['cyberpunks_pick_display_name']) : '';

			if ($display_name === '' && $pick_display_name === '') {
				continue;
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_product_option_label` SET product_id = '" . $product_id . "', option_id = '" . $option_id . "', display_name = '" . $this->db->escape($display_name) . "', pick_display_name = '" . $this->db->escape($pick_display_name) . "', date_modified = NOW()");
		}
	}

	/** @deprecated use saveProductOptionLabels */
	public function saveProductOptionPickDisplayName($product_id, array $post) {
		$this->saveProductOptionLabels($product_id, $post);
	}

	public function deleteProductOptionLabels($product_id) {
		$product_id = (int)$product_id;

		if ($product_id <= 0) {
			return;
		}

		$table = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_product_option_label") . "'");

		if ($table->num_rows) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_option_label` WHERE product_id = '" . $product_id . "'");
		}
	}

	/** @deprecated use deleteProductOptionLabels */
	public function deleteProductOptionPickDisplayName($product_id) {
		$this->deleteProductOptionLabels($product_id);
	}

	public function install() {
		$this->ensureSchema();
		$this->removeLegacyProductScopeFields();
	}

	public function uninstall() {
		// Keep data by default on uninstall.
	}

	public function getCustomFields($only_active = false) {
		$this->ensureSchema();

		$sql = "SELECT * FROM `" . DB_PREFIX . "cyberpunks_option_custom_field` WHERE scope != 'product'";

		if ($only_active) {
			$sql .= " AND status = '1'";
		}

		$sql .= " ORDER BY sort_order ASC, field_id ASC";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function saveCustomFields($fields) {
		$this->ensureSchema();

		$this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "cyberpunks_option_custom_field`");

		foreach ($fields as $field) {
			$field_key = isset($field['field_key']) ? strtolower(trim((string)$field['field_key'])) : '';
			$field_key = preg_replace('/[^a-z0-9_]/', '_', $field_key);
			$label = isset($field['label']) ? trim((string)$field['label']) : '';

			if ($field_key === '' || $label === '') {
				continue;
			}

			$field_type = 'text';
			if (isset($field['field_type']) && in_array($field['field_type'], array('textarea', 'boolean'))) {
				$field_type = $field['field_type'];
			}
			$scope = 'option_value';
			if (isset($field['scope'])) {
				if ($field['scope'] === 'option') {
					$scope = 'option';
				}
			}
			$sort_order = isset($field['sort_order']) ? (int)$field['sort_order'] : 0;
			$status = !empty($field['status']) ? 1 : 0;

			$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_option_custom_field` SET
				`field_key` = '" . $this->db->escape($field_key) . "',
				`label` = '" . $this->db->escape($label) . "',
				`field_type` = '" . $this->db->escape($field_type) . "',
				`scope` = '" . $this->db->escape($scope) . "',
				`sort_order` = '" . (int)$sort_order . "',
				`status` = '" . (int)$status . "',
				`date_added` = NOW(),
				`date_modified` = NOW()");
		}
	}

	public function getOptionMode($option_id) {
		$this->ensureSchema();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cyberpunks_option_display_mode` WHERE option_id = '" . (int)$option_id . "'");

		if ($query->num_rows) {
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

		return array('show_image' => 0, 'enabled_field_ids' => array(), 'values_mode' => 'default', 'filament_type' => '');
	}

	public function saveOptionMode($option_id, $post) {
		$this->ensureSchema();

		$show_image = isset($post['cyberpunks_option_mode']['show_image']) ? 1 : 0;
		$values_mode = (isset($post['cyberpunks_option_mode']['values_mode']) && $post['cyberpunks_option_mode']['values_mode'] === 'color_palette') ? 'color_palette' : 'default';
		$filament_type = isset($post['cyberpunks_option_mode']['filament_type']) ? strtoupper(trim((string)$post['cyberpunks_option_mode']['filament_type'])) : '';

		if ($values_mode !== 'color_palette' || !in_array($filament_type, array('PLA', 'ASA', 'ABS'), true)) {
			$filament_type = '';
		}

		$enabled_field_ids = array();
		if (isset($post['cyberpunks_option_mode']['enabled_field_ids']) && is_array($post['cyberpunks_option_mode']['enabled_field_ids'])) {
			foreach ($post['cyberpunks_option_mode']['enabled_field_ids'] as $field_id) {
				$field_id = (int)$field_id;
				if ($field_id > 0) {
					$enabled_field_ids[] = $field_id;
				}
			}
		}
		$enabled_field_ids = array_values(array_unique($enabled_field_ids));

		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_display_mode` WHERE option_id = '" . (int)$option_id . "'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_option_display_mode` SET option_id = '" . (int)$option_id . "', show_image = '" . (int)$show_image . "', enabled_field_ids = '" . $this->db->escape(json_encode($enabled_field_ids)) . "', values_mode = '" . $this->db->escape($values_mode) . "', filament_type = '" . $this->db->escape($filament_type) . "', date_modified = NOW()");
	}

	public function getOptionValueFieldValues($option_id) {
		$this->ensureSchema();

		$data = array();
		$query = $this->db->query("SELECT option_value_id, field_id, value FROM `" . DB_PREFIX . "cyberpunks_option_custom_field_value` WHERE option_id = '" . (int)$option_id . "'");

		foreach ($query->rows as $row) {
			$option_value_id = (int)$row['option_value_id'];
			$field_id = (int)$row['field_id'];
			if (!isset($data[$option_value_id])) {
				$data[$option_value_id] = array();
			}
			$data[$option_value_id][$field_id] = $row['value'];
		}

		return $data;
	}

	public function getOptionFieldValues($option_id) {
		$this->ensureSchema();

		$data = array();
		$query = $this->db->query("SELECT field_id, value FROM `" . DB_PREFIX . "cyberpunks_option_custom_field_value` WHERE option_id = '" . (int)$option_id . "' AND option_value_id = '0'");

		foreach ($query->rows as $row) {
			$data[(int)$row['field_id']] = $row['value'];
		}

		return $data;
	}

	public function saveOptionFieldValues($option_id, $values) {
		$this->ensureSchema();

		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_custom_field_value` WHERE option_id = '" . (int)$option_id . "' AND option_value_id = '0'");

		if (is_array($values)) {
			foreach ($values as $field_id => $value) {
				$field_id = (int)$field_id;
				if ($field_id <= 0) {
					continue;
				}

				$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_option_custom_field_value` SET option_id = '" . (int)$option_id . "', option_value_id = '0', field_id = '" . (int)$field_id . "', value = '" . $this->db->escape((string)$value) . "', date_modified = NOW()");
			}
		}
	}

	public function saveOptionValueFieldValues($option_id, $option_value_id, $values) {
		$this->ensureSchema();

		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_custom_field_value` WHERE option_value_id = '" . (int)$option_value_id . "'");

		if (is_array($values)) {
			foreach ($values as $field_id => $value) {
				$field_id = (int)$field_id;
				if ($field_id <= 0) {
					continue;
				}
				$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_option_custom_field_value` SET option_id = '" . (int)$option_id . "', option_value_id = '" . (int)$option_value_id . "', field_id = '" . (int)$field_id . "', value = '" . $this->db->escape((string)$value) . "', date_modified = NOW()");
			}
		}
	}

	public function deleteOptionData($option_id) {
		$this->ensureSchema();

		$option_id = (int)$option_id;
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_display_mode` WHERE option_id = '" . $option_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_custom_field_value` WHERE option_id = '" . $option_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_color_palette` WHERE option_id = '" . $option_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_value_palette_color` WHERE option_id = '" . $option_id . "'");

		$label_table = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_product_option_label") . "'");

		if ($label_table->num_rows) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_option_label` WHERE option_id = '" . $option_id . "'");
		}
	}

	private function removeLegacyProductScopeFields() {
		$this->ensureSchema();

		$query = $this->db->query("SELECT field_id FROM `" . DB_PREFIX . "cyberpunks_option_custom_field` WHERE scope = 'product'");

		foreach ($query->rows as $row) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_custom_field` WHERE field_id = '" . (int)$row['field_id'] . "'");
		}
	}

	public function getPalettes($only_active = false) {
		$this->ensureSchema();

		$sql = "SELECT * FROM `" . DB_PREFIX . "cyberpunks_color_palette`";

		if ($only_active) {
			$sql .= " WHERE status = '1'";
		}

		$sql .= " ORDER BY sort_order ASC, palette_id ASC";

		return $this->db->query($sql)->rows;
	}

	public function getPaletteColors($palette_id, $only_active_palette = false) {
		$this->ensureSchema();

		$palette_id = (int)$palette_id;

		if ($palette_id <= 0) {
			return array();
		}

		if ($only_active_palette) {
			$palette = $this->db->query("SELECT palette_id FROM `" . DB_PREFIX . "cyberpunks_color_palette` WHERE palette_id = '" . $palette_id . "' AND status = '1'");

			if (!$palette->num_rows) {
				return array();
			}
		}

		return $this->db->query("SELECT * FROM `" . DB_PREFIX . "cyberpunks_color_palette_color` WHERE palette_id = '" . $palette_id . "' ORDER BY sort_order ASC, color_id ASC")->rows;
	}

	public function getColorById($color_id) {
		$this->ensureSchema();

		$query = $this->db->query("SELECT c.*, p.name AS palette_name, p.status AS palette_status
			FROM `" . DB_PREFIX . "cyberpunks_color_palette_color` c
			LEFT JOIN `" . DB_PREFIX . "cyberpunks_color_palette` p ON (c.palette_id = p.palette_id)
			WHERE c.color_id = '" . (int)$color_id . "'");

		return $query->num_rows ? $query->row : null;
	}

	public function getAdminPalettesWithColors() {
		$palettes = array();

		foreach ($this->getPalettes(false) as $palette) {
			$palette_id = (int)$palette['palette_id'];
			$palette['colors'] = $this->getPaletteColors($palette_id);
			$palettes[] = $palette;
		}

		return $palettes;
	}

	public function getOptionFormPalettes($only_active = true) {
		$this->ensureSchema();

		$palettes = array();

		foreach ($this->getPalettes($only_active) as $palette) {
			$palette_id = (int)$palette['palette_id'];
			$palette['colors'] = $this->getPaletteColors($palette_id, $only_active);
			$palettes[] = $palette;
		}

		return $palettes;
	}

	public function savePalettes($palettes) {
		$this->ensureSchema();

		if (!is_array($palettes)) {
			$palettes = array();
		}

		$existing_palette_ids = array();
		$palette_query = $this->db->query("SELECT palette_id FROM `" . DB_PREFIX . "cyberpunks_color_palette`");

		foreach ($palette_query->rows as $row) {
			$existing_palette_ids[] = (int)$row['palette_id'];
		}

		$kept_palette_ids = array();
		$changed_color_ids = array();

		foreach ($palettes as $palette) {
			$palette_id = isset($palette['palette_id']) ? (int)$palette['palette_id'] : 0;
			$name = isset($palette['name']) ? trim((string)$palette['name']) : '';
			$sort_order = isset($palette['sort_order']) ? (int)$palette['sort_order'] : 0;
			$status = !empty($palette['status']) ? 1 : 0;

			if ($name === '') {
				continue;
			}

			if ($palette_id > 0) {
				$this->db->query("UPDATE `" . DB_PREFIX . "cyberpunks_color_palette` SET
					name = '" . $this->db->escape($name) . "',
					sort_order = '" . (int)$sort_order . "',
					status = '" . (int)$status . "',
					date_modified = NOW()
					WHERE palette_id = '" . (int)$palette_id . "'");
				$kept_palette_ids[] = $palette_id;
			} else {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_color_palette` SET
					name = '" . $this->db->escape($name) . "',
					sort_order = '" . (int)$sort_order . "',
					status = '" . (int)$status . "',
					date_added = NOW(),
					date_modified = NOW()");
				$palette_id = (int)$this->db->getLastId();
				$kept_palette_ids[] = $palette_id;
			}

			$existing_color_ids = array();
			$color_query = $this->db->query("SELECT color_id FROM `" . DB_PREFIX . "cyberpunks_color_palette_color` WHERE palette_id = '" . (int)$palette_id . "'");

			foreach ($color_query->rows as $row) {
				$existing_color_ids[] = (int)$row['color_id'];
			}

			$kept_color_ids = array();
			$colors = isset($palette['colors']) && is_array($palette['colors']) ? $palette['colors'] : array();

			foreach ($colors as $color) {
				$color_id = isset($color['color_id']) ? (int)$color['color_id'] : 0;
				$color_name = isset($color['name']) ? trim((string)$color['name']) : '';
				$swatch_color = $this->normalizeColorValue(isset($color['swatch_color']) ? $color['swatch_color'] : '');
				$model_color = $this->normalizeColorValue(isset($color['model_color']) ? $color['model_color'] : '');
				$is_random = !empty($color['is_random']) ? 1 : 0;
				$color_sort_order = isset($color['sort_order']) ? (int)$color['sort_order'] : 0;

				if ($color_name === '') {
					continue;
				}

				if ($is_random) {
					$swatch_color = 'random';
					$model_color = 'random';
				} elseif ($model_color === '') {
					$model_color = $swatch_color;
				}

				if ($color_id > 0) {
					$this->db->query("UPDATE `" . DB_PREFIX . "cyberpunks_color_palette_color` SET
						palette_id = '" . (int)$palette_id . "',
						name = '" . $this->db->escape($color_name) . "',
						swatch_color = '" . $this->db->escape($swatch_color) . "',
						model_color = '" . $this->db->escape($model_color) . "',
						is_random = '" . (int)$is_random . "',
						sort_order = '" . (int)$color_sort_order . "',
						date_modified = NOW()
						WHERE color_id = '" . (int)$color_id . "'");
					$kept_color_ids[] = $color_id;
					$changed_color_ids[] = $color_id;
				} else {
					$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_color_palette_color` SET
						palette_id = '" . (int)$palette_id . "',
						name = '" . $this->db->escape($color_name) . "',
						swatch_color = '" . $this->db->escape($swatch_color) . "',
						model_color = '" . $this->db->escape($model_color) . "',
						is_random = '" . (int)$is_random . "',
						sort_order = '" . (int)$color_sort_order . "',
						date_modified = NOW()");
					$kept_color_ids[] = (int)$this->db->getLastId();
				}
			}

			$removed_color_ids = array_diff($existing_color_ids, $kept_color_ids);

			foreach ($removed_color_ids as $removed_color_id) {
				$this->deleteColorLinks((int)$removed_color_id);
				$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_color_palette_color` WHERE color_id = '" . (int)$removed_color_id . "'");
			}
		}

		$removed_palette_ids = array_diff($existing_palette_ids, $kept_palette_ids);

		foreach ($removed_palette_ids as $removed_palette_id) {
			$this->deletePaletteLinks((int)$removed_palette_id);
			$color_rows = $this->db->query("SELECT color_id FROM `" . DB_PREFIX . "cyberpunks_color_palette_color` WHERE palette_id = '" . (int)$removed_palette_id . "'");

			foreach ($color_rows->rows as $color_row) {
				$this->deleteColorLinks((int)$color_row['color_id']);
			}

			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_color_palette_color` WHERE palette_id = '" . (int)$removed_palette_id . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_color_palette` WHERE palette_id = '" . (int)$removed_palette_id . "'");
		}

		foreach (array_unique($changed_color_ids) as $color_id) {
			$this->syncLinkedOptionValueNames((int)$color_id);
		}
	}

	public function getOptionPaletteIds($option_id) {
		$this->ensureSchema();

		$option_id = (int)$option_id;
		$palette_ids = array();
		$query = $this->db->query("SELECT palette_id FROM `" . DB_PREFIX . "cyberpunks_option_color_palette` WHERE option_id = '" . $option_id . "' ORDER BY palette_id ASC");

		foreach ($query->rows as $row) {
			$palette_ids[] = (int)$row['palette_id'];
		}

		return $palette_ids;
	}

	public function saveOptionPalettes($option_id, $palette_ids) {
		$this->ensureSchema();

		$option_id = (int)$option_id;
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_color_palette` WHERE option_id = '" . $option_id . "'");

		if (!is_array($palette_ids)) {
			return;
		}

		foreach ($palette_ids as $palette_id) {
			$palette_id = (int)$palette_id;

			if ($palette_id <= 0) {
				continue;
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_option_color_palette` SET option_id = '" . $option_id . "', palette_id = '" . $palette_id . "'");
		}
	}

	public function getOptionValuePaletteColors($option_id) {
		$this->ensureSchema();

		$data = array();
		$query = $this->db->query("SELECT option_value_id, color_id FROM `" . DB_PREFIX . "cyberpunks_option_value_palette_color` WHERE option_id = '" . (int)$option_id . "'");

		foreach ($query->rows as $row) {
			$data[(int)$row['option_value_id']] = (int)$row['color_id'];
		}

		return $data;
	}

	public function saveOptionValuePaletteColor($option_id, $option_value_id, $color_id) {
		$this->ensureSchema();

		$option_id = (int)$option_id;
		$option_value_id = (int)$option_value_id;
		$color_id = (int)$color_id;

		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_value_palette_color` WHERE option_value_id = '" . $option_value_id . "'");

		if ($color_id <= 0) {
			return;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_option_value_palette_color` SET
			option_id = '" . $option_id . "',
			option_value_id = '" . $option_value_id . "',
			color_id = '" . $color_id . "',
			date_modified = NOW()");

		$this->syncLinkedOptionValueNames($color_id, $option_value_id);
	}

	public function applyPaletteNamesToPost(&$post) {
		if (!isset($post['option_value']) || !is_array($post['option_value'])) {
			return;
		}

		foreach ($post['option_value'] as $index => $option_value) {
			$color_id = isset($option_value['palette_color_id']) ? (int)$option_value['palette_color_id'] : 0;

			if ($color_id <= 0) {
				continue;
			}

			$color = $this->getColorById($color_id);

			if (!$color || empty($color['name'])) {
				continue;
			}

			if (!isset($post['option_value'][$index]['option_value_description']) || !is_array($post['option_value'][$index]['option_value_description'])) {
				continue;
			}

			foreach ($post['option_value'][$index]['option_value_description'] as $language_id => $description) {
				$post['option_value'][$index]['option_value_description'][$language_id]['name'] = $color['name'];
			}
		}
	}

	public function saveOptionValuePaletteColorsFromPost($option_id, $post) {
		$this->ensureSchema();

		$option_id = (int)$option_id;

		if (!isset($post['option_value']) || !is_array($post['option_value'])) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_value_palette_color` WHERE option_id = '" . $option_id . "'");
			return;
		}

		$seen_option_value_ids = array();

		foreach ($post['option_value'] as $option_value) {
			$option_value_id = isset($option_value['option_value_id']) ? (int)$option_value['option_value_id'] : 0;

			if ($option_value_id <= 0) {
				continue;
			}

			$seen_option_value_ids[] = $option_value_id;
			$this->saveOptionValuePaletteColor($option_id, $option_value_id, isset($option_value['palette_color_id']) ? (int)$option_value['palette_color_id'] : 0);
		}

		if ($seen_option_value_ids) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_value_palette_color`
				WHERE option_id = '" . $option_id . "'
				AND option_value_id NOT IN (" . implode(',', array_map('intval', $seen_option_value_ids)) . ")");
		} else {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_value_palette_color` WHERE option_id = '" . $option_id . "'");
		}
	}

	public function syncLinkedOptionValueNames($color_id, $only_option_value_id = 0) {
		$color = $this->getColorById($color_id);

		if (!$color || empty($color['name'])) {
			return;
		}

		$sql = "SELECT option_value_id FROM `" . DB_PREFIX . "cyberpunks_option_value_palette_color` WHERE color_id = '" . (int)$color_id . "'";

		if ((int)$only_option_value_id > 0) {
			$sql .= " AND option_value_id = '" . (int)$only_option_value_id . "'";
		}

		$links = $this->db->query($sql);

		foreach ($links->rows as $link) {
			$option_value_id = (int)$link['option_value_id'];
			$this->db->query("UPDATE `" . DB_PREFIX . "option_value_description` SET name = '" . $this->db->escape($color['name']) . "' WHERE option_value_id = '" . $option_value_id . "'");
		}
	}

	private function deletePaletteLinks($palette_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_color_palette` WHERE palette_id = '" . (int)$palette_id . "'");
	}

	private function deleteColorLinks($color_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_value_palette_color` WHERE color_id = '" . (int)$color_id . "'");
	}

	/**
	 * When a select/radio/checkbox/image option is attached to a product with no values,
	 * OpenCart skips saving the option entirely. Fill all catalog option values with defaults.
	 */
	public function ensureProductOptionValues(&$data) {
		if (empty($data['product_option']) || !is_array($data['product_option'])) {
			return;
		}

		foreach ($data['product_option'] as $key => $product_option) {
			$type = isset($product_option['type']) ? $product_option['type'] : '';

			if (!in_array($type, array('select', 'radio', 'checkbox', 'image'), true)) {
				continue;
			}

			if (!empty($product_option['product_option_value']) && is_array($product_option['product_option_value'])) {
				continue;
			}

			$option_id = isset($product_option['option_id']) ? (int)$product_option['option_id'] : 0;

			if ($option_id <= 0) {
				continue;
			}

			$query = $this->db->query("SELECT option_value_id FROM `" . DB_PREFIX . "option_value` WHERE option_id = '" . $option_id . "' ORDER BY sort_order ASC, option_value_id ASC");
			$values = array();

			foreach ($query->rows as $row) {
				$values[] = array(
					'product_option_value_id' => '',
					'option_value_id' => (int)$row['option_value_id'],
					'quantity' => 1,
					'subtract' => 0,
					'price' => 0,
					'price_prefix' => '+',
					'points' => 0,
					'points_prefix' => '+',
					'weight' => 0,
					'weight_prefix' => '+'
				);
			}

			$data['product_option'][$key]['product_option_value'] = $values;
		}
	}

	/**
	 * Ensure catalog option_value rows match colors from attached palettes.
	 * Keeps existing option_value_id when already linked to a palette color.
	 */
	public function syncOptionValuesFromPalettes($option_id) {
		$this->ensureSchema();

		$option_id = (int)$option_id;
		$result = array(
			'option_values_added' => 0,
			'option_values_updated' => 0,
			'option_values_removed' => 0
		);

		if ($option_id <= 0) {
			return $result;
		}

		$palette_ids = $this->getOptionPaletteIds($option_id);

		if (!$palette_ids) {
			return $result;
		}

		$wanted_colors = array();

		foreach ($palette_ids as $palette_id) {
			foreach ($this->getPaletteColors((int)$palette_id, true) as $color) {
				$color_id = (int)$color['color_id'];

				if ($color_id <= 0 || empty($color['name']) || isset($wanted_colors[$color_id])) {
					continue;
				}

				$wanted_colors[$color_id] = $color;
			}
		}

		if (!$wanted_colors) {
			return $result;
		}

		$existing_values_query = $this->db->query("SELECT option_value_id, sort_order, image FROM `" . DB_PREFIX . "option_value` WHERE option_id = '" . $option_id . "'");
		$existing_value_ids = array();

		foreach ($existing_values_query->rows as $row) {
			$existing_value_ids[(int)$row['option_value_id']] = $row;
		}

		$links = $this->getOptionValuePaletteColors($option_id);
		$color_to_option_value = array();

		foreach ($links as $option_value_id => $color_id) {
			$option_value_id = (int)$option_value_id;
			$color_id = (int)$color_id;

			if ($option_value_id <= 0 || !isset($existing_value_ids[$option_value_id]) || !isset($wanted_colors[$color_id]) || isset($color_to_option_value[$color_id])) {
				continue;
			}

			$color_to_option_value[$color_id] = $option_value_id;
		}

		// Fallback: match leftover option values by name to unmatched colors
		$name_to_color = array();

		foreach ($wanted_colors as $color_id => $color) {
			if (isset($color_to_option_value[$color_id])) {
				continue;
			}

			$name_key = utf8_strtolower(trim($color['name']));

			if ($name_key !== '' && !isset($name_to_color[$name_key])) {
				$name_to_color[$name_key] = $color_id;
			}
		}

		if ($name_to_color && $existing_value_ids) {
			$used_option_value_ids = array_values($color_to_option_value);
			$descriptions = $this->db->query("SELECT option_value_id, name FROM `" . DB_PREFIX . "option_value_description` WHERE option_value_id IN (" . implode(',', array_map('intval', array_keys($existing_value_ids))) . ") AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

			foreach ($descriptions->rows as $description) {
				$option_value_id = (int)$description['option_value_id'];

				if (in_array($option_value_id, $used_option_value_ids, true)) {
					continue;
				}

				$name_key = utf8_strtolower(trim($description['name']));

				if ($name_key === '' || !isset($name_to_color[$name_key])) {
					continue;
				}

				$color_id = (int)$name_to_color[$name_key];
				unset($name_to_color[$name_key]);
				$color_to_option_value[$color_id] = $option_value_id;
				$used_option_value_ids[] = $option_value_id;
			}
		}

		$languages = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language`");
		$swatch_field_id = $this->getCustomFieldIdByKey('swatch_color');
		$model_field_id = $this->getCustomFieldIdByKey('model_color');
		$keep_option_value_ids = array();
		$sort_index = 0;

		foreach ($wanted_colors as $color_id => $color) {
			$swatch_color = isset($color['swatch_color']) ? (string)$color['swatch_color'] : '';
			$model_color = isset($color['model_color']) ? (string)$color['model_color'] : '';

			if ($model_color === '') {
				$model_color = $swatch_color;
			}

			$sort_order = isset($color['sort_order']) ? (int)$color['sort_order'] : $sort_index;

			if (isset($color_to_option_value[$color_id])) {
				$option_value_id = (int)$color_to_option_value[$color_id];
				$this->db->query("UPDATE `" . DB_PREFIX . "option_value` SET sort_order = '" . $sort_order . "' WHERE option_value_id = '" . $option_value_id . "' AND option_id = '" . $option_id . "'");

				foreach ($languages->rows as $language) {
					$language_id = (int)$language['language_id'];
					$exists = $this->db->query("SELECT option_value_id FROM `" . DB_PREFIX . "option_value_description` WHERE option_value_id = '" . $option_value_id . "' AND language_id = '" . $language_id . "'");

					if ($exists->num_rows) {
						$this->db->query("UPDATE `" . DB_PREFIX . "option_value_description` SET name = '" . $this->db->escape($color['name']) . "' WHERE option_value_id = '" . $option_value_id . "' AND language_id = '" . $language_id . "'");
					} else {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "option_value_description` SET option_value_id = '" . $option_value_id . "', language_id = '" . $language_id . "', option_id = '" . $option_id . "', name = '" . $this->db->escape($color['name']) . "'");
					}
				}

				$this->saveOptionValuePaletteColor($option_id, $option_value_id, $color_id);
				$this->savePaletteColorCustomFields($option_id, $option_value_id, $swatch_field_id, $model_field_id, $swatch_color, $model_color);
				$result['option_values_updated']++;
			} else {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "option_value` SET option_id = '" . $option_id . "', image = '', sort_order = '" . $sort_order . "'");
				$option_value_id = (int)$this->db->getLastId();

				foreach ($languages->rows as $language) {
					$this->db->query("INSERT INTO `" . DB_PREFIX . "option_value_description` SET option_value_id = '" . $option_value_id . "', language_id = '" . (int)$language['language_id'] . "', option_id = '" . $option_id . "', name = '" . $this->db->escape($color['name']) . "'");
				}

				$this->saveOptionValuePaletteColor($option_id, $option_value_id, $color_id);
				$this->savePaletteColorCustomFields($option_id, $option_value_id, $swatch_field_id, $model_field_id, $swatch_color, $model_color);
				$result['option_values_added']++;
			}

			$keep_option_value_ids[] = $option_value_id;
			$sort_index++;
		}

		$keep_lookup = array_fill_keys($keep_option_value_ids, true);

		foreach ($existing_value_ids as $option_value_id => $row) {
			if (isset($keep_lookup[$option_value_id])) {
				continue;
			}

			$this->db->query("DELETE FROM `" . DB_PREFIX . "option_value_description` WHERE option_value_id = '" . (int)$option_value_id . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_custom_field_value` WHERE option_value_id = '" . (int)$option_value_id . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_value_palette_color` WHERE option_value_id = '" . (int)$option_value_id . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "option_value` WHERE option_value_id = '" . (int)$option_value_id . "' AND option_id = '" . $option_id . "'");
			$result['option_values_removed']++;
		}

		return $result;
	}

	private function getCustomFieldIdByKey($field_key) {
		$query = $this->db->query("SELECT field_id FROM `" . DB_PREFIX . "cyberpunks_option_custom_field` WHERE field_key = '" . $this->db->escape((string)$field_key) . "' LIMIT 1");

		return $query->num_rows ? (int)$query->row['field_id'] : 0;
	}

	private function savePaletteColorCustomFields($option_id, $option_value_id, $swatch_field_id, $model_field_id, $swatch_color, $model_color) {
		$pairs = array();

		if ($swatch_field_id > 0) {
			$pairs[$swatch_field_id] = $swatch_color;
		}

		if ($model_field_id > 0) {
			$pairs[$model_field_id] = $model_color;
		}

		foreach ($pairs as $field_id => $value) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_custom_field_value` WHERE option_value_id = '" . (int)$option_value_id . "' AND field_id = '" . (int)$field_id . "'");
			$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_option_custom_field_value` SET option_id = '" . (int)$option_id . "', option_value_id = '" . (int)$option_value_id . "', field_id = '" . (int)$field_id . "', value = '" . $this->db->escape((string)$value) . "', date_modified = NOW()");
		}
	}

	/**
	 * Sync catalog option_value list into every product that uses this option.
	 * - Missing option values are inserted with qty=1, subtract=0, price/points/weight=0
	 * - Existing rows keep quantity / subtract / price / points / weight untouched
	 * - Orphans / duplicates / removed catalog values are deleted from products
	 */
	public function syncOptionValuesToProducts($option_id) {
		$this->ensureSchema();

		$option_id = (int)$option_id;
		$result = array(
			'products' => 0,
			'added' => 0,
			'removed' => 0,
			'kept' => 0
		);

		if ($option_id <= 0) {
			return $result;
		}

		$option_values_query = $this->db->query("SELECT option_value_id FROM `" . DB_PREFIX . "option_value` WHERE option_id = '" . $option_id . "' ORDER BY sort_order ASC, option_value_id ASC");
		$wanted_ids = array();

		foreach ($option_values_query->rows as $row) {
			$wanted_ids[] = (int)$row['option_value_id'];
		}

		$wanted_lookup = array_fill_keys($wanted_ids, true);

		$product_options_query = $this->db->query("SELECT product_option_id, product_id FROM `" . DB_PREFIX . "product_option` WHERE option_id = '" . $option_id . "'");

		foreach ($product_options_query->rows as $product_option) {
			$result['products']++;

			$product_option_id = (int)$product_option['product_option_id'];
			$product_id = (int)$product_option['product_id'];
			$have = array();

			$existing_query = $this->db->query("SELECT product_option_value_id, option_value_id FROM `" . DB_PREFIX . "product_option_value` WHERE product_option_id = '" . $product_option_id . "'");

			foreach ($existing_query->rows as $existing) {
				$product_option_value_id = (int)$existing['product_option_value_id'];
				$option_value_id = (int)$existing['option_value_id'];

				if ($option_value_id <= 0 || !isset($wanted_lookup[$option_value_id]) || isset($have[$option_value_id])) {
					$this->db->query("DELETE FROM `" . DB_PREFIX . "product_option_value` WHERE product_option_value_id = '" . $product_option_value_id . "'");
					$result['removed']++;
					continue;
				}

				$have[$option_value_id] = $product_option_value_id;
				$result['kept']++;
			}

			foreach ($wanted_ids as $option_value_id) {
				if (isset($have[$option_value_id])) {
					continue;
				}

				$this->db->query("INSERT INTO `" . DB_PREFIX . "product_option_value` SET
					product_option_id = '" . $product_option_id . "',
					product_id = '" . $product_id . "',
					option_id = '" . $option_id . "',
					option_value_id = '" . (int)$option_value_id . "',
					quantity = '1',
					subtract = '0',
					price = '0',
					price_prefix = '+',
					points = '0',
					points_prefix = '+',
					weight = '0',
					weight_prefix = '+'");

				$result['added']++;
			}
		}

		return $result;
	}

	private function normalizeColorValue($value) {
		$value = trim((string)$value);

		if ($value === '') {
			return '';
		}

		if (strtolower($value) === 'random') {
			return 'random';
		}

		if ($value[0] !== '#') {
			$value = '#' . $value;
		}

		return strtolower($value);
	}
}

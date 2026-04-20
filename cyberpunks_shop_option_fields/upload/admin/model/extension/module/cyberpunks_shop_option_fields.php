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
	}

	public function install() {
		$this->ensureSchema();
	}

	public function uninstall() {
		// Keep data by default on uninstall.
	}

	public function getCustomFields($only_active = false) {
		$this->ensureSchema();

		$sql = "SELECT * FROM `" . DB_PREFIX . "cyberpunks_option_custom_field`";

		if ($only_active) {
			$sql .= " WHERE status = '1'";
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
			$scope = (isset($field['scope']) && $field['scope'] === 'option') ? 'option' : 'option_value';
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

			return array(
				'show_image' => (int)$query->row['show_image'],
				'enabled_field_ids' => $enabled_field_ids
			);
		}

		return array('show_image' => 1, 'enabled_field_ids' => array());
	}

	public function saveOptionMode($option_id, $post) {
		$this->ensureSchema();

		$show_image = isset($post['cyberpunks_option_mode']['show_image']) ? 1 : 0;
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
		$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_option_display_mode` SET option_id = '" . (int)$option_id . "', show_image = '" . (int)$show_image . "', enabled_field_ids = '" . $this->db->escape(json_encode($enabled_field_ids)) . "', date_modified = NOW()");
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

		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_display_mode` WHERE option_id = '" . (int)$option_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_custom_field_value` WHERE option_id = '" . (int)$option_id . "'");
	}
}

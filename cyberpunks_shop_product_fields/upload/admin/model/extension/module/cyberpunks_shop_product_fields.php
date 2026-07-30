<?php
class ModelExtensionModuleCyberpunksShopProductFields extends Model {
	private function ensureSchema() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_product_field` (
			`field_id` INT(11) NOT NULL AUTO_INCREMENT,
			`field_key` VARCHAR(64) NOT NULL,
			`label` VARCHAR(128) NOT NULL,
			`field_type` VARCHAR(32) NOT NULL DEFAULT 'text',
			`select_options` TEXT NOT NULL,
			`admin_hint` VARCHAR(255) NOT NULL,
			`sort_order` INT(11) NOT NULL DEFAULT '0',
			`status` TINYINT(1) NOT NULL DEFAULT '1',
			`date_added` DATETIME NOT NULL,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`field_id`),
			UNIQUE KEY `field_key` (`field_key`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_product_field_value` (
			`product_id` INT(11) NOT NULL,
			`field_id` INT(11) NOT NULL,
			`value` MEDIUMTEXT NOT NULL,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`product_id`,`field_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");

		$col = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "cyberpunks_product_field_value` LIKE 'value'");
		if ($col->num_rows && stripos($col->row['Type'], 'mediumtext') === false) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "cyberpunks_product_field_value` MODIFY `value` MEDIUMTEXT NOT NULL");
		}
	}

	public function install() {
		$this->ensureSchema();
	}

	public function getFields($only_active = false) {
		$this->ensureSchema();

		$sql = "SELECT * FROM `" . DB_PREFIX . "cyberpunks_product_field`";

		if ($only_active) {
			$sql .= " WHERE status = '1'";
		}

		$sql .= " ORDER BY sort_order ASC, field_id ASC";

		$rows = $this->db->query($sql)->rows;

		foreach ($rows as $index => $row) {
			$rows[$index]['options'] = $this->parseSelectOptions($row['select_options']);
		}

		return $rows;
	}

	public function saveFields($fields) {
		$this->ensureSchema();

		$keep_ids = array();

		if (!is_array($fields)) {
			$fields = array();
		}

		foreach ($fields as $field) {
			$field_id = isset($field['field_id']) ? (int)$field['field_id'] : 0;
			$field_key = $this->normalizeFieldKey(isset($field['field_key']) ? $field['field_key'] : '');
			$label = isset($field['label']) ? trim((string)$field['label']) : '';

			if ($field_key === '' || $label === '') {
				continue;
			}

			$field_type = 'text';
			if (isset($field['field_type']) && in_array($field['field_type'], array('checkbox', 'select', 'html', 'textarea'), true)) {
				$field_type = $field['field_type'];
			}

			$select_options = isset($field['select_options']) ? trim((string)$field['select_options']) : '';
			$admin_hint = isset($field['admin_hint']) ? trim((string)$field['admin_hint']) : '';
			$sort_order = isset($field['sort_order']) ? (int)$field['sort_order'] : 0;
			$status = !empty($field['status']) ? 1 : 0;

			if ($field_id > 0) {
				$this->db->query("UPDATE `" . DB_PREFIX . "cyberpunks_product_field` SET
					`field_key` = '" . $this->db->escape($field_key) . "',
					`label` = '" . $this->db->escape($label) . "',
					`field_type` = '" . $this->db->escape($field_type) . "',
					`select_options` = '" . $this->db->escape($select_options) . "',
					`admin_hint` = '" . $this->db->escape($admin_hint) . "',
					`sort_order` = '" . (int)$sort_order . "',
					`status` = '" . (int)$status . "',
					`date_modified` = NOW()
					WHERE field_id = '" . (int)$field_id . "'");
				$keep_ids[] = $field_id;
			} else {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_product_field` SET
					`field_key` = '" . $this->db->escape($field_key) . "',
					`label` = '" . $this->db->escape($label) . "',
					`field_type` = '" . $this->db->escape($field_type) . "',
					`select_options` = '" . $this->db->escape($select_options) . "',
					`admin_hint` = '" . $this->db->escape($admin_hint) . "',
					`sort_order` = '" . (int)$sort_order . "',
					`status` = '" . (int)$status . "',
					`date_added` = NOW(),
					`date_modified` = NOW()");
				$keep_ids[] = (int)$this->db->getLastId();
			}
		}

		if ($keep_ids) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_field` WHERE field_id NOT IN (" . implode(',', array_map('intval', $keep_ids)) . ")");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_field_value` WHERE field_id NOT IN (" . implode(',', array_map('intval', $keep_ids)) . ")");
		} else {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_field_value`");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_field`");
		}
	}

	public function getProductFieldValues($product_id) {
		$this->ensureSchema();

		$data = array();
		$query = $this->db->query("SELECT field_id, value FROM `" . DB_PREFIX . "cyberpunks_product_field_value` WHERE product_id = '" . (int)$product_id . "'");

		foreach ($query->rows as $row) {
			$data[(int)$row['field_id']] = $row['value'];
		}

		return $data;
	}

	public function saveProductFieldValues($product_id, $values) {
		$this->ensureSchema();

		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_field_value` WHERE product_id = '" . (int)$product_id . "'");

		if (!is_array($values)) {
			return;
		}

		foreach ($values as $field_id => $value) {
			$field_id = (int)$field_id;
			if ($field_id <= 0) {
				continue;
			}

			if (is_array($value)) {
				$value = '';
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_product_field_value` SET product_id = '" . (int)$product_id . "', field_id = '" . (int)$field_id . "', value = '" . $this->db->escape((string)$value) . "', date_modified = NOW()");
		}
	}

	public function deleteProductFieldValues($product_id) {
		$this->ensureSchema();

		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_field_value` WHERE product_id = '" . (int)$product_id . "'");
	}

	public function getProductFieldsMap($product_id) {
		$this->ensureSchema();

		$data = array();
		$query = $this->db->query("SELECT f.field_key, v.value FROM `" . DB_PREFIX . "cyberpunks_product_field_value` v LEFT JOIN `" . DB_PREFIX . "cyberpunks_product_field` f ON (v.field_id = f.field_id) WHERE v.product_id = '" . (int)$product_id . "' AND f.status = '1'");

		foreach ($query->rows as $row) {
			if (!empty($row['field_key'])) {
				$data[$row['field_key']] = $row['value'];
			}
		}

		return $data;
	}

	private function normalizeFieldKey($field_key) {
		$field_key = strtolower(trim((string)$field_key));
		return preg_replace('/[^a-z0-9_]/', '_', $field_key);
	}

	private function parseSelectOptions($raw) {
		$options = array();
		$lines = preg_split('/\R/', (string)$raw);

		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}

			$parts = explode('|', $line, 2);
			$value = trim($parts[0]);
			$label = isset($parts[1]) ? trim($parts[1]) : $value;

			if ($value === '') {
				continue;
			}

			$options[] = array(
				'value' => $value,
				'label' => $label
			);
		}

		return $options;
	}
}

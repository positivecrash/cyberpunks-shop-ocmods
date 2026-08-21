<?php
class ModelExtensionModuleCyberpunksShopProductFields extends Model {
	private function ensureSchema() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_product_field_section` (
			`section_id` INT(11) NOT NULL AUTO_INCREMENT,
			`title` VARCHAR(128) NOT NULL,
			`sort_order` INT(11) NOT NULL DEFAULT '0',
			`date_added` DATETIME NOT NULL,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`section_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_product_field` (
			`field_id` INT(11) NOT NULL AUTO_INCREMENT,
			`section_id` INT(11) NOT NULL DEFAULT '0',
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
			UNIQUE KEY `field_key` (`field_key`),
			KEY `section_id` (`section_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");

		$section_col = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "cyberpunks_product_field` LIKE 'section_id'");
		if (!$section_col->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "cyberpunks_product_field` ADD `section_id` INT(11) NOT NULL DEFAULT '0' AFTER `field_id`, ADD KEY `section_id` (`section_id`)");
		}

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

	public function getSections() {
		$this->ensureSchema();

		return $this->db->query("SELECT * FROM `" . DB_PREFIX . "cyberpunks_product_field_section` ORDER BY sort_order ASC, section_id ASC")->rows;
	}

	public function getFields($only_active = false) {
		$this->ensureSchema();

		$sql = "SELECT f.*, s.title AS section_title, s.sort_order AS section_sort_order
			FROM `" . DB_PREFIX . "cyberpunks_product_field` f
			LEFT JOIN `" . DB_PREFIX . "cyberpunks_product_field_section` s ON (f.section_id = s.section_id)";

		if ($only_active) {
			$sql .= " WHERE f.status = '1'";
		}

		$sql .= " ORDER BY COALESCE(s.sort_order, 2147483647) ASC, s.section_id ASC, f.sort_order ASC, f.field_id ASC";

		$rows = $this->db->query($sql)->rows;

		foreach ($rows as $index => $row) {
			$rows[$index]['options'] = $this->parseSelectOptions($row['select_options']);
		}

		return $rows;
	}

	public function getFieldGroups($only_active = false) {
		$this->ensureSchema();

		$sections = $this->getSections();
		$fields = $this->getFields($only_active);
		$groups = array();
		$ungrouped = array();

		foreach ($sections as $section) {
			$groups[(int)$section['section_id']] = array(
				'section_id' => (int)$section['section_id'],
				'title' => $section['title'],
				'sort_order' => (int)$section['sort_order'],
				'fields' => array()
			);
		}

		foreach ($fields as $field) {
			$section_id = (int)$field['section_id'];

			if ($section_id > 0 && isset($groups[$section_id])) {
				$groups[$section_id]['fields'][] = $field;
			} else {
				$ungrouped[] = $field;
			}
		}

		$ordered_groups = array_values($groups);

		usort($ordered_groups, function ($a, $b) {
			if ($a['sort_order'] === $b['sort_order']) {
				return $a['section_id'] <=> $b['section_id'];
			}

			return $a['sort_order'] <=> $b['sort_order'];
		});

		return array(
			'ungrouped_fields' => $ungrouped,
			'sections' => $ordered_groups
		);
	}

	public function getAdminLayout() {
		$layout = $this->getFieldGroups(false);

		foreach ($layout['sections'] as $index => $section) {
			$layout['sections'][$index]['section_row'] = $index;
		}

		return $layout;
	}

	public function saveSectionsAndFields($sections, $fields) {
		$this->ensureSchema();

		$duplicate_keys = $this->findDuplicateFieldKeys($fields);

		if ($duplicate_keys) {
			return $duplicate_keys;
		}

		$section_map = $this->saveSections($sections);
		$this->saveFields($fields, $section_map);

		return array();
	}

	public function findDuplicateFieldKeys($fields) {
		$duplicate_keys = array();

		if (!is_array($fields)) {
			return $duplicate_keys;
		}

		$seen = array();

		foreach ($fields as $field) {
			$field_key = $this->normalizeFieldKey(isset($field['field_key']) ? $field['field_key'] : '');
			$label = isset($field['label']) ? trim((string)$field['label']) : '';

			if ($field_key === '' || $label === '') {
				continue;
			}

			if (isset($seen[$field_key])) {
				$duplicate_keys[$field_key] = $field_key;
				continue;
			}

			$seen[$field_key] = true;
		}

		return array_values($duplicate_keys);
	}

	public function saveSections($sections) {
		$this->ensureSchema();

		$keep_ids = array();
		$section_map = array();

		if (!is_array($sections)) {
			$sections = array();
		}

		foreach ($sections as $section_row => $section) {
			$section_id = isset($section['section_id']) ? (int)$section['section_id'] : 0;
			$title = isset($section['title']) ? trim((string)$section['title']) : '';

			if ($title === '') {
				continue;
			}

			$sort_order = isset($section['sort_order']) ? (int)$section['sort_order'] : 0;

			if ($section_id > 0) {
				$this->db->query("UPDATE `" . DB_PREFIX . "cyberpunks_product_field_section` SET
					`title` = '" . $this->db->escape($title) . "',
					`sort_order` = '" . (int)$sort_order . "',
					`date_modified` = NOW()
					WHERE section_id = '" . (int)$section_id . "'");
				$keep_ids[] = $section_id;
				$section_map[(int)$section_row] = $section_id;
			} else {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_product_field_section` SET
					`title` = '" . $this->db->escape($title) . "',
					`sort_order` = '" . (int)$sort_order . "',
					`date_added` = NOW(),
					`date_modified` = NOW()");
				$new_id = (int)$this->db->getLastId();
				$keep_ids[] = $new_id;
				$section_map[(int)$section_row] = $new_id;
			}
		}

		if ($keep_ids) {
			$this->db->query("UPDATE `" . DB_PREFIX . "cyberpunks_product_field` SET section_id = '0' WHERE section_id NOT IN (" . implode(',', array_map('intval', $keep_ids)) . ")");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_field_section` WHERE section_id NOT IN (" . implode(',', array_map('intval', $keep_ids)) . ")");
		} else {
			$this->db->query("UPDATE `" . DB_PREFIX . "cyberpunks_product_field` SET section_id = '0'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_field_section`");
		}

		return $section_map;
	}

	public function saveFields($fields, $section_map = array()) {
		$this->ensureSchema();

		if (!is_array($fields)) {
			$fields = array();
		}

		if (!is_array($section_map)) {
			$section_map = array();
		}

		$pending = array();

		foreach ($fields as $field) {
			$field_id = isset($field['field_id']) ? (int)$field['field_id'] : 0;
			$field_key = $this->normalizeFieldKey(isset($field['field_key']) ? $field['field_key'] : '');
			$label = isset($field['label']) ? trim((string)$field['label']) : '';

			if ($field_key === '' || $label === '') {
				continue;
			}

			$section_row = isset($field['section_row']) ? (int)$field['section_row'] : -1;
			$section_id = 0;

			if ($section_row >= 0 && isset($section_map[$section_row])) {
				$section_id = (int)$section_map[$section_row];
			}

			$field_type = 'text';
			if (isset($field['field_type']) && in_array($field['field_type'], array('checkbox', 'checkboxes', 'select', 'html', 'textarea', 'image'), true)) {
				$field_type = $field['field_type'];
			}

			$pending[] = array(
				'field_id' => $field_id,
				'section_id' => $section_id,
				'field_key' => $field_key,
				'label' => $label,
				'field_type' => $field_type,
				'select_options' => isset($field['select_options']) ? trim((string)$field['select_options']) : '',
				'admin_hint' => isset($field['admin_hint']) ? trim((string)$field['admin_hint']) : '',
				'sort_order' => isset($field['sort_order']) ? (int)$field['sort_order'] : 0,
				'status' => !empty($field['status']) ? 1 : 0
			);
		}

		$keep_ids = array();

		foreach ($pending as $field) {
			if ($field['field_id'] > 0) {
				$keep_ids[] = (int)$field['field_id'];
			}
		}

		if ($keep_ids) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_field_value` WHERE field_id NOT IN (" . implode(',', array_map('intval', $keep_ids)) . ")");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_field` WHERE field_id NOT IN (" . implode(',', array_map('intval', $keep_ids)) . ")");
		} elseif ($pending) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_field_value`");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_field`");
		} else {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_field_value`");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_field`");
			return;
		}

		$keep_ids = array();

		foreach ($pending as $field) {
			if ($field['field_id'] > 0) {
				$this->db->query("UPDATE `" . DB_PREFIX . "cyberpunks_product_field` SET
					`section_id` = '" . (int)$field['section_id'] . "',
					`field_key` = '" . $this->db->escape($field['field_key']) . "',
					`label` = '" . $this->db->escape($field['label']) . "',
					`field_type` = '" . $this->db->escape($field['field_type']) . "',
					`select_options` = '" . $this->db->escape($field['select_options']) . "',
					`admin_hint` = '" . $this->db->escape($field['admin_hint']) . "',
					`sort_order` = '" . (int)$field['sort_order'] . "',
					`status` = '" . (int)$field['status'] . "',
					`date_modified` = NOW()
					WHERE field_id = '" . (int)$field['field_id'] . "'");
				$keep_ids[] = (int)$field['field_id'];
			} else {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_product_field` SET
					`section_id` = '" . (int)$field['section_id'] . "',
					`field_key` = '" . $this->db->escape($field['field_key']) . "',
					`label` = '" . $this->db->escape($field['label']) . "',
					`field_type` = '" . $this->db->escape($field['field_type']) . "',
					`select_options` = '" . $this->db->escape($field['select_options']) . "',
					`admin_hint` = '" . $this->db->escape($field['admin_hint']) . "',
					`sort_order` = '" . (int)$field['sort_order'] . "',
					`status` = '" . (int)$field['status'] . "',
					`date_added` = NOW(),
					`date_modified` = NOW()");
				$keep_ids[] = (int)$this->db->getLastId();
			}
		}
	}

	public function getProductFieldValues($product_id) {
		$this->ensureSchema();

		$data = array();
		$query = $this->db->query("SELECT v.field_id, v.value, f.field_type FROM `" . DB_PREFIX . "cyberpunks_product_field_value` v LEFT JOIN `" . DB_PREFIX . "cyberpunks_product_field` f ON (v.field_id = f.field_id) WHERE v.product_id = '" . (int)$product_id . "'");

		foreach ($query->rows as $row) {
			$field_id = (int)$row['field_id'];
			$value = $row['value'];

			if (isset($row['field_type']) && $row['field_type'] === 'checkboxes') {
				$value = $this->decodeCheckboxListValue($value);
			}

			$data[$field_id] = $value;
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
				$clean = array();

				foreach ($value as $item) {
					$item = trim((string)$item);

					if ($item !== '') {
						$clean[] = $item;
					}
				}

				$value = $clean ? json_encode(array_values($clean)) : '';
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

	private function decodeCheckboxListValue($raw) {
		$raw = trim((string)$raw);

		if ($raw === '') {
			return array();
		}

		$decoded = json_decode($raw, true);

		if (is_array($decoded)) {
			$values = array();

			foreach ($decoded as $item) {
				$item = trim((string)$item);

				if ($item !== '') {
					$values[] = $item;
				}
			}

			return $values;
		}

		$lines = preg_split('/\R/', $raw);
		$values = array();

		foreach ($lines as $line) {
			$line = trim($line);

			if ($line !== '') {
				$values[] = $line;
			}
		}

		return $values;
	}
}

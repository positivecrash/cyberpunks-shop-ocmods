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
			`language_id` INT(11) NOT NULL DEFAULT '0',
			`value` MEDIUMTEXT NOT NULL,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`product_id`,`field_id`,`language_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");

		$col = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "cyberpunks_product_field_value` LIKE 'value'");
		if ($col->num_rows && stripos($col->row['Type'], 'mediumtext') === false) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "cyberpunks_product_field_value` MODIFY `value` MEDIUMTEXT NOT NULL");
		}

		$this->ensureValueLanguageColumn();
	}

	private function ensureValueLanguageColumn() {
		$col = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "cyberpunks_product_field_value` LIKE 'language_id'");

		if ($col->num_rows) {
			return;
		}

		$this->db->query("ALTER TABLE `" . DB_PREFIX . "cyberpunks_product_field_value` ADD `language_id` INT(11) NOT NULL DEFAULT '0' AFTER `field_id`");
		$this->db->query("ALTER TABLE `" . DB_PREFIX . "cyberpunks_product_field_value` DROP PRIMARY KEY, ADD PRIMARY KEY (`product_id`, `field_id`, `language_id`)");

		$languages = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE status = '1'")->rows;

		if (!$languages) {
			return;
		}

		$legacy = $this->db->query("SELECT v.product_id, v.field_id, v.value
			FROM `" . DB_PREFIX . "cyberpunks_product_field_value` v
			INNER JOIN `" . DB_PREFIX . "cyberpunks_product_field` f ON (v.field_id = f.field_id)
			WHERE v.language_id = '0'
				AND f.field_type IN ('text', 'textarea', 'html')");

		foreach ($legacy->rows as $row) {
			foreach ($languages as $language) {
				$language_id = (int)$language['language_id'];

				if ($language_id < 1) {
					continue;
				}

				$exists = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "cyberpunks_product_field_value`
					WHERE product_id = '" . (int)$row['product_id'] . "'
						AND field_id = '" . (int)$row['field_id'] . "'
						AND language_id = '" . (int)$language_id . "'");

				if ($exists->num_rows) {
					continue;
				}

				$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_product_field_value` SET
					product_id = '" . (int)$row['product_id'] . "',
					field_id = '" . (int)$row['field_id'] . "',
					language_id = '" . (int)$language_id . "',
					value = '" . $this->db->escape($row['value']) . "',
					date_modified = NOW()");
			}
		}
	}

	public function isMultilingualFieldType($field_type) {
		return in_array((string)$field_type, array('text', 'textarea', 'html'), true);
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

			if (isset($row['field_type']) && $row['field_type'] === 'repeater') {
				$rows[$index]['repeater_schema'] = $this->parseRepeaterSchema($row['select_options']);
			} else {
				$rows[$index]['repeater_schema'] = array();
			}
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
			if (isset($field['field_type']) && in_array($field['field_type'], array('checkbox', 'checkboxes', 'select', 'html', 'textarea', 'image', 'repeater'), true)) {
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
		$query = $this->db->query("SELECT v.field_id, v.language_id, v.value, f.field_type FROM `" . DB_PREFIX . "cyberpunks_product_field_value` v LEFT JOIN `" . DB_PREFIX . "cyberpunks_product_field` f ON (v.field_id = f.field_id) WHERE v.product_id = '" . (int)$product_id . "'");

		foreach ($query->rows as $row) {
			$field_id = (int)$row['field_id'];
			$language_id = isset($row['language_id']) ? (int)$row['language_id'] : 0;
			$field_type = isset($row['field_type']) ? $row['field_type'] : 'text';
			$value = $row['value'];

			if ($this->isMultilingualFieldType($field_type)) {
				if (!isset($data[$field_id]) || !is_array($data[$field_id])) {
					$data[$field_id] = array();
				}

				if ($language_id > 0) {
					$data[$field_id][$language_id] = $value;
				} elseif (!isset($data[$field_id]['_legacy'])) {
					$data[$field_id]['_legacy'] = $value;
				}

				continue;
			}

			if ($field_type === 'checkboxes') {
				$value = $this->decodeCheckboxListValue($value);
			} elseif ($field_type === 'repeater') {
				$value = $this->decodeRepeaterValue($value);
			}

			$data[$field_id] = $value;
		}

		foreach ($data as $field_id => $value) {
			if (!is_array($value) || !array_key_exists('_legacy', $value)) {
				continue;
			}

			$legacy = $value['_legacy'];
			unset($value['_legacy']);

			$languages = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE status = '1'")->rows;

			foreach ($languages as $language) {
				$language_id = (int)$language['language_id'];

				if ($language_id < 1) {
					continue;
				}

				if (!isset($value[$language_id]) || $value[$language_id] === '') {
					$value[$language_id] = $legacy;
				}
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

		$field_types = array();
		$field_type_query = $this->db->query("SELECT field_id, field_type FROM `" . DB_PREFIX . "cyberpunks_product_field`");

		foreach ($field_type_query->rows as $field_type_row) {
			$field_types[(int)$field_type_row['field_id']] = isset($field_type_row['field_type']) ? $field_type_row['field_type'] : 'text';
		}

		foreach ($values as $field_id => $value) {
			$field_id = (int)$field_id;
			if ($field_id <= 0) {
				continue;
			}

			$field_type = isset($field_types[$field_id]) ? $field_types[$field_id] : 'text';

			if ($this->isMultilingualFieldType($field_type)) {
				if (!is_array($value)) {
					$this->insertProductFieldValue($product_id, $field_id, 0, (string)$value);
					continue;
				}

				foreach ($value as $language_id => $text) {
					$language_id = (int)$language_id;

					if ($language_id < 1 || is_array($text)) {
						continue;
					}

					$this->insertProductFieldValue($product_id, $field_id, $language_id, (string)$text);
				}

				continue;
			}

			if (is_array($value)) {
				if ($field_type === 'repeater') {
					$value = $this->encodeRepeaterValue($value);
				} else {
					$clean = array();

					foreach ($value as $item) {
						$item = trim((string)$item);

						if ($item !== '') {
							$clean[] = $item;
						}
					}

					$value = $clean ? json_encode(array_values($clean)) : '';
				}
			}

			$this->insertProductFieldValue($product_id, $field_id, 0, (string)$value);
		}
	}

	private function insertProductFieldValue($product_id, $field_id, $language_id, $value) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_product_field_value` SET
			product_id = '" . (int)$product_id . "',
			field_id = '" . (int)$field_id . "',
			language_id = '" . (int)$language_id . "',
			value = '" . $this->db->escape($value) . "',
			date_modified = NOW()");
	}

	public function deleteProductFieldValues($product_id) {
		$this->ensureSchema();

		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_product_field_value` WHERE product_id = '" . (int)$product_id . "'");
	}

	public function getProductFieldsMap($product_id) {
		$this->ensureSchema();

		$data = array();
		$query = $this->db->query("SELECT f.field_key, f.field_type, v.language_id, v.value FROM `" . DB_PREFIX . "cyberpunks_product_field_value` v LEFT JOIN `" . DB_PREFIX . "cyberpunks_product_field` f ON (v.field_id = f.field_id) WHERE v.product_id = '" . (int)$product_id . "' AND f.status = '1'");
		$grouped = array();

		foreach ($query->rows as $row) {
			if (empty($row['field_key'])) {
				continue;
			}

			$key = $row['field_key'];
			$language_id = isset($row['language_id']) ? (int)$row['language_id'] : 0;

			if (!isset($grouped[$key])) {
				$grouped[$key] = array(
					'type' => isset($row['field_type']) ? $row['field_type'] : 'text',
					'values' => array()
				);
			}

			$grouped[$key]['values'][$language_id] = $row['value'];
		}

		$language_id = (int)$this->config->get('config_language_id');

		foreach ($grouped as $key => $item) {
			$values = $item['values'];

			if ($this->isMultilingualFieldType($item['type'])) {
				if ($language_id > 0 && isset($values[$language_id]) && $values[$language_id] !== '') {
					$data[$key] = $values[$language_id];
				} elseif (isset($values[0]) && $values[0] !== '') {
					$data[$key] = $values[0];
				} else {
					$data[$key] = $values ? (string)reset($values) : '';
				}
			} else {
				$data[$key] = isset($values[0]) ? $values[0] : (string)reset($values);
			}
		}

		return $data;
	}

	private function normalizeFieldKey($field_key) {
		$field_key = strtolower(trim((string)$field_key));
		return preg_replace('/[^a-z0-9_]/', '_', $field_key);
	}

	public function parseRepeaterSchema($raw) {
		$fields = array();
		$lines = preg_split('/\R/', (string)$raw);

		foreach ($lines as $line) {
			$line = trim($line);

			if ($line === '' || strpos($line, '#') === 0) {
				continue;
			}

			$parts = explode('|', $line);
			$key = isset($parts[0]) ? trim($parts[0]) : '';
			$type = isset($parts[1]) ? trim(strtolower($parts[1])) : 'text';
			$label = isset($parts[2]) ? trim($parts[2]) : $key;

			if ($key === '') {
				continue;
			}

			if (!in_array($type, array('text', 'textarea', 'select', 'checkbox', 'checkboxes', 'image'), true)) {
				$type = 'text';
			}

			$field = array(
				'key' => $key,
				'type' => $type,
				'label' => $label !== '' ? $label : $key,
				'options' => array()
			);

			if (isset($parts[3]) && trim($parts[3]) !== '' && in_array($type, array('select', 'checkboxes'), true)) {
				foreach (explode(',', $parts[3]) as $option_part) {
					$option_part = trim($option_part);

					if ($option_part === '') {
						continue;
					}

					if (strpos($option_part, ':') !== false) {
						list($option_value, $option_label) = explode(':', $option_part, 2);
						$option_value = trim($option_value);
						$option_label = trim($option_label);
					} else {
						$option_value = $option_part;
						$option_label = $option_part;
					}

					if ($option_value === '') {
						continue;
					}

					$field['options'][] = array(
						'value' => $option_value,
						'label' => $option_label !== '' ? $option_label : $option_value
					);
				}
			}

			$fields[] = $field;
		}

		return $fields;
	}

	private function encodeRepeaterValue($value) {
		if (!is_array($value)) {
			return '';
		}

		$rows = array();

		foreach ($value as $row) {
			if (!is_array($row)) {
				continue;
			}

			$clean_row = array();
			$has_content = false;

			foreach ($row as $sub_key => $sub_value) {
				$sub_key = trim((string)$sub_key);

				if ($sub_key === '') {
					continue;
				}

				if (is_array($sub_value)) {
					$clean_list = array();

					foreach ($sub_value as $item) {
						$item = trim((string)$item);

						if ($item !== '') {
							$clean_list[] = $item;
							$has_content = true;
						}
					}

					if ($clean_list) {
						$clean_row[$sub_key] = array_values($clean_list);
					}

					continue;
				}

				$sub_value = trim((string)$sub_value);

				if ($sub_value !== '') {
					$clean_row[$sub_key] = $sub_value;
					$has_content = true;
				}
			}

			if ($has_content) {
				$rows[] = $clean_row;
			}
		}

		return $rows ? json_encode(array_values($rows), JSON_UNESCAPED_UNICODE) : '';
	}

	private function decodeRepeaterValue($raw) {
		$raw = trim((string)$raw);

		if ($raw === '') {
			return array();
		}

		$decoded = json_decode($raw, true);

		if (!is_array($decoded)) {
			return array();
		}

		$rows = array();

		foreach ($decoded as $row) {
			if (!is_array($row)) {
				continue;
			}

			$clean_row = array();

			foreach ($row as $sub_key => $sub_value) {
				$sub_key = trim((string)$sub_key);

				if ($sub_key === '') {
					continue;
				}

				if (is_array($sub_value)) {
					$clean_list = array();

					foreach ($sub_value as $item) {
						$item = trim((string)$item);

						if ($item !== '') {
							$clean_list[] = $item;
						}
					}

					$clean_row[$sub_key] = $clean_list;
				} else {
					$clean_row[$sub_key] = (string)$sub_value;
				}
			}

			if ($clean_row) {
				$rows[] = $clean_row;
			}
		}

		return $rows;
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

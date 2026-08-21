<?php
class ModelExtensionModuleCyberpunksShopColorPalettes extends Model {
	public function install() {
		$this->ensureSchema();
	}

	public function uninstall() {
		// Keep data by default on uninstall.
	}

	private function ensureSchema() {
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

	public function saveOptionValuePaletteColorsAfterAdd($option_id, $post, $option_value_id_map) {
		if (!isset($post['option_value']) || !is_array($post['option_value']) || !is_array($option_value_id_map)) {
			return;
		}

		foreach ($post['option_value'] as $row => $option_value) {
			if (!isset($option_value_id_map[$row])) {
				continue;
			}

			$option_value_id = (int)$option_value_id_map[$row];
			$color_id = isset($option_value['palette_color_id']) ? (int)$option_value['palette_color_id'] : 0;
			$this->saveOptionValuePaletteColor((int)$option_id, $option_value_id, $color_id);
		}
	}

	public function deleteOptionData($option_id) {
		$this->ensureSchema();

		$option_id = (int)$option_id;
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_color_palette` WHERE option_id = '" . $option_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_value_palette_color` WHERE option_id = '" . $option_id . "'");
	}

	private function deletePaletteLinks($palette_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_color_palette` WHERE palette_id = '" . (int)$palette_id . "'");
	}

	private function deleteColorLinks($color_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_option_value_palette_color` WHERE color_id = '" . (int)$color_id . "'");
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

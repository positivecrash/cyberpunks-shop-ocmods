<?php
class ModelExtensionModuleCyberpunksShopSpecEntities extends Model {
	private function ensureSchema() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_spec_entity` (
			`entity_id` INT(11) NOT NULL AUTO_INCREMENT,
			`sort_order` INT(11) NOT NULL DEFAULT '0',
			`status` TINYINT(1) NOT NULL DEFAULT '1',
			`date_added` DATETIME NOT NULL,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`entity_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_spec_entity_description` (
			`entity_id` INT(11) NOT NULL,
			`language_id` INT(11) NOT NULL,
			`name` VARCHAR(255) NOT NULL,
			`description` MEDIUMTEXT NOT NULL,
			PRIMARY KEY (`entity_id`,`language_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_spec_entity_item` (
			`item_id` INT(11) NOT NULL AUTO_INCREMENT,
			`entity_id` INT(11) NOT NULL,
			`sort_order` INT(11) NOT NULL DEFAULT '0',
			PRIMARY KEY (`item_id`),
			KEY `entity_id` (`entity_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cyberpunks_spec_entity_item_description` (
			`item_id` INT(11) NOT NULL,
			`language_id` INT(11) NOT NULL,
			`name` VARCHAR(255) NOT NULL,
			`description` MEDIUMTEXT NOT NULL,
			PRIMARY KEY (`item_id`,`language_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");
	}

	public function install() {
		$this->ensureSchema();
	}

	public function addEntity($data) {
		$this->ensureSchema();

		$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_spec_entity` SET
			sort_order = '" . (int)$data['sort_order'] . "',
			status = '" . (int)$data['status'] . "',
			date_added = NOW(),
			date_modified = NOW()");

		$entity_id = (int)$this->db->getLastId();

		$this->saveEntityDescriptions($entity_id, $data);
		$this->saveEntityItems($entity_id, $data);

		return $entity_id;
	}

	public function editEntity($entity_id, $data) {
		$this->ensureSchema();

		$this->db->query("UPDATE `" . DB_PREFIX . "cyberpunks_spec_entity` SET
			sort_order = '" . (int)$data['sort_order'] . "',
			status = '" . (int)$data['status'] . "',
			date_modified = NOW()
			WHERE entity_id = '" . (int)$entity_id . "'");

		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_spec_entity_description` WHERE entity_id = '" . (int)$entity_id . "'");
		$this->saveEntityDescriptions($entity_id, $data);

		$this->deleteEntityItems($entity_id);
		$this->saveEntityItems($entity_id, $data);
	}

	public function deleteEntity($entity_id) {
		$this->ensureSchema();

		$item_ids = $this->getEntityItemIds($entity_id);

		if ($item_ids) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_spec_entity_item_description` WHERE item_id IN (" . implode(',', array_map('intval', $item_ids)) . ")");
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_spec_entity_item` WHERE entity_id = '" . (int)$entity_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_spec_entity_description` WHERE entity_id = '" . (int)$entity_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_spec_entity` WHERE entity_id = '" . (int)$entity_id . "'");
	}

	public function getEntity($entity_id) {
		$this->ensureSchema();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cyberpunks_spec_entity` WHERE entity_id = '" . (int)$entity_id . "'");

		return $query->row;
	}

	public function getEntityDescriptions($entity_id) {
		$this->ensureSchema();

		$data = array();
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cyberpunks_spec_entity_description` WHERE entity_id = '" . (int)$entity_id . "'");

		foreach ($query->rows as $row) {
			$data[(int)$row['language_id']] = array(
				'name' => $row['name'],
				'description' => $row['description']
			);
		}

		return $data;
	}

	public function getEntityItems($entity_id) {
		$this->ensureSchema();

		$items = array();
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cyberpunks_spec_entity_item` WHERE entity_id = '" . (int)$entity_id . "' ORDER BY sort_order ASC, item_id ASC");

		foreach ($query->rows as $row) {
			$item_id = (int)$row['item_id'];
			$items[$item_id] = array(
				'item_id' => $item_id,
				'sort_order' => (int)$row['sort_order'],
				'spec_entity_item_description' => $this->getEntityItemDescriptions($item_id)
			);
		}

		return array_values($items);
	}

	public function getEntities($data = array()) {
		$this->ensureSchema();

		$sql = "SELECT e.*, ed.name FROM `" . DB_PREFIX . "cyberpunks_spec_entity` e LEFT JOIN `" . DB_PREFIX . "cyberpunks_spec_entity_description` ed ON (e.entity_id = ed.entity_id AND ed.language_id = '" . (int)$this->config->get('config_language_id') . "')";

		$sort_data = array(
			'ed.name',
			'e.sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY e.sort_order ASC, ed.name ASC";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if (!isset($data['start']) || $data['start'] < 0) {
				$data['start'] = 0;
			}

			if (!isset($data['limit']) || $data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		return $this->db->query($sql)->rows;
	}

	public function getTotalEntities() {
		$this->ensureSchema();

		return (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "cyberpunks_spec_entity`")->row['total'];
	}

	private function saveEntityDescriptions($entity_id, $data) {
		if (!isset($data['spec_entity_description']) || !is_array($data['spec_entity_description'])) {
			return;
		}

		foreach ($data['spec_entity_description'] as $language_id => $value) {
			$name = isset($value['name']) ? trim((string)$value['name']) : '';
			$description = isset($value['description']) ? (string)$value['description'] : '';

			if ($name === '' && $description === '') {
				continue;
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_spec_entity_description` SET
				entity_id = '" . (int)$entity_id . "',
				language_id = '" . (int)$language_id . "',
				name = '" . $this->db->escape($name) . "',
				description = '" . $this->db->escape($description) . "'");
		}
	}

	private function saveEntityItems($entity_id, $data) {
		if (!isset($data['spec_entity_item']) || !is_array($data['spec_entity_item'])) {
			return;
		}

		foreach ($data['spec_entity_item'] as $item) {
			$sort_order = isset($item['sort_order']) ? (int)$item['sort_order'] : 0;
			$descriptions = isset($item['spec_entity_item_description']) && is_array($item['spec_entity_item_description']) ? $item['spec_entity_item_description'] : array();

			if (!$this->itemHasContent($descriptions)) {
				continue;
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_spec_entity_item` SET
				entity_id = '" . (int)$entity_id . "',
				sort_order = '" . (int)$sort_order . "'");

			$item_id = (int)$this->db->getLastId();

			foreach ($descriptions as $language_id => $value) {
				$name = isset($value['name']) ? trim((string)$value['name']) : '';
				$description = isset($value['description']) ? (string)$value['description'] : '';

				if ($name === '' && $description === '') {
					continue;
				}

				$this->db->query("INSERT INTO `" . DB_PREFIX . "cyberpunks_spec_entity_item_description` SET
					item_id = '" . (int)$item_id . "',
					language_id = '" . (int)$language_id . "',
					name = '" . $this->db->escape($name) . "',
					description = '" . $this->db->escape($description) . "'");
			}
		}
	}

	private function deleteEntityItems($entity_id) {
		$item_ids = $this->getEntityItemIds($entity_id);

		if ($item_ids) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_spec_entity_item_description` WHERE item_id IN (" . implode(',', array_map('intval', $item_ids)) . ")");
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "cyberpunks_spec_entity_item` WHERE entity_id = '" . (int)$entity_id . "'");
	}

	private function getEntityItemIds($entity_id) {
		$item_ids = array();
		$query = $this->db->query("SELECT item_id FROM `" . DB_PREFIX . "cyberpunks_spec_entity_item` WHERE entity_id = '" . (int)$entity_id . "'");

		foreach ($query->rows as $row) {
			$item_ids[] = (int)$row['item_id'];
		}

		return $item_ids;
	}

	private function getEntityItemDescriptions($item_id) {
		$data = array();
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cyberpunks_spec_entity_item_description` WHERE item_id = '" . (int)$item_id . "'");

		foreach ($query->rows as $row) {
			$data[(int)$row['language_id']] = array(
				'name' => $row['name'],
				'description' => $row['description']
			);
		}

		return $data;
	}

	private function itemHasContent($descriptions) {
		foreach ($descriptions as $value) {
			$name = isset($value['name']) ? trim((string)$value['name']) : '';
			$description = isset($value['description']) ? trim(strip_tags((string)$value['description'])) : '';

			if ($name !== '' || $description !== '') {
				return true;
			}
		}

		return false;
	}
}

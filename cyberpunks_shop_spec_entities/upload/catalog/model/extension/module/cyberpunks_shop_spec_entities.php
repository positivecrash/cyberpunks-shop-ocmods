<?php
class ModelExtensionModuleCyberpunksShopSpecEntities extends Model {
	public function getEntity($entity_id, $language_id = 0) {
		if (!$language_id) {
			$language_id = (int)$this->config->get('config_language_id');
		}

		$entity_table = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_spec_entity") . "'");

		if (!$entity_table->num_rows) {
			return array();
		}

		$query = $this->db->query("SELECT e.entity_id, e.sort_order, e.status, ed.name, ed.description
			FROM `" . DB_PREFIX . "cyberpunks_spec_entity` e
			LEFT JOIN `" . DB_PREFIX . "cyberpunks_spec_entity_description` ed ON (e.entity_id = ed.entity_id AND ed.language_id = '" . (int)$language_id . "')
			WHERE e.entity_id = '" . (int)$entity_id . "' AND e.status = '1'");

		if (!$query->num_rows) {
			return array();
		}

		$entity = $query->row;
		$entity['items'] = $this->getEntityItems((int)$entity_id, $language_id);

		return $entity;
	}

	public function getEntitiesByIds($entity_ids, $language_id = 0) {
		if (!$language_id) {
			$language_id = (int)$this->config->get('config_language_id');
		}

		if (!is_array($entity_ids)) {
			$entity_ids = array();
		}

		$entity_ids = array_values(array_unique(array_filter(array_map('intval', $entity_ids))));

		if (!$entity_ids) {
			return array();
		}

		$entities = array();

		foreach ($entity_ids as $entity_id) {
			$entity = $this->getEntity($entity_id, $language_id);

			if ($entity) {
				$entities[] = $entity;
			}
		}

		return $entities;
	}

	private function getEntityItems($entity_id, $language_id) {
		$items = array();
		$query = $this->db->query("SELECT i.item_id, i.sort_order, id.name, id.description
			FROM `" . DB_PREFIX . "cyberpunks_spec_entity_item` i
			LEFT JOIN `" . DB_PREFIX . "cyberpunks_spec_entity_item_description` id ON (i.item_id = id.item_id AND id.language_id = '" . (int)$language_id . "')
			WHERE i.entity_id = '" . (int)$entity_id . "'
			ORDER BY i.sort_order ASC, i.item_id ASC");

		foreach ($query->rows as $row) {
			if ($row['name'] === '' && trim(strip_tags((string)$row['description'])) === '') {
				continue;
			}

			$items[] = array(
				'item_id' => (int)$row['item_id'],
				'sort_order' => (int)$row['sort_order'],
				'name' => $row['name'],
				'description' => $row['description']
			);
		}

		return $items;
	}
}

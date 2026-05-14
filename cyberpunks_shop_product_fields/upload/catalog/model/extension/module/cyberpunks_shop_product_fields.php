<?php
class ModelExtensionModuleCyberpunksShopProductFields extends Model {
	public function getProductFieldsMap($product_id) {
		$table = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_product_field") . "'");
		$value_table = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_product_field_value") . "'");

		if (!$table->num_rows || !$value_table->num_rows) {
			return array();
		}

		$data = array();
		$query = $this->db->query("SELECT f.field_key, v.value FROM `" . DB_PREFIX . "cyberpunks_product_field_value` v LEFT JOIN `" . DB_PREFIX . "cyberpunks_product_field` f ON (v.field_id = f.field_id) WHERE v.product_id = '" . (int)$product_id . "' AND f.status = '1'");

		foreach ($query->rows as $row) {
			if (!empty($row['field_key'])) {
				$data[$row['field_key']] = $row['value'];
			}
		}

		return $data;
	}
}

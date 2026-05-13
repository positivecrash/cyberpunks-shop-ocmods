<?php
class ModelExtensionModuleCyberpunksShopOptionFields extends Model {
	public function getProductFieldValueByKey($product_id, $field_key) {
		$field_key = strtolower(trim((string)$field_key));
		$field_key = preg_replace('/[^a-z0-9_]/', '_', $field_key);

		if ($field_key === '') {
			return '';
		}

		$field_table = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_option_custom_field") . "'");
		$value_table = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "cyberpunks_product_custom_field_value") . "'");

		if (!$field_table->num_rows || !$value_table->num_rows) {
			return '';
		}

		$query = $this->db->query("SELECT v.value FROM `" . DB_PREFIX . "cyberpunks_product_custom_field_value` v LEFT JOIN `" . DB_PREFIX . "cyberpunks_option_custom_field` f ON (v.field_id = f.field_id) WHERE v.product_id = '" . (int)$product_id . "' AND f.field_key = '" . $this->db->escape($field_key) . "' AND f.scope = 'product' AND f.status = '1' LIMIT 1");

		if ($query->num_rows) {
			return $query->row['value'];
		}

		return '';
	}
}

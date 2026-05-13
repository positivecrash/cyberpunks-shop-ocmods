<?php
class ModelExtensionModuleCyberpunksShopInformationAdditionalFields extends Model {
	public function install() {
		$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "information` LIKE 'noindex'");

		if (!$query->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "information` ADD `noindex` tinyint(1) NOT NULL DEFAULT '0' AFTER `status`");
		}

		$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "information` LIKE 'og_image'");

		if (!$query->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "information` ADD `og_image` varchar(512) NOT NULL DEFAULT '' AFTER `noindex`");
		}

		$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "information` LIKE 'show_shipping_rates'");

		if (!$query->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "information` ADD `show_shipping_rates` tinyint(1) NOT NULL DEFAULT '0' AFTER `og_image`");
		}

		$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "information` LIKE 'show_in_footer_info_links'");

		if (!$query->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "information` ADD `show_in_footer_info_links` tinyint(1) NOT NULL DEFAULT '0' AFTER `show_shipping_rates`");
		}
	}
}

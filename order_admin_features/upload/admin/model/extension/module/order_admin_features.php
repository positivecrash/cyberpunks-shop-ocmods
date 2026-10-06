<?php
class ModelExtensionModuleOrderAdminFeatures extends Model {
	const TABLE = 'order_shipment_photo';
	const MAX_PHOTOS_PER_ORDER = 30;

	public function ensureSchema() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::TABLE . "` (
			`photo_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			`order_id` INT(11) UNSIGNED NOT NULL,
			`filename` VARCHAR(64) NOT NULL,
			`thumb` VARCHAR(64) NOT NULL,
			`original_name` VARCHAR(255) NOT NULL DEFAULT '',
			`filesize` INT(11) UNSIGNED NOT NULL DEFAULT 0,
			`width` INT(11) UNSIGNED NOT NULL DEFAULT 0,
			`height` INT(11) UNSIGNED NOT NULL DEFAULT 0,
			`user_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
			`date_added` DATETIME NOT NULL,
			PRIMARY KEY (`photo_id`),
			KEY `order_id` (`order_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
	}

	/**
	 * Grant module access to any user group that can use Sales → Orders.
	 * Called when the order tab renders so the next AJAX upload passes startup permission.
	 */
	public function ensurePermissions() {
		$query = $this->db->query("SELECT user_group_id, permission FROM `" . DB_PREFIX . "user_group`");
		$route = 'extension/module/order_admin_features';

		foreach ($query->rows as $row) {
			$permission = json_decode($row['permission'], true);

			if (!is_array($permission)) {
				continue;
			}

			$changed = false;

			foreach (array('access', 'modify') as $key) {
				if (!isset($permission[$key]) || !is_array($permission[$key])) {
					$permission[$key] = array();
				}

				$has_orders = in_array('sale/order', $permission[$key], true);
				$has_route = in_array($route, $permission[$key], true);

				if ($has_orders && !$has_route) {
					$permission[$key][] = $route;
					$changed = true;
				}
			}

			if ($changed) {
				$this->db->query("UPDATE `" . DB_PREFIX . "user_group` SET permission = '" . $this->db->escape(json_encode($permission)) . "' WHERE user_group_id = '" . (int)$row['user_group_id'] . "'");
			}
		}
	}

	public function getStorageRoot() {
		// Use DIR_UPLOAD (storage/upload/) — usually already writable on production.
		// Creating a new top-level folder under DIR_STORAGE often fails (permissions / open_basedir).
		return rtrim(DIR_UPLOAD, '/\\') . DIRECTORY_SEPARATOR . 'order_shipment' . DIRECTORY_SEPARATOR;
	}

	public function getOrderDir($order_id) {
		return $this->getStorageRoot() . (int)$order_id . DIRECTORY_SEPARATOR;
	}

	public function getPhotos($order_id) {
		$this->ensureSchema();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . self::TABLE . "` WHERE `order_id` = '" . (int)$order_id . "' ORDER BY `photo_id` ASC");

		return $query->rows;
	}

	public function getPhoto($photo_id) {
		$this->ensureSchema();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . self::TABLE . "` WHERE `photo_id` = '" . (int)$photo_id . "' LIMIT 1");

		return $query->num_rows ? $query->row : null;
	}

	public function countPhotos($order_id) {
		$this->ensureSchema();

		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . self::TABLE . "` WHERE `order_id` = '" . (int)$order_id . "'");

		return (int)$query->row['total'];
	}

	public function addPhoto($order_id, $filename, $thumb, $original_name, $filesize, $width, $height, $user_id) {
		$this->ensureSchema();

		$this->db->query("INSERT INTO `" . DB_PREFIX . self::TABLE . "` SET
			`order_id` = '" . (int)$order_id . "',
			`filename` = '" . $this->db->escape($filename) . "',
			`thumb` = '" . $this->db->escape($thumb) . "',
			`original_name` = '" . $this->db->escape($original_name) . "',
			`filesize` = '" . (int)$filesize . "',
			`width` = '" . (int)$width . "',
			`height` = '" . (int)$height . "',
			`user_id` = '" . (int)$user_id . "',
			`date_added` = NOW()");

		return (int)$this->db->getLastId();
	}

	public function deletePhoto($photo_id) {
		$photo = $this->getPhoto($photo_id);

		if (!$photo) {
			return false;
		}

		$dir = $this->getOrderDir($photo['order_id']);

		foreach (array($photo['filename'], $photo['thumb']) as $name) {
			$path = $dir . $name;
			if ($name !== '' && is_file($path)) {
				@unlink($path);
			}
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . self::TABLE . "` WHERE `photo_id` = '" . (int)$photo_id . "'");

		$left = glob($dir . '*');
		if (is_dir($dir) && ($left === false || count($left) === 0)) {
			@rmdir($dir);
		}

		return true;
	}

	public function absolutePath($order_id, $filename) {
		$filename = basename((string)$filename);

		if ($filename === '' || preg_match('/[^a-zA-Z0-9._-]/', $filename)) {
			return '';
		}

		return $this->getOrderDir($order_id) . $filename;
	}
}

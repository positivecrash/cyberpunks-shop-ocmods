<?php
class CyberpunksShopVariantImagesStorage {
	const SETTING_CODE = 'module_cyberpunks_variant_images';
	const LEGACY_MAPPINGS_KEY = 'module_cyberpunks_variant_images_mappings';
	const MEDIA_PREFIX = 'catalog/view/theme/cybershops/media/';

	public static function mappingsKeyForProduct($product_id) {
		return self::LEGACY_MAPPINGS_KEY . '_' . (int)$product_id;
	}

	public static function loadAll($registry) {
		$registry->get('load')->model('setting/setting');
		$settings = $registry->get('model_setting_setting')->getSetting(self::SETTING_CODE);
		$merged = array();

		foreach ($settings as $key => $value) {
			if ($key === self::LEGACY_MAPPINGS_KEY) {
				$rows = self::decodeRows($value);
				if ($rows) {
					$merged = array_merge($merged, $rows);
				}
				continue;
			}

			if (preg_match('/^' . preg_quote(self::LEGACY_MAPPINGS_KEY, '/') . '_(\d+)$/', (string)$key, $m)) {
				$rows = self::decodeRows($value);
				if ($rows) {
					$merged = array_merge($merged, $rows);
				}
			}
		}

		if (!$merged) {
			$config = $registry->get('config');
			$from_config = $config->get(self::LEGACY_MAPPINGS_KEY);
			$rows = self::decodeRows($from_config);
			if ($rows) {
				$merged = $rows;
			}
		}

		return $merged;
	}

	/** Merged rows with expanded image paths for catalog config consumers (cart, checkout review). */
	public static function loadAllForConfig($registry) {
		$rows = self::loadAll($registry);
		$result = array();

		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$product_id = isset($row['p']) ? (int)$row['p'] : (isset($row['product_id']) ? (int)$row['product_id'] : 0);
			$image = isset($row['i']) ? trim((string)$row['i']) : (isset($row['image']) ? trim((string)$row['image']) : '');
			$image = self::expandImagePathFromStorage($image, $product_id);

			if ($image !== '') {
				$row['i'] = $image;
			}

			$result[] = $row;
		}

		return $result;
	}

	public static function hydrateConfig($registry) {
		$rows = self::loadAllForConfig($registry);

		if ($rows) {
			$registry->get('config')->set(self::LEGACY_MAPPINGS_KEY, $rows);
		}
	}

	public static function saveGrouped($registry, array $mappings_by_product, $status = null) {
		$registry->get('load')->model('setting/setting');
		$model = $registry->get('model_setting_setting');
		$config = $registry->get('config');

		if ($status === null) {
			$status = (int)$config->get('module_cyberpunks_variant_images_status');
		}

		$save_data = array(
			'module_cyberpunks_variant_images_status' => (int)$status
		);

		foreach ($mappings_by_product as $product_id => $rows) {
			$product_id = (int)$product_id;
			if ($product_id <= 0) {
				continue;
			}
			$save_data[self::mappingsKeyForProduct($product_id)] = is_array($rows) ? $rows : array();
		}

		$model->editSetting(self::SETTING_CODE, $save_data);
	}

	public static function groupCompactRowsByProduct(array $compact_rows) {
		$grouped = array();
		foreach ($compact_rows as $row) {
			$product_id = isset($row['p']) ? (int)$row['p'] : (isset($row['product_id']) ? (int)$row['product_id'] : 0);
			if ($product_id <= 0) {
				continue;
			}
			if (!isset($grouped[$product_id])) {
				$grouped[$product_id] = array();
			}
			$grouped[$product_id][] = $row;
		}
		return $grouped;
	}

	public static function compactImagePathForStorage($image) {
		$image = trim((string)$image);
		if ($image === '') {
			return '';
		}

		$prefix = self::MEDIA_PREFIX;
		if (stripos($image, $prefix) === 0) {
			return ltrim(substr($image, strlen($prefix)), '/');
		}

		$image = preg_replace('#^/?catalog/view/theme/cybershops/media/#i', '', $image);
		$image = preg_replace('#^altruist-bundle/product-previews/#i', '', $image);

		return ltrim($image, '/');
	}

	public static function expandImagePathFromStorage($image, $product_id = 0) {
		$image = trim((string)$image);
		if ($image === '') {
			return '';
		}

		if (stripos($image, 'catalog/') === 0) {
			return $image;
		}

		if (strpos($image, '/') !== false) {
			return self::MEDIA_PREFIX . ltrim($image, '/');
		}

		return self::MEDIA_PREFIX . 'altruist-bundle/product-previews/' . $image;
	}

	private static function decodeRows($raw) {
		if (is_array($raw)) {
			return $raw;
		}

		if (!is_string($raw) || $raw === '') {
			return array();
		}

		$json = json_decode($raw, true);
		if (is_array($json)) {
			return $json;
		}

		$php = @unserialize($raw);
		return is_array($php) ? $php : array();
	}
}

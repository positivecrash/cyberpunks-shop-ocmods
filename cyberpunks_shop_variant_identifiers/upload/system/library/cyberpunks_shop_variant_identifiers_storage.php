<?php
class CyberpunksShopVariantIdentifiersStorage {
	const SETTING_CODE = 'module_cyberpunks_variant_identifiers';
	const LEGACY_MAPPINGS_KEY = 'module_cyberpunks_variant_identifiers_mappings';

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

			if (preg_match('/^' . preg_quote(self::LEGACY_MAPPINGS_KEY, '/') . '_\d+(_c\d+)?$/', (string)$key)) {
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

	public static function loadAllForConfig($registry) {
		return self::loadAll($registry);
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
			$status = (int)$config->get('module_cyberpunks_variant_identifiers_status');
		}

		$save_data = array(
			'module_cyberpunks_variant_identifiers_status' => (int)$status
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

	/**
	 * Most-specific match for cart/selected options.
	 * Compact row: p, s, k (sku), g (gtin), t (status).
	 * Supports named signatures (n:opt=val|...) and numeric id signatures.
	 *
	 * @return array{sku:string,gtin:string}
	 */
	public static function resolveCartIdentifiers(array $variant_mappings, $product_id, array $options) {
		$selected_named = self::selectedNamedOptions($options);
		$signature_ids = array();

		foreach ($options as $option_item) {
			if (!empty($option_item['product_option_value_id'])) {
				$signature_ids[] = (int)$option_item['product_option_value_id'];
			}
		}

		$signature_ids = array_values(array_unique($signature_ids));
		sort($signature_ids, SORT_NUMERIC);
		$current_ids_lookup = array_flip($signature_ids);

		$empty = array('sku' => '', 'gtin' => '');

		if (!$selected_named && !$signature_ids) {
			return $empty;
		}

		$matched = $empty;
		$matched_map_size = -1;
		$product_id = (int)$product_id;

		foreach ($variant_mappings as $variant_mapping) {
			if (!is_array($variant_mapping)) {
				continue;
			}

			$map_status = isset($variant_mapping['status']) ? $variant_mapping['status'] : (isset($variant_mapping['t']) ? $variant_mapping['t'] : 1);
			if (empty($map_status)) {
				continue;
			}

			$map_product_id = isset($variant_mapping['product_id']) ? (int)$variant_mapping['product_id'] : (isset($variant_mapping['p']) ? (int)$variant_mapping['p'] : 0);
			$map_signature = isset($variant_mapping['option_value_signature']) ? trim((string)$variant_mapping['option_value_signature']) : (isset($variant_mapping['s']) ? trim((string)$variant_mapping['s']) : '');
			$map_sku = isset($variant_mapping['sku']) ? trim((string)$variant_mapping['sku']) : (isset($variant_mapping['k']) ? trim((string)$variant_mapping['k']) : '');
			$map_gtin = isset($variant_mapping['gtin']) ? trim((string)$variant_mapping['gtin']) : (isset($variant_mapping['g']) ? trim((string)$variant_mapping['g']) : '');

			if ($map_product_id !== $product_id || $map_signature === '' || ($map_sku === '' && $map_gtin === '')) {
				continue;
			}

			$map_pairs = self::pairsFromMapping($variant_mapping);
			$match_size = 0;
			$is_match = false;

			if ($map_pairs) {
				if (!$selected_named) {
					continue;
				}
				$is_match = true;
				foreach ($map_pairs as $opt_key => $opt_val) {
					if (!isset($selected_named[$opt_key]) || $selected_named[$opt_key] !== $opt_val) {
						$is_match = false;
						break;
					}
				}
				$match_size = count($map_pairs);
			} else {
				$map_ids = array_filter(array_map('intval', explode('-', $map_signature)));
				if (!$map_ids || !$signature_ids) {
					continue;
				}
				$map_ids = array_values(array_unique($map_ids));
				$is_match = true;
				foreach ($map_ids as $map_id) {
					if (!isset($current_ids_lookup[$map_id])) {
						$is_match = false;
						break;
					}
				}
				$match_size = count($map_ids);
			}

			if ($is_match && $match_size > $matched_map_size) {
				$matched_map_size = $match_size;
				$matched = array(
					'sku' => $map_sku,
					'gtin' => $map_gtin
				);
			}
		}

		return $matched;
	}

	private static function pairsFromMapping(array $mapping) {
		$pairs = array();

		if (!empty($mapping['o']) && is_array($mapping['o'])) {
			foreach ($mapping['o'] as $key => $value) {
				$opt_key = self::normalizeOptionKey($key);
				$opt_val = self::normalizeValueSlug($value);
				if ($opt_key !== '' && $opt_val !== '') {
					$pairs[$opt_key] = $opt_val;
				}
			}
			if ($pairs) {
				return $pairs;
			}
		}

		$signature = isset($mapping['s']) ? trim((string)$mapping['s']) : (isset($mapping['option_value_signature']) ? trim((string)$mapping['option_value_signature']) : '');
		if ($signature === '' || strpos($signature, 'n:') !== 0) {
			return array();
		}

		$signature = substr($signature, 2);
		foreach (explode('|', $signature) as $part) {
			$part = trim($part);
			if ($part === '' || strpos($part, '=') === false) {
				continue;
			}
			list($key, $value) = explode('=', $part, 2);
			$opt_key = self::normalizeOptionKey($key);
			$opt_val = self::normalizeValueSlug($value);
			if ($opt_key !== '' && $opt_val !== '') {
				$pairs[$opt_key] = $opt_val;
			}
		}

		return $pairs;
	}

	private static function selectedNamedOptions(array $options) {
		$selected = array();

		foreach ($options as $option_item) {
			if (!is_array($option_item)) {
				continue;
			}
			$name = isset($option_item['name']) ? (string)$option_item['name'] : '';
			$value = isset($option_item['value']) ? (string)$option_item['value'] : '';
			if ($name === '' || $value === '') {
				continue;
			}
			$opt_key = self::optionKeyFromCartName($name);
			$opt_val = self::normalizeValueSlug($value);
			if ($opt_key !== '' && $opt_val !== '') {
				$selected[$opt_key] = $opt_val;
			}
		}

		return $selected;
	}

	private static function optionKeyFromCartName($name) {
		$compact = self::normalizeValueSlug($name);

		if (strpos($compact, 'emotion') !== false) {
			return 'urban-emotion';
		}
		if (strpos($compact, 'hood') !== false) {
			return 'urban-hood-color';
		}
		if (strpos($compact, 'insight') !== false && strpos($compact, 'color') !== false) {
			return 'insight-color';
		}
		if (strpos($compact, 'wall') !== false || strpos($compact, 'mount') !== false) {
			return 'urban-wallmount';
		}
		if (strpos($compact, 'color') !== false) {
			return 'urban-color';
		}

		return self::normalizeOptionKey($name);
	}

	private static function normalizeOptionKey($key) {
		$key = strtolower(trim((string)$key));
		$key = preg_replace('/[\s_]+/', '-', $key);
		$key = preg_replace('/[^a-z0-9\-]+/', '', $key);
		$key = preg_replace('/-+/', '-', $key);
		$key = trim($key, '-');

		$compact = str_replace('-', '', $key);
		$aliases = array(
			'urbanemotion' => 'urban-emotion',
			'urbancolor' => 'urban-color',
			'insightcolor' => 'insight-color',
			'urbanhoodcolor' => 'urban-hood-color',
			'urbanwallmount' => 'urban-wallmount'
		);

		if (isset($aliases[$compact])) {
			return $aliases[$compact];
		}

		return $key;
	}

	private static function normalizeValueSlug($value) {
		$value = strtolower(trim((string)$value));
		if (function_exists('mb_strtolower')) {
			$value = mb_strtolower(trim((string)$value), 'UTF-8');
		}
		return preg_replace('/[^a-z0-9]+/', '', $value);
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

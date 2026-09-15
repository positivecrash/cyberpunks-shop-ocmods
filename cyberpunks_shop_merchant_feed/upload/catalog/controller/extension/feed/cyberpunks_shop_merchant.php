<?php
class ControllerExtensionFeedCyberpunksShopMerchant extends Controller {
	public function index() {
		if (!$this->config->get('feed_cyberpunks_shop_merchant_status')) {
			return;
		}

		if (!class_exists('CyberpunksShopVariantIdentifiersStorage')) {
			$file = DIR_SYSTEM . 'library/cyberpunks_shop_variant_identifiers_storage.php';
			if (!is_file($file)) {
				return;
			}
			require_once($file);
		}

		CyberpunksShopVariantIdentifiersStorage::hydrateConfig($this->registry);

		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('tool/image');

		$currency_code = (string)$this->config->get('feed_cyberpunks_shop_merchant_currency');
		if ($currency_code === '') {
			$currency_code = (string)$this->config->get('config_currency');
		}
		$currency_value = $this->currency->getValue($currency_code);
		if (!$currency_value) {
			$currency_value = 1;
		}

		$google_category = trim((string)$this->config->get('feed_cyberpunks_shop_merchant_google_category'));
		$image_rows = $this->loadVariantImageRows();

		$mappings = CyberpunksShopVariantIdentifiersStorage::loadAll($this->registry);
		$product_cache = array();
		$option_lookup_cache = array();
		$seen_ids = array();
		$items_xml = '';

		foreach ($mappings as $mapping) {
			if (!is_array($mapping)) {
				continue;
			}

			$status = isset($mapping['t']) ? $mapping['t'] : (isset($mapping['status']) ? $mapping['status'] : 1);
			if (empty($status)) {
				continue;
			}

			$product_id = isset($mapping['p']) ? (int)$mapping['p'] : (isset($mapping['product_id']) ? (int)$mapping['product_id'] : 0);
			$signature = isset($mapping['s']) ? trim((string)$mapping['s']) : (isset($mapping['option_value_signature']) ? trim((string)$mapping['option_value_signature']) : '');
			$sku = isset($mapping['k']) ? trim((string)$mapping['k']) : (isset($mapping['sku']) ? trim((string)$mapping['sku']) : '');
			$gtin = isset($mapping['g']) ? trim((string)$mapping['g']) : (isset($mapping['gtin']) ? trim((string)$mapping['gtin']) : '');

			if ($product_id <= 0 || $sku === '') {
				continue;
			}

			$id_key = strtolower($sku);
			if (isset($seen_ids[$id_key])) {
				continue;
			}
			$seen_ids[$id_key] = true;

			if (!isset($product_cache[$product_id])) {
				$product_cache[$product_id] = $this->model_catalog_product->getProduct($product_id);
			}

			$product = $product_cache[$product_id];
			if (!$product || !(int)$product['status']) {
				continue;
			}

			// Skip orphan numeric signatures (option value removed from the product).
			if (!$this->signatureStillValid($product_id, $signature, $option_lookup_cache)) {
				continue;
			}

			$title = html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8');
			$pairs = $this->namedPairsFromMapping($product_id, $signature, $option_lookup_cache);
			$variant_attrs = $this->variantAttributesFromPairs($pairs);

			if ($variant_attrs['color'] !== '') {
				$title .= ', ' . $variant_attrs['color'];
			} elseif ($variant_attrs['pattern'] !== '') {
				$title .= ', ' . $variant_attrs['pattern'];
			} else {
				$option_label = $this->titleSuffixFromSignature($signature);
				if ($option_label !== '') {
					$title .= ' - ' . $option_label;
				}
			}

			$description = trim(strip_tags(html_entity_decode($product['description'], ENT_QUOTES, 'UTF-8')));
			$link = $this->url->link('product/product', 'product_id=' . $product_id);
			$link_sep = (strpos($link, '?') !== false) ? '&' : '?';
			$link .= $link_sep . 'variant=' . rawurlencode($sku);
			$brand = html_entity_decode(isset($product['manufacturer']) ? $product['manufacturer'] : '', ENT_QUOTES, 'UTF-8');

			$image_link = $this->resolveImageLink($product_id, $signature, $product, $image_rows, $option_lookup_cache);

			$base_price = (!is_null($product['special']) && (float)$product['special'] >= 0)
				? (float)$product['special']
				: (float)$product['price'];
			$price = $this->currency->format(
				$this->tax->calculate($base_price, $product['tax_class_id'], $this->config->get('config_tax')),
				$currency_code,
				$currency_value,
				false
			);

			$availability = $this->resolveAvailability($product_id, $signature, $product, $option_lookup_cache);

			$items_xml .= "<item>\n";
			$items_xml .= '  <g:id>' . $this->xmlText($sku) . "</g:id>\n";
			$items_xml .= '  <g:item_group_id>' . $this->xmlText((string)$product_id) . "</g:item_group_id>\n";
			$items_xml .= '  <title><![CDATA[' . $title . "]]></title>\n";
			$items_xml .= '  <description><![CDATA[' . $description . "]]></description>\n";
			$items_xml .= '  <link>' . $this->xmlText($link) . "</link>\n";
			if ($image_link !== '') {
				$items_xml .= '  <g:image_link>' . $this->xmlText($image_link) . "</g:image_link>\n";
			}
			$items_xml .= '  <g:condition>new</g:condition>\n';
			$items_xml .= '  <g:availability>' . $availability . "</g:availability>\n";
			$items_xml .= '  <g:price>' . $this->xmlText($price . ' ' . $currency_code) . "</g:price>\n";

			if ($variant_attrs['color'] !== '') {
				$items_xml .= '  <g:color><![CDATA[' . $variant_attrs['color'] . "]]></g:color>\n";
			}
			if ($variant_attrs['pattern'] !== '') {
				$items_xml .= '  <g:pattern><![CDATA[' . $variant_attrs['pattern'] . "]]></g:pattern>\n";
			}

			if ($brand !== '') {
				$items_xml .= '  <g:brand><![CDATA[' . $brand . "]]></g:brand>\n";
			}

			if ($gtin !== '') {
				$items_xml .= '  <g:gtin>' . $this->xmlText($gtin) . "</g:gtin>\n";
			} elseif ($brand !== '') {
				$items_xml .= '  <g:mpn><![CDATA[' . $sku . "]]></g:mpn>\n";
			} else {
				$items_xml .= "  <g:identifier_exists>false</g:identifier_exists>\n";
			}

			if ($google_category !== '') {
				$items_xml .= '  <g:google_product_category>' . $this->xmlText($google_category) . "</g:google_product_category>\n";
			}

			$product_type = $this->firstProductType($product_id);
			if ($product_type !== '') {
				$items_xml .= '  <g:product_type><![CDATA[' . $product_type . "]]></g:product_type>\n";
			}

			$items_xml .= "</item>\n";
		}

		$output  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$output .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
		$output .= "<channel>\n";
		$output .= '  <title><![CDATA[' . $this->config->get('config_name') . "]]></title>\n";
		$output .= '  <link>' . $this->xmlText($this->config->get('config_url')) . "</link>\n";
		$output .= '  <description><![CDATA[Cyberpunks Merchant feed]]></description>\n';
		$output .= $items_xml;
		$output .= "</channel>\n";
		$output .= "</rss>\n";

		$this->response->addHeader('Content-Type: application/xml; charset=utf-8');
		$this->response->setOutput($output);
	}

	private function loadVariantImageRows() {
		if (!class_exists('CyberpunksShopVariantImagesStorage')) {
			$file = DIR_SYSTEM . 'library/cyberpunks_shop_variant_images_storage.php';
			if (!is_file($file)) {
				return array();
			}
			require_once($file);
		}

		CyberpunksShopVariantImagesStorage::hydrateConfig($this->registry);

		return CyberpunksShopVariantImagesStorage::loadAllForConfig($this->registry);
	}

	/**
	 * Named signatures (n:…) stay valid without live option rows.
	 * Numeric signatures require every product_option_value_id to still exist on the product.
	 */
	private function signatureStillValid($product_id, $signature, array &$option_lookup_cache) {
		$signature = trim((string)$signature);

		if ($signature === '') {
			return true;
		}

		if (strpos($signature, 'n:') === 0) {
			return true;
		}

		$ids = array_values(array_unique(array_filter(array_map('intval', explode('-', $signature)))));

		if (!$ids) {
			return false;
		}

		$lookup = $this->productOptionValueLookup($product_id, $option_lookup_cache);

		foreach ($ids as $id) {
			if (!isset($lookup[$id])) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Product qty + palette "In stock" (Option Fields color palettes).
	 * Emotion/pattern options without a palette link stay treated as in stock.
	 */
	private function resolveAvailability($product_id, $signature, array $product, array &$option_lookup_cache) {
		if ((int)$product['quantity'] <= 0) {
			return 'out of stock';
		}

		if (!$this->ensurePaletteStockLibrary()) {
			return 'in stock';
		}

		$options = $this->optionsFromSignature($product_id, $signature, $option_lookup_cache);

		foreach ($options as $option) {
			$pov_id = isset($option['product_option_value_id']) ? (int)$option['product_option_value_id'] : 0;

			if ($pov_id <= 0) {
				$pov_id = $this->findProductOptionValueId(
					$product_id,
					isset($option['name']) ? $option['name'] : '',
					isset($option['value']) ? $option['value'] : '',
					$option_lookup_cache
				);
			}

			if ($pov_id <= 0) {
				continue;
			}

			if (!CyberpunksPaletteStock::isProductOptionValueInStock($this->db, $pov_id)) {
				return 'out of stock';
			}
		}

		return 'in stock';
	}

	private function ensurePaletteStockLibrary() {
		if (class_exists('CyberpunksPaletteStock')) {
			return true;
		}

		$file = DIR_SYSTEM . 'library/cyberpunks_palette_stock.php';

		if (!is_file($file)) {
			return false;
		}

		require_once($file);

		return class_exists('CyberpunksPaletteStock');
	}

	/**
	 * Resolve product_option_value_id from option key/name + value slug (for n: signatures).
	 */
	private function findProductOptionValueId($product_id, $option_name, $value_slug, array &$option_lookup_cache) {
		$want_key = $this->optionKeyFromName($option_name);
		$want_slug = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string)$value_slug));

		if ($want_slug === '') {
			return 0;
		}

		$lookup = $this->productOptionValueLookup($product_id, $option_lookup_cache);

		foreach ($lookup as $pov_id => $row) {
			$row_key = $this->optionKeyFromName(isset($row['name']) ? $row['name'] : '');
			$row_slug = strtolower(preg_replace('/[^a-z0-9]+/i', '', isset($row['value']) ? $row['value'] : ''));

			if ($row_slug !== $want_slug) {
				continue;
			}

			if ($want_key !== '' && $row_key !== '' && $want_key !== $row_key) {
				continue;
			}

			return (int)$pov_id;
		}

		return 0;
	}

	/**
	 * Prefer Variant Images for the identifier signature (exact, then named/id subset match).
	 * Falls back to the product main image.
	 */
	private function resolveImageLink($product_id, $signature, array $product, array $image_rows, array &$option_lookup_cache) {
		$image = '';
		$signature = trim((string)$signature);
		$product_id = (int)$product_id;

		if ($signature !== '' && $image_rows) {
			foreach ($image_rows as $row) {
				if (!is_array($row)) {
					continue;
				}
				$status = isset($row['t']) ? $row['t'] : (isset($row['status']) ? $row['status'] : 1);
				if (empty($status)) {
					continue;
				}
				$row_product_id = isset($row['p']) ? (int)$row['p'] : (isset($row['product_id']) ? (int)$row['product_id'] : 0);
				$row_signature = isset($row['s']) ? trim((string)$row['s']) : (isset($row['option_value_signature']) ? trim((string)$row['option_value_signature']) : '');
				$row_image = isset($row['i']) ? trim((string)$row['i']) : (isset($row['image']) ? trim((string)$row['image']) : '');
				if ($row_product_id === $product_id && $row_signature === $signature && $row_image !== '') {
					$image = $row_image;
					break;
				}
			}

			if ($image === '' && class_exists('CyberpunksShopVariantImagesStorage')) {
				$options = $this->optionsFromSignature($product_id, $signature, $option_lookup_cache);
				if ($options) {
					$image = CyberpunksShopVariantImagesStorage::resolveCartImage($image_rows, $product_id, $options);
				}
			}
		}

		if ($image === '' && !empty($product['image'])) {
			$image = (string)$product['image'];
		}

		return $this->absoluteImageUrl($image);
	}

	private function optionsFromSignature($product_id, $signature, array &$option_lookup_cache) {
		$signature = trim((string)$signature);
		if ($signature === '') {
			return array();
		}

		if (strpos($signature, 'n:') === 0) {
			$options = array();
			foreach (explode('|', substr($signature, 2)) as $part) {
				$part = trim($part);
				if ($part === '' || strpos($part, '=') === false) {
					continue;
				}
				list($key, $value) = explode('=', $part, 2);
				$key = trim($key);
				$value = trim($value);
				if ($key === '' || $value === '') {
					continue;
				}
				$options[] = array(
					'name' => $key,
					'value' => $value
				);
			}
			return $options;
		}

		$ids = array_values(array_unique(array_filter(array_map('intval', explode('-', $signature)))));
		if (!$ids) {
			return array();
		}

		$lookup = $this->productOptionValueLookup($product_id, $option_lookup_cache);
		$options = array();

		foreach ($ids as $id) {
			if (!isset($lookup[$id])) {
				continue;
			}
			$options[] = array(
				'product_option_value_id' => $id,
				'name' => $lookup[$id]['name'],
				'value' => $lookup[$id]['value']
			);
		}

		return $options;
	}

	private function productOptionValueLookup($product_id, array &$option_lookup_cache) {
		$product_id = (int)$product_id;
		if (isset($option_lookup_cache[$product_id])) {
			return $option_lookup_cache[$product_id];
		}

		$lookup = array();
		$product_options = $this->model_catalog_product->getProductOptions($product_id);

		foreach ($product_options as $product_option) {
			$option_name = isset($product_option['name']) ? (string)$product_option['name'] : '';
			if ($option_name === '' || empty($product_option['product_option_value']) || !is_array($product_option['product_option_value'])) {
				continue;
			}
			foreach ($product_option['product_option_value'] as $product_option_value) {
				$pov_id = isset($product_option_value['product_option_value_id']) ? (int)$product_option_value['product_option_value_id'] : 0;
				$value_name = isset($product_option_value['name']) ? (string)$product_option_value['name'] : '';
				if ($pov_id <= 0 || $value_name === '') {
					continue;
				}
				$lookup[$pov_id] = array(
					'name' => $option_name,
					'value' => $value_name
				);
			}
		}

		$option_lookup_cache[$product_id] = $lookup;

		return $lookup;
	}

	private function absoluteImageUrl($image) {
		$image = ltrim((string)$image, '/');
		if ($image === '') {
			return '';
		}

		if (strpos($image, 'http://') === 0 || strpos($image, 'https://') === 0) {
			return $image;
		}

		if (strpos($image, 'catalog/view/theme/') === 0) {
			$base = rtrim((string)$this->config->get('config_url'), '/');
			return $base . '/' . $image;
		}

		if (defined('DIR_IMAGE') && is_file(DIR_IMAGE . $image)) {
			return $this->model_tool_image->resize($image, 800, 800);
		}

		$base = rtrim((string)$this->config->get('config_url'), '/');
		return $base . '/' . $image;
	}

	private function titleSuffixFromSignature($signature) {
		$signature = trim((string)$signature);
		if ($signature === '' || strpos($signature, 'n:') !== 0) {
			return '';
		}

		$parts = array();
		foreach (explode('|', substr($signature, 2)) as $part) {
			$part = trim($part);
			if ($part === '' || strpos($part, '=') === false) {
				continue;
			}
			list($key, $value) = explode('=', $part, 2);
			$value = trim($value);
			if ($value !== '') {
				$parts[] = $this->labelFromSlug($value);
			}
		}

		return implode(' / ', $parts);
	}

	/**
	 * Named option pairs from identifier signature.
	 * Supports n:key=value|… and numeric product_option_value_id signatures (local DB).
	 *
	 * @return array<string,string> option_key => value slug
	 */
	private function namedPairsFromMapping($product_id, $signature, array &$option_lookup_cache) {
		$pairs = $this->namedPairsFromSignature($signature);

		if ($pairs) {
			return $pairs;
		}

		$options = $this->optionsFromSignature($product_id, $signature, $option_lookup_cache);

		foreach ($options as $option) {
			$name = isset($option['name']) ? (string)$option['name'] : '';
			$value = isset($option['value']) ? (string)$option['value'] : '';
			$key = $this->optionKeyFromName($name);

			if ($key === '' || $value === '') {
				continue;
			}

			$slug = strtolower(preg_replace('/[^a-z0-9]+/i', '', $value));

			if ($slug !== '') {
				$pairs[$key] = $slug;
			}
		}

		return $pairs;
	}

	/**
	 * Named option pairs from identifier signature (n:key=value|…).
	 *
	 * @return array<string,string> option_key => value slug
	 */
	private function namedPairsFromSignature($signature) {
		$signature = trim((string)$signature);
		$pairs = array();

		if ($signature === '' || strpos($signature, 'n:') !== 0) {
			return $pairs;
		}

		foreach (explode('|', substr($signature, 2)) as $part) {
			$part = trim($part);
			if ($part === '' || strpos($part, '=') === false) {
				continue;
			}
			list($key, $value) = explode('=', $part, 2);
			$key = strtolower(trim($key));
			$value = strtolower(preg_replace('/[^a-z0-9]+/i', '', trim($value)));
			if ($key !== '' && $value !== '') {
				$pairs[$key] = $value;
			}
		}

		return $pairs;
	}

	private function optionKeyFromName($name) {
		$compact = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string)$name));

		if ($compact === '') {
			return '';
		}

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

		$key = strtolower(trim((string)$name));
		$key = preg_replace('/[\s_]+/', '-', $key);
		$key = preg_replace('/[^a-z0-9\-]+/', '', $key);

		return trim((string)$key, '-');
	}

	/**
	 * Google Merchant variant attributes from named option pairs.
	 * Dual: g:color = UrbanColor/InsightColor; g:pattern = emotion/face.
	 *
	 * @return array{color:string,pattern:string}
	 */
	private function variantAttributesFromPairs(array $pairs) {
		$urban_color = isset($pairs['urban-color']) ? $this->labelFromSlug($pairs['urban-color']) : '';
		$insight_color = isset($pairs['insight-color']) ? $this->labelFromSlug($pairs['insight-color']) : '';
		$hood_color = isset($pairs['urban-hood-color']) ? $this->labelFromSlug($pairs['urban-hood-color']) : '';
		$pattern = isset($pairs['urban-emotion']) ? $this->labelFromSlug($pairs['urban-emotion']) : '';

		$color = '';

		if ($urban_color !== '' && $insight_color !== '') {
			$color = $urban_color . '/' . $insight_color;
		} elseif ($urban_color !== '') {
			$color = $urban_color;
		} elseif ($insight_color !== '') {
			$color = $insight_color;
		} elseif ($hood_color !== '') {
			$color = $hood_color;
		}

		return array(
			'color'   => $color,
			'pattern' => $pattern,
		);
	}

	private function labelFromSlug($slug) {
		$slug = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string)$slug));
		if ($slug === '') {
			return '';
		}

		$labels = array(
			'blue'            => 'Blue',
			'brightgreen'     => 'Bright Green',
			'cyan'            => 'Cyan',
			'green'           => 'Green',
			'lilac'           => 'Lilac',
			'magenta'         => 'Magenta',
			'mistletoegreen'  => 'Mistletoe Green',
			'orange'          => 'Orange',
			'red'             => 'Red',
			'white'           => 'White',
			'black'           => 'Black',
			'jadewhite'       => 'Jade White',
			'lilacpurple'     => 'Lilac',
			'random'          => 'Random',
			'smile'           => 'smile',
			'deadly'          => 'deadly',
			'enjoy'           => 'enjoy',
		);

		if (isset($labels[$slug])) {
			return $labels[$slug];
		}

		return ucfirst($slug);
	}

	private function firstProductType($product_id) {
		$categories = $this->model_catalog_product->getCategories((int)$product_id);
		if (!$categories) {
			return '';
		}

		$category_id = (int)$categories[0]['category_id'];
		$path_ids = array();
		$current = $category_id;

		for ($i = 0; $i < 10 && $current > 0; $i++) {
			$info = $this->model_catalog_category->getCategory($current);
			if (!$info) {
				break;
			}
			array_unshift($path_ids, $info['name']);
			$current = (int)$info['parent_id'];
		}

		return implode(' > ', $path_ids);
	}

	private function xmlText($value) {
		return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}
}

<?php
/**
 * Sale price (special / qty discount) + coupon: do not stack.
 * For each cart line pick the better outcome for the customer:
 *   - keep sale price (no coupon on that line), or
 *   - apply coupon alone against the catalog (non-sale) price.
 * Relative to the cart subtotal (already at sale prices), the coupon total line
 * is adjusted so the paid amount matches that choice.
 */
class CyberpunksCouponCombine {
	const SESSION_KEY = 'cyberpunks_coupon_combine';

	/**
	 * @param object $model ModelExtensionTotalCoupon (needs cart, db, session, config, tax, language)
	 * @param array  $total OpenCart totals accumulator
	 * @return bool true if handled
	 */
	public static function applyGetTotal($model, &$total) {
		if (!isset($model->session->data['coupon'])) {
			unset($model->session->data[self::SESSION_KEY]);
			return true;
		}

		$model->load->language('extension/total/coupon', 'coupon');

		$coupon_info = $model->getCoupon($model->session->data['coupon']);

		if (!$coupon_info) {
			unset($model->session->data[self::SESSION_KEY]);
			return true;
		}

		$products = $model->cart->getProducts();
		$eligible = array();

		foreach ($products as $product) {
			if (!$coupon_info['product']) {
				$eligible[] = $product;
				continue;
			}

			if (in_array($product['product_id'], $coupon_info['product'])) {
				$eligible[] = $product;
			}
		}

		if (!$eligible) {
			unset($model->session->data[self::SESSION_KEY]);
			return true;
		}

		$cart_sub_total = 0.0;
		$catalog_sub_total = 0.0;
		$meta = array();

		foreach ($eligible as $product) {
			$cart_line = (float)$product['total'];
			$catalog_line = self::catalogLineTotal($model, $product);
			$cart_sub_total += $cart_line;
			$catalog_sub_total += $catalog_line;
			$meta[] = array(
				'product'      => $product,
				'cart_total'   => $cart_line,
				'catalog_total'=> $catalog_line,
				'on_sale'      => ($catalog_line - $cart_line) > 0.0001,
			);
		}

		if ($cart_sub_total <= 0) {
			unset($model->session->data[self::SESSION_KEY]);
			return true;
		}

		$type = $coupon_info['type'];
		$coupon_value = (float)$coupon_info['discount'];

		if ($type == 'F') {
			$coupon_value = min($coupon_value, $catalog_sub_total > 0 ? $catalog_sub_total : $cart_sub_total);
		}

		$discount_total = 0.0;
		$used_best_of = false;
		$kept_sale = 0;
		$used_coupon_alone = 0;

		foreach ($meta as $row) {
			$product = $row['product'];
			$cart_line = $row['cart_total'];
			$catalog_line = $row['catalog_total'];
			$on_sale = $row['on_sale'];
			$discount = 0.0;

			if ($type == 'F') {
				$share_catalog = ($catalog_sub_total > 0)
					? $coupon_value * ($catalog_line / $catalog_sub_total)
					: 0.0;
				$share_cart = $coupon_value * ($cart_line / $cart_sub_total);

				if ($on_sale) {
					$used_best_of = true;
					$price_sale_only = $cart_line;
					$price_coupon_only = max(0.0, $catalog_line - $share_catalog);

					if ($price_coupon_only + 0.0001 < $price_sale_only) {
						$discount = $cart_line - $price_coupon_only;
						$used_coupon_alone++;
					} else {
						$discount = 0.0;
						$kept_sale++;
					}
				} else {
					$discount = $share_cart;
				}
			} elseif ($type == 'P') {
				$pct = $coupon_value;

				if ($on_sale) {
					$used_best_of = true;
					$price_sale_only = $cart_line;
					$price_coupon_only = $catalog_line * (1.0 - ($pct / 100.0));

					if ($price_coupon_only < 0) {
						$price_coupon_only = 0.0;
					}

					if ($price_coupon_only + 0.0001 < $price_sale_only) {
						$discount = $cart_line - $price_coupon_only;
						$used_coupon_alone++;
					} else {
						$discount = 0.0;
						$kept_sale++;
					}
				} else {
					$discount = $cart_line / 100.0 * $pct;
				}
			}

			if ($discount < 0) {
				$discount = 0.0;
			}

			if ($discount > $cart_line) {
				$discount = $cart_line;
			}

			if ($product['tax_class_id'] && $discount > 0) {
				$tax_rates = $model->tax->getRates($product['total'] - ($product['total'] - $discount), $product['tax_class_id']);

				foreach ($tax_rates as $tax_rate) {
					if ($tax_rate['type'] == 'P') {
						$total['taxes'][$tax_rate['tax_rate_id']] -= $tax_rate['amount'];
					}
				}
			}

			$discount_total += $discount;
		}

		if (!empty($coupon_info['shipping']) && isset($model->session->data['shipping_method'])) {
			if (!empty($model->session->data['shipping_method']['tax_class_id'])) {
				$tax_rates = $model->tax->getRates($model->session->data['shipping_method']['cost'], $model->session->data['shipping_method']['tax_class_id']);

				foreach ($tax_rates as $tax_rate) {
					if ($tax_rate['type'] == 'P') {
						$total['taxes'][$tax_rate['tax_rate_id']] -= $tax_rate['amount'];
					}
				}
			}

			$discount_total += (float)$model->session->data['shipping_method']['cost'];
		}

		if ($discount_total > $total['total']) {
			$discount_total = $total['total'];
		}

		if ($used_best_of) {
			$model->session->data[self::SESSION_KEY] = array(
				'active'             => 1,
				'kept_sale'          => $kept_sale,
				'used_coupon_alone'  => $used_coupon_alone,
			);
		} else {
			unset($model->session->data[self::SESSION_KEY]);
		}

		if ($discount_total > 0) {
			$code = (string)$model->session->data['coupon'];
			$title = sprintf($model->language->get('coupon')->get('text_coupon'), $code);
			$rate_label = self::formatDiscountFromInfo($coupon_info, $model);

			if ($rate_label !== '') {
				$model->session->data['cyberpunks_coupon_discount_label'] = $rate_label;

				if (substr($title, -1) === ')') {
					$title = substr($title, 0, -1) . ', ' . $rate_label . ')';
				} else {
					$title .= ' (' . $rate_label . ')';
				}
			}

			$total['totals'][] = array(
				'code'       => 'coupon',
				'title'      => $title,
				'value'      => -$discount_total,
				'sort_order' => $model->config->get('total_coupon_sort_order')
			);

			$total['total'] -= $discount_total;
		}

		return true;
	}

	/**
	 * Catalog (non-sale) line total: product.price + option prices × qty.
	 */
	public static function catalogLineTotal($model, array $product) {
		$product_id = (int)$product['product_id'];
		$qty = (int)$product['quantity'];

		if ($product_id < 1 || $qty < 1) {
			return (float)$product['total'];
		}

		$query = $model->db->query("SELECT price FROM `" . DB_PREFIX . "product` WHERE product_id = '" . $product_id . "' LIMIT 1");

		if (!$query->num_rows) {
			return (float)$product['total'];
		}

		$unit = (float)$query->row['price'];

		if (!empty($product['option']) && is_array($product['option'])) {
			foreach ($product['option'] as $option) {
				$opt_price = isset($option['price']) ? (float)$option['price'] : 0.0;
				$prefix = isset($option['price_prefix']) ? (string)$option['price_prefix'] : '+';

				if ($prefix === '-') {
					$unit -= $opt_price;
				} else {
					$unit += $opt_price;
				}
			}
		}

		if ($unit < 0) {
			$unit = 0.0;
		}

		return $unit * $qty;
	}

	/**
	 * Configured coupon rate for storefront labels, e.g. "10%" or "€5.00".
	 * Reads the coupon row from DB (does not re-run getCoupon cart validation).
	 *
	 * @param object      $loaderish Controller/model with ->db, ->currency, ->session, ->config
	 * @param object|null $session   Optional session; defaults to $loaderish->session
	 * @return string
	 */
	public static function discountLabel($loaderish, $session = null) {
		// Controllers have __get but no __isset — never use isset()/empty() on $loaderish->db/session.
		if (!$session && is_object($loaderish)) {
			$session = $loaderish->session;
		}

		if (!$session || !is_object($session)) {
			return '';
		}

		if (empty($session->data['coupon'])) {
			unset($session->data['cyberpunks_coupon_discount_label']);
			return '';
		}

		if (!empty($session->data['cyberpunks_coupon_discount_label'])) {
			return (string)$session->data['cyberpunks_coupon_discount_label'];
		}

		if (!is_object($loaderish) || !is_object($loaderish->db)) {
			return '';
		}

		$code = (string)$session->data['coupon'];
		$query = $loaderish->db->query("SELECT `type`, `discount` FROM `" . DB_PREFIX . "coupon` WHERE `code` = '" . $loaderish->db->escape($code) . "' AND `status` = '1' LIMIT 1");

		if (!$query->num_rows) {
			return '';
		}

		$label = self::formatDiscountFromInfo($query->row, $loaderish);

		if ($label !== '') {
			$session->data['cyberpunks_coupon_discount_label'] = $label;
		}

		return $label;
	}

	/**
	 * @param array  $coupon_info Row with type + discount
	 * @param object $registryish Needs currency + config + session for fixed coupons
	 * @return string
	 */
	public static function formatDiscountFromInfo(array $coupon_info, $registryish) {
		$type = isset($coupon_info['type']) ? strtoupper(trim((string)$coupon_info['type'])) : '';

		if ($type === '') {
			return '';
		}

		$discount = isset($coupon_info['discount']) ? (float)$coupon_info['discount'] : 0.0;

		if ($type === 'P') {
			if (abs($discount - round($discount)) < 0.001) {
				return (string)(int)round($discount) . '%';
			}

			return rtrim(rtrim(number_format($discount, 2, '.', ''), '0'), '.') . '%';
		}

		if ($type === 'F' && is_object($registryish) && is_object($registryish->currency)) {
			$session = is_object($registryish->session) ? $registryish->session : null;
			$currency = ($session && !empty($session->data['currency']))
				? $session->data['currency']
				: $registryish->config->get('config_currency');

			return $registryish->currency->format($discount, $currency);
		}

		return '';
	}

	/**
	 * Soft storefront notice code for the theme (cb_lang lives in Twig / CSV), or empty.
	 * Codes: kept_sale | used_coupon | mixed
	 */
	public static function noticeCode($session) {
		if (empty($session->data[self::SESSION_KEY]['active'])) {
			return '';
		}

		$info = $session->data[self::SESSION_KEY];
		$kept_sale = !empty($info['kept_sale']) ? (int)$info['kept_sale'] : 0;
		$used_coupon = !empty($info['used_coupon_alone']) ? (int)$info['used_coupon_alone'] : 0;

		if ($kept_sale > 0 && $used_coupon < 1) {
			return 'kept_sale';
		}

		if ($used_coupon > 0 && $kept_sale < 1) {
			return 'used_coupon';
		}

		return 'mixed';
	}

	/**
	 * @deprecated use noticeCode(); kept for older patches that still call noticeText()
	 */
	public static function noticeText($session) {
		return self::noticeCode($session);
	}
}

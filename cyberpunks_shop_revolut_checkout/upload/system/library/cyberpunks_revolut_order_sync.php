<?php
/**
 * Copy Revolut Pay shipping + customer onto an OpenCart order.
 * Address: Fast-checkout cache file (written by validate_address webhook).
 * Name/email: Merchant API customer object when present.
 */
class CyberpunksRevolutOrderSync {
	public static function applyToOpenCartOrder($registry, $oc_order_id, $revolut_order_id, $revolut_order = null) {
		$oc_order_id = (int)$oc_order_id;
		$revolut_order_id = trim((string)$revolut_order_id);
		$result = array('applied' => false, 'source' => 'none', 'detail' => '');

		if ($oc_order_id <= 0 || $revolut_order_id === '') {
			$result['detail'] = 'missing_ids';
			return $result;
		}

		$db = $registry->get('db');
		$log = $registry->get('log');

		if ($revolut_order === null || !is_array($revolut_order) || empty($revolut_order['id'])) {
			$revolut_order = self::fetchOrder($registry, $revolut_order_id);
		}

		$ship = self::loadShipCache($revolut_order_id, $revolut_order);
		$source = $ship ? 'cache' : 'none';

		if (!$ship) {
			$ship = self::extractShip($revolut_order);
			if ($ship) {
				$source = 'api';
			}
		}

		$customer = (isset($revolut_order['customer']) && is_array($revolut_order['customer'])) ? $revolut_order['customer'] : array();
		$contact = (isset($revolut_order['shipping']['contact']) && is_array($revolut_order['shipping']['contact'])) ? $revolut_order['shipping']['contact'] : array();

		$email = self::clean(isset($customer['email']) ? $customer['email'] : '');
		$name = self::clean(isset($customer['full_name']) ? $customer['full_name'] : '');
		if ($name === '' && !empty($contact['name'])) {
			$name = self::clean($contact['name']);
		}
		$phone = self::clean(isset($customer['phone']) ? $customer['phone'] : '');

		$registry->get('load')->model('checkout/order');
		$existing = $registry->get('model_checkout_order')->getOrder($oc_order_id);
		if (!$existing) {
			$result['detail'] = 'oc_order_missing';
			return $result;
		}

		if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$email = self::clean(isset($existing['email']) ? $existing['email'] : '');
		}

		list($firstname, $lastname) = self::splitName($name);
		$had_revolut_name = ($firstname !== '' || $lastname !== '');
		if ($firstname === '') {
			$firstname = self::clean(isset($existing['firstname']) ? $existing['firstname'] : '');
		}
		if ($lastname === '') {
			$lastname = self::clean(isset($existing['lastname']) ? $existing['lastname'] : '');
		}
		if ($phone === '') {
			$phone = self::clean(isset($existing['telephone']) ? $existing['telephone'] : '');
		}

		// Keep OC draft from the checkout form unless Revolut actually sent better data.
		$set = array(
			"date_modified = NOW()",
		);

		if ($had_revolut_name || $firstname !== '' || $lastname !== '') {
			$set[] = "firstname = '" . $db->escape($firstname) . "'";
			$set[] = "lastname = '" . $db->escape($lastname) . "'";
			$set[] = "payment_firstname = '" . $db->escape($firstname) . "'";
			$set[] = "payment_lastname = '" . $db->escape($lastname) . "'";
			$set[] = "shipping_firstname = '" . $db->escape($firstname) . "'";
			$set[] = "shipping_lastname = '" . $db->escape($lastname) . "'";
		}

		if ($phone !== '') {
			$set[] = "telephone = '" . $db->escape($phone) . "'";
		}

		if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$set[] = "email = '" . $db->escape($email) . "'";
		}

		$street_1 = self::pick($ship, array('street_line_1', 'streetLine1', 'address_1', 'street'));
		$street_2 = self::pick($ship, array('street_line_2', 'streetLine2', 'address_2'));
		$city = self::pick($ship, array('city', 'locality'));
		$postcode = self::pick($ship, array('postcode', 'postalCode', 'zip'));
		$region = self::pick($ship, array('region', 'administrativeArea', 'state'));
		$country_code = self::pick($ship, array('country_code', 'countryCode', 'country'));

		if ($street_1 !== '' && ($city !== '' || $postcode !== '') && $country_code !== '') {
			$country = self::countryByIso($db, $country_code);
			if ($country) {
				$zone = self::resolveZone($db, (int)$country['country_id'], $region);
				$zone_name = $zone['name'] !== '' ? $zone['name'] : $region;
				foreach (array('payment', 'shipping') as $p) {
					$set[] = "{$p}_address_1 = '" . $db->escape($street_1) . "'";
					$set[] = "{$p}_address_2 = '" . $db->escape($street_2) . "'";
					$set[] = "{$p}_city = '" . $db->escape($city) . "'";
					$set[] = "{$p}_postcode = '" . $db->escape($postcode) . "'";
					$set[] = "{$p}_country = '" . $db->escape($country['name']) . "'";
					$set[] = "{$p}_country_id = '" . (int)$country['country_id'] . "'";
					$set[] = "{$p}_zone = '" . $db->escape($zone_name) . "'";
					$set[] = "{$p}_zone_id = '" . (int)$zone['zone_id'] . "'";
					$set[] = "{$p}_address_format = '" . $db->escape($country['address_format']) . "'";
				}
				$result['applied'] = true;
				$result['source'] = $source;
			}
		} elseif ($firstname !== '' || ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))) {
			$result['applied'] = true;
			$result['source'] = 'contact';
		}

		$db->query("UPDATE `" . DB_PREFIX . "order` SET " . implode(', ', $set) . " WHERE order_id = '" . (int)$oc_order_id . "'");
		$result['detail'] = 'street=' . ($street_1 !== '' ? 'yes' : 'no') . ';name=' . ($name !== '' ? 'yes' : 'no');

		if ($log) {
			$log->write('CyberpunksRevolutOrderSync OC#' . $oc_order_id . ' source=' . $result['source'] . ' ' . $result['detail']);
		}

		return $result;
	}

	private static function fetchOrder($registry, $revolut_order_id) {
		$file = DIR_SYSTEM . 'library/vendor/revolut/api_request.php';
		if (!is_file($file)) {
			return array();
		}
		require_once($file);
		$config = $registry->get('config');
		$api = new ApiRequest($config->get('payment_revolut_api_key'), $config->get('payment_revolut_test'));
		$res = $api->get('orders/' . $revolut_order_id, true);
		return (!empty($res['response']) && is_array($res['response'])) ? $res['response'] : array();
	}

	private static function loadShipCache($revolut_order_id, array $revolut_order) {
		$refs = array($revolut_order_id);
		if (!empty($revolut_order['token'])) {
			$refs[] = $revolut_order['token'];
		}
		foreach ($refs as $ref) {
			$safe = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$ref);
			if ($safe === '' || !defined('DIR_CACHE')) {
				continue;
			}
			$path = DIR_CACHE . 'cp_revolut_ship_' . $safe . '.json';
			if (!is_file($path)) {
				continue;
			}
			$decoded = json_decode((string)file_get_contents($path), true);
			if (is_array($decoded) && self::pick($decoded, array('street_line_1', 'streetLine1', 'address_1', 'street')) !== '') {
				return $decoded;
			}
		}
		return null;
	}

	private static function extractShip(array $order) {
		if (isset($order['shipping']['address']) && is_array($order['shipping']['address'])) {
			return $order['shipping']['address'];
		}
		if (isset($order['shipping_address']) && is_array($order['shipping_address'])) {
			return $order['shipping_address'];
		}
		return array();
	}

	private static function pick($arr, $keys) {
		if (!is_array($arr)) {
			return '';
		}
		foreach ($keys as $key) {
			if (!isset($arr[$key])) {
				continue;
			}
			$val = self::clean($arr[$key]);
			if ($val !== '') {
				return $val;
			}
		}
		return '';
	}

	private static function clean($value) {
		$value = trim((string)$value);
		if ($value === '' || strcasecmp($value, 'undefined') === 0 || strcasecmp($value, 'null') === 0) {
			return '';
		}
		if (strcasecmp($value, 'Express checkout') === 0 || strcasecmp($value, 'City') === 0 || $value === '00000') {
			return '';
		}
		if (strcasecmp($value, 'Guest') === 0 || strcasecmp($value, 'Customer') === 0) {
			return '';
		}
		return $value;
	}

	private static function splitName($name) {
		$name = trim((string)$name);
		if ($name === '') {
			return array('', '');
		}
		$parts = preg_split('/\s+/', $name, 2);
		return array($parts[0], isset($parts[1]) ? $parts[1] : '');
	}

	private static function countryByIso($db, $iso) {
		$iso = strtoupper(trim((string)$iso));
		if (strlen($iso) !== 2) {
			return null;
		}
		$q = $db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE iso_code_2 = '" . $db->escape($iso) . "' AND status = '1' LIMIT 1");
		return $q->num_rows ? $q->row : null;
	}

	private static function resolveZone($db, $country_id, $region) {
		$out = array('zone_id' => 0, 'name' => '');
		$region = trim((string)$region);
		if ($region === '') {
			return $out;
		}
		$q = $db->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE country_id = '" . (int)$country_id . "' AND (name = '" . $db->escape($region) . "' OR code = '" . $db->escape($region) . "') AND status = '1' LIMIT 1");
		if ($q->num_rows) {
			$out['zone_id'] = (int)$q->row['zone_id'];
			$out['name'] = $q->row['name'];
		}
		return $out;
	}
}

<?php

class ControllerExtensionAnalyticsMatomo extends Controller {
	public function index() {
		if (!$this->config->get('analytics_matomo_status')) {
			return '';
		}

		$server = trim((string) $this->config->get('analytics_matomo_server'));
		$site_id = (int) $this->config->get('analytics_matomo_site_id');

		if ($server === '' || $site_id < 1) {
			return '';
		}

		$server = rtrim($server, '/');
		$server_js = json_encode($server . '/');

		if ($this->config->get('analytics_matomo_respect_dnt')) {
			if (!empty($_SERVER['HTTP_DNT']) && (string) $_SERVER['HTTP_DNT'] === '1') {
				return '';
			}
		}

		$lines = array(
			'<script>',
			'var _paq = window._paq = window._paq || [];',
		);

		if ($this->config->get('analytics_matomo_disable_cookies')) {
			$lines[] = "_paq.push(['disableCookies']);";
		}

		$lines[] = "_paq.push(['setTrackerUrl', " . $server_js . " + 'matomo.php']);";
		$lines[] = "_paq.push(['setSiteId', " . json_encode((string) $site_id) . ']);';
		$lines[] = "_paq.push(['trackPageView']);";
		$lines[] = "_paq.push(['enableLinkTracking']);";
		$lines[] = '(function() {';
		$lines[] = 'var d=document, g=d.createElement("script"), s=d.getElementsByTagName("script")[0];';
		$lines[] = 'g.async=true; g.src=' . $server_js . ' + "matomo.js"; s.parentNode.insertBefore(g,s);';
		$lines[] = '})();';
		$lines[] = '</script>';

		return implode("\n", $lines);
	}

	public function captureOrder(&$route, &$data) {
		$this->registry->set('matomo_ecommerce_snapshot', null);

		if (!$this->config->get('analytics_matomo_status') || !$this->config->get('analytics_matomo_ecommerce')) {
			return;
		}

		if (empty($this->session->data['order_id'])) {
			return;
		}

		$order_id = (int) $this->session->data['order_id'];

		$this->load->model('checkout/order');

		$order = $this->model_checkout_order->getOrder($order_id);

		if (!$order) {
			return;
		}

		$products = $this->model_checkout_order->getOrderProducts($order_id);
		$totals = $this->model_checkout_order->getOrderTotals($order_id);

		$shipping = 0.0;
		$tax = 0.0;
		$discount = 0.0;

		foreach ($totals as $total) {
			$code = isset($total['code']) ? $total['code'] : '';
			$value = isset($total['value']) ? (float) $total['value'] : 0.0;

			if ($code === 'shipping') {
				$shipping += $value;
			} elseif ($code === 'tax') {
				$tax += $value;
			} elseif (in_array($code, array('coupon', 'voucher', 'reward'), true)) {
				$discount += abs($value);
			}
		}

		$subtotal = 0.0;

		foreach ($products as $product) {
			$subtotal += isset($product['total']) ? (float) $product['total'] : 0.0;
		}

		$sku_field = $this->config->get('analytics_matomo_sku_field');
		if ($sku_field !== 'sku') {
			$sku_field = 'model';
		}

		$items = array();

		foreach ($products as $product) {
			$sku = isset($product['model']) ? (string) $product['model'] : '';

			if ($sku_field === 'sku' && !empty($product['product_id'])) {
				$this->load->model('catalog/product');
				$pinfo = $this->model_catalog_product->getProduct((int) $product['product_id']);
				if ($pinfo && isset($pinfo['sku']) && $pinfo['sku'] !== '') {
					$sku = (string) $pinfo['sku'];
				}
			}

			$qty = isset($product['quantity']) ? (int) $product['quantity'] : 1;
			$line_total = isset($product['total']) ? (float) $product['total'] : 0.0;
			$unit = $qty > 0 ? $line_total / $qty : $line_total;

			$items[] = array(
				'sku'      => $sku !== '' ? $sku : 'item-' . (isset($product['order_product_id']) ? (int) $product['order_product_id'] : 0),
				'name'     => isset($product['name']) ? (string) $product['name'] : '',
				'category' => '',
				'price'    => round($unit, 4),
				'quantity' => $qty,
			);
		}

		$this->registry->set('matomo_ecommerce_snapshot', array(
			'order_id'    => (string) $order_id,
			'grand_total' => isset($order['total']) ? (float) $order['total'] : 0.0,
			'subtotal'    => round($subtotal, 4),
			'tax'         => round($tax, 4),
			'shipping'    => round($shipping, 4),
			'discount'    => round($discount, 4),
			'items'       => $items,
		));
	}

	public function injectOrderTracking(&$route, &$data, &$output) {
		if (!is_string($output) || strpos($output, '</body>') === false) {
			return;
		}

		if (!$this->config->get('analytics_matomo_status') || !$this->config->get('analytics_matomo_ecommerce')) {
			$this->registry->set('matomo_ecommerce_snapshot', null);

			return;
		}

		$snapshot = $this->registry->get('matomo_ecommerce_snapshot');

		$this->registry->set('matomo_ecommerce_snapshot', null);

		if (!is_array($snapshot) || empty($snapshot['items'])) {
			return;
		}

		$lines = array(
			'<script>',
			'var _paq = window._paq = window._paq || [];',
		);

		foreach ($snapshot['items'] as $item) {
			$lines[] = "_paq.push(['addEcommerceItem', " . json_encode($item['sku']) . ', ' . json_encode($item['name']) . ', ' . json_encode($item['category']) . ', ' . json_encode((float) $item['price']) . ', ' . json_encode((int) $item['quantity']) . ']);';
		}

		$lines[] = "_paq.push(['trackEcommerceOrder', " . json_encode($snapshot['order_id']) . ', ' . json_encode((float) $snapshot['grand_total']) . ', ' . json_encode((float) $snapshot['subtotal']) . ', ' . json_encode((float) $snapshot['tax']) . ', ' . json_encode((float) $snapshot['shipping']) . ', ' . json_encode((float) $snapshot['discount']) . ']);';
		$lines[] = '</script>';

		$block = "\n" . implode("\n", $lines) . "\n";
		$output = str_replace('</body>', $block . '</body>', $output);
	}
}

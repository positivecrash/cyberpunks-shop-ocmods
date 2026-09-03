<?php
class ControllerExtensionModuleCyberpunksShopOptionFields extends Controller {
	public function paletteStockCartView(&$route, &$data, &$code) {
		if (empty($data['products']) || !is_array($data['products'])) {
			return;
		}

		if (!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) {
			return;
		}

		if (!is_file(DIR_SYSTEM . 'library/cyberpunks_palette_stock.php')) {
			return;
		}

		require_once(DIR_SYSTEM . 'library/cyberpunks_palette_stock.php');

		$cart_products = $this->cart->getProducts();
		$cart_by_id = array();

		foreach ($cart_products as $cart_product) {
			$cart_by_id[(int)$cart_product['cart_id']] = $cart_product;
		}

		$has_palette_issue = false;

		foreach ($data['products'] as &$product) {
			$cart_id = isset($product['cart_id']) ? (int)$product['cart_id'] : 0;

			if ($cart_id <= 0 || !isset($cart_by_id[$cart_id])) {
				continue;
			}

			$cart_product = $cart_by_id[$cart_id];
			$palette_availability = CyberpunksPaletteStock::getCartProductPaletteAvailability($this->db, $cart_product, (int)$this->config->get('config_language_id'));

			if (!$palette_availability) {
				continue;
			}

			$has_palette_issue = true;
			$product['palette_option_availability'] = $palette_availability;
			$product['stock'] = false;
		}
		unset($product);

		if ($has_palette_issue) {
			$this->load->language('checkout/cart');
			$data['error_warning'] = $this->language->get('error_stock');
		}
	}

	public function paletteStockCheckoutGuard(&$route, &$data) {
		if ($this->cartHasPaletteStock()) {
			return;
		}

		$this->load->language('checkout/cart');
		$this->session->data['error'] = $this->language->get('error_stock');
		$this->response->redirect($this->url->link('checkout/cart'));

		return '';
	}

	public function paletteStockCartAddGuard(&$route, &$data) {
		if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST') {
			return;
		}

		if (!isset($this->request->post['product_id'])) {
			return;
		}

		if (!is_file(DIR_APPLICATION . 'model/extension/module/cyberpunks_shop_option_fields.php')) {
			return;
		}

		$product_id = (int)$this->request->post['product_id'];
		$option = isset($this->request->post['option']) && is_array($this->request->post['option'])
			? array_filter($this->request->post['option'])
			: array();

		$this->load->model('extension/module/cyberpunks_shop_option_fields');
		$error = $this->model_extension_module_cyberpunks_shop_option_fields->validateSelectedOptionsPaletteStock($product_id, $option);

		if ($error === '') {
			return;
		}

		$json = array(
			'error' => array(
				'warning' => $error
			),
			'redirect' => str_replace('&amp;', '&', $this->url->link('product/product', 'product_id=' . $product_id))
		);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));

		return $this->response->getOutput();
	}

	private function cartHasPaletteStock() {
		if (!is_file(DIR_SYSTEM . 'library/cyberpunks_palette_stock.php')) {
			return true;
		}

		require_once(DIR_SYSTEM . 'library/cyberpunks_palette_stock.php');

		return CyberpunksPaletteStock::cartHasPaletteStock($this->db, $this->cart->getProducts());
	}
}

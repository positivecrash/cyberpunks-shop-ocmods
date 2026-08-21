<?php
class ControllerExtensionAdvertiseCyberpunksShopMarketing extends Controller {
	public function captureSuccess(&$route, &$data) {
		$this->registry->set('cyberpunks_marketing_purchase_ecommerce', null);
		$this->registry->set('cyberpunks_marketing_matomo_snapshot', null);

		if (!is_file(DIR_APPLICATION . 'model/extension/advertise/cyberpunks_shop_marketing.php')) {
			return;
		}

		if (empty($this->session->data['order_id'])) {
			return;
		}

		$order_id = (int)$this->session->data['order_id'];

		$this->load->model('extension/advertise/cyberpunks_shop_marketing');

		$ecommerce = $this->model_extension_advertise_cyberpunks_shop_marketing->buildPurchaseEcommerce($order_id);

		if ($ecommerce) {
			$this->registry->set('cyberpunks_marketing_purchase_ecommerce', $ecommerce);
		}

		$snapshot = $this->model_extension_advertise_cyberpunks_shop_marketing->buildMatomoEcommerceSnapshot($order_id);

		if ($snapshot) {
			$this->registry->set('cyberpunks_marketing_matomo_snapshot', $snapshot);
		}
	}

	public function injectSuccess(&$route, &$data, &$output) {
		$html = $this->response->getOutput();

		if (!is_string($html) || $html === '' || stripos($html, '</body>') === false) {
			return;
		}

		if (!is_file(DIR_APPLICATION . 'model/extension/advertise/cyberpunks_shop_marketing.php')) {
			return;
		}

		$this->load->model('extension/advertise/cyberpunks_shop_marketing');

		$blocks = array();

		$ecommerce = $this->registry->get('cyberpunks_marketing_purchase_ecommerce');
		$this->registry->set('cyberpunks_marketing_purchase_ecommerce', null);

		if (is_array($ecommerce) && !empty($ecommerce['items'])) {
			$blocks[] = $this->model_extension_advertise_cyberpunks_shop_marketing->renderDataLayerScript('purchase', $ecommerce);
		}

		$snapshot = $this->registry->get('cyberpunks_marketing_matomo_snapshot');
		$this->registry->set('cyberpunks_marketing_matomo_snapshot', null);

		if (is_array($snapshot) && !empty($snapshot['items'])) {
			$blocks[] = $this->model_extension_advertise_cyberpunks_shop_marketing->renderMatomoOrderScript($snapshot);
		}

		if (!$blocks) {
			return;
		}

		$block = "\n" . implode("\n", $blocks) . "\n";
		$html = $this->injectBeforeBodyClose($html, $block);
		$this->response->setOutput($html);
		$output = $html;
	}

	public function captureViewItem(&$route, &$data) {
		$this->registry->set('cyberpunks_marketing_view_item_ecommerce', null);

		if (!is_file(DIR_APPLICATION . 'model/extension/advertise/cyberpunks_shop_marketing.php')) {
			return;
		}

		$this->load->model('extension/advertise/cyberpunks_shop_marketing');

		if (!$this->model_extension_advertise_cyberpunks_shop_marketing->isViewItemEnabled()) {
			return;
		}

		$product_id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;

		if ($product_id <= 0) {
			return;
		}

		$ecommerce = $this->model_extension_advertise_cyberpunks_shop_marketing->buildViewItemEcommerce($product_id);

		if ($ecommerce) {
			$this->registry->set('cyberpunks_marketing_view_item_ecommerce', $ecommerce);
		}
	}

	public function injectViewItem(&$route, &$data, &$output) {
		$html = $this->response->getOutput();

		if (!is_string($html) || $html === '' || stripos($html, '</body>') === false) {
			return;
		}

		if (!is_file(DIR_APPLICATION . 'model/extension/advertise/cyberpunks_shop_marketing.php')) {
			return;
		}

		$this->load->model('extension/advertise/cyberpunks_shop_marketing');

		$ecommerce = $this->registry->get('cyberpunks_marketing_view_item_ecommerce');
		$this->registry->set('cyberpunks_marketing_view_item_ecommerce', null);

		if (!is_array($ecommerce) || empty($ecommerce['items'])) {
			return;
		}

		$block = "\n" . $this->model_extension_advertise_cyberpunks_shop_marketing->renderDataLayerScript('view_item', $ecommerce) . "\n";
		$html = $this->injectBeforeBodyClose($html, $block);
		$this->response->setOutput($html);
		$output = $html;
	}

	private function injectBeforeBodyClose($html, $block) {
		$pos = stripos($html, '</body>');

		if ($pos === false) {
			return $html;
		}

		return substr($html, 0, $pos) . $block . substr($html, $pos);
	}
}

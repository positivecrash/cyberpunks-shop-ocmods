<?php
class ControllerExtensionModuleCyberpunksShopSupport extends Controller {
	public function cron() {
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->addHeader('Cache-Control: no-store');

		$key = isset($this->request->get['key']) ? (string)$this->request->get['key'] : '';
		$expected = (string)$this->config->get('module_cyberpunks_shop_support_imap_cron_key');

		if ($expected === '' || !hash_equals($expected, $key)) {
			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 403 Forbidden');
			$this->response->setOutput(json_encode(array('ok' => false, 'message' => 'Forbidden')));
			return;
		}

		if (!is_file(DIR_SYSTEM . 'library/cyberpunks_support_imap.php')) {
			$this->response->setOutput(json_encode(array('ok' => false, 'message' => 'IMAP library missing')));
			return;
		}

		require_once(DIR_SYSTEM . 'library/cyberpunks_support_imap.php');
		$imap = new CyberpunksSupportImap($this->registry);
		$result = $imap->poll();

		$this->response->setOutput(json_encode($result));
	}
}

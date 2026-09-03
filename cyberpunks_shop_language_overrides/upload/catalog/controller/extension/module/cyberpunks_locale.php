<?php

class ControllerExtensionModuleCyberpunksLocale extends Controller {
	public function early() {
		$file = DIR_SYSTEM . 'library/cyberpunks_url_locale.php';

		if (!is_file($file)) {
			return;
		}

		require_once($file);

		$this->load->model('localisation/language');
		CyberpunksUrlLocale::consumeRouteLocale($this->request, $this->model_localisation_language->getLanguages());

		if (!empty($this->request->get['language'])) {
			$this->session->data['language'] = $this->request->get['language'];
		}
	}

	public function late() {
		$file = DIR_SYSTEM . 'library/cyberpunks_url_locale.php';

		if (!is_file($file)) {
			return;
		}

		require_once($file);

		$this->url->addRewrite($this);
		CyberpunksUrlLocale::repairIndexPhpLocaleUrl($this->registry);
		CyberpunksUrlLocale::redirectIfMissingPrefix($this->registry);
	}

	public function rewrite($link) {
		$code = isset($this->session->data['language']) ? $this->session->data['language'] : $this->config->get('config_language');

		return CyberpunksUrlLocale::applyPrefix($link, $code);
	}
}

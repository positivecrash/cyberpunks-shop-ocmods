<?php
class ControllerExtensionModuleCyberpunksVisitorCountry extends Controller {
	public function index() {
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->addHeader('Cache-Control: no-store, no-cache, must-revalidate');

		$resolved = $this->resolveIsoCode2();

		$this->response->setOutput(json_encode(array(
			'iso_code_2' => isset($resolved['iso_code_2']) ? $resolved['iso_code_2'] : '',
			'source'     => isset($resolved['source']) ? $resolved['source'] : 'unknown'
		)));
	}

	private function resolveIsoCode2() {
		$session_key = 'cyberpunks_visitor_geo';
		$ip = $this->getClientIp();

		unset($this->session->data['cyberpunks_visitor_iso']);

		if (!empty($this->session->data[$session_key]) && is_array($this->session->data[$session_key])) {
			$cached = $this->session->data[$session_key];
			$cached_ip = isset($cached['ip']) ? (string)$cached['ip'] : '';
			$cached_iso = isset($cached['iso']) ? strtoupper(trim((string)$cached['iso'])) : '';

			// Same IP → reuse country, no geojs call.
			if ($cached_ip === $ip && $this->isValidIso($cached_iso)) {
				return array(
					'iso_code_2' => $cached_iso,
					'source'     => 'session'
				);
			}
		}

		if ($ip === '' || $this->isPrivateIp($ip)) {
			return array('iso_code_2' => '', 'source' => 'local');
		}

		$iso = $this->lookupIsoByIp($ip);

		if ($this->isValidIso($iso)) {
			$this->session->data[$session_key] = array('ip' => $ip, 'iso' => $iso);

			return array('iso_code_2' => $iso, 'source' => 'ip');
		}

		return array('iso_code_2' => '', 'source' => 'unknown');
	}

	private function lookupIsoByIp($ip) {
		$url = 'https://get.geojs.io/v1/ip/country/' . rawurlencode($ip) . '.json';
		$raw = $this->httpGet($url);

		if ($raw === '') {
			return '';
		}

		$data = json_decode($raw, true);

		if (!is_array($data) || empty($data['country'])) {
			return '';
		}

		$iso = strtoupper(trim((string)$data['country']));

		return $this->isValidIso($iso) ? $iso : '';
	}

	private function httpGet($url) {
		if (function_exists('curl_init')) {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
			curl_setopt($ch, CURLOPT_TIMEOUT, 3);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_USERAGENT, 'cyberpunks-shop-visitor-country/1.2.0');
			$body = curl_exec($ch);
			$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($body === false || $code < 200 || $code >= 300) {
				return '';
			}

			return trim((string)$body);
		}

		$context = stream_context_create(array(
			'http' => array(
				'timeout' => 3,
				'header'  => "User-Agent: cyberpunks-shop-visitor-country/1.2.0\r\n"
			)
		));

		$body = @file_get_contents($url, false, $context);

		return $body === false ? '' : trim((string)$body);
	}

	private function getClientIp() {
		$candidates = array();

		if (!empty($this->request->server['HTTP_CF_CONNECTING_IP'])) {
			$candidates[] = $this->request->server['HTTP_CF_CONNECTING_IP'];
		}

		if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
			foreach (explode(',', $this->request->server['HTTP_X_FORWARDED_FOR']) as $part) {
				$candidates[] = $part;
			}
		}

		if (!empty($this->request->server['REMOTE_ADDR'])) {
			$candidates[] = $this->request->server['REMOTE_ADDR'];
		}

		foreach ($candidates as $candidate) {
			$ip = trim((string)$candidate);

			if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
				return $ip;
			}
		}

		return '';
	}

	private function isPrivateIp($ip) {
		return !filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}

	private function isValidIso($iso) {
		return (bool)preg_match('/^[A-Z]{2}$/', $iso) && $iso !== 'XX' && $iso !== 'T1';
	}
}

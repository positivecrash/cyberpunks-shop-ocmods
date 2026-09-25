<?php
/**
 * Contact-form anti-bot guard: one-time-issued form token + minimum fill time.
 * Tokens stay valid for the whole TTL so a rejected submit (cooldown, blocklist)
 * can be retried without reloading the page.
 */
class CyberpunksSupportGuard {
	const SESSION_KEY = 'cyberpunks_support_form_tokens';
	const MAX_TOKENS = 5;
	const TOKEN_TTL = 7200;

	private $registry;
	private $config;
	private $session;

	public function __construct($registry) {
		$this->registry = $registry;
		$this->config = $registry->get('config');
		$this->session = $registry->get('session');
	}

	/**
	 * Store a fresh token in the session and return it for the form markup.
	 */
	public function issueToken() {
		$token = bin2hex(random_bytes(16));
		$now = time();

		$tokens = array();
		if (!empty($this->session->data[self::SESSION_KEY]) && is_array($this->session->data[self::SESSION_KEY])) {
			foreach ($this->session->data[self::SESSION_KEY] as $stored) {
				if (!empty($stored['token']) && !empty($stored['time']) && ($now - (int)$stored['time']) <= self::TOKEN_TTL) {
					$tokens[] = array('token' => (string)$stored['token'], 'time' => (int)$stored['time']);
				}
			}
		}

		$tokens[] = array('token' => $token, 'time' => $now);

		if (count($tokens) > self::MAX_TOKENS) {
			$tokens = array_slice($tokens, -self::MAX_TOKENS);
		}

		$this->session->data[self::SESSION_KEY] = $tokens;

		return $token;
	}

	public function isTokenEnabled() {
		$value = $this->config->get('module_cyberpunks_shop_support_form_token');
		return ($value === null || $value === '') ? true : (bool)(int)$value;
	}

	public function getMinSeconds() {
		$value = $this->config->get('module_cyberpunks_shop_support_form_min_seconds');
		return ($value === null || $value === '') ? 3 : max(0, (int)$value);
	}

	/**
	 * Contact-form message from Support module settings (no hardcoded defaults).
	 * @param string $code success|token|too_fast|blocked|soft_limit|recaptcha
	 */
	public function getFormMessage($code) {
		return trim((string)$this->config->get('module_cyberpunks_shop_support_msg_' . (string)$code));
	}

	public function isRecaptchaEnabled() {
		$site = trim((string)$this->config->get('module_cyberpunks_shop_support_recaptcha_site_key'));
		$secret = trim((string)$this->config->get('module_cyberpunks_shop_support_recaptcha_secret_key'));
		return ($site !== '' && $secret !== '');
	}

	public function getRecaptchaSiteKey() {
		return trim((string)$this->config->get('module_cyberpunks_shop_support_recaptcha_site_key'));
	}

	/**
	 * Verify Google reCAPTCHA v2 Invisible token.
	 * @return bool
	 */
	public function verifyRecaptcha($response, $remote_ip = '') {
		if (!$this->isRecaptchaEnabled()) {
			return true;
		}

		$response = trim((string)$response);
		if ($response === '') {
			return false;
		}

		$secret = trim((string)$this->config->get('module_cyberpunks_shop_support_recaptcha_secret_key'));
		$query = http_build_query(array(
			'secret'   => $secret,
			'response' => $response,
			'remoteip' => (string)$remote_ip
		));

		$result = false;

		if (function_exists('curl_init')) {
			$ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			$body = curl_exec($ch);
			curl_close($ch);
			if ($body !== false) {
				$decoded = json_decode($body, true);
				$result = !empty($decoded['success']);
			}
		} else {
			$ctx = stream_context_create(array(
				'http' => array(
					'method'  => 'POST',
					'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
					'content' => $query,
					'timeout' => 10
				)
			));
			$body = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
			if ($body !== false) {
				$decoded = json_decode($body, true);
				$result = !empty($decoded['success']);
			}
		}

		return $result;
	}

	/**
	 * @return array{error:string,wait_seconds:int} error is '' | 'token' | 'too_fast'
	 */
	public function checkSubmission($post) {
		$result = array('error' => '', 'wait_seconds' => 0);

		if (!$this->isTokenEnabled()) {
			return $result;
		}

		$posted = isset($post['cyberpunks_form_token']) ? trim((string)$post['cyberpunks_form_token']) : '';
		if ($posted === '') {
			$result['error'] = 'token';
			return $result;
		}

		$tokens = array();
		if (!empty($this->session->data[self::SESSION_KEY]) && is_array($this->session->data[self::SESSION_KEY])) {
			$tokens = $this->session->data[self::SESSION_KEY];
		}

		$now = time();
		$issued_at = 0;

		foreach ($tokens as $stored) {
			if (empty($stored['token']) || empty($stored['time'])) {
				continue;
			}
			if (hash_equals((string)$stored['token'], $posted)) {
				$issued_at = (int)$stored['time'];
				break;
			}
		}

		if (!$issued_at || ($now - $issued_at) > self::TOKEN_TTL) {
			$result['error'] = 'token';
			return $result;
		}

		$min_seconds = $this->getMinSeconds();
		$elapsed = $now - $issued_at;

		if ($min_seconds > 0 && $elapsed < $min_seconds) {
			$result['error'] = 'too_fast';
			$result['wait_seconds'] = max(1, $min_seconds - $elapsed);
			return $result;
		}

		return $result;
	}
}

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
	 * @param string $code success|token|too_fast|blocked|soft_limit
	 */
	public function getFormMessage($code) {
		return trim((string)$this->config->get('module_cyberpunks_shop_support_msg_' . (string)$code));
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

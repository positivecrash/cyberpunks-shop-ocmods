<?php
/**
 * IMAP inbound for Cyberpunks Shop Support.
 * Fetches ONLY messages whose subject matches the support request pattern
 * (IMAP SEARCH SUBJECT) — not the whole mailbox.
 */
class CyberpunksSupportImap {
	private $registry;
	private $config;
	private $db;
	private $log;

	public function __construct($registry) {
		$this->registry = $registry;
		$this->config = $registry->get('config');
		$this->db = $registry->get('db');
		$this->log = $registry->get('log');
	}

	public function isExtensionAvailable() {
		return function_exists('imap_open');
	}

	/**
	 * @return array{ok:bool,imported:int,skipped:int,errors:array,message:string}
	 */
	public function poll() {
		$result = array(
			'ok' => false,
			'imported' => 0,
			'skipped' => 0,
			'errors' => array(),
			'message' => ''
		);

		if (!$this->isExtensionAvailable()) {
			$result['message'] = 'PHP IMAP extension is not enabled.';
			$result['errors'][] = $result['message'];
			return $result;
		}

		if (!(int)$this->config->get('module_cyberpunks_shop_support_status')) {
			$result['message'] = 'Support module is disabled.';
			return $result;
		}

		if (!(int)$this->config->get('module_cyberpunks_shop_support_imap_status')) {
			$result['message'] = 'IMAP import is disabled.';
			return $result;
		}

		$host = trim((string)$this->config->get('module_cyberpunks_shop_support_imap_host'));
		$user = trim((string)$this->config->get('module_cyberpunks_shop_support_imap_user'));
		$pass = (string)$this->config->get('module_cyberpunks_shop_support_imap_password');
		$port = (int)$this->config->get('module_cyberpunks_shop_support_imap_port');
		$enc = strtolower(trim((string)$this->config->get('module_cyberpunks_shop_support_imap_encryption')));
		$folder = trim((string)$this->config->get('module_cyberpunks_shop_support_imap_folder'));
		$subject_needle = trim((string)$this->config->get('module_cyberpunks_shop_support_imap_subject'));
		$require_from_match = (int)$this->config->get('module_cyberpunks_shop_support_imap_require_from_match');
		$mark_seen = (int)$this->config->get('module_cyberpunks_shop_support_imap_mark_seen');
		$max = (int)$this->config->get('module_cyberpunks_shop_support_imap_max');

		if ($port < 1) {
			$port = ($enc === 'ssl') ? 993 : 143;
		}
		if ($folder === '') {
			$folder = 'INBOX';
		}
		if ($subject_needle === '') {
			$subject_needle = 'Cyberpunks.shop - Support request';
		}
		if ($max < 1) {
			$max = 25;
		}
		if ($max > 100) {
			$max = 100;
		}

		if ($host === '' || $user === '' || $pass === '') {
			$result['message'] = 'IMAP host, username or password is not configured.';
			$result['errors'][] = $result['message'];
			return $result;
		}

		$mailbox = '{' . $host . ':' . $port . '/imap';
		if ($enc === 'ssl') {
			$mailbox .= '/ssl';
		} elseif ($enc === 'tls') {
			$mailbox .= '/tls';
		}
		$mailbox .= '/novalidate-cert}' . $folder;

		$inbox = @imap_open($mailbox, $user, $pass);
		if (!$inbox) {
			$err = imap_last_error() ? imap_last_error() : 'Unable to connect to IMAP.';
			$result['message'] = $err;
			$result['errors'][] = $err;
			$this->log->write('Cyberpunks Support IMAP: connect failed — ' . $err);
			return $result;
		}

		// Security: only search messages with support subject needle (UNSEEN preferred).
		$criteria = 'UNSEEN SUBJECT "' . $this->escapeSearch($subject_needle) . '"';
		$uids = @imap_search($inbox, $criteria, SE_UID);

		if ($uids === false) {
			imap_close($inbox);
			$result['ok'] = true;
			$result['message'] = 'No matching unread support emails.';
			return $result;
		}

		rsort($uids);
		$uids = array_slice($uids, 0, $max);

		$this->registry->get('load')->model('extension/module/cyberpunks_shop_support');
		$model = $this->registry->get('model_extension_module_cyberpunks_shop_support');
		$model->ensureSchema();

		foreach ($uids as $uid) {
			$overview = @imap_fetch_overview($inbox, (string)$uid, FT_UID);
			$header_raw = @imap_fetchheader($inbox, (string)$uid, FT_UID);
			$structure = @imap_fetchstructure($inbox, (string)$uid, FT_UID);

			if (!$overview || empty($overview[0])) {
				$result['skipped']++;
				continue;
			}

			$ov = $overview[0];
			$subject = isset($ov->subject) ? $this->decodeMimeHeader($ov->subject) : '';
			$request_code = $this->extractRequestCode($subject, $subject_needle);

			if ($request_code === '') {
				$result['skipped']++;
				continue;
			}

			$ticket = $model->getTicketByCode($request_code);
			if (!$ticket) {
				$result['skipped']++;
				continue;
			}

			$email_message_id = $this->extractHeader($header_raw, 'Message-ID');
			if ($email_message_id === '') {
				$email_message_id = 'uid-' . md5($mailbox . '|' . $uid . '|' . $subject);
			}
			$email_message_id = substr($email_message_id, 0, 255);

			if ($model->hasEmailMessageId($email_message_id)) {
				$result['skipped']++;
				if ($mark_seen) {
					@imap_setflag_full($inbox, (string)$uid, '\\Seen', ST_UID);
				}
				continue;
			}

			$from_email = $this->extractFromEmail($header_raw, isset($ov->from) ? $ov->from : '');
			if ($require_from_match) {
				$ticket_email = utf8_strtolower(trim((string)$ticket['email']));
				if ($from_email === '' || $ticket_email === '' || $from_email !== $ticket_email) {
					$result['skipped']++;
					$this->log->write('Cyberpunks Support IMAP: skip uid ' . $uid . ' — From ' . $from_email . ' != ticket ' . $ticket_email);
					continue;
				}
			}

			$body = $this->getBodyText($inbox, $uid, $structure);
			$body = $this->stripQuotedReply($body);
			$body = trim($body);

			if ($body === '') {
				$result['skipped']++;
				continue;
			}

			$added = $model->addCustomerMessageFromImap((int)$ticket['ticket_id'], $body, $email_message_id);
			if ($added) {
				$result['imported']++;
				if ($mark_seen) {
					@imap_setflag_full($inbox, (string)$uid, '\\Seen', ST_UID);
				}
			} else {
				$result['skipped']++;
			}
		}

		imap_close($inbox);
		$result['ok'] = true;
		$result['message'] = 'Imported ' . $result['imported'] . ', skipped ' . $result['skipped'] . '.';
		return $result;
	}

	private function escapeSearch($value) {
		return str_replace(array('\\', '"'), array('\\\\', '\\"'), $value);
	}

	private function extractRequestCode($subject, $needle) {
		$subject = trim((string)$subject);
		if ($subject === '') {
			return '';
		}

		if (preg_match('/Support request\s*-\s*([A-Z0-9]{6,16})\b/i', $subject, $m)) {
			return strtoupper($m[1]);
		}

		// Fallback: last token after needle
		$pos = stripos($subject, $needle);
		if ($pos === false) {
			return '';
		}
		$tail = trim(substr($subject, $pos + strlen($needle)));
		$tail = ltrim($tail, " \t-–—:");
		if (preg_match('/^([A-Z0-9]{6,16})\b/i', $tail, $m2)) {
			return strtoupper($m2[1]);
		}

		return '';
	}

	private function extractHeader($raw, $name) {
		if (!$raw) {
			return '';
		}
		if (preg_match('/^' . preg_quote($name, '/') . ':\s*(.+)$/mi', $raw, $m)) {
			return trim($m[1]);
		}
		return '';
	}

	private function extractFromEmail($header_raw, $overview_from) {
		$from = $this->extractHeader($header_raw, 'From');
		if ($from === '') {
			$from = (string)$overview_from;
		}
		$from = $this->decodeMimeHeader($from);
		if (preg_match('/<([^>]+)>/', $from, $m)) {
			return utf8_strtolower(trim($m[1]));
		}
		if (preg_match('/([a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,})/i', $from, $m2)) {
			return utf8_strtolower(trim($m2[1]));
		}
		return '';
	}

	private function decodeMimeHeader($value) {
		$value = (string)$value;
		if ($value === '') {
			return '';
		}
		if (function_exists('imap_mime_header_decode')) {
			$parts = @imap_mime_header_decode($value);
			if (is_array($parts)) {
				$out = '';
				foreach ($parts as $part) {
					$charset = (!empty($part->charset) && strtoupper($part->charset) !== 'DEFAULT') ? $part->charset : 'UTF-8';
					$text = $part->text;
					if (strtoupper($charset) !== 'UTF-8') {
						$converted = @iconv($charset, 'UTF-8//IGNORE', $text);
						if ($converted !== false) {
							$text = $converted;
						}
					}
					$out .= $text;
				}
				return $out;
			}
		}
		return $value;
	}

	private function getBodyText($inbox, $uid, $structure) {
		if (!$structure) {
			$raw = @imap_body($inbox, (string)$uid, FT_UID | FT_PEEK);
			return $this->decodePart($raw, 0);
		}

		if (empty($structure->parts)) {
			$raw = @imap_body($inbox, (string)$uid, FT_UID | FT_PEEK);
			return $this->decodePart($raw, isset($structure->encoding) ? (int)$structure->encoding : 0);
		}

		$text = $this->findPartText($inbox, $uid, $structure->parts, '');
		return $text;
	}

	private function findPartText($inbox, $uid, $parts, $prefix) {
		$html = '';
		$plain = '';

		foreach ($parts as $index => $part) {
			$part_no = $prefix === '' ? (string)($index + 1) : $prefix . '.' . ($index + 1);
			$type = isset($part->type) ? (int)$part->type : 0;
			$subtype = isset($part->subtype) ? strtoupper($part->subtype) : '';

			if (!empty($part->parts)) {
				$nested = $this->findPartText($inbox, $uid, $part->parts, $part_no);
				if ($nested !== '') {
					return $nested;
				}
				continue;
			}

			if ($type === 0 && $subtype === 'PLAIN') {
				$raw = @imap_fetchbody($inbox, (string)$uid, $part_no, FT_UID | FT_PEEK);
				$plain = $this->decodePart($raw, isset($part->encoding) ? (int)$part->encoding : 0);
			} elseif ($type === 0 && $subtype === 'HTML' && $plain === '') {
				$raw = @imap_fetchbody($inbox, (string)$uid, $part_no, FT_UID | FT_PEEK);
				$html = $this->decodePart($raw, isset($part->encoding) ? (int)$part->encoding : 0);
			}
		}

		if ($plain !== '') {
			return $plain;
		}
		if ($html !== '') {
			return trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
		}
		return '';
	}

	private function decodePart($raw, $encoding) {
		$raw = (string)$raw;
		if ($raw === '') {
			return '';
		}
		if ($encoding === 3) {
			$raw = base64_decode($raw);
		} elseif ($encoding === 4) {
			$raw = quoted_printable_decode($raw);
		}
		if (!mb_check_encoding($raw, 'UTF-8')) {
			$converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $raw);
			if ($converted !== false) {
				$raw = $converted;
			}
		}
		return $raw;
	}

	private function stripQuotedReply($body) {
		$body = str_replace(array("\r\n", "\r"), "\n", (string)$body);
		$lines = explode("\n", $body);
		$out = array();

		foreach ($lines as $line) {
			if (preg_match('/^On .+ wrote:\s*$/i', $line)) {
				break;
			}
			if (preg_match('/^-{2,}\s*Original Message\s*-{2,}/i', $line)) {
				break;
			}
			if (preg_match('/^From:\s.+/i', $line) && count($out) > 2) {
				// likely forwarded header block start — keep going unless classic quote
			}
			if (strpos($line, '>') === 0) {
				continue;
			}
			$out[] = $line;
		}

		$text = trim(implode("\n", $out));
		$text = preg_replace("/\n{3,}/", "\n\n", $text);
		return trim($text);
	}
}

<?php
class CyberpunksMailHtml {
	const HTML_OPEN = '<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">';

	public static function decodeStored($html) {
		$html = (string)$html;
		$prev = null;

		while ($prev !== $html && (strpos($html, '&lt;') !== false || strpos($html, '&gt;') !== false || strpos($html, '&amp;') !== false)) {
			$prev = $html;
			$html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
		}

		return $html;
	}

	public static function isFullDocument($html) {
		$html = ltrim((string)$html);

		return (bool)preg_match('/<!DOCTYPE\s+html/i', $html) || (bool)preg_match('/<html[\s>]/i', $html);
	}

	/**
	 * Wrap partial markup in a valid HTML email document.
	 * Restores structure when Summernote (or similar) stripped DOCTYPE/html/head/body.
	 */
	public static function normalizeDocument($html, $force_wrap = true) {
		$html = trim(self::decodeStored($html));

		if ($html === '') {
			return '';
		}

		if (self::isFullDocument($html)) {
			return $html;
		}

		if (!$force_wrap) {
			return $html;
		}

		$head = '';
		$body = $html;

		if (preg_match('/^((?:\s|<!--(?:.(?!-->))*-->|<meta\b[^>]*>|<title\b[^>]*>.*?<\/title>|<link\b[^>]*>|<style\b[^>]*>.*?<\/style>)+)([\s\S]*)$/is', $html, $matches)) {
			$head = trim($matches[1]);
			$body = trim($matches[2]);

			if ($body === '') {
				$body = $head;
				$head = '<meta charset="utf-8">';
			}
		} else {
			$head = '<meta charset="utf-8">';
		}

		if (!preg_match('/<meta\b[^>]*charset/i', $head)) {
			$head = '<meta charset="utf-8">' . "\n" . $head;
		}

		return '<!DOCTYPE html>' . "\n"
			. self::HTML_OPEN . "\n"
			. '<head>' . "\n" . $head . "\n" . '</head>' . "\n"
			. '<body>' . "\n" . $body . "\n" . '</body>' . "\n"
			. '</html>';
	}

	/**
	 * Email clients cannot resolve site-relative paths like /catalog/... — rewrite to absolute.
	 * Conservative replacements only (no open-ended regex that can swallow half the document).
	 */
	public static function absolutizeUrls($html, $base_url) {
		$html = (string)$html;
		$base = rtrim((string)$base_url, '/');

		if ($base === '' || $html === '') {
			return $html;
		}

		$out = preg_replace_callback(
			'/\b(src|href|background)=(["\'])(\/[^"\'>]{1,500})\2/i',
			function ($m) use ($base) {
				$path = $m[3];

				// Protocol-relative //cdn... — do not touch.
				if (isset($path[1]) && $path[1] === '/') {
					return $m[0];
				}

				return $m[1] . '=' . $m[2] . $base . $path . $m[2];
			},
			$html
		);

		// PCRE failure must never wipe the HTML part (clients then show plain text only).
		if ($out === null) {
			return $html;
		}

		// Common unquoted theme paths: src=/catalog/...
		$out2 = preg_replace_callback(
			'/\b(src|href)=(\/catalog\/[a-zA-Z0-9_\/.\-]+)/i',
			function ($m) use ($base) {
				return $m[1] . '=' . $base . $m[2];
			},
			$out
		);

		return ($out2 === null) ? $out : $out2;
	}

	/**
	 * Short plain-text alternative. Do NOT dump the full HTML as text — some clients
	 * then prefer the text part and the message looks like a blank/broken email.
	 */
	public static function htmlToPlainText($html, $store_name = '') {
		$store_name = trim((string)$store_name);

		if ($store_name === '') {
			$store_name = 'cyberpunks.shop';
		}

		$snippet = self::decodeStored($html);
		$snippet = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $snippet);
		$snippet = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $snippet);
		$snippet = strip_tags($snippet);
		$snippet = html_entity_decode($snippet, ENT_QUOTES, 'UTF-8');
		$snippet = preg_replace('/\s+/u', ' ', $snippet);
		$snippet = trim($snippet);

		if (function_exists('utf8_strlen') && function_exists('utf8_substr')) {
			if (utf8_strlen($snippet) > 180) {
				$snippet = utf8_substr($snippet, 0, 177) . '...';
			}
		} elseif (function_exists('mb_strlen') && function_exists('mb_substr')) {
			if (mb_strlen($snippet, 'UTF-8') > 180) {
				$snippet = mb_substr($snippet, 0, 177, 'UTF-8') . '...';
			}
		} elseif (strlen($snippet) > 180) {
			$snippet = substr($snippet, 0, 177) . '...';
		}

		$lines = array(
			'Update from ' . $store_name . '.',
		);

		if ($snippet !== '') {
			$lines[] = '';
			$lines[] = $snippet;
		}

		$lines[] = '';
		$lines[] = 'Open this message in an HTML email client to see the full layout.';

		return implode("\n", $lines);
	}
}

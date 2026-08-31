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
}

<?php
/**
 * Convert / resize admin shipment photos to WebP.
 * Accepts JPEG, PNG, WebP via GD; HEIC/HEIF via Imagick when available.
 * Prefer browser-side HEIC→JPEG (heic2any) when Imagick is missing.
 */
class OrderAdminFeaturesImage {
	const MAX_SIDE = 2000;
	const WEBP_QUALITY = 80;
	const THUMB_SIDE = 240;
	const THUMB_QUALITY = 75;
	const MAX_BYTES = 8388608; // 8 MB — client already resizes before upload

	public static function allowedExtensions() {
		return array('jpg', 'jpeg', 'png', 'webp', 'heic', 'heif');
	}

	/**
	 * @param string $original_name Client filename (tmp upload paths have no extension)
	 * @return array{ok:bool,error?:string,width?:int,height?:int,filesize?:int}
	 */
	public static function convertToWebp($source_path, $dest_webp_path, $max_side = self::MAX_SIDE, $quality = self::WEBP_QUALITY, $original_name = '') {
		// Admin-only processing; avoid fatal on large phone photos if a full-size file slips through.
		if (function_exists('ini_set')) {
			@ini_set('memory_limit', '256M');
		}

		if (!is_file($source_path) || !is_readable($source_path)) {
			return array('ok' => false, 'error' => 'Source file missing');
		}

		$size = filesize($source_path);

		if ($size === false || $size < 1) {
			return array('ok' => false, 'error' => 'Empty file');
		}

		if ($size > self::MAX_BYTES) {
			return array('ok' => false, 'error' => 'File too large (max 20 MB)');
		}

		$ext = strtolower(pathinfo($original_name !== '' ? $original_name : $source_path, PATHINFO_EXTENSION));
		$finfo_mime = '';

		if (function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			if ($finfo) {
				$finfo_mime = (string)finfo_file($finfo, $source_path);
				finfo_close($finfo);
			}
		}

		$is_heic = in_array($ext, array('heic', 'heif'), true)
			|| in_array($finfo_mime, array('image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'), true)
			|| self::sniffHeic($source_path);

		$image = null;

		if ($is_heic) {
			$image = self::loadHeic($source_path);

			if ($image === null) {
				return array('ok' => false, 'error' => 'HEIC requires Imagick with HEIC support on the server (or upload JPG/PNG/WebP)');
			}
		} else {
			$image = self::loadViaGd($source_path, $finfo_mime, $ext);

			if ($image === null) {
				return array('ok' => false, 'error' => 'Unsupported or corrupt image');
			}
		}

		$width = imagesx($image);
		$height = imagesy($image);

		if ($width < 1 || $height < 1) {
			imagedestroy($image);
			return array('ok' => false, 'error' => 'Invalid image dimensions');
		}

		$max_side = max(1, (int)$max_side);
		$longest = max($width, $height);

		if ($longest > $max_side) {
			$scale = $max_side / $longest;
			$new_w = max(1, (int)round($width * $scale));
			$new_h = max(1, (int)round($height * $scale));
			$resized = imagecreatetruecolor($new_w, $new_h);

			if ($resized === false) {
				imagedestroy($image);
				return array('ok' => false, 'error' => 'Resize failed');
			}

			imagealphablending($resized, false);
			imagesavealpha($resized, true);
			$transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
			imagefilledrectangle($resized, 0, 0, $new_w, $new_h, $transparent);
			imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_w, $new_h, $width, $height);
			imagedestroy($image);
			$image = $resized;
			$width = $new_w;
			$height = $new_h;
		}

		$dir = dirname($dest_webp_path);

		if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
			imagedestroy($image);
			return array('ok' => false, 'error' => 'Cannot create storage directory');
		}

		if (!function_exists('imagewebp')) {
			imagedestroy($image);
			return array('ok' => false, 'error' => 'PHP GD WebP support missing');
		}

		$quality = max(1, min(100, (int)$quality));
		$ok = @imagewebp($image, $dest_webp_path, $quality);
		imagedestroy($image);

		if (!$ok || !is_file($dest_webp_path)) {
			return array('ok' => false, 'error' => 'WebP encode failed');
		}

		return array(
			'ok'       => true,
			'width'    => $width,
			'height'   => $height,
			'filesize' => (int)filesize($dest_webp_path)
		);
	}

	/**
	 * @return array{ok:bool,error?:string}
	 */
	public static function makeThumb($source_webp, $dest_thumb, $max_side = self::THUMB_SIDE, $quality = self::THUMB_QUALITY) {
		return self::convertToWebp($source_webp, $dest_thumb, $max_side, $quality, 'thumb.webp');
	}

	/** HEIC/HEIF brands in ISO BMFF ftyp box */
	public static function sniffHeic($path) {
		$fh = @fopen($path, 'rb');

		if (!$fh) {
			return false;
		}

		$header = @fread($fh, 16);
		fclose($fh);

		if ($header === false || strlen($header) < 12) {
			return false;
		}

		if (substr($header, 4, 4) !== 'ftyp') {
			return false;
		}

		$brand = strtolower(substr($header, 8, 4));

		return in_array($brand, array('heic', 'heif', 'mif1', 'msf1', 'heix', 'hevc', 'heim', 'heis', 'avic'), true);
	}

	/** @return resource|GdImage|null */
	private static function loadViaGd($path, $mime, $ext) {
		if (!function_exists('imagecreatefromstring')) {
			return null;
		}

		$data = @file_get_contents($path);

		if ($data === false || $data === '') {
			return null;
		}

		$image = @imagecreatefromstring($data);

		if ($image !== false) {
			return $image;
		}

		if (($mime === 'image/jpeg' || in_array($ext, array('jpg', 'jpeg'), true)) && function_exists('imagecreatefromjpeg')) {
			$image = @imagecreatefromjpeg($path);
			if ($image !== false) {
				return $image;
			}
		}

		if (($mime === 'image/png' || $ext === 'png') && function_exists('imagecreatefrompng')) {
			$image = @imagecreatefrompng($path);
			if ($image !== false) {
				return $image;
			}
		}

		if (($mime === 'image/webp' || $ext === 'webp') && function_exists('imagecreatefromwebp')) {
			$image = @imagecreatefromwebp($path);
			if ($image !== false) {
				return $image;
			}
		}

		return null;
	}

	/** @return resource|GdImage|null */
	private static function loadHeic($path) {
		if (!extension_loaded('imagick') || !class_exists('Imagick')) {
			return null;
		}

		try {
			$imagick = new Imagick();
			$imagick->readImage($path);
			$imagick->setImageFormat('png');
			$blob = $imagick->getImageBlob();
			$imagick->clear();
			$imagick->destroy();

			if ($blob === false || $blob === '') {
				return null;
			}

			$image = @imagecreatefromstring($blob);

			return ($image !== false) ? $image : null;
		} catch (Exception $e) {
			return null;
		}
	}
}

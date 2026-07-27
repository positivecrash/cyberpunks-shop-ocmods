<?php

class CyberpunksLanguageOverrides {
	public static function apply($language, $filename, $config) {
		if (!$language || !$config) {
			return;
		}

		$map = $config->get('module_cyberpunks_language_overrides_map');

		if (!is_array($map)) {
			return;
		}

		$prefix = $filename . ':';

		foreach ($map as $key => $value) {
			if (!is_string($key) || $value === '') {
				continue;
			}

			if (strpos($key, ':') !== false) {
				if (strpos($key, $prefix) === 0) {
					$language->set(substr($key, strlen($prefix)), $value);
				}

				continue;
			}

			// Legacy bare keys (pre-1.5.0 saves): re-apply on every language load.
			$language->set($key, $value);
		}
	}
}

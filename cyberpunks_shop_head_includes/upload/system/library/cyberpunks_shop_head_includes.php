<?php
/**
 * Head asset rules (OCMOD Cyberpunks Shop Head Includes).
 */
final class CyberpunksShopHeadIncludes {
	private static $applied_controller_routes = array();
	private static $theme_asset_prefix = 'catalog/view/theme/cybershops/';

	private static function normalizeRoute($route) {
		$route = trim((string)$route);
		$route = preg_replace('/\|.*$/', '', $route);
		$route = trim($route, '/');

		if (substr($route, -6) === '/index') {
			$route = substr($route, 0, -6);
		}

		return $route;
	}

	public static function applyControllerRules($registry, $route) {
		if (!$registry->has('document')) {
			return;
		}

		$rules = $registry->get('config')->get('cyberpunks_shop_head_includes_rules');

		if (!is_array($rules)) {
			return;
		}

		if (isset(self::$applied_controller_routes[$route])) {
			return;
		}

		self::$applied_controller_routes[$route] = true;

		$request_route = '';
		if ($registry->has('request')) {
			$request = $registry->get('request');
			if (isset($request->get['route'])) {
				$request_route = (string)$request->get['route'];
			}
		}

		self::mergeRules($registry, $rules, function ($template) use ($route, $request_route) {
			return self::ruleMatchesController($template, $route, $request_route);
		});
	}

	public static function applyViewRules($registry, $view_route) {
		if (!$registry->has('document')) {
			return;
		}

		$rules = $registry->get('config')->get('cyberpunks_shop_head_includes_rules');

		if (!is_array($rules)) {
			return;
		}

		$view_route = trim((string)$view_route);

		if ($view_route === '') {
			return;
		}

		self::mergeRules($registry, $rules, function ($template) use ($view_route) {
			return self::ruleMatchesView($template, $view_route);
		});
	}

	private static function ruleMatchesController($template, $route, $request_route = '') {
		$t = trim((string)$template);
		$route_normalized = self::normalizeRoute($route);
		$request_route_normalized = self::normalizeRoute($request_route);

		if ($t === '') {
			return false;
		}

		if (stripos($t, 'view:') === 0) {
			return false;
		}

		if ($t === '*') {
			return true;
		}

		if (stripos($t, 'route:') === 0) {
			$t = trim(substr($t, 6));
		}

		$t = self::normalizeRoute($t);

		return $t === $route_normalized || ($request_route_normalized !== '' && $t === $request_route_normalized);
	}

	private static function ruleMatchesView($template, $view_route) {
		$t = trim((string)$template);

		if ($t === '' || $t === '*') {
			return false;
		}

		if (stripos($t, 'route:') === 0) {
			return false;
		}

		if (stripos($t, 'view:') === 0) {
			return trim(substr($t, 5)) === $view_route;
		}

		if ($t === 'product/product') {
			return false;
		}

		return $t === $view_route;
	}

	private static function mergeRules($registry, $rules, callable $matcher) {
		$exclude = $registry->has('cyberpunks_shop_head_includes_exclude') ? $registry->get('cyberpunks_shop_head_includes_exclude') : array('js' => array(), 'css' => array());
		$script_types = $registry->has('cyberpunks_shop_head_includes_script_types') ? $registry->get('cyberpunks_shop_head_includes_script_types') : array();

		if (!isset($exclude['js']) || !is_array($exclude['js'])) {
			$exclude['js'] = array();
		}

		if (!isset($exclude['css']) || !is_array($exclude['css'])) {
			$exclude['css'] = array();
		}
		if (!is_array($script_types)) {
			$script_types = array();
		}

		$document = $registry->get('document');

		foreach ($rules as $rule) {
			if (empty($rule['status']) || empty($rule['template'])) {
				continue;
			}

			if (!$matcher($rule['template'])) {
				continue;
			}

			if (!empty($rule['js'])) {
				$js_lines = preg_split('/\r\n|\r|\n/', (string)$rule['js']);

				foreach ($js_lines as $js_path) {
					$js_path = trim($js_path);

					if ($js_path !== '') {
						$is_module = false;
						if (stripos($js_path, 'module:') === 0) {
							$is_module = true;
							$js_path = trim(substr($js_path, 7));
						}

						if ($js_path === '') {
							continue;
						}

						$resolved_js_path = self::applyAssetVersion($js_path);
						$document->addScript($resolved_js_path);

						if ($is_module) {
							$script_types[$resolved_js_path] = 'module';
						}
					}
				}
			}

			if (!empty($rule['css'])) {
				$css_lines = preg_split('/\r\n|\r|\n/', (string)$rule['css']);

				foreach ($css_lines as $css_path) {
					$css_path = trim($css_path);

					if ($css_path !== '') {
						$document->addStyle(self::applyAssetVersion($css_path));
					}
				}
			}

			if (!empty($rule['js_exclude'])) {
				$js_exclude_lines = preg_split('/\r\n|\r|\n/', (string)$rule['js_exclude']);

				foreach ($js_exclude_lines as $js_exclude_path) {
					$js_exclude_path = trim($js_exclude_path);

					if ($js_exclude_path !== '') {
						$exclude['js'][$js_exclude_path] = $js_exclude_path;
					}
				}
			}

			if (!empty($rule['css_exclude'])) {
				$css_exclude_lines = preg_split('/\r\n|\r|\n/', (string)$rule['css_exclude']);

				foreach ($css_exclude_lines as $css_exclude_path) {
					$css_exclude_path = trim($css_exclude_path);

					if ($css_exclude_path !== '') {
						$exclude['css'][$css_exclude_path] = $css_exclude_path;
					}
				}
			}
		}

		$registry->set('cyberpunks_shop_head_includes_exclude', $exclude);
		$registry->set('cyberpunks_shop_head_includes_script_types', $script_types);
	}

	private static function applyAssetVersion($asset_path) {
		$asset_path = trim((string)$asset_path);

		if ($asset_path === '' || strpos($asset_path, '?v=') !== false) {
			return $asset_path;
		}

		$asset_path_without_query = preg_replace('/\?.*$/', '', $asset_path);

		// Skip remote URLs and protocol-relative URLs.
		if (preg_match('#^(https?:)?//#i', $asset_path)) {
			return $asset_path;
		}

		$local_path = ltrim($asset_path_without_query, '/');

		if (strpos($local_path, self::$theme_asset_prefix) !== 0) {
			return $asset_path;
		}

		$relative_theme_file = substr($local_path, strlen(self::$theme_asset_prefix));
		$absolute_file = rtrim(DIR_APPLICATION, '/\\') . '/view/theme/cybershops/' . $relative_theme_file;

		if (!is_file($absolute_file)) {
			return $asset_path;
		}

		$version = (string)@filemtime($absolute_file);
		if ($version === '' || $version === '0') {
			return $asset_path;
		}

		return $asset_path . (strpos($asset_path, '?') === false ? '?' : '&') . 'v=' . $version;
	}
}

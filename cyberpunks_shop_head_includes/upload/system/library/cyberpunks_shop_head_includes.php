<?php
/**
 * Head asset rules (OCMOD Cyberpunks Shop Head Includes).
 */
final class CyberpunksShopHeadIncludes {
	private static $applied_controller_routes = array();

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

		if (!isset($exclude['js']) || !is_array($exclude['js'])) {
			$exclude['js'] = array();
		}

		if (!isset($exclude['css']) || !is_array($exclude['css'])) {
			$exclude['css'] = array();
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
						$document->addScript($js_path);
					}
				}
			}

			if (!empty($rule['css'])) {
				$css_lines = preg_split('/\r\n|\r|\n/', (string)$rule['css']);

				foreach ($css_lines as $css_path) {
					$css_path = trim($css_path);

					if ($css_path !== '') {
						$document->addStyle($css_path);
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
	}
}

<?php
class ModelExtensionModuleCyberpunksMailTemplates extends Model {
	public function isEnabled() {
		return (bool)$this->config->get('module_cyberpunks_mail_templates_status');
	}

	public function getStatusCodes() {
		return array('paid', 'pending', 'processing', 'shipped', 'canceled', 'complete');
	}

	public function getTemplateHtml($code) {
		$templates = $this->config->get('module_cyberpunks_mail_templates_html');

		if (!is_array($templates)) {
			return '';
		}

		$code = (string)$code;

		if (!is_file(DIR_SYSTEM . 'library/cyberpunks_mail_html.php')) {
			return isset($templates[$code]) ? trim((string)$templates[$code]) : '';
		}

		require_once(DIR_SYSTEM . 'library/cyberpunks_mail_html.php');

		return isset($templates[$code]) ? trim(CyberpunksMailHtml::decodeStored((string)$templates[$code])) : '';
	}

	public function getSubject($code) {
		if (!$this->isEnabled()) {
			return '';
		}

		$subjects = $this->config->get('module_cyberpunks_mail_templates_subject');

		if (!is_array($subjects)) {
			return '';
		}

		$code = (string)$code;

		return isset($subjects[$code]) ? trim((string)$subjects[$code]) : '';
	}

	public function isTemplateEnabled($code) {
		if (!$this->isEnabled() || $code === '') {
			return false;
		}

		if ($this->getTemplateHtml($code) !== '') {
			return true;
		}

		return $this->getLayoutIdForStatus($code) !== '';
	}

	/**
	 * Resolve template code from OpenCart order_status_id (by status name).
	 */
	public function resolveStatusCode($order_status_id, $language_id = 0) {
		$order_status_id = (int)$order_status_id;

		if ($order_status_id < 1) {
			return '';
		}

		if ($language_id < 1) {
			$language_id = (int)$this->config->get('config_language_id');
		}

		$query = $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "order_status` WHERE order_status_id = '" . (int)$order_status_id . "' AND language_id = '" . (int)$language_id . "' LIMIT 1");

		if (!$query->num_rows) {
			$query = $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "order_status` WHERE order_status_id = '" . (int)$order_status_id . "' ORDER BY language_id ASC LIMIT 1");
		}

		if (!$query->num_rows) {
			return '';
		}

		return $this->statusNameToCode($query->row['name']);
	}

	public function statusNameToCode($name) {
		$key = strtolower(trim(html_entity_decode((string)$name, ENT_QUOTES, 'UTF-8')));
		$map = array(
			'paid'       => 'paid',
			'pending'    => 'pending',
			'processing' => 'processing',
			'shipped'    => 'shipped',
			'canceled'   => 'canceled',
			'cancelled'  => 'canceled',
			'complete'   => 'complete'
		);

		return isset($map[$key]) ? $map[$key] : '';
	}

	/**
	 * Apply subject + body for an order status customer email.
	 * Falls back to stock OpenCart mail when no custom subject/HTML for that status.
	 *
	 * @param object $mail
	 * @param string $route mail/order_add|mail/order_edit
	 * @param array  $data
	 * @param string $default_subject
	 * @param int    $order_status_id
	 * @param array  $order_info optional — used to enrich edit emails with products/totals
	 */
	public function applyOrderStatusMail($mail, $route, $data, $default_subject, $order_status_id, $order_info = array()) {
		$code = $this->resolveStatusCode($order_status_id, !empty($order_info['language_id']) ? (int)$order_info['language_id'] : 0);

		$custom_subject = ($code !== '') ? $this->getSubject($code) : '';
		$use_html = ($code !== '') && $this->isTemplateEnabled($code);

		if ($custom_subject === '' && !$use_html) {
			$mail->setSubject($default_subject);

			if ($route === 'mail/order_add') {
				$vars = $this->buildVariables($data);
				$body = $this->load->view('mail/order_add', $data);
				$this->assignHtmlMail($mail, $this->finalizeHtmlEmail($this->applyLayout($body, $vars, $code), $order_info));
			} else {
				$mail->setText($this->load->view('mail/order_edit', $data));
			}

			return;
		}

		if ($use_html && !empty($order_info['order_id'])) {
			$data = $this->enrichOrderData($data, $order_info, $order_status_id);
		}

		$vars = $this->buildVariables($data);

		if ($custom_subject !== '') {
			$mail->setSubject(html_entity_decode($this->replaceShortcodes($custom_subject, $vars), ENT_QUOTES, 'UTF-8'));
		} else {
			$mail->setSubject($default_subject);
		}

		if ($use_html) {
			$body = $this->replaceShortcodes($this->getTemplateHtml($code), $vars);
			$this->assignHtmlMail($mail, $this->finalizeHtmlEmail($this->applyLayout($body, $vars, $code), $order_info));
			return;
		}

		if ($route === 'mail/order_add') {
			$body = $this->load->view('mail/order_add', $data);
			$this->assignHtmlMail($mail, $this->finalizeHtmlEmail($this->applyLayout($body, $vars, $code), $order_info));
		} else {
			$mail->setText($this->load->view('mail/order_edit', $data));
		}
	}

	/**
	 * Send only the template HTML. OpenCart requires a non-empty text part or it injects
	 * the scary "does not support HTML email" stub — use a single space, nothing else.
	 */
	private function assignHtmlMail($mail, $html) {
		$mail->setText(' ');
		$mail->setHtml((string)$html);
	}

	public function getLayouts() {
		$layouts = $this->config->get('module_cyberpunks_mail_templates_layouts');

		return is_array($layouts) ? $layouts : array();
	}

	public function getLayoutById($layout_id) {
		$layout_id = (string)$layout_id;

		if ($layout_id === '') {
			return null;
		}

		foreach ($this->getLayouts() as $layout) {
			if (!is_array($layout) || empty($layout['id'])) {
				continue;
			}

			if ((string)$layout['id'] === $layout_id) {
				return $layout;
			}
		}

		return null;
	}

	public function getLayoutIdForStatus($status_code) {
		$map = $this->config->get('module_cyberpunks_mail_templates_layout');

		if (!is_array($map) || $status_code === '') {
			return '';
		}

		return isset($map[$status_code]) ? trim((string)$map[$status_code]) : '';
	}

	/**
	 * Wrap status/stock body in the layout chosen for this status ({content} shortcode).
	 */
	private function applyLayout($body_html, array $vars, $status_code) {
		if (!$this->isEnabled() || $status_code === '') {
			return $body_html;
		}

		$layout = $this->getLayoutById($this->getLayoutIdForStatus($status_code));

		if (!$layout || !isset($layout['html']) || trim((string)$layout['html']) === '') {
			return $body_html;
		}

		if (!is_file(DIR_SYSTEM . 'library/cyberpunks_mail_html.php')) {
			$html = str_replace('{content}', $body_html, (string)$layout['html']);

			return $this->replaceShortcodes($html, $vars);
		}

		require_once(DIR_SYSTEM . 'library/cyberpunks_mail_html.php');

		$html = str_replace('{content}', $body_html, CyberpunksMailHtml::decodeStored((string)$layout['html']));

		return $this->replaceShortcodes($html, $vars);
	}

	private function finalizeHtmlEmail($html, $order_info = array()) {
		if (!is_file(DIR_SYSTEM . 'library/cyberpunks_mail_html.php')) {
			return (string)$html;
		}

		require_once(DIR_SYSTEM . 'library/cyberpunks_mail_html.php');

		$html = CyberpunksMailHtml::normalizeDocument($html, true);
		$base = $this->resolveMailBaseUrl($order_info);

		return CyberpunksMailHtml::absolutizeUrls($html, $base);
	}

	/**
	 * Public storefront origin for absolute image URLs in outbound mail.
	 */
	private function resolveMailBaseUrl($order_info = array()) {
		$candidates = array();

		if (!empty($order_info['store_url'])) {
			$candidates[] = (string)$order_info['store_url'];
		}

		$candidates[] = (string)$this->config->get('config_ssl');
		$candidates[] = (string)$this->config->get('config_url');

		if (defined('HTTPS_SERVER')) {
			$candidates[] = (string)HTTPS_SERVER;
		}

		if (defined('HTTP_SERVER')) {
			$candidates[] = (string)HTTP_SERVER;
		}

		foreach ($candidates as $url) {
			$url = trim($url);

			if ($url === '') {
				continue;
			}

			// Drop path like /index.php/ — keep scheme + host (+ optional port).
			$parts = parse_url($url);

			if (empty($parts['scheme']) || empty($parts['host'])) {
				continue;
			}

			$base = $parts['scheme'] . '://' . $parts['host'];

			if (!empty($parts['port'])) {
				$base .= ':' . $parts['port'];
			}

			return $base;
		}

		return 'https://cyberpunks.shop';
	}

	private function replaceShortcodes($text, array $vars) {
		foreach ($vars as $key => $value) {
			$text = str_replace('{' . $key . '}', (string)$value, $text);
		}

		return $text;
	}

	private function buildVariables(array $data) {
		$store_name = isset($data['store_name']) ? $data['store_name'] : $this->config->get('config_name');
		$comment = $this->normalizeComment(isset($data['comment']) ? $data['comment'] : '');

		return array(
			'store_name'       => $store_name,
			'store_url'        => isset($data['store_url']) ? $data['store_url'] : HTTP_SERVER,
			'logo'             => isset($data['logo']) ? $data['logo'] : '',
			'email'            => isset($data['email']) ? $data['email'] : '',
			'telephone'        => isset($data['telephone']) ? $data['telephone'] : '',
			'order_id'         => isset($data['order_id']) ? $data['order_id'] : '',
			'date_added'       => isset($data['date_added']) ? $data['date_added'] : '',
			'order_status'     => isset($data['order_status']) ? $data['order_status'] : '',
			'payment_method'   => isset($data['payment_method']) ? $data['payment_method'] : '',
			'shipping_method'  => isset($data['shipping_method']) ? $data['shipping_method'] : '',
			'payment_address'  => isset($data['payment_address']) ? $data['payment_address'] : '',
			'shipping_address' => isset($data['shipping_address']) ? $data['shipping_address'] : '',
			'comment'          => $this->formatCommentHtml($comment),
			'firstname'        => isset($data['firstname']) ? $data['firstname'] : '',
			'lastname'         => isset($data['lastname']) ? $data['lastname'] : '',
			'order_products'   => $this->renderOrderProducts($data),
		);
	}

	/**
	 * Plain text from Add History → Comment (Notify Customer).
	 * Decodes admin Request::clean() entities and strips tags.
	 */
	private function normalizeComment($comment) {
		$comment = html_entity_decode((string)$comment, ENT_QUOTES, 'UTF-8');
		$comment = strip_tags($comment);
		$comment = preg_replace("/\r\n|\r/", "\n", $comment);

		return trim((string)$comment);
	}

	private function formatCommentHtml($comment) {
		$comment = $this->normalizeComment($comment);

		if ($comment === '') {
			return '';
		}

		return nl2br(htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'), false);
	}

	/**
	 * Fill products / totals / addresses when status-update mail only had thin $data.
	 */
	private function enrichOrderData(array $data, array $order_info, $order_status_id) {
		if (!isset($this->model_checkout_order)) {
			$this->load->model('checkout/order');
		}

		if (!isset($this->model_tool_upload)) {
			$this->load->model('tool/upload');
		}

		$language = new Language($order_info['language_code']);
		$language->load($order_info['language_code']);
		$language->load('mail/order_add');

		if (empty($data['store_name'])) {
			$data['store_name'] = $order_info['store_name'];
		}
		if (empty($data['store_url'])) {
			$data['store_url'] = $order_info['store_url'];
		}
		if (empty($data['logo'])) {
			$data['logo'] = $order_info['store_url'] . 'image/' . $this->config->get('config_logo');
		}
		if (empty($data['email'])) {
			$data['email'] = $order_info['email'];
		}
		if (empty($data['telephone'])) {
			$data['telephone'] = $order_info['telephone'];
		}
		if (empty($data['ip'])) {
			$data['ip'] = $order_info['ip'];
		}
		if (empty($data['payment_method'])) {
			$data['payment_method'] = $order_info['payment_method'];
		}
		if (empty($data['shipping_method'])) {
			$data['shipping_method'] = $order_info['shipping_method'];
		}
		if (empty($data['date_added'])) {
			$data['date_added'] = date($language->get('date_format_short'), strtotime($order_info['date_added']));
		}
		if (empty($data['firstname'])) {
			$data['firstname'] = $order_info['firstname'];
		}
		if (empty($data['lastname'])) {
			$data['lastname'] = $order_info['lastname'];
		}

		$data['text_product'] = $language->get('text_product');
		$data['text_model'] = $language->get('text_model');
		$data['text_quantity'] = $language->get('text_quantity');
		$data['text_price'] = $language->get('text_price');
		$data['text_total'] = $language->get('text_total');

		if (empty($data['payment_address'])) {
			$data['payment_address'] = $this->formatAddress($order_info, 'payment');
		}
		if (empty($data['shipping_address'])) {
			$data['shipping_address'] = $this->formatAddress($order_info, 'shipping');
		}

		$order_products = $this->model_checkout_order->getOrderProducts($order_info['order_id']);
		$data['products'] = array();

		foreach ($order_products as $order_product) {
			$option_data = array();
			$order_options = $this->model_checkout_order->getOrderOptions($order_info['order_id'], $order_product['order_product_id']);

			foreach ($order_options as $order_option) {
				if ($order_option['type'] != 'file') {
					$value = $order_option['value'];
				} else {
					$upload_info = $this->model_tool_upload->getUploadByCode($order_option['value']);
					$value = $upload_info ? $upload_info['name'] : '';
				}

				$option_data[] = array(
					'name'                    => $this->resolveOrderOptionDisplayName(
						isset($order_product['product_id']) ? (int)$order_product['product_id'] : 0,
						isset($order_option['product_option_id']) ? (int)$order_option['product_option_id'] : 0,
						isset($order_option['name']) ? $order_option['name'] : ''
					),
					'value'                   => $value,
					'product_option_value_id' => isset($order_option['product_option_value_id']) ? (int)$order_option['product_option_value_id'] : 0
				);
			}

			$data['products'][] = array(
				'product_id' => $order_product['product_id'],
				'name'     => $order_product['name'],
				'model'    => $order_product['model'],
				'option'   => $option_data,
				'quantity' => $order_product['quantity'],
				'price'    => $this->currency->format($order_product['price'] + ($this->config->get('config_tax') ? $order_product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
				'total'    => $this->currency->format($order_product['total'] + ($this->config->get('config_tax') ? ($order_product['tax'] * $order_product['quantity']) : 0), $order_info['currency_code'], $order_info['currency_value'])
			);
		}

		$data['vouchers'] = array();
		foreach ($this->model_checkout_order->getOrderVouchers($order_info['order_id']) as $order_voucher) {
			$data['vouchers'][] = array(
				'description' => $order_voucher['description'],
				'amount'      => $this->currency->format($order_voucher['amount'], $order_info['currency_code'], $order_info['currency_value'])
			);
		}

		$data['totals'] = array();
		foreach ($this->model_checkout_order->getOrderTotals($order_info['order_id']) as $order_total) {
			$data['totals'][] = array(
				'title' => $order_total['title'],
				'text'  => $this->currency->format($order_total['value'], $order_info['currency_code'], $order_info['currency_value'])
			);
		}

		return $data;
	}

	/**
	 * Prefer Cyberpunks Display Name over internal option key (e.g. insight-desktop-stand-color → Color).
	 */
	private function resolveOrderOptionDisplayName($product_id, $product_option_id, $fallback_name) {
		$fallback_name = (string)$fallback_name;
		$option_id = 0;

		if ($product_option_id > 0) {
			$query = $this->db->query("SELECT option_id FROM `" . DB_PREFIX . "product_option` WHERE product_option_id = '" . (int)$product_option_id . "' LIMIT 1");

			if ($query->num_rows) {
				$option_id = (int)$query->row['option_id'];
			}
		}

		if ($option_id > 0 && is_file(DIR_APPLICATION . 'model/extension/module/cyberpunks_shop_option_fields.php')) {
			$this->load->model('extension/module/cyberpunks_shop_option_fields');

			if (method_exists($this->model_extension_module_cyberpunks_shop_option_fields, 'resolveDisplayName')) {
				$resolved = trim((string)$this->model_extension_module_cyberpunks_shop_option_fields->resolveDisplayName($product_id, $option_id, $fallback_name));

				if ($resolved !== '') {
					return $resolved;
				}
			}
		}

		if ($option_id > 0 && is_file(DIR_SYSTEM . 'library/cyberpunks_palette_stock.php')) {
			require_once(DIR_SYSTEM . 'library/cyberpunks_palette_stock.php');

			if (class_exists('CyberpunksPaletteStock') && method_exists('CyberpunksPaletteStock', 'resolveOptionDisplayName')) {
				$resolved = trim((string)CyberpunksPaletteStock::resolveOptionDisplayName($this->db, $product_id, $option_id, $fallback_name));

				if ($resolved !== '' && $resolved !== $fallback_name) {
					return $resolved;
				}
			}
		}

		if ($fallback_name !== '' && (strpos($fallback_name, '-') !== false || strpos($fallback_name, '_') !== false)) {
			return ucwords(str_replace(array('-', '_'), ' ', $fallback_name));
		}

		return $fallback_name;
	}

	private function formatAddress(array $order_info, $prefix) {
		$format = !empty($order_info[$prefix . '_address_format'])
			? $order_info[$prefix . '_address_format']
			: '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';

		$find = array('{firstname}', '{lastname}', '{company}', '{address_1}', '{address_2}', '{city}', '{postcode}', '{zone}', '{zone_code}', '{country}');
		$replace = array(
			'firstname' => $order_info[$prefix . '_firstname'],
			'lastname'  => $order_info[$prefix . '_lastname'],
			'company'   => $order_info[$prefix . '_company'],
			'address_1' => $order_info[$prefix . '_address_1'],
			'address_2' => $order_info[$prefix . '_address_2'],
			'city'      => $order_info[$prefix . '_city'],
			'postcode'  => $order_info[$prefix . '_postcode'],
			'zone'      => $order_info[$prefix . '_zone'],
			'zone_code' => $order_info[$prefix . '_zone_code'],
			'country'   => $order_info[$prefix . '_country']
		);

		return str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));
	}

	/**
	 * Product thumb for order emails — variant mapping first, then catalog image.
	 */
	private function resolveOrderProductImage($product_id, array $options, $store_url) {
		$product_id = (int)$product_id;
		$image = '';

		if (!isset($this->model_tool_image)) {
			$this->load->model('tool/image');
		}

		if ($product_id && is_file(DIR_SYSTEM . 'library/cyberpunks_shop_variant_images_storage.php')) {
			require_once(DIR_SYSTEM . 'library/cyberpunks_shop_variant_images_storage.php');

			if (method_exists('CyberpunksShopVariantImagesStorage', 'resolveCartImage')) {
				$variant_mappings = $this->config->get('module_cyberpunks_variant_images_mappings');

				if (!is_array($variant_mappings)) {
					$variant_mappings = array();
				}

				$resolved_image = CyberpunksShopVariantImagesStorage::resolveCartImage(
					$variant_mappings,
					$product_id,
					$options
				);

				if ($resolved_image !== '') {
					if (method_exists('CyberpunksShopVariantImagesStorage', 'pathToUrl')) {
						$image = CyberpunksShopVariantImagesStorage::pathToUrl($resolved_image, $this->model_tool_image, 128, 128);
					} else {
						$resolved = ltrim((string)$resolved_image, '/');

						if (strpos($resolved, 'catalog/view/theme/') === 0) {
							$image = '/' . $resolved;
						} elseif ($resolved !== '' && is_file(DIR_IMAGE . $resolved)) {
							$image = $this->model_tool_image->resize($resolved, 128, 128);
						} else {
							$image = '/' . $resolved;
						}
					}
				}
			}
		}

		if ($image === '' && $product_id) {
			if (!isset($this->model_catalog_product)) {
				$this->load->model('catalog/product');
			}

			$product_info = $this->model_catalog_product->getProduct($product_id);

			if (!empty($product_info['image'])) {
				$image = $this->model_tool_image->resize($product_info['image'], 128, 128);
			}
		}

		if ($image !== '' && strpos($image, 'http') !== 0 && strpos($image, '//') !== 0) {
			$image = rtrim((string)$store_url, '/') . '/' . ltrim($image, '/');
		}

		return $image;
	}

	/**
	 * Product list block for emails: thumb + name + options + qty.
	 */
	private function renderOrderProducts(array $data) {
		$products = isset($data['products']) && is_array($data['products']) ? $data['products'] : array();

		if (!$products) {
			return '';
		}

		$store_url = isset($data['store_url']) ? rtrim((string)$data['store_url'], '/') . '/' : HTTP_SERVER;
		$rows = '';

		foreach ($products as $product) {
			$name = isset($product['name']) ? htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') : '';
			$qty = isset($product['quantity']) ? (int)$product['quantity'] : 0;
			$image = '';

			if (!empty($product['product_id'])) {
				$image = $this->resolveOrderProductImage(
					(int)$product['product_id'],
					isset($product['option']) && is_array($product['option']) ? $product['option'] : array(),
					$store_url
				);
			}

			$option_lines = array();

			if (!empty($product['option']) && is_array($product['option'])) {
				foreach ($product['option'] as $option) {
					$option_lines[] = htmlspecialchars($option['name'], ENT_QUOTES, 'UTF-8') . ': '
						. htmlspecialchars($option['value'], ENT_QUOTES, 'UTF-8');
				}
			}

			$rows .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:16px;">';
			$rows .= '<tr>';
			$rows .= '<td width="72" valign="top" style="padding-right:16px;">';

			if ($image) {
				$rows .= '<img src="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '" width="64" height="64" alt="" style="width:64px;height:64px;display:block;" />';
			} else {
				$rows .= '<div style="width:64px;height:64px;background-color:#f5f5f5;font-size:0;line-height:0;">&nbsp;</div>';
			}

			$rows .= '</td>';
			$rows .= '<td valign="top" style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:22px;color:#000000;">';
			$rows .= '<strong style="font-weight:700;">' . $name . '</strong><br />';

			if ($option_lines) {
				$rows .= '<span style="font-size:14px;line-height:20px;color:#333333;">' . implode('<br />', $option_lines) . '</span><br />';
			}

			$rows .= '<span style="font-size:14px;line-height:20px;">Qty: ' . $qty . '</span>';
			$rows .= '</td></tr></table>';
		}

		return '<!-- Items in this shipment -->'
			. '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">'
			. '<tr>'
			. '<td align="left" class="email-pad" style="padding:0 40px 8px;font-family:Arial,Helvetica,sans-serif;font-size:18px;line-height:26px;font-weight:700;color:#000000;text-align:left;">'
			. 'Items in this shipment'
			. '</td>'
			. '</tr>'
			. '<tr>'
			. '<td align="left" class="email-pad" style="padding:0 40px 36px;font-family:Arial,Helvetica,sans-serif;color:#000000;text-align:left;">'
			. $rows
			. '</td>'
			. '</tr>'
			. '</table>';
	}
}

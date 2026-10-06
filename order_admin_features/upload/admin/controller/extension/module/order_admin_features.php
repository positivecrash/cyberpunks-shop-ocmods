<?php
class ControllerExtensionModuleOrderAdminFeatures extends Controller {
	public function order() {
		if (!isset($this->request->get['order_id'])) {
			return '';
		}

		$order_id = (int)$this->request->get['order_id'];

		if ($order_id < 1 || !$this->user->hasPermission('access', 'sale/order')) {
			return '';
		}

		$this->load->language('extension/module/order_admin_features');
		$this->load->model('extension/module/order_admin_features');
		$this->load->model('sale/order');

		$order_info = $this->model_sale_order->getOrder($order_id);

		if (!$order_info) {
			return '';
		}

		$this->model_extension_module_order_admin_features->ensureSchema();
		$this->model_extension_module_order_admin_features->ensurePermissions();

		$data['order_id'] = $order_id;
		$data['user_token'] = $this->session->data['user_token'];
		$data['can_modify'] = $this->user->hasPermission('modify', 'sale/order');
		$data['text_shipment_photos'] = $this->language->get('text_shipment_photos');
		$data['text_admin_only'] = $this->language->get('text_admin_only');
		$data['text_no_photos'] = $this->language->get('text_no_photos');
		$data['text_drop'] = $this->language->get('text_drop');
		$data['text_formats'] = $this->language->get('text_formats');
		$data['text_uploading'] = $this->language->get('text_uploading');
		$data['text_confirm_delete'] = $this->language->get('text_confirm_delete');
		$data['button_upload'] = $this->language->get('button_upload');
		$data['button_delete'] = $this->language->get('button_delete');
		$data['button_open'] = $this->language->get('button_open');
		$data['upload_url'] = str_replace('&amp;', '&', $this->url->link('extension/module/order_admin_features/upload', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id, true));
		$data['delete_url'] = str_replace('&amp;', '&', $this->url->link('extension/module/order_admin_features/delete', 'user_token=' . $this->session->data['user_token'], true));
		$data['photos'] = $this->buildPhotoRows($order_id);
		$data['js_upload_url'] = json_encode($data['upload_url']);
		$data['js_delete_url'] = json_encode($data['delete_url']);
		$data['js_can_modify'] = $data['can_modify'] ? 'true' : 'false';
		$data['js_confirm_delete'] = json_encode($data['text_confirm_delete']);
		$data['js_text_uploading'] = json_encode($data['text_uploading']);
		$data['js_text_no_photos'] = json_encode($data['text_no_photos']);
		$data['js_button_open'] = json_encode($data['button_open']);
		$data['js_button_delete'] = json_encode($data['button_delete']);
		$data['js_text_converting'] = json_encode($this->language->get('text_converting'));
		$data['js_error_heic_client'] = json_encode($this->language->get('error_heic_client'));
		$data['heic2any_url'] = 'view/javascript/order_admin_features/heic2any.min.js';
		$data['js_heic2any_url'] = json_encode($data['heic2any_url']);

		return $this->load->view('extension/module/order_admin_features_order', $data);
	}

	public function upload() {
		while (ob_get_level() > 0) {
			ob_end_clean();
		}

		$this->load->language('extension/module/order_admin_features');

		$json = array();

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
			return $this->jsonOut($json);
		}

		$order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;

		$this->load->model('sale/order');
		$this->load->model('extension/module/order_admin_features');

		$order_info = $this->model_sale_order->getOrder($order_id);

		if (!$order_info) {
			$json['error'] = $this->language->get('error_order');
			return $this->jsonOut($json);
		}

		if ($this->model_extension_module_order_admin_features->countPhotos($order_id) >= ModelExtensionModuleOrderAdminFeatures::MAX_PHOTOS_PER_ORDER) {
			$json['error'] = $this->language->get('error_limit');
			return $this->jsonOut($json);
		}

		if (!isset($this->request->files['file']) || !is_array($this->request->files['file'])) {
			$json['error'] = $this->language->get('error_upload');
			return $this->jsonOut($json);
		}

		$file = $this->request->files['file'];

		if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
			$json['error'] = $this->language->get('error_upload');
			return $this->jsonOut($json);
		}

		if (!empty($file['error'])) {
			$json['error'] = $this->language->get('error_upload');
			return $this->jsonOut($json);
		}

		$original_name = isset($file['name']) ? (string)$file['name'] : 'photo';
		$ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

		require_once(DIR_SYSTEM . 'library/order_admin_features_image.php');

		$looks_heic = in_array($ext, array('heic', 'heif'), true) || OrderAdminFeaturesImage::sniffHeic($file['tmp_name']);

		if ($ext === '' && $looks_heic) {
			$ext = 'heic';
			if (!preg_match('/\.(heic|heif)$/i', $original_name)) {
				$original_name .= '.heic';
			}
		}

		if (!in_array($ext, OrderAdminFeaturesImage::allowedExtensions(), true) && !$looks_heic) {
			$json['error'] = $this->language->get('error_file_type');
			return $this->jsonOut($json);
		}

		$dir = $this->model_extension_module_order_admin_features->getOrderDir($order_id);

		if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
			$json['error'] = $this->language->get('error_convert') . ' (Cannot create storage directory — check DIR_STORAGE permissions)';
			return $this->jsonOut($json);
		}

		$id = bin2hex(random_bytes(8));
		$filename = $id . '.webp';
		$thumb = 't_' . $id . '.webp';
		$dest = $dir . $filename;
		$thumb_dest = $dir . $thumb;

		$result = OrderAdminFeaturesImage::convertToWebp($file['tmp_name'], $dest, OrderAdminFeaturesImage::MAX_SIDE, OrderAdminFeaturesImage::WEBP_QUALITY, $original_name);

		if (empty($result['ok'])) {
			$error = isset($result['error']) ? $result['error'] : $this->language->get('error_convert');

			if (stripos($error, 'HEIC') !== false) {
				$json['error'] = $this->language->get('error_heic');
			} else {
				$json['error'] = $this->language->get('error_convert') . ' (' . $error . ')';
			}

			return $this->jsonOut($json);
		}

		$thumb_result = OrderAdminFeaturesImage::makeThumb($dest, $thumb_dest);

		if (empty($thumb_result['ok'])) {
			@unlink($dest);
			$thumb_error = isset($thumb_result['error']) ? $thumb_result['error'] : 'thumb failed';
			$json['error'] = $this->language->get('error_convert') . ' (' . $thumb_error . ')';
			return $this->jsonOut($json);
		}

		$photo_id = $this->model_extension_module_order_admin_features->addPhoto(
			$order_id,
			$filename,
			$thumb,
			$original_name,
			isset($result['filesize']) ? (int)$result['filesize'] : 0,
			isset($result['width']) ? (int)$result['width'] : 0,
			isset($result['height']) ? (int)$result['height'] : 0,
			(int)$this->user->getId()
		);

		$rows = $this->buildPhotoRows($order_id, $photo_id);
		$photo = $rows ? $rows[0] : null;

		$json['success'] = $this->language->get('success_upload');
		$json['photo'] = $photo;

		return $this->jsonOut($json);
	}

	public function delete() {
		$this->load->language('extension/module/order_admin_features');

		$json = array();

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
			return $this->jsonOut($json);
		}

		$photo_id = isset($this->request->post['photo_id']) ? (int)$this->request->post['photo_id'] : (isset($this->request->get['photo_id']) ? (int)$this->request->get['photo_id'] : 0);

		$this->load->model('extension/module/order_admin_features');

		$photo = $this->model_extension_module_order_admin_features->getPhoto($photo_id);

		if (!$photo) {
			$json['error'] = $this->language->get('error_photo');
			return $this->jsonOut($json);
		}

		$this->model_extension_module_order_admin_features->deletePhoto($photo_id);

		$json['success'] = $this->language->get('success_delete');
		$json['photo_id'] = $photo_id;

		return $this->jsonOut($json);
	}

	public function image() {
		if (!$this->user->hasPermission('access', 'sale/order')) {
			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 403 Forbidden');
			$this->response->setOutput('Forbidden');
			return;
		}

		$photo_id = isset($this->request->get['photo_id']) ? (int)$this->request->get['photo_id'] : 0;
		$thumb = !empty($this->request->get['thumb']);

		$this->load->model('extension/module/order_admin_features');

		$photo = $this->model_extension_module_order_admin_features->getPhoto($photo_id);

		if (!$photo) {
			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');
			$this->response->setOutput('Not found');
			return;
		}

		$name = $thumb ? $photo['thumb'] : $photo['filename'];
		$path = $this->model_extension_module_order_admin_features->absolutePath($photo['order_id'], $name);

		if ($path === '' || !is_file($path)) {
			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');
			$this->response->setOutput('Not found');
			return;
		}

		$this->response->addHeader('Content-Type: image/webp');
		$this->response->addHeader('Content-Length: ' . filesize($path));
		$this->response->addHeader('Cache-Control: private, max-age=86400');
		$this->response->setOutput(file_get_contents($path));
	}

	private function buildPhotoRows($order_id, $only_photo_id = 0) {
		$rows = array();
		$photos = $this->model_extension_module_order_admin_features->getPhotos($order_id);

		foreach ($photos as $photo) {
			if ($only_photo_id && (int)$photo['photo_id'] !== (int)$only_photo_id) {
				continue;
			}

			$rows[] = array(
				'photo_id'      => (int)$photo['photo_id'],
				'original_name' => $photo['original_name'],
				'width'         => (int)$photo['width'],
				'height'        => (int)$photo['height'],
				'filesize'      => (int)$photo['filesize'],
				'date_added'    => $photo['date_added'],
				'thumb_url'     => str_replace('&amp;', '&', $this->url->link('extension/module/order_admin_features/image', 'user_token=' . $this->session->data['user_token'] . '&photo_id=' . (int)$photo['photo_id'] . '&thumb=1', true)),
				'image_url'     => str_replace('&amp;', '&', $this->url->link('extension/module/order_admin_features/image', 'user_token=' . $this->session->data['user_token'] . '&photo_id=' . (int)$photo['photo_id'], true))
			);
		}

		return $rows;
	}

	private function jsonOut($json) {
		while (ob_get_level() > 0) {
			ob_end_clean();
		}

		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->setOutput(json_encode($json));
	}
}

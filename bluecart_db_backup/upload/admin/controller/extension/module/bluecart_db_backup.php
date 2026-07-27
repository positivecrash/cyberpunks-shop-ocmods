<?php
/*
 * =============================================================
 *  BlueCart DB Backup Manager – v1.0 
 *  © 2025 BlueCart.ro — eCommerce Software Development
 * =============================================================
 */

class ControllerExtensionModuleBluecartDbBackup extends Controller {
	private $error = [];

	// =========================================================
	// 🔹 Validare permisiuni (mutat sus – fix fatal error)
	// =========================================================
	private function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/bluecart_db_backup')) {
			$this->error['warning'] = '⚠️ Nu ai permisiuni pentru modificare!';
		}
		return !$this->error;
	}

	// =========================================================
	// 🔹 Pagina principală + salvare setări
	// =========================================================
	public function index() {
		$this->load->language('extension/module/bluecart_db_backup');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$checkboxes = [
				'module_bluecart_db_backup_status',
				'module_bluecart_db_backup_zip',
				'module_bluecart_db_backup_email_enabled',
				'module_bluecart_db_backup_email_attach',
				'module_bluecart_db_backup_notification',
				'module_bluecart_db_backup_ftp_status',
				'module_bluecart_db_backup_ftp_passive',
				'module_bluecart_db_backup_log'
			];

			foreach ($checkboxes as $chk) {
				if (!isset($this->request->post[$chk])) $this->request->post[$chk] = 0;
			}

			$this->model_setting_setting->editSetting('module_bluecart_db_backup', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link(
				'extension/module/bluecart_db_backup',
				'user_token=' . $this->session->data['user_token'],
				true
			));
		}

		// ✅ Mesaje o singură dată
		if (isset($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		} elseif (!empty($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		// Breadcrumbs
		$data['breadcrumbs'] = [
			[
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
			],
			[
				'text' => $this->language->get('text_extension'),
				'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
			],
			[
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/bluecart_db_backup', 'user_token=' . $this->session->data['user_token'], true)
			]
		];

		// Linkuri acțiuni
		$data['action'] = $this->url->link('extension/module/bluecart_db_backup', 'user_token=' . $this->session->data['user_token'], true);
		$data['backup_now'] = $this->url->link('extension/module/bluecart_db_backup/backupNow', 'user_token=' . $this->session->data['user_token'], true);
		$data['test_ftp'] = $this->url->link('extension/module/bluecart_db_backup/testFtpConnection', 'user_token=' . $this->session->data['user_token'], true);
		$data['test_email'] = $this->url->link('extension/module/bluecart_db_backup/testEmail', 'user_token=' . $this->session->data['user_token'], true);
		$data['delete_selected'] = $this->url->link('extension/module/bluecart_db_backup/deleteSelected', 'user_token=' . $this->session->data['user_token'], true);

		// Câmpuri
		$fields = [
			'status','keep_days','keep_count','frequency','time','day','zip',
			'email_enabled','email','email_subject','email_attach','notification',
			'ftp_status','ftp_host','ftp_port','ftp_user','ftp_pass','ftp_path','ftp_passive',
			'log'
		];

		foreach ($fields as $field) {
			$key = 'module_bluecart_db_backup_' . $field;
			$data[$key] = $this->request->post[$key] ?? $this->config->get($key);
		}
		
		// 🔹 Test temporar: forțăm ora pentru cron
        //$data['module_bluecart_db_backup_time'] = '23:00';


		// Fișiere existente
		$backup_dir = DIR_STORAGE . 'backup/';
		if (!is_dir($backup_dir)) mkdir($backup_dir, 0755, true);
		$data['backups'] = [];
		foreach (glob($backup_dir . '*.sql*') as $f) {
			$data['backups'][] = [
				'name' => basename($f),
				'size' => round(filesize($f) / 1024, 2) . ' KB',
				'date' => date('d.m.Y H:i:s', filemtime($f)),
				'download' => $this->url->link('extension/module/bluecart_db_backup/download', 'user_token=' . $this->session->data['user_token'] . '&file=' . basename($f), true),
				'delete' => $this->url->link('extension/module/bluecart_db_backup/delete', 'user_token=' . $this->session->data['user_token'] . '&file=' . basename($f), true)
			];
		}

$data['cron_line'] = "/usr/bin/php " . dirname(DIR_SYSTEM) . "/system/library/bluecart_backup_scheduler.php >/dev/null 2>&1";
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/module/bluecart_db_backup', $data));
	}

	// =========================================================
	// 🔹 Generare backup (cu fallback limbă pentru Force)
	// =========================================================
	public function backupNow() {
		header('Content-Type: application/json; charset=utf-8');
		// 🔧 Fallback pentru rulare prin cron (fără forțare GET)
       if (!isset($this->language) || !$this->language->get('text_email_subject_backup')) {
       $this->language = new Language('ro-ro');
       $this->language->load('extension/module/bluecart_db_backup');
		$response = ['success' => false, 'message' => ''];

		$backup_dir = DIR_STORAGE . 'backup/';
		if (!is_dir($backup_dir)) mkdir($backup_dir, 0755, true);
		$logfile = DIR_STORAGE . 'logs/bluecart_db_backup.log';
		$ftp_debug = DIR_STORAGE . 'logs/bluecart_ftp_debug.log';
		$do_log = (bool)$this->config->get('module_bluecart_db_backup_log');

		try {
			$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
			$db->set_charset("utf8mb4");
			$db_name = DB_DATABASE;
			$filename = 'backup_' . $db_name . '_' . date('d.m.Y_H-i-s') . '.sql';
			$filepath = $backup_dir . $filename;

			$sql = "-- BlueCart Backup\n-- DB: {$db_name}\n-- Date: " . date('d.m.Y H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
			$tables = $db->query("SHOW TABLES");
			while ($row = $tables->fetch_row()) {
				$t = $row[0];
				$create = $db->query("SHOW CREATE TABLE `$t`")->fetch_assoc();
				$sql .= "DROP TABLE IF EXISTS `$t`;\n" . $create['Create Table'] . ";\n\n";
				$rows = $db->query("SELECT * FROM `$t`");
				while ($r = $rows->fetch_assoc()) {
    $vals = [];
    foreach ($r as $v) {
        if (is_null($v)) {
            $vals[] = 'NULL';
        } elseif (is_numeric($v)) {
            $vals[] = $v;
        } else {
            $vals[] = "'" . $db->real_escape_string($v) . "'";
        }
    }
    $sql .= "INSERT INTO `$t` VALUES (" . implode(',', $vals) . ");\n";
}

				$sql .= "\n";
			}
			file_put_contents($filepath, $sql);
			if ($do_log) file_put_contents($logfile, "[OK] SQL file $filename\n", FILE_APPEND);

			// ZIP opțional
			if ($this->config->get('module_bluecart_db_backup_zip')) {
				$zip = new ZipArchive();
				if ($zip->open($filepath . '.zip', ZipArchive::CREATE) === TRUE) {
					$zip->addFile($filepath, basename($filepath));
					$zip->close();
					unlink($filepath);
					$filepath .= '.zip';
					if ($do_log) file_put_contents($logfile, "[OK] ZIP created: $filepath\n", FILE_APPEND);
				}
			}

			// Email
if ($this->config->get('module_bluecart_db_backup_email_enabled')) {
    $to = $this->config->get('module_bluecart_db_backup_email');
    if ($to) {

        // ✅ Asigură limba activă din admin (nu doar ro-ro)
        if (!isset($this->language) || !$this->language->get('text_email_subject_backup')) {
            $admin_lang = $this->config->get('config_admin_language') ?: 'en-gb';
            $this->language = new Language($admin_lang);
            $this->language->load('extension/module/bluecart_db_backup');
        }

        $mail = new Mail($this->config->get('config_mail_engine'));
        $mail->parameter = $this->config->get('config_mail_parameter');
        $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
        $mail->smtp_username = $this->config->get('config_mail_smtp_username');
        $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
        $mail->smtp_port = $this->config->get('config_mail_smtp_port');
        $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');
        $mail->setTo($to);
        $mail->setFrom($this->config->get('config_email'));
        $mail->setSender($this->config->get('config_name'));

        $subject = sprintf($this->language->get('text_email_subject_backup'), $this->config->get('config_name'));
        $domain = defined('HTTP_CATALOG') ? HTTP_CATALOG : (defined('HTTPS_CATALOG') ? HTTPS_CATALOG : HTTP_SERVER);
        $body = sprintf($this->language->get('text_email_message_backup'), $domain, DB_DATABASE, basename($filepath), round(filesize($filepath)/1024,2), date('d.m.Y H:i:s'));

        $mail->setSubject($subject);
        $mail->setText($body);
        if ($this->config->get('module_bluecart_db_backup_email_attach')) {
            $mail->addAttachment($filepath);
        }
        $mail->send();
        if ($do_log) file_put_contents($logfile, "[OK] Email sent to $to\n", FILE_APPEND);
    }
}


			// FTP upload
			if ($this->config->get('module_bluecart_db_backup_ftp_status')) {
				$ftp_host = preg_replace('#^ftp://#i', '', trim($this->config->get('module_bluecart_db_backup_ftp_host')));
				$ftp_port = (int)($this->config->get('module_bluecart_db_backup_ftp_port') ?: 21);
				$ftp_user = $this->config->get('module_bluecart_db_backup_ftp_user');
				$ftp_pass = $this->config->get('module_bluecart_db_backup_ftp_pass');
				$ftp_path = $this->config->get('module_bluecart_db_backup_ftp_path') ?: '/';
				$passive  = (bool)$this->config->get('module_bluecart_db_backup_ftp_passive');

				$ftp = @ftp_connect($ftp_host, $ftp_port, 15);
				if ($ftp && @ftp_login($ftp, $ftp_user, $ftp_pass)) {
					ftp_pasv($ftp, $passive);
					$remote = rtrim($ftp_path, '/') . '/' . basename($filepath);

					$list = @ftp_nlist($ftp, $ftp_path);
					$exists = $list && in_array($remote, $list);
					if ($exists) {
						@ftp_delete($ftp, $remote);
						if ($do_log) file_put_contents($ftp_debug, "[FORCE] Existing file deleted: $remote\n", FILE_APPEND);
					}

					$upload = @ftp_put($ftp, $remote, $filepath, FTP_BINARY);
					if (!$upload) {
						if ($do_log) file_put_contents($ftp_debug, "[WARN] FTP upload failed, retrying...\n", FILE_APPEND);
						sleep(1);
						$reconnect = @ftp_connect($ftp_host, $ftp_port, 15);
						if ($reconnect && @ftp_login($reconnect, $ftp_user, $ftp_pass)) {
							ftp_pasv($reconnect, $passive);
							@ftp_put($reconnect, $remote, $filepath, FTP_BINARY);
							ftp_close($reconnect);
						}
					} else {
						if ($do_log) file_put_contents($ftp_debug, "[OK] FTP upload successful: $remote\n", FILE_APPEND);
					}
					ftp_close($ftp);
				} else {
					if ($do_log) file_put_contents($ftp_debug, "[ERR] FTP login/connect failed for {$ftp_host}:{$ftp_port}\n", FILE_APPEND);
				}
			}

			$response['success'] = true;
			$response['message'] = $this->language->get('text_backup_success');

		} catch (Throwable $e) {
			$response['success'] = false;
			$response['message'] = '❌ ' . $e->getMessage();
			if ($do_log) file_put_contents($logfile, "[ERR] " . $e->getMessage() . "\n", FILE_APPEND);
		}
		if (ob_get_length()) ob_clean();
		echo json_encode($response);
		exit;
	}
	}
	// =========================================================
	// 🔹 Test FTP
	// =========================================================
	public function testFtpConnection() {
		$this->load->language('extension/module/bluecart_db_backup');
		$host = preg_replace('#^ftp://#i', '', trim($this->config->get('module_bluecart_db_backup_ftp_host')));
		$port = (int)($this->config->get('module_bluecart_db_backup_ftp_port') ?: 21);
		$user = $this->config->get('module_bluecart_db_backup_ftp_user');
		$pass = $this->config->get('module_bluecart_db_backup_ftp_pass');

		if (empty($host) || empty($user) || empty($pass)) {
			$this->session->data['error_warning'] = $this->language->get('error_ftp_host_missing');
		} else {
			try {
				$conn = @ftp_connect($host, $port, 10);
				if (!$conn) throw new Exception(sprintf($this->language->get('error_ftp_connect'), $host, $port));
				if (!@ftp_login($conn, $user, $pass)) throw new Exception(sprintf($this->language->get('error_ftp_login'), $user));
				ftp_close($conn);
				$this->session->data['success'] = sprintf($this->language->get('text_ftp_success'), $host);
			} catch (Exception $e) {
				$this->session->data['error_warning'] = '❌ ' . $e->getMessage();
			}
		}

		$this->response->redirect($this->url->link('extension/module/bluecart_db_backup', 'user_token=' . $this->session->data['user_token'], true));
	}

	// =========================================================
	// 🔹 Test Email
	// =========================================================

	public function testEmail() {
		$this->load->language('extension/module/bluecart_db_backup');
		$to = trim($this->config->get('module_bluecart_db_backup_email'));

		if (empty($to)) {
			$this->session->data['error_warning'] = $this->language->get('error_email_not_set');
		} elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
			$this->session->data['error_warning'] = sprintf($this->language->get('error_email_invalid'), $to);
		} else {
			try {
				$mail = new Mail($this->config->get('config_mail_engine'));
				$mail->parameter = $this->config->get('config_mail_parameter');
				$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
				$mail->smtp_username = $this->config->get('config_mail_smtp_username');
				$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
				$mail->smtp_port = $this->config->get('config_mail_smtp_port');
				$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');
				$mail->setTo($to);
				$mail->setFrom($this->config->get('config_email'));
				$mail->setSender($this->config->get('config_name'));
				$mail->setSubject('Test BlueCart Backup');
				$mail->setText($this->language->get('text_email_test_body'));
				$mail->send();
				$this->session->data['success'] = sprintf($this->language->get('text_email_success'), $to);
			} catch (Exception $e) {
				$this->session->data['error_warning'] = '❌ ' . $e->getMessage();
			}
		}

		$this->response->redirect($this->url->link('extension/module/bluecart_db_backup', 'user_token=' . $this->session->data['user_token'], true));
	}

	// =========================================================
	// 🔹 Delete / Validate
	// =========================================================
	public function delete() {
		$this->load->language('extension/module/bluecart_db_backup');
		if (!$this->user->hasPermission('modify', 'extension/module/bluecart_db_backup')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission_delete');
			$this->response->redirect($this->url->link('extension/module/bluecart_db_backup', 'user_token=' . $this->session->data['user_token'], true));
		}
		$f = basename($this->request->get['file'] ?? '');
		$p = DIR_STORAGE . 'backup/' . $f;
		if ($f && file_exists($p)) {
			unlink($p);
			$this->session->data['success'] = sprintf($this->language->get('text_backup_deleted'), $f);
		} else {
			$this->session->data['error_warning'] = $this->language->get('error_file_not_found');
		}
		$this->response->redirect($this->url->link('extension/module/bluecart_db_backup', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function deleteSelected() {
    $this->load->language('extension/module/bluecart_db_backup');

    if (!$this->user->hasPermission('modify', 'extension/module/bluecart_db_backup')) {
        $this->session->data['error_warning'] = $this->language->get('error_permission_delete');
        $this->response->redirect($this->url->link('extension/module/bluecart_db_backup', 'user_token=' . $this->session->data['user_token'], true));
    }

    $selected = $this->request->post['selected'] ?? [];
    $deleted = 0;
    foreach ($selected as $file) {
        $f = basename($file);
        $p = DIR_STORAGE . 'backup/' . $f;
        if (file_exists($p)) { unlink($p); $deleted++; }
    }

    $this->session->data['success'] = sprintf($this->language->get('text_bulk_deleted'), $deleted);
    $this->response->redirect($this->url->link('extension/module/bluecart_db_backup', 'user_token=' . $this->session->data['user_token'], true));
}

public function download() {
    $this->load->language('extension/module/bluecart_db_backup');

    if (!$this->user->hasPermission('modify', 'extension/module/bluecart_db_backup')) {
        $this->session->data['error_warning'] = $this->language->get('error_permission_download');
        $this->response->redirect($this->url->link('extension/module/bluecart_db_backup', 'user_token=' . $this->session->data['user_token'], true));
    }

    $file = basename($this->request->get['file'] ?? '');
    $path = DIR_STORAGE . 'backup/' . $file;

    if (!$file || !is_file($path)) {
        $this->session->data['error_warning'] = $this->language->get('error_file_not_found');
        $this->response->redirect($this->url->link('extension/module/bluecart_db_backup', 'user_token=' . $this->session->data['user_token'], true));
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}


}

<?php
/*
 * =============================================================
 *  BlueCart Backup Scheduler – v1.0
 *  © 2025 BlueCart.ro — eCommerce Software Development
 * =============================================================
 */

if (!defined('VERSION')) define('VERSION','3.0.3.6');
error_reporting(E_ALL);
ini_set('display_errors',1);

require_once(dirname(__DIR__,2).'/config.php');
require_once(DIR_SYSTEM.'startup.php');

$storage  = defined('DIR_STORAGE') ? DIR_STORAGE : dirname(DIR_SYSTEM).'/storage/';
$logfile  = $storage.'logs/bluecart_scheduler.log';
$ftplog   = $storage.'logs/bluecart_ftp_debug.log';
$lockfile = $storage.'logs/bluecart_backup.lock';

$registry = new Registry();
$config   = new Config();
$registry->set('config',$config);

// =====================================================
// ✅ Logging control based on admin setting
// =====================================================
$debug_enabled = false;
try {
	$db_tmp = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
	$q = $db_tmp->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `key`='module_bluecart_db_backup_log' LIMIT 1");
	if ($q->num_rows && (int)$q->row['value'] === 1) {
		$debug_enabled = true;
	} else {
		echo "[BlueCart Backup] Silent mode active — no logs will be written.\n";
	}
	unset($db_tmp);
} catch (Throwable $e) {
	$debug_enabled = true; // fallback if DB not ready
	echo "[BlueCart Backup] ⚠️ Could not read log setting, fallback to verbose mode.\n";
}

// helper
function logLine($msg) {
	global $logfile, $debug_enabled;
	if (!$debug_enabled) return;
	file_put_contents($logfile, '['.date('Y-m-d H:i:s').'] '.$msg."\n", FILE_APPEND);
}

// =====================================================
// 🔒 Lock protection (prevents overlapping runs)
// =====================================================
$is_force = (
	(isset($_GET['force']) && $_GET['force']) ||
	(isset($argv) && is_array($argv) && in_array('--force', $argv))
);

if (file_exists($lockfile)) {
	$age = time() - filemtime($lockfile);

	// dacă nu e FORCE → respectă lock-ul 5 minute
	if (!$is_force && $age < 300) {
		echo "[BlueCart Backup] 🔒 Lock active, skipping ({$age}s old).\n";
		logLine("Lock active, skipped execution ({$age}s old).");
		exit;
	}

	// dacă e FORCE, dar lock-ul e recent (<120s) → prevenim coliziune
	if ($is_force && $age < 120) {
		echo "[BlueCart Backup] ⚠️ Lock active (".$age."s old), skipping force to prevent overlap.\n";
		logLine("Force skipped — active lock detected ({$age}s old).");
		exit;
	}
}
touch($lockfile);

logLine("==========================");
logLine("Starting BlueCart Backup Scheduler v2.1.3 (EN)");

// =====================================================
// 🕒 Smart frequency + time + FORCE mode
// =====================================================
try {
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
	$registry->set('db',$db);

	$r = $db->query("SELECT `key`,`value` FROM `".DB_PREFIX."setting` WHERE `store_id`=0");
	foreach($r->rows as $s) $config->set($s['key'],$s['value']);
	$m = $db->query("SELECT `key`,`value` FROM `".DB_PREFIX."setting` WHERE `key` LIKE 'module_bluecart_db_backup_%'");
	foreach($m->rows as $s) $config->set($s['key'],$s['value']);

	$status      = (int)$config->get('module_bluecart_db_backup_status');
	$frequency   = strtolower($config->get('module_bluecart_db_backup_frequency') ?? 'daily');
	$backup_time = $config->get('module_bluecart_db_backup_time') ?? '03:00';
	$backup_day  = strtolower($config->get('module_bluecart_db_backup_day') ?? 'monday');
	$now_time    = date('H:i');
	$weekday     = strtolower(date('l'));
	$day_num     = date('d');

	if (!$status && !$is_force) {
		logLine("🚫 Module disabled, exiting.");
		if (file_exists($lockfile)) unlink($lockfile);
		exit;
	}

	$should_run = false;
	switch ($frequency) {
		case 'daily':
			$should_run = ($now_time === $backup_time);
			break;
		case 'weekly':
			$should_run = ($weekday === $backup_day && $now_time === $backup_time);
			break;
		case 'monthly':
			$should_run = ($day_num === '01' && $now_time === $backup_time);
			break;
	}

	if ($is_force) {
		$should_run = true;
		logLine("⚙️ FORCE mode enabled — manual execution detected.");
		echo "[BlueCart Backup] ⚙️ FORCE mode active — executing immediately.\n";
	}

	if (!$should_run) {
		logLine("🕒 Not scheduled time ($now_time) → expected {$backup_time} [{$frequency}]. Skipping backup.");
		if (file_exists($lockfile)) unlink($lockfile);
		exit;
	}

// =====================================================
// 💾 Backup process
// =====================================================
	$backup_dir = $storage.'backup/';
	if (!is_dir($backup_dir)) mkdir($backup_dir,0755,true);
	$dbn = DB_DATABASE;
	$file = 'backup_'.$dbn.'_'.date('d.m.Y_H-i-s').'.sql';
	$path = $backup_dir.$file;

	$conn = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, $dbn);
	if ($conn->connect_errno) throw new Exception($conn->connect_error);
	$conn->set_charset('utf8mb4');

	$sql = "-- BlueCart Backup\n-- DB: {$dbn}\n-- Date: ".date('d.m.Y H:i:s')."\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
	$tabs = $conn->query("SHOW TABLES");
	while ($t = $tabs->fetch_row()) {
		$n = $t[0];
		$c = $conn->query("SHOW CREATE TABLE `$n`")->fetch_assoc();
		$sql .= "DROP TABLE IF EXISTS `$n`;\n".$c['Create Table'].";\n\n";
		$d = $conn->query("SELECT * FROM `$n`");
		while ($r = $d->fetch_assoc()) {
			$vals = [];

foreach ($r as $value) {
    if ($value === null) {
        $vals[] = 'NULL';
    } else {
        $vals[] = "'" . $conn->real_escape_string((string)$value) . "'";
    }
}

$sql .= "INSERT INTO `$n` VALUES(" . implode(",", $vals) . ");\n";
		}
		$sql .= "\n";
	}
	file_put_contents($path,$sql);
	$conn->close();
	logLine("SQL saved: ".basename($path));

	if ($config->get('module_bluecart_db_backup_zip')) {
		$z = new ZipArchive();
		if ($z->open($path.'.zip',ZipArchive::CREATE)===TRUE){
			$z->addFile($path,basename($path));
			$z->close(); unlink($path);
			$path .= '.zip';
			logLine("ZIP created: ".basename($path));
		}
	}

	// === Email ===
	if ($config->get('module_bluecart_db_backup_email_enabled')) {
		require_once(DIR_SYSTEM.'library/mail.php');
		$to = $config->get('module_bluecart_db_backup_email');
		if (filter_var($to,FILTER_VALIDATE_EMAIL)) {
			$mail = new Mail($config->get('config_mail_engine') ?: 'mail');
			$mail->parameter     = $config->get('config_mail_parameter');
			$mail->smtp_hostname = $config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($config->get('config_mail_smtp_password'),ENT_QUOTES,'UTF-8');
			$mail->smtp_port     = $config->get('config_mail_smtp_port');
			$mail->smtp_timeout  = $config->get('config_mail_smtp_timeout');
			$mail->setTo($to);
			$mail->setFrom($config->get('config_email'));
			$mail->setSender($config->get('config_name') ?: 'BlueCart Backup');

			$subject = "Database Backup Completed";
			$body = "The database backup process has been successfully completed.\n\n".
			        "Details:\n".
			        "- File: ".basename($path)."\n".
			        "- Date: ".date('d.m.Y H:i:s')."\n\n".
			        "The backup file is available in the administration panel, here attached or in the configured FTP folder.\n\n".
			        "Kind regards,\n".
			        "BlueCart Team\n".
			        "© 2025 BlueCart — eCommerce Software Development";

			$mail->setSubject($subject);
			$mail->setText($body);
			if ($config->get('module_bluecart_db_backup_email_attach')) $mail->addAttachment($path);
			$mail->send();
			logLine("Email sent to {$to}");
		} else logLine("Invalid email: {$to}");
	}

	// === FTP ===
	if ($config->get('module_bluecart_db_backup_ftp_status')) {
		$h = preg_replace('#^ftp://#','',$config->get('module_bluecart_db_backup_ftp_host'));
		$p = (int)($config->get('module_bluecart_db_backup_ftp_port') ?: 21);
		$u = $config->get('module_bluecart_db_backup_ftp_user');
		$pw= $config->get('module_bluecart_db_backup_ftp_pass');
		$fp= $config->get('module_bluecart_db_backup_ftp_path') ?: '/';
		$pasv = (bool)$config->get('module_bluecart_db_backup_ftp_passive');

		logLine("Connecting to FTP {$h}:{$p}");
		$f = @ftp_connect($h,$p,15);
		if ($f && @ftp_login($f,$u,$pw)) {
			ftp_pasv($f,$pasv);
			$remote = rtrim($fp,'/').'/'.basename($path);
			if (@ftp_put($f,$remote,$path,FTP_BINARY)) {
				logLine("FTP upload OK: {$remote}");
				if ($debug_enabled) file_put_contents($ftplog,"[OK] {$remote}\n",FILE_APPEND);
			} else {
				logLine("FTP upload failed.");
				if ($debug_enabled) file_put_contents($ftplog,"[ERR] Upload fail: {$remote}\n",FILE_APPEND);
			}
			ftp_close($f);
		} else {
			logLine("FTP connection failed for {$h}:{$p}");
			if ($debug_enabled) file_put_contents($ftplog,"[ERR] FTP connect/login fail\n",FILE_APPEND);
		}
	} else logLine("FTP upload disabled.");

	logLine("✅ Backup + Email + FTP finished successfully.");
	echo "[BlueCart Backup] ✅ Process finished successfully.\n";

} catch (Throwable $e) {
	logLine("❌ Exception: ".$e->getMessage());
	echo "[BlueCart Backup] ❌ Exception: ".$e->getMessage()."\n";
}

// cleanup lock
if (file_exists($lockfile)) unlink($lockfile);
logLine("==========================");

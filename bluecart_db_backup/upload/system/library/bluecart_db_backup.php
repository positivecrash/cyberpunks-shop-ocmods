<?php
/*
 * =============================================================
 *  BlueCart Cron Engine – Secure DB Backup
 *  Version: 1.0 (PHP 7.3 Compatible + Execution Timer)
 *  © 2025 BlueCart.ro — eCommerce Software Development
 * =============================================================
 */

// 1️⃣  Detectăm și includem config-ul OpenCart
$root_path = dirname(__DIR__, 2);
$config_files = [
    $root_path . '/config.php',
    $root_path . '/admin/config.php'
];
foreach ($config_files as $conf) {
    if (file_exists($conf)) {
        require_once($conf);
        break;
    }
}

// 2️⃣  Verificăm calea spre storage
if (!defined('DIR_STORAGE') || !is_dir(DIR_STORAGE)) {
    die("[BlueCart Backup] ❌ Eroare: DIR_STORAGE nu este definit corect.\n");
}

// 3️⃣  Setăm timezone
try {
    $db = new \DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
    $tz = $db->query("SELECT value FROM " . DB_PREFIX . "setting WHERE `key` = 'config_timezone'");
    date_default_timezone_set(!empty($tz->row['value']) ? $tz->row['value'] : 'Europe/Bucharest');
} catch (Throwable $e) {
    date_default_timezone_set('Europe/Bucharest');
}

// 4️⃣  Inițializăm logurile
$logfile    = DIR_STORAGE . 'logs/bluecart_db_backup.log';
$backup_dir = DIR_STORAGE . 'backup/';
if (!is_dir($backup_dir)) mkdir($backup_dir, 0755, true);

$timestamp = date('Y-m-d H:i:s');
$log = "===========================\n";
$log .= "[{$timestamp}] BlueCart Backup Job Started\n";

// Pornim cronometrul ⏱️
$start_time = microtime(true);

try {
    // 5️⃣  Citim setările cache (generate de scheduler)
    $settings_file = DIR_STORAGE . 'cache/bluecart_backup_settings.json';
    $settings = file_exists($settings_file) ? json_decode(file_get_contents($settings_file), true) : [];

    $db_host = DB_HOSTNAME;
    $db_user = DB_USERNAME;
    $db_pass = DB_PASSWORD;
    $db_name = DB_DATABASE;

    $filename = 'backup_' . $db_name . '_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backup_dir . $filename;

    // 6️⃣  Generăm SQL dump prin mysqli (fallback sigur)
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($mysqli->connect_error) {
        throw new Exception('Conectare eșuată: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');

    $sql  = "-- BlueCart Backup\n-- Database: {$db_name}\n-- Date: {$timestamp}\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tables = [];
    $res = $mysqli->query("SHOW TABLES");
    while ($row = $res->fetch_row()) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        $create = $mysqli->query("SHOW CREATE TABLE `{$table}`")->fetch_assoc();
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n" . $create['Create Table'] . ";\n\n";

        $rows = $mysqli->query("SELECT * FROM `{$table}`");
        while ($row = $rows->fetch_assoc()) {
            $vals = array_map([$mysqli, 'real_escape_string'], array_values($row));
            $sql .= "INSERT INTO `{$table}` VALUES ('" . implode("','", $vals) . "');\n";
        }
        $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    file_put_contents($filepath, $sql);
    $log .= "✅ SQL dump created: {$filename}\n";

    // 7️⃣  Compresie ZIP
    if (!empty($settings['zip']) && (int)$settings['zip'] === 1) {
        $zip = new ZipArchive();
        $zipname = $filepath . '.zip';
        if ($zip->open($zipname, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($filepath, basename($filepath));
            $zip->close();
            unlink($filepath);
            $filepath = $zipname;
            $log .= "🗜️ ZIP created: " . basename($filepath) . "\n";
        }
    }

    // 8️⃣  Curățare backupuri vechi
    $keep_days  = !empty($settings['keep_days']) ? (int)$settings['keep_days'] : 7;
    $keep_count = !empty($settings['keep_count']) ? (int)$settings['keep_count'] : 5;

    foreach (glob($backup_dir . 'backup_*.sql*') as $f) {
        if (filemtime($f) < time() - ($keep_days * 86400)) {
            unlink($f);
            $log .= "🧹 Deleted old backup (by date): " . basename($f) . "\n";
        }
    }

    $files = glob($backup_dir . 'backup_*.sql*');
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    if (count($files) > $keep_count) {
        foreach (array_slice($files, $keep_count) as $old) {
            unlink($old);
            $log .= "🗑️ Deleted old backup (max count reached): " . basename($old) . "\n";
        }
    }

    // 9️⃣  Trimitere e-mail (opțional)
    if (!empty($settings['email_enabled']) && $settings['email_enabled'] == 1 && !empty($settings['email'])) {
        $to       = $settings['email'];
        $subject  = !empty($settings['email_subject']) ? $settings['email_subject'] : "BlueCart DB Backup - {$db_name}";
        $message  = "✅ Backup realizat cu succes la {$timestamp}\n";
        $message .= "Fișier: " . basename($filepath) . "\n";
        $message .= "Dimensiune: " . round(filesize($filepath)/1024, 2) . " KB\n";

        $headers = "From: backup@" . ($_SERVER['SERVER_NAME'] ?? 'bluecart.ro');

        if (!empty($settings['email_attach']) && $settings['email_attach'] == 1) {
            $boundary = md5(time());
            $headers .= "\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"{$boundary}\"";

            $attachment = chunk_split(base64_encode(file_get_contents($filepath)));
            $body  = "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n{$message}\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: application/octet-stream; name=\"" . basename($filepath) . "\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"" . basename($filepath) . "\"\r\n\r\n";
            $body .= "{$attachment}\r\n--{$boundary}--";

            mail($to, $subject, $body, $headers);
            $log .= "📧 Email sent with attachment to {$to}\n";
        } else {
            mail($to, $subject, $message, $headers);
            $log .= "📨 Email sent to {$to}\n";
        }
    } else {
        $log .= "📭 Email notifications disabled.\n";
    }

    // 🔟 Upload FTP (opțional)
    if (!empty($settings['ftp_status']) && $settings['ftp_status'] == 1 && !empty($settings['ftp_host'])) {
        $ftp_host = preg_replace('#^ftp://#i', '', $settings['ftp_host']);
        $ftp_port = !empty($settings['ftp_port']) ? (int)$settings['ftp_port'] : 21;
        $ftp_user = $settings['ftp_user'];
        $ftp_pass = $settings['ftp_pass'];
        $ftp_path = rtrim($settings['ftp_path'], '/') . '/';
        $passive  = !empty($settings['ftp_passive']) ? (bool)$settings['ftp_passive'] : true;

        $conn = @ftp_connect($ftp_host, $ftp_port, 15);
        if ($conn && @ftp_login($conn, $ftp_user, $ftp_pass)) {
            ftp_pasv($conn, $passive);
            if (@ftp_put($conn, $ftp_path . basename($filepath), $filepath, FTP_BINARY)) {
                $log .= "📤 FTP upload success → {$ftp_host}{$ftp_path}\n";
            } else {
                $log .= "⚠️ FTP upload failed → {$ftp_host}{$ftp_path}\n";
            }
            ftp_close($conn);
        } else {
            $log .= "❌ FTP connection/login failed ({$ftp_host})\n";
        }
    } else {
        $log .= "🌐 FTP upload disabled.\n";
    }

    // ✅ Calculăm durata execuției
    $duration = round(microtime(true) - $start_time, 2);
    $log .= "✅ Backup job finished successfully.\n";
    $log .= "⏱️  Duration: {$duration} seconds\n";

} catch (Exception $e) {
    $log .= "❌ Exception: " . $e->getMessage() . "\n";
}

$log .= "===========================\n\n";
file_put_contents($logfile, $log, FILE_APPEND);
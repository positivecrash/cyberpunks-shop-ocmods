<?php
/*
 * =============================================================
 *  BlueCart DB Backup Manager - Versiunea Română (RO)
 *  © 2025 BlueCart.ro — eCommerce Software Development
 * =============================================================
 */

$_['heading_title']          = 'Backup Baze de Date BlueCart';
$_['text_edit']              = 'Setări backup automat';
$_['text_success']           = 'Setările au fost salvate cu succes!';
$_['text_home']              = 'Acasă';
$_['text_extension']         = 'Module';
$_['text_frequency_daily']   = 'Zilnic';
$_['text_frequency_weekly']  = 'Săptămânal';
$_['text_frequency_monthly'] = 'Lunar';
$_['text_no_backups']        = 'Nu există backup-uri disponibile.';
$_['text_backup_list']       = '📦 Lista Backup-urilor Existente';
$_['text_yes']               = 'activat';
$_['text_no']                = 'dezactivat';

$_['text_cron_title']     = '⏰ Linie CRON';
$_['text_cron_info']      = 'Rulează următoarea comandă în CRON-ul serverului pentru execuție automată:';
$_['text_file']           = 'Fișier';
$_['text_size']           = 'Dimensiune';
$_['text_date']           = 'Dată';
$_['text_actions']        = 'Acțiuni';
$_['button_copy_cron']    = 'Copiază';
$_['text_copied']         = 'Copiat!';
$_['entry_keep_count']    = 'Păstrează ultimele N backup-uri';
$_['help_keep_count']     = 'Dacă acest număr este depășit, cele mai vechi backup-uri vor fi șterse automat.';
$_['help_cron_hint']      = 'Recomandare: setează CRON-ul serverului să ruleze la fiecare cinci minute (*/5 * * * *). Modulul va declanșa automat backup-ul la ora configurată.';
$_['text_confirm_delete'] = 'Ești sigur că vrei să ștergi?';

$_['entry_day'] = 'Ziua programată pentru backup';
$_['tooltip_day'] = 'Selectează ziua săptămânii când va rula backup-ul automat.';
$_['text_monday'] = 'Luni';
$_['text_tuesday'] = 'Marți';
$_['text_wednesday'] = 'Miercuri';
$_['text_thursday'] = 'Joi';
$_['text_friday'] = 'Vineri';
$_['text_saturday'] = 'Sâmbătă';
$_['text_sunday'] = 'Duminică';

// Buton FTP
$_['button_test_ftp'] = 'Testează Conexiunea FTP';

// Mesaje FTP
$_['text_ftp_success'] = 'Conexiunea FTP a fost testată cu succes!';
$_['error_ftp_host_missing'] = 'Adresa serverului FTP nu este setată în configurare.';
$_['error_ftp_connect'] = 'Conexiunea FTP a eșuat (%s:%s). Verifică dacă adresa este corectă.';
$_['error_ftp_login'] = 'Autentificarea FTP a eșuat pentru utilizatorul %s.';
$_['entry_ftp_passive'] = 'Mod pasiv';
$_['help_ftp_passive']  = 'Activează această opțiune dacă serverul FTP necesită conexiuni pasive. Recomandat pentru majoritatea serverelor.';
$_['entry_ftp_path'] = 'Calea FTP';
$_['tooltip_ftp_path'] = 'Directorul unde vor fi stocate fișierele de backup.';
$_['help_ftp_digi'] = 'Pentru utilizatorii <b>Digi Storage</b>: setează calea FTP la <b>/Digi Cloud/backup</b> și activează modul pasiv.';
$_['text_ftp_path_warning'] = 'Se pare că folosești Digi Storage, dar calea nu conține <b>/Digi Cloud/backup</b>. Corectează pentru a evita erorile de încărcare.';
$_['entry_log_enabled'] = 'Activează jurnalul de activitate (mod debug)';
$_['help_log_enabled']  = 'Dacă este activat, toate acțiunile modulului (backup, email, FTP, cron) vor fi înregistrate în fișierul bluecart_db_backup.log.';

// --- EMAIL ---
$_['button_test_email'] = 'Testează Trimiterea Emailului';
$_['text_email_subject_default'] = 'BlueCart Backup - Email de test';
$_['text_email_intro'] = 'Acesta este un email de test trimis automat din modulul BlueCart DB Backup.';
$_['text_email_date'] = 'Dată:';
$_['text_email_domain'] = 'Domeniu:';
$_['text_email_attached'] = 'Fișier de backup atașat: %s';
$_['text_email_no_attach'] = 'Niciun fișier atașat (opțiunea este dezactivată sau nu există backup-uri).';
$_['text_email_success'] = 'Emailul de test a fost trimis cu succes către %s.';
$_['text_email_with_attach'] = 'Emailul de test a fost trimis cu succes către %s cu fișierul de backup atașat.';
$_['error_email_not_set'] = 'Adresa de email nu este configurată.';
$_['text_email_title']           = 'Backup-ul bazei de date a fost finalizat cu succes!';
$_['text_email_subject_backup']  = 'Backup BlueCart finalizat';
$_['text_email_body']            = "Procesul de backup a fost finalizat cu succes.\n\nDetalii:\n- Domeniu: %s\n- Bază de date: %s\n- Fișier: %s\n- Dimensiune: %s KB\n- Dată: %s\n\nFișierul este disponibil în panoul de administrare sau în folderul de backup configurat.";
$_['text_email_attached']        = 'Fișierul „%s” a fost atașat la acest email.';
$_['text_email_no_attach']       = 'Backup-ul a fost finalizat, dar atașamentul este dezactivat.';
$_['text_email_sent']            = 'Email trimis către %s';
$_['text_email_failed']          = 'Trimiterea emailului către %s a eșuat.';
$_['text_email_disabled']        = 'Trimiterea emailurilor este dezactivată.';
$_['text_email_signature']       = "Cu stimă,\nEchipa BlueCart\n© 2025 BlueCart — eCommerce Software Development";
$_['text_email_message_backup'] = "Procesul de backup al bazei de date a fost finalizat cu succes.\n\nDetalii:\n- Domeniu: %s\n- Bază de date: %s\n- Fișier: %s\n- Dimensiune: %s KB\n- Dată: %s\n\nFișierul de backup este disponibil în panoul de administrare sau în folderul FTP configurat.\n\nCu stimă,\nEchipa BlueCart\n© 2025 BlueCart — eCommerce Software Development";



// --- Ștergere multiplă ---
$_['button_delete_selected'] = 'Șterge selecția';
$_['text_confirm_delete']    = 'Sigur dorești să ștergi backup-urile selectate?';
$_['text_bulk_deleted']      = '%s fișiere de backup au fost șterse cu succes.';
$_['error_no_selection']     = 'Nu ai selectat niciun fișier!';
$_['error_bulk_none']        = 'Nu a fost șters niciun fișier.';
$_['error_permission_delete'] = 'Nu ai permisiunea de a șterge backup-uri!';

// --- Backup manual ---
$_['text_backup_now']        = 'Generează backup acum';
$_['text_backup_generating'] = 'Se generează backup-ul bazei de date...';
$_['text_backup_success']    = 'Backup-ul a fost finalizat cu succes!';
$_['text_backup_error']      = 'A apărut o eroare în timpul procesului de backup.';
$_['text_backup_unknown']    = 'Eroare necunoscută!';
$_['text_reload_after_backup'] = 'Se reîncarcă pagina pentru actualizarea listei de backup-uri...';

// --- LOG ---
$_['text_log_backup_start']      = 'Procesul de backup a început';
$_['text_log_sql_created']       = 'Fișier SQL creat: %s';
$_['text_log_zip_created']       = 'Fișier ZIP creat: %s';
$_['text_log_email_sent']        = 'Email trimis către: %s';
$_['text_log_email_disabled']    = 'Notificările prin email sunt dezactivate';
$_['text_log_ftp_success']       = 'Încărcare FTP reușită: %s';
$_['text_log_ftp_failed']        = 'Încărcare FTP eșuată: %s';
$_['text_log_ftp_disabled']      = 'Încărcarea FTP este dezactivată';
$_['text_log_complete']          = 'Backup finalizat cu succes';
$_['text_log_exception']         = 'Excepție: %s';
$_['text_log_no_log']            = 'Jurnalizarea este dezactivată';
$_['text_log_cron_start']        = 'Scheduler-ul a pornit';
$_['text_log_cron_skip']         = 'Nu este încă momentul (%s @ %s). Ora curentă: %s';
$_['text_log_cron_force']        = 'Mod forțat activ — rulare manuală';
$_['text_log_cron_lock']         = 'Proces blocat — execuție duplicat omisă';
$_['text_log_cron_done']         = 'Jobul CRON a fost finalizat cu succes';

// --- EMAIL / FTP ---
$_['text_email_intro']           = 'Backup-ul a fost finalizat cu succes.';
$_['text_email_file']            = 'Fișier: %s';
$_['text_email_size']            = 'Dimensiune: %s KB';
$_['text_email_date']            = 'Dată: %s';
$_['text_email_no_attach']       = 'Email trimis fără atașament.';
$_['text_email_with_attach']     = 'Email trimis cu fișier atașat.';
$_['text_email_failed']          = 'Eroare la trimiterea emailului: %s';
$_['text_ftp_connect']           = 'Conexiune FTP stabilită.';
$_['text_ftp_login']             = 'Autentificare FTP reușită.';
$_['text_ftp_upload']            = 'Încărcare către: %s';
$_['text_ftp_success']           = 'Încărcare FTP reușită: %s';
$_['text_ftp_failed']            = 'Încărcare FTP eșuată: %s';
$_['text_ftp_disabled']          = 'Încărcarea FTP este dezactivată.';
$_['text_ftp_settings'] = 'Setări FTP';

// --- Formulare ---
$_['entry_status']           = 'Stare modul';
$_['entry_keep_days']        = 'Păstrează backup-urile (zile)';
$_['entry_frequency']        = 'Frecvență Backup';
$_['entry_time']             = 'Ora execuției';
$_['entry_zip']              = 'Comprimă fișierul (ZIP)';
$_['entry_email_enabled']    = 'Activează trimiterea emailurilor';
$_['entry_email']            = 'Adresă email destinatar';
$_['entry_email_subject']    = 'Subiect email (opțional)';
$_['entry_email_attach']     = 'Atașează fișierul de backup';
$_['entry_ftp_enabled']      = 'Activează încărcarea FTP';
$_['entry_ftp_server']       = 'Server FTP';
$_['entry_ftp_user']         = 'Utilizator FTP';
$_['entry_ftp_pass']         = 'Parolă FTP';
$_['entry_ftp_path']         = 'Director destinație FTP';

// --- Butoane ---
$_['button_save']            = 'Salvează';
$_['button_cancel']          = 'Anulează';
$_['button_backup_now']      = 'Backup acum';
$_['button_download']        = 'Descarcă';
$_['button_delete']          = 'Șterge';
$_['button_generate_cron']   = 'Generează linia CRON';

// --- Informații ---
$_['text_success_backup']    = 'Backup creat cu succes!';
$_['text_error_backup']      = 'Eroare la generarea backup-ului!';
$_['text_log']               = 'Jurnal Backup';
$_['text_last_run']          = 'Ultima execuție:';
$_['text_cron_info']         = 'Rulează următoarea linie în CRON pentru automatizare:';
$_['text_email_notice']      = 'Setări email';
$_['text_ftp_notice']        = 'Dacă datele FTP nu sunt completate, backup-urile vor rămâne doar local.';

// --- Ajutor ---
$_['help_keep_days']         = 'Numărul de zile pentru păstrarea fișierelor de backup.';
$_['help_frequency']         = 'Selectează cât de des să ruleze backup-ul automat.';
$_['help_zip']               = 'Comprimă fișierul SQL într-un ZIP (recomandat pentru economisirea spațiului).';
$_['help_email_attach']      = 'Trimite fișierul SQL ca atașament în emailul de notificare.';
$_['help_ftp']               = 'Completează datele FTP doar dacă dorești încărcarea automată.';
$_['help_cron']              = 'Linia CRON poate fi generată automat folosind butonul de mai jos.';

//---+---
$_['error_file_not_found'] = 'Fișierul specificat nu există!';
$_['text_backup_deleted']  = 'Backup-ul %s a fost șters cu succes.';
$_['error_email_invalid'] = 'Adresa de e-mail introdusă „%s” nu este validă. Verifică formatul (ex: nume@domeniu.ro).';
$_['text_email_test_body']   = 'Acesta este un e-mail de test pentru verificarea setărilor BlueCart DB Backup.';
$_['text_email_success']     = '📧 E-mail trimis cu succes către %s.';
$_['error_email_not_set']    = '⚠️ Adresa de e-mail nu este setată.';
$_['text_bulk_deleted'] = '%s fișiere de backup au fost șterse cu succes.';
$_['text_selected_backups'] = '%s backup%s selectat%s.';

// === BlueCart Cloud Sync (Funcționalitate viitoare)
$_['entry_cloud_sync']         = 'Cloud Sync';
$_['text_cloud_sync_disabled'] = 'Sincronizare automată a backupurilor cu Google Drive, Dropbox și OneDrive.';
$_['help_cloud_sync']          = '<i>Această funcție va permite sincronizarea automată a copiilor de siguranță cu serviciile tale cloud preferate. Momentan este dezactivată — va fi disponibilă într-o versiune viitoare.</i>';

$_['coming_soon']          = 'În curând';
$_['entry_sync']          = 'Cloud';


<?php
/*
 * =============================================================
 *  BlueCart DB Backup Manager — English Language
 *  © 2025 BlueCart.ro — eCommerce Software Development
 * =============================================================
 */

$_['heading_title']          = 'BlueCart Database Backup Manager';
$_['text_edit']              = 'Automatic Backup Settings';
$_['text_success']           = 'Settings saved successfully.';
$_['text_home']              = 'Home';
$_['text_extension']         = 'Modules';
$_['text_frequency_daily']   = 'Daily';
$_['text_frequency_weekly']  = 'Weekly';
$_['text_frequency_monthly'] = 'Monthly';
$_['text_no_backups']        = 'No backups available.';
$_['text_backup_list']       = '📦 Existing Backup Files';
$_['text_yes']               = 'enabled';
$_['text_no']                = 'disabled';

// --- CRON ---
$_['text_cron_title']     = '⏰ CRON Line';
$_['text_cron_info']      = 'Run the following command in your server CRON to enable automatic backups:';
$_['text_file']           = 'File';
$_['text_size']           = 'Size';
$_['text_date']           = 'Date';
$_['text_actions']        = 'Actions';
$_['button_copy_cron']    = 'Copy';
$_['text_copied']         = 'Copied!';
$_['entry_keep_count']    = 'Keep last N backups';
$_['help_keep_count']     = 'If this number is exceeded, the oldest backups will be deleted automatically.';
$_['help_cron_hint']      = 'Tip: set your CRON job to run every 5 minutes. The module will trigger backups at the configured time.';
$_['text_confirm_delete'] = 'Are you sure you want to delete this backup?';

// --- Days ---
$_['entry_day']      = 'Scheduled Backup Day';
$_['tooltip_day']    = 'Select the day of the week when the automatic backup will run.';
$_['text_monday']    = 'Monday';
$_['text_tuesday']   = 'Tuesday';
$_['text_wednesday'] = 'Wednesday';
$_['text_thursday']  = 'Thursday';
$_['text_friday']    = 'Friday';
$_['text_saturday']  = 'Saturday';
$_['text_sunday']    = 'Sunday';

// --- FTP ---
$_['button_test_ftp'] = 'Test FTP Connection';
$_['text_ftp_success'] = 'FTP connection tested successfully.';
$_['error_ftp_host_missing'] = 'The FTP server address is not set.';
$_['error_ftp_connect'] = 'FTP connection failed (%s:%s). Please check if the address is correct.';
$_['error_ftp_login'] = 'FTP login failed for user %s.';
$_['entry_ftp_passive'] = 'Passive Mode';
$_['help_ftp_passive']  = 'Enable if your server requires passive FTP connections.';
$_['entry_ftp_path'] = 'FTP Path';
$_['tooltip_ftp_path'] = 'Destination directory for uploaded backups.';
$_['help_ftp_digi'] = 'For Digi Storage users: set the FTP path to /Digi Cloud/backup and enable passive mode.';
$_['text_ftp_path_warning'] = 'Digi Storage detected, but path does not contain /Digi Cloud/backup.';
$_['entry_log_enabled'] = 'Enable activity log';
$_['help_log_enabled']  = 'If enabled, all module actions (backup, email, FTP, cron) will be recorded.';
$_['text_ftp_settings'] = 'FTP settings';

// --- EMAIL ---
$_['button_test_email'] = 'Test Email';
$_['text_email_subject_default'] = 'BlueCart Backup - Test Email';
$_['text_email_intro'] = 'This is a test email sent automatically from the BlueCart Backup module.';
$_['text_email_date'] = 'Date:';
$_['text_email_domain'] = 'Domain:';
$_['text_email_attached'] = 'Attached backup file: %s';
$_['text_email_no_attach'] = 'No attachment (option disabled or no backups found).';
$_['text_email_success'] = 'Test email sent successfully to %s.';
$_['text_email_with_attach'] = 'Test email sent successfully to %s with attachment.';
$_['error_email_not_set'] = 'Email address not configured.';
$_['text_email_subject_backup'] = 'BlueCart Database Backup Completed';
$_['text_email_message_backup'] = "The database backup has been completed successfully.\n\nDetails:\n- Domain: %s\n- Database: %s\n- File: %s\n- Size: %s KB\n- Date: %s\n\nThe backup file is available in the admin panel or in your configured FTP folder.\n\nRegards,\nBlueCart Team\n© 2025 BlueCart — eCommerce Software Development";
$_['text_email_failed'] = 'Error sending email: %s';
$_['text_email_disabled'] = 'Email sending disabled.';
$_['text_email_signature'] = "Regards,\nBlueCart Team\n© 2025 BlueCart — eCommerce Software Development";

// --- BULK DELETE ---
$_['button_delete_selected'] = 'Delete Selected';
$_['text_bulk_deleted']      = '%s backup files deleted successfully.';
$_['error_no_selection']     = 'No files selected.';
$_['error_bulk_none']        = 'No files were deleted.';
$_['error_permission_delete'] = 'You do not have permission to delete backups.';

// --- BACKUP ---
$_['text_backup_now']        = 'Generate Backup Now';
$_['text_backup_generating'] = 'Generating database backup...';
$_['text_backup_success']    = 'Backup completed successfully.';
$_['text_backup_error']      = 'An error occurred during backup.';
$_['text_backup_unknown']    = 'Unknown error.';
$_['text_reload_after_backup'] = 'Reloading page to update list.';

// --- LOG ---
$_['text_log_backup_start'] = 'Backup process started';
$_['text_log_sql_created']  = 'SQL file created: %s';
$_['text_log_zip_created']  = 'ZIP file created: %s';
$_['text_log_email_sent']   = 'Email sent to: %s';
$_['text_log_email_disabled'] = 'Email notifications disabled';
$_['text_log_ftp_success']  = 'FTP upload success: %s';
$_['text_log_ftp_failed']   = 'FTP upload failed: %s';
$_['text_log_ftp_disabled'] = 'FTP upload disabled';
$_['text_log_complete']     = 'Backup completed successfully';
$_['text_log_exception']    = 'Exception: %s';
$_['text_log_no_log']       = 'Logging disabled';
$_['text_log_cron_start']   = 'Scheduler started';
$_['text_log_cron_skip']    = 'Not time yet (%s @ %s). Current: %s';
$_['text_log_cron_force']   = 'Force mode active (manual run)';
$_['text_log_cron_lock']    = 'Lock active — skipping duplicate execution';
$_['text_log_cron_done']    = 'Cron job completed successfully';

// --- FORM FIELDS ---
$_['entry_status']        = 'Module Status';
$_['entry_keep_days']     = 'Keep backups (days)';
$_['entry_frequency']     = 'Backup Frequency';
$_['entry_time']          = 'Execution Time';
$_['entry_zip']           = 'Compress backup (ZIP)';
$_['entry_email_enabled'] = 'Enable Email Notifications';
$_['entry_email']         = 'Recipient Email';
$_['entry_email_subject'] = 'Email Subject (optional)';
$_['entry_email_attach']  = 'Attach backup file';
$_['entry_ftp_enabled']   = 'Enable FTP Upload';
$_['entry_ftp_server']    = 'FTP Server';
$_['entry_ftp_user']      = 'FTP Username';
$_['entry_ftp_pass']      = 'FTP Password';
$_['entry_ftp_path']      = 'FTP Folder';

// --- BUTTONS ---
$_['button_save']          = 'Save';
$_['button_cancel']        = 'Cancel';
$_['button_backup_now']    = 'Backup Now';
$_['button_download']      = 'Download';
$_['button_delete']        = 'Delete';
$_['button_generate_cron'] = 'Generate CRON Line';

// --- INFO ---
$_['text_success_backup'] = 'Backup created successfully.';
$_['text_error_backup']   = 'Error generating backup.';
$_['text_log']            = 'Backup Log';
$_['text_last_run']       = 'Last Run:';
$_['text_cron_info']      = 'Run this CRON line on your server to automate backups.';
$_['text_email_notice']   = 'Email settings';
$_['text_ftp_notice']     = 'If FTP credentials are missing, backups remain local only.';

// --- HELP ---
$_['help_keep_days']      = 'Number of days to keep backup files.';
$_['help_frequency']      = 'Select how often backups should run.';
$_['help_zip']            = 'Compress SQL file into ZIP for space saving.';
$_['help_email_attach']   = 'Send the backup file as attachment in notification email.';
$_['help_ftp']            = 'Fill FTP details only if you want automatic upload.';
$_['help_cron']           = 'The CRON line below can be copied directly into your server scheduler.';

//---+---
$_['error_file_not_found'] = 'The specified file does not exist!';
$_['text_backup_deleted']  = 'Backup file %s has been successfully deleted.';
$_['error_email_invalid']  = 'The email address “%s” is not valid. Please check the format (e.g., name@domain.com).';
$_['text_email_test_body'] = 'This is a test email to verify the BlueCart DB Backup settings.';
$_['text_email_success']   = '📧 Test email successfully sent to %s.';
$_['error_email_not_set']  = '⚠️ Email address is not configured.';
$_['text_bulk_deleted']    = '%s backup files have been successfully deleted.';
$_['text_selected_backups'] = '%s backup%s selected.';

// === BlueCart Cloud Sync (Future Feature)
$_['entry_cloud_sync']         = 'Cloud Sync';
$_['text_cloud_sync_disabled'] = 'Automatic backup synchronization with Google Drive, Dropbox, and OneDrive.';
$_['help_cloud_sync']          = '<i>This feature will automatically sync your database backups with your preferred cloud storage provider. Currently disabled — available in a future update.</i>';
$_['coming_soon']          = 'Coming Soon';
$_['entry_sync']          = 'Cloud';


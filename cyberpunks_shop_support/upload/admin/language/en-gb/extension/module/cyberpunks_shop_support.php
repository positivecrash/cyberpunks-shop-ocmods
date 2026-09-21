<?php
$_['heading_title'] = 'Cyberpunks Shop Support';
$_['heading_title_blocklist'] = 'Support blacklist';

$_['text_extension'] = 'Extensions';
$_['text_edit'] = 'Edit Cyberpunks Shop Support';
$_['text_enabled'] = 'Enabled';
$_['text_disabled'] = 'Disabled';
$_['text_home'] = 'Home';
$_['text_list'] = 'Support request list';
$_['text_ticket'] = 'Request %s';
$_['text_no_results'] = 'No support requests yet.';
$_['text_confirm'] = 'Are you sure?';
$_['text_confirm_blacklist'] = 'Add the email addresses of the selected requests to the blacklist?';
$_['text_blocklist_add'] = 'Add to blacklist';
$_['text_blocklist_list'] = 'Blacklisted emails';
$_['text_no_blocked'] = 'The blacklist is empty.';
$_['text_success'] = 'Success: You have modified Cyberpunks Shop Support!';
$_['text_success_reply'] = 'Reply saved and emailed to the customer.';
$_['text_success_logged'] = 'Customer message logged on the ticket.';
$_['text_success_internal'] = 'Internal note saved (not emailed).';
$_['text_success_status'] = 'Status updated.';
$_['text_success_delete'] = 'Selected requests deleted.';
$_['text_success_blacklist'] = 'Blacklist updated. Added: %d, already listed or invalid: %d.';
$_['text_success_blocklist_delete'] = 'Selected emails removed from the blacklist.';
$_['text_pagination'] = 'Showing %d to %d of %d (%d Pages)';

$_['text_status_open'] = 'Open';
$_['text_status_in_progress'] = 'In progress';
$_['text_status_waiting'] = 'Waiting for customer';
$_['text_status_closed'] = 'Closed';

$_['text_author_customer'] = 'Customer';
$_['text_author_admin'] = 'Support';
$_['text_author_internal'] = 'Internal note';

$_['text_thread'] = 'Conversation';
$_['text_reply'] = 'Reply to customer';
$_['text_log_customer'] = 'Log as customer message (no email)';
$_['text_log_internal'] = 'Log as internal note (managers only, no email)';
$_['text_set_waiting'] = 'Set status to “Waiting for customer” after reply';

$_['column_request'] = 'Request';
$_['column_email'] = 'Email';
$_['column_reason'] = 'Reason';
$_['column_status'] = 'Status';
$_['column_messages'] = 'Msgs';
$_['column_preview'] = 'Preview';
$_['column_date_added'] = 'Created';
$_['column_note'] = 'Note';
$_['column_date_modified'] = 'Updated';
$_['column_action'] = 'Action';

$_['entry_request_code'] = 'Request ID';
$_['entry_email'] = 'Email';
$_['entry_status'] = 'Status';
$_['entry_module_status'] = 'Status';
$_['entry_language'] = 'Language';
$_['entry_currency'] = 'Currency';
$_['entry_country'] = 'Country';
$_['entry_reply'] = 'Message';
$_['entry_email_signature'] = 'Email signature';
$_['entry_imap_status'] = 'IMAP import';
$_['entry_imap_host'] = 'IMAP host';
$_['entry_imap_port'] = 'IMAP port';
$_['entry_imap_encryption'] = 'Encryption';
$_['entry_imap_user'] = 'IMAP username';
$_['entry_imap_password'] = 'IMAP password';
$_['entry_imap_folder'] = 'Mailbox folder';
$_['entry_imap_subject'] = 'Subject filter';
$_['entry_imap_require_from_match'] = 'Require From = ticket email';
$_['entry_imap_mark_seen'] = 'Mark imported as Seen';
$_['entry_imap_max'] = 'Max messages per run';
$_['entry_imap_cron_key'] = 'Cron secret key';

$_['entry_blocklist_emails'] = 'Emails';
$_['entry_blocklist_note'] = 'Note';
$_['entry_antispam_cooldown_hours'] = 'Rate-limit window (hours)';
$_['entry_antispam_daily_max'] = 'Max messages per email';
$_['entry_antispam_append_open'] = 'Append to open request';
$_['entry_form_token'] = 'Form token';
$_['entry_form_min_seconds'] = 'Minimum fill time (seconds)';

$_['text_antispam_heading'] = 'Contact form protection';
$_['text_form_messages_heading'] = 'Contact form messages';
$_['text_imap_heading'] = 'Inbound email (IMAP)';
$_['text_yes'] = 'Yes';
$_['text_no'] = 'No';
$_['text_imap_extension_missing'] = 'Warning: PHP IMAP extension is not available on this server. Install php-imap to use inbound import.';
$_['text_imap_fetch_result'] = 'IMAP fetch finished. Imported: %d, skipped: %d.';

$_['entry_msg_success'] = 'Success message';
$_['entry_msg_token'] = 'Invalid / missing form token';
$_['entry_msg_too_fast'] = 'Submitted too fast';
$_['entry_msg_blocked'] = 'Blacklisted email';
$_['entry_msg_soft_limit'] = 'Daily message limit';

$_['help_form_messages'] = 'Shown on the themed Contact us form. Stored in module settings only — fill them in before going live.';
$_['help_msg_too_fast'] = 'Use %d where the remaining wait time in seconds should appear.';
$_['help_status'] = 'When Enabled, the left Navigation shows Cyberpunks Shop Support and contact-form tickets are stored.';
$_['help_email_signature'] = 'Optional. Appended to customer emails (first confirmation and admin replies) only if filled here. Plain text. Not hardcoded in the extension.';
$_['help_blocklist_emails'] = 'One or more email addresses, separated by space, comma or new line. Blacklisted senders cannot submit the contact form.';
$_['help_antispam'] = 'Applied to the themed Contact us form. Blacklist is managed on the separate blacklist page.';
$_['help_antispam_cooldown_hours'] = 'Rolling window used for the max-messages limit (usually 24).';
$_['help_antispam_daily_max'] = 'After this many customer messages from the same email within the window, further submits are refused (message text below). 0 disables the limit.';
$_['help_antispam_append_open'] = 'When Yes, a new submit from an email that already has an Open / In progress / Waiting request is added to that thread instead of creating a new request. Closed requests always start a new one.';
$_['help_form_token'] = 'Requires a token issued when the form is rendered, so bots cannot post directly to the endpoint.';
$_['help_form_min_seconds'] = 'Reject submits that arrive faster than this after the form was rendered. 0 disables the check.';
$_['help_imap'] = 'Polls the mailbox for unread messages whose subject contains the filter below (IMAP SEARCH SUBJECT). Other mail is never downloaded. Prefer a dedicated support inbox when possible.';
$_['help_imap_subject'] = 'Only emails with this text in the subject are considered (default matches outbound support subjects). Request ID is parsed after “Support request -”.';
$_['help_imap_from'] = 'When Yes, the sender must match the ticket customer email (recommended).';
$_['help_imap_password'] = 'Leave blank to keep the current password.';
$_['help_imap_cron'] = 'Call this URL from server cron every 5 minutes. Keep the key secret.';

$_['button_imap_fetch'] = 'Fetch IMAP now';
$_['error_imap_fetch'] = 'IMAP fetch failed.';

$_['button_filter'] = 'Filter';
$_['button_view'] = 'View';
$_['button_delete'] = 'Delete';
$_['button_save'] = 'Save / Send';
$_['button_save_settings'] = 'Save';
$_['button_cancel'] = 'Back';
$_['button_cancel_extension'] = 'Cancel';
$_['button_tickets'] = 'Support requests';
$_['button_settings'] = 'Module settings';
$_['button_blacklist'] = 'Blacklist emails of selected requests';
$_['button_blacklist_add'] = 'Add to blacklist';
$_['button_blocklist'] = 'Blacklist';

$_['email_subject_reply'] = 'Cyberpunks.shop - Support request - %s';
$_['email_text_reply_intro'] = "Reply from Cyberpunks Shop support (request %s):\n";

$_['error_permission'] = 'Warning: You do not have permission to modify support requests!';
$_['error_not_found'] = 'Support request not found.';

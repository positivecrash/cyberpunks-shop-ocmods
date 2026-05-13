<?php

$_['heading_title'] = 'Cyberpunks Shop Matomo Analytics';

$_['text_extension'] = 'Extensions';
$_['text_success'] = 'Success: You have modified Matomo Analytics!';
$_['text_edit'] = 'Edit Matomo Analytics';
$_['text_help_server'] = 'Full base URL of your Matomo instance (where matomo.js is served), e.g. https://analytics.example.com or https://example.com/matomo — no trailing slash required.';
$_['text_help_site_id'] = 'Site ID from Matomo → Administration → Measurables → Manage.';
$_['text_help_ecommerce'] = 'Send order and line items on the checkout success page (enable Ecommerce in Matomo for this site).';
$_['text_help_sku'] = 'Product identifier sent to Matomo: OpenCart model (order line) or SKU from the product catalog (extra query per line).';
$_['text_setup_hint'] = 'To avoid showing a cookie consent banner, you should: use self-hosted Matomo; avoid extra marketing pixels (such as Google or Facebook); turn on IP anonymisation in Matomo (Administration → Privacy → Anonymise data); keep Disable analytics cookies enabled below; and provide a Matomo opt-out on your privacy policy page.';
$_['text_help_disable_cookies'] = 'Sends Matomo disableCookies: Matomo does not set first-party analytics cookies. Recommended ON when you are not using other trackers and want to stay close to a no cookie-banner approach together with Matomo privacy settings.';
$_['text_help_dnt'] = 'If the browser sends DNT:1, the Matomo snippet is not output on catalog pages. Optional; does not replace a privacy policy.';

$_['entry_server'] = 'Matomo base URL';
$_['entry_site_id'] = 'Site ID';
$_['entry_status'] = 'Status';
$_['entry_ecommerce'] = 'Ecommerce order tracking';
$_['entry_sku_field'] = 'Product SKU source';
$_['entry_disable_cookies'] = 'Disable analytics cookies';
$_['entry_respect_dnt'] = 'Respect Do Not Track';

$_['text_enabled'] = 'Enabled';
$_['text_disabled'] = 'Disabled';
$_['text_sku_model'] = 'Model (order line)';
$_['text_sku_sku'] = 'SKU (catalog product)';

$_['button_save'] = 'Save';
$_['button_cancel'] = 'Cancel';

$_['error_permission'] = 'Warning: You do not have permission to modify Matomo Analytics!';
$_['error_server'] = 'Matomo base URL is required.';
$_['error_server_url'] = 'Matomo base URL must be a valid URL (including https://).';
$_['error_site_id'] = 'Site ID must be a positive integer.';

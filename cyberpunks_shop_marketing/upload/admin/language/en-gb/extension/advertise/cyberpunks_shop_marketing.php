<?php
$_['heading_title'] = 'Cyberpunks Shop Marketing';

$_['text_extension'] = 'Extensions';
$_['text_success'] = 'Success: You have modified marketing settings!';
$_['text_edit'] = 'Edit Marketing';
$_['text_enabled'] = 'Enabled';
$_['text_disabled'] = 'Disabled';
$_['text_model'] = 'Model (order line)';
$_['text_sku'] = 'SKU (catalog product)';
$_['text_gtm_section'] = 'Google Tag Manager';
$_['text_consent_section'] = 'Google cookie consent';
$_['text_matomo_section'] = 'Matomo Analytics';
$_['text_shared_section'] = 'Shared';
$_['text_setup_hint'] = 'GTM + Matomo marketing tags and ecommerce events. Replaces the separate GTM and Matomo extensions. Google Merchant Center / Shopping feed is not included yet.';

$_['entry_status'] = 'Status';
$_['entry_gtm_status'] = 'Enable GTM';
$_['entry_container_id'] = 'GTM container ID';
$_['entry_event_purchase'] = 'Purchase event (checkout success)';
$_['entry_event_view_item'] = 'View item event (product page)';
$_['entry_consent_status'] = 'Enable consent banner';
$_['entry_consent_message'] = 'Banner message';
$_['entry_consent_privacy_label'] = 'Privacy link label';
$_['entry_consent_privacy_url'] = 'Privacy link URL';
$_['entry_consent_deny_label'] = 'Decline button label';
$_['entry_consent_grant_label'] = 'Accept button label';
$_['entry_consent_expiry_days'] = 'Choice expiry (days)';
$_['entry_matomo_status'] = 'Enable Matomo';
$_['entry_matomo_server'] = 'Matomo base URL';
$_['entry_matomo_site_id'] = 'Site ID';
$_['entry_matomo_ecommerce'] = 'Ecommerce order tracking';
$_['entry_matomo_disable_cookies'] = 'Disable analytics cookies';
$_['entry_matomo_respect_dnt'] = 'Respect Do Not Track';
$_['entry_item_id_field'] = 'Product identifier';

$_['help_container'] = 'Example: GTM-K79KW7T2. Injects the GTM container snippet into the theme header via OCMOD.';
$_['help_events'] = 'Events are pushed to window.dataLayer. Configure GA4 tags and triggers inside Google Tag Manager.';
$_['help_no_gtag'] = 'Do not install gtag.js (G-…) on the site — connect GA4 only as a tag inside GTM.';
$_['help_consent'] = 'Shows a cookie banner and sets Google Consent Mode v2 defaults before GTM. Requires GTM to be enabled. Matomo is not controlled by this banner.';
$_['help_consent_message'] = 'Plain text shown before the privacy link. The link is appended automatically (“See …”).';
$_['help_consent_privacy'] = 'Relative path (e.g. /privacy-policy) or full URL for the privacy policy link.';
$_['help_consent_expiry_days'] = 'After this many days the visitor is asked again (stored in localStorage). Default: 30.';
$_['help_matomo_server'] = 'Full base URL of your Matomo instance, e.g. https://analytics.example.com — no trailing slash.';
$_['help_matomo_site_id'] = 'Site ID from Matomo → Administration → Measurables → Manage.';
$_['help_matomo_ecommerce'] = 'Send order and line items on checkout success (enable Ecommerce in Matomo for this site).';
$_['help_matomo_disable_cookies'] = 'Matomo does not set first-party analytics cookies when enabled.';
$_['help_matomo_dnt'] = 'If the browser sends DNT:1, the Matomo snippet is not output on catalog pages.';
$_['help_item_id_field'] = 'Used for GA4 item_id and Matomo ecommerce SKU.';

$_['button_save'] = 'Save';
$_['button_cancel'] = 'Cancel';

$_['error_permission'] = 'Warning: You do not have permission to modify marketing settings!';
$_['error_container_id'] = 'GTM container ID is required (format GTM-XXXXXXX) when GTM is enabled.';
$_['error_consent_expiry_days'] = 'Consent expiry must be between 1 and 3650 days when the consent banner is enabled.';
$_['error_matomo_server'] = 'Matomo base URL is required when Matomo is enabled.';
$_['error_matomo_server_url'] = 'Matomo base URL must be a valid URL (including https://).';
$_['error_matomo_site_id'] = 'Site ID must be a positive integer when Matomo is enabled.';

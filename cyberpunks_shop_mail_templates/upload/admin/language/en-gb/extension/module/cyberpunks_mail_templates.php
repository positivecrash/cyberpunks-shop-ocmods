<?php
$_['heading_title'] = 'Cyberpunks Mail Templates';

$_['text_extension'] = 'Extensions';
$_['text_success'] = 'Success: You have updated mail templates.';
$_['text_edit'] = 'Edit order-status email subjects and HTML';
$_['text_home'] = 'Home';
$_['text_enabled'] = 'Enabled';
$_['text_disabled'] = 'Disabled';
$_['text_setup_hint'] = 'Create layout templates (name + full HTML with {content}). On each status tab pick a Template and write the HTML body — it is injected into {content}. Custom HTML is used automatically when a Template is selected or the HTML body is filled. Empty subject → OpenCart default subject.';
$_['text_raw_html_hint'] = 'HTML fields are plain code textareas (not Summernote). OpenCart admin encodes markup on POST; this module decodes on save/send. Layout templates are auto-wrapped if document tags are missing. Use absolute image URLs.';
$_['text_variables'] = 'Variables / shortcodes';
$_['text_html'] = 'HTML body';
$_['text_status_mapped'] = 'Mapped to store status';
$_['text_status_missing'] = 'No matching order status found in this store — create/rename it under System → Localisation → Order Statuses (exact name).';
$_['text_main_tab'] = 'Templates';
$_['text_main_help'] = 'Reusable full email documents. Keep &lt;!DOCTYPE html&gt;, &lt;html&gt;, &lt;head&gt;, &lt;body&gt;. Put {content} where the status HTML body should appear.';
$_['text_layout_none'] = '— None (body only) —';
$_['text_layout_item'] = 'Template';

$_['entry_status'] = 'Status';
$_['entry_subject'] = 'Subject';
$_['entry_layout'] = 'Template';
$_['entry_layout_name'] = 'Name';
$_['entry_layout_html'] = 'HTML';
$_['help_status'] = 'Master switch. When Disabled, stock OpenCart order emails are used.';
$_['help_subject'] = 'Leave empty for the OpenCart default subject. Shortcodes OK, e.g. {store_name}, {order_id}, {order_status}.';
$_['help_layout'] = 'Wrapper from the Templates tab. Status HTML body replaces {content}. Selecting a Template enables custom HTML for this status.';
$_['help_layout_html'] = 'Full email HTML. Must include {content}. Shortcodes: {order_id}, {comment}, {order_products}, … Image src must be absolute (https://…).';
$_['help_status_html'] = 'Fragment injected into the layout’s {content}. Raw HTML, not a full document. Use {order_products} for the products list.';

$_['help_status_paid'] = 'Sent when the order status becomes Paid.';
$_['help_status_pending'] = 'Sent when the order status becomes Pending.';
$_['help_status_processing'] = 'Sent when the order status becomes Processing.';
$_['help_status_shipped'] = 'Sent when the order status becomes Shipped.';
$_['help_status_canceled'] = 'Sent when the order status becomes Canceled (or Cancelled).';
$_['help_status_complete'] = 'Sent when the order status becomes Complete.';

$_['error_permission'] = 'Warning: You do not have permission to modify this module!';

$_['button_save'] = 'Save';
$_['button_cancel'] = 'Cancel';
$_['button_add_layout'] = 'Add template';
$_['button_remove'] = 'Remove';

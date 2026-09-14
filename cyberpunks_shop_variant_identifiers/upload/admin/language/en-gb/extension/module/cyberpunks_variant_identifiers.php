<?php
$_['heading_title'] = 'Cyberpunks Variant Identifiers';

$_['text_extension'] = 'Extensions';
$_['text_success'] = 'Success: You have modified Cyberpunks Variant Identifiers module!';
$_['text_edit'] = 'Edit Variant SKU / GTIN Mappings';
$_['text_enabled'] = 'Enabled';
$_['text_disabled'] = 'Disabled';

$_['entry_status'] = 'Status';
$_['entry_product_id'] = 'Product ID';
$_['entry_signature'] = 'Variant Builder';
$_['entry_sku'] = 'SKU';
$_['entry_gtin'] = 'GTIN';
$_['entry_mapping_status'] = 'Row Status';

$_['button_add_mapping'] = 'Add mapping';
$_['button_remove'] = 'Remove';
$_['button_add_option_pair'] = 'Add option';
$_['button_edit_options'] = 'Edit options';
$_['button_done_options'] = 'Done';
$_['button_import'] = 'Import';
$_['button_export'] = 'Export';
$_['button_delete_tab'] = 'Delete all tab';

$_['help_signature'] = 'Rows stay compact for speed. Click Edit options to change the variant builder; signature updates automatically.';
$_['help_sku'] = 'Internal / Merchant item id for this option combination. Required (or provide GTIN).';
$_['help_gtin'] = 'Optional EAN/UPC/GTIN for Google Merchant. Leave empty if the variant has no barcode.';
$_['help_import'] = 'YAML keyed by model (portable across local/server). Legacy product_id still accepted. Import replaces mappings only for the resolved product.';
$_['help_import_product'] = 'Import YAML into this product tab. Option names + sku/gtin; binds to this product (model/product_id in file are ignored for targeting).';
$_['help_tab_import'] = 'Open a product tab, then use Import next to Export inside that tab.';
$_['help_export_model'] = 'Export uses product Model (not numeric ID), so the same file works locally and on the server.';
$_['help_product_tab_save'] = 'Save the product (top-right Save) to persist mapping rows edited here.';
$_['text_save_product_first'] = 'Save the product first, then reopen Edit to import or manage variant SKU/GTIN mappings.';
$_['text_import_success'] = 'Import completed successfully.';
$_['text_import_success_count'] = 'Import completed successfully. Rows: %d';
$_['text_delete_tab_success'] = 'All mappings for product #%d were deleted.';
$_['text_confirm_delete_tab'] = 'Delete all mappings in this product tab?';
$_['error_import_model_not_found'] = 'Import model did not match any product.';
$_['tab_variant_identifiers'] = 'Variant SKU/GTIN';
$_['error_permission'] = 'Warning: You do not have permission to modify Cyberpunks Variant Identifiers module!';
$_['error_import_file_required'] = 'Import file is required.';
$_['error_import_file_empty'] = 'Import file is empty.';
$_['error_import_invalid_format'] = 'Import format is invalid.';
$_['error_import_no_rows'] = 'No valid import rows were found.';
$_['error_export_product_required'] = 'Select a product tab for export.';
$_['error_delete_tab_product_required'] = 'Cannot delete tab: product id is missing.';

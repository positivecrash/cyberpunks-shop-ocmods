# Cyberpunks Shop Option Fields

OpenCart 3.x OCMOD + module that replaces the old `options_icon` approach with configurable custom option fields.

## Features

- Separate table for option display mode (`show_image`) per option.
- Separate table for custom field definitions (key, label, type, status, sort order).
- Separate value table to store custom field values per option.
- Admin module to add/remove custom fields.
- Option form integration in `Catalog -> Options` with dynamic custom fields.

## Database tables

- `oc_cyberpunks_option_display_mode`
- `oc_cyberpunks_option_custom_field`
- `oc_cyberpunks_option_custom_field_value`

(`oc_` is your `DB_PREFIX`.)

## Build

From repository root:

```bash
./build-ocmod.sh cyberpunks_shop_option_fields
```

## Install

1. `Extensions -> Installer` -> upload generated `.ocmod.zip`
2. `Extensions -> Modifications -> Refresh`
3. `Extensions -> Modules -> Cyberpunks Shop Option Fields -> Install -> Edit`
4. Add custom fields in the module page

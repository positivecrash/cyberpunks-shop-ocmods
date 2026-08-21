# Cyberpunks Shop Color Palettes

OpenCart 3.x OCMOD + module for reusable product option color palettes.

## Features

- Admin page to define palettes and their colors (name, swatch hex, model hex, random flag).
- Attach one or more palettes to a catalog option in the standard **Catalog → Options** form.
- Option values pick a palette color from a dropdown; names are filled from the palette.
- Storefront resolves `swatch_color`, `model_color`, and `color` from the linked palette color at runtime.

## Admin

1. **Extensions → Extensions → Modules → Cyberpunks Shop Color Palettes** — create palettes and colors.
2. **Catalog → Options** — check the palettes to use, then pick a palette color for each option value row.

Option values, prices, and sort order are still managed in the standard OpenCart options UI.

## Database tables

- `oc_cyberpunks_color_palette`
- `oc_cyberpunks_color_palette_color`
- `oc_cyberpunks_option_color_palette`
- `oc_cyberpunks_option_value_palette_color`

(`oc_` is your `DB_PREFIX`.)

## Dependencies

Works together with **Cyberpunks Shop Option Fields** (for option form custom columns). Install Option Fields first, then Color Palettes.

## Build

From repository root:

```bash
./build-ocmod.sh cyberpunks_shop_color_palettes
```

## Install

1. `Extensions → Installer` → upload generated `.ocmod.zip`
2. `Extensions → Modifications → Refresh`
3. `Extensions → Modules → Cyberpunks Shop Color Palettes → Install → Edit` — define palettes
4. `Catalog → Options` — attach palettes and pick colors per option value

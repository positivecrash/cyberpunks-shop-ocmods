# Cyberpunks Shop Option Fields

OpenCart 3.x OCMOD + module for custom option fields **and** reusable color palettes.

Replaces the separate `cyberpunks_shop_color_palettes` extension (merged in 1.4.0).

## Features

- Custom field definitions (key, label, type, scope, status, sort order)
- Per-option display mode (`show_image` + enabled field ids)
- Color palettes (swatch hex, model hex, random, **in stock**) managed in the same module
- Attach palettes on **Catalog → Options**; option values pick a palette color
- Storefront resolves `swatch_color`, `model_color`, and `color` from the linked palette color
- **Product → Option**: optional **Slide #** per value → switches product gallery slide on the storefront (single number or comma-separated, e.g. `5,6`)
- **Product → Option**: **Display Name** and **Pick Display Name** next to Required (fall back to Catalog → Options defaults when empty)

## Admin

1. **Extensions → Modules → Cyberpunks Shop Option Fields**
   - Tab **Custom fields** — field definitions
   - Tab **Color palettes** — palettes and colors
2. **Catalog → Options** — attach palettes and pick a color per option value
3. After changing a palette: open the option → **Update in products** (syncs palette → option values → all products; existing qty/price kept)

## Database tables

- `oc_cyberpunks_option_display_mode`
- `oc_cyberpunks_option_custom_field`
- `oc_cyberpunks_option_custom_field_value`
- `oc_cyberpunks_color_palette`
- `oc_cyberpunks_color_palette_color`
- `oc_cyberpunks_option_color_palette`
- `oc_cyberpunks_option_value_palette_color`
- `oc_cyberpunks_product_option_gallery` (per product + option value → gallery slide index)
- `oc_cyberpunks_product_option_label` (per product + option → Display Name / Pick Display Name overrides)

(`oc_` is your `DB_PREFIX`.)

## Changelog

### 1.7.5
- Cart palette availability label keeps Display Name casing from admin (no forced lowercase)

### 1.7.4
- Cart palette availability label uses product/option Display Name instead of internal option key (e.g. `Color` not `urban-hood-color`)

### 1.7.3
- Cart line items with out-of-stock palette colors behave like standard OpenCart: `nostock` styling, `hasStock()` false, checkout blocked
- Cart shows `Availability (color - Green): 0` for disabled palette colors
- `Cart\Cart::hasStock()` also checks palette stock (works with checkout facade)
- Checkout guard uses redirect; extra events for checkout facade routes

### 1.7.2
- Register catalog events for palette stock validation (cart warning, checkout block, add-to-cart block) so checks work even when core OCMOD patches are missing or skipped
- Events auto-register on module install or next admin visit to Option Fields

### 1.7.1
- Fix cart palette stock check (`Cart\Cart` has no Loader — use `system/library/cyberpunks_palette_stock.php`)
- Cart / checkout: standard OpenCart `error_stock` when a cart line uses an out-of-stock palette color; checkout blocked
- Storefront: out-of-stock swatch uses diagonal strikethrough (not faded opacity)

### 1.7.0
- Color palettes: **In stock** Yes/No per color (global filament availability; no auto-decrement on order)
- Storefront: out-of-stock palette colors → disabled swatches / select options (Random available when any palette color is in stock)
- Cart / checkout: block add / confirm when a selected palette color is out of stock (product qty unchanged)
- **Update in products**: preserve Slide # when option_value_id changes during palette sync (migrate gallery + product option rows)

### 1.6.1
- Product → Option: **Display Name** next to Required (same pattern as Pick Display Name)
- Cart / checkout resolve Display Name: product override → option default → option Pick Display Name → name

### 1.6.0
- Product → Option: **Pick Display Name** next to Required; empty uses Catalog → Options default
- Catalog → Options Pick Display Name is the default for all products using that option
- Display Name stays option-level (cart / checkout labels)

### 1.5.9
- **Slide #** supports comma-separated values (e.g. `5,6`); stored as VARCHAR
- Theme (`product-oc.js`): when an option has multiple slides, picks the one matching other selected options

### 1.5.8
- Product → Option: **Slide #** column per option value (per-product gallery banner index)
- Storefront merges `gallery_banner_index` into `cyberpunks_fields`; theme switches gallery on option change

### 1.5.7
- Fix product form OCMOD (single-line searches): auto-fill all option values when attaching an option; Add all button
- On product save: if a select/radio/checkbox/image option has no values, fill them from the catalog (qty=1, subtract=No) so the option is actually stored

### 1.5.6
- Option Values Settings layout: **Fields in Options Values** right after Mode; **Sync palettes** section after Filament type; slightly larger spacing between blocks

### 1.5.5
- **Update in products** also syncs option values from attached palettes into the catalog option first (fixes only 3 colors when the form showed 10 unsaved rows)
- Admin sync button / help / confirm strings in English
- Reload option form after a successful sync

### 1.5.4
- Catalog → Options (Color palette): **Update in products** button syncs current option values into all products using that option
- New colors → add product option values (qty=1, subtract=No, price/points/weight=0)
- Existing rows → keep quantity / subtract / price / points / weight
- Stale / duplicate / orphaned product option values are removed (fixes admin “all Magenta” after palette resave)

### 1.5.3
- Option Values Settings: Filament type (PLA / ASA / ABS) when mode is Color palette
- Fix left-aligned labels inside Option Values Settings (Bootstrap nested form-group floated them right)
- Storefront gets `cyberpunks_option_mode` (`values_mode`, `filament_type`); ASA info block no longer hard-tied to `urban-hood-color`

### 1.5.2
- Fix palette sync creating empty/duplicate option value rows (JS ran before `option_value_row` was initialized)
- Palette mode sync now drops unlinked/duplicate rows and skips colors without a name

### 1.5.1
- Fix Catalog → Options form patch: OpenCart matches OCMOD search one line at a time, so the multiline Sort Order replace never applied (Custom fields / Values Settings missing; value columns stayed hidden)

### 1.5.0
- Redesign Catalog → Options: Display Type, Custom option fields, Option Values Settings (mode default / color palette)
- Hide Palette color column (link kept as hidden field)
- Color palette mode: option values sync from selected palettes (update existing, add/remove); fill only Swatch + Model (not Color)
- Remove option-level Sort Order from the form

### 1.4.5
- Fix Option Values column headers: palette column header search matched wrong markup (`required` class)

### 1.4.4
- Color palettes admin: remove unused **Random** column

### 1.4.3
- Catalog → Options: checking a color palette (or **Fill option values from palettes**) creates option values from all palette colors

### 1.4.2
- Product form: **Add all option values** button (qty=1, subtract=No by default)
- Auto-fill all catalog option values when attaching a select/radio/checkbox/image option to a product

### 1.4.1
- Auto-increment Sort order when adding a custom field, palette, or palette color row

### 1.4.0
- Merge **Cyberpunks Shop Color Palettes** into this module (tabs: Custom fields / Color palettes)
- Single OCMOD code `cyberpunks_shop_option_fields` covers option form + storefront palette merge
- DB tables unchanged (existing palette data keeps working)

### 1.3.0
- Previous option-fields-only release

## Build

From `cyberpunks-shop-ocmods/`:

```bash
./build-ocmod.sh cyberpunks_shop_option_fields
```

## Install / upgrade

1. If the old **Color Palettes** module is installed:
   - **Modules → Cyberpunks Shop Color Palettes → Uninstall** (data tables are kept)
   - **Modifications** → disable/delete `cyberpunks_shop_color_palettes` → **Refresh**
2. **Installer** → upload `cyberpunks_shop_option_fields_1_6_1.ocmod.zip`
3. **Modifications → Refresh**
4. **Modules → Cyberpunks Shop Option Fields → Install** (or Edit if already installed)
5. Confirm palettes appear under the **Color palettes** tab

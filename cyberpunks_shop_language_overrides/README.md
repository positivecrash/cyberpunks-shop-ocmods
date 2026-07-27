# Cyberpunks Shop Language Overrides

Module + OCMOD for managing storefront language strings, cart total labels, and price formatting from admin.

## What this module includes

- Admin page grouped by catalog language file (`checkout/cart`, `checkout/checkout`, `error/not_found`, checkout facade, …)
- Storefront overrides applied globally after each language file load (`system/engine/loader.php` + startup)
- Namespaced override keys: `route/to/file:key` (legacy bare keys from older saves still work)
- Cart total label overrides by code (cart, checkout facade, review data OCMODs)
- Storefront thousands separator for prices (`currency->format()`), applied on startup
- Overridden keys are sorted to top within each group
- Saves into `module_cyberpunks_language_overrides_*` settings

## Adding more language files

Edit `getCatalogLanguageFiles()` in `upload/admin/controller/extension/module/cyberpunks_language_overrides.php`.

## Install / update

1. Build: `./build-ocmod.sh cyberpunks_shop_language_overrides`
2. Upload the zip in Extensions → Installer (wait until upload finishes)
3. Refresh Modifications
4. Open: Extensions → Modules → Cyberpunks Language Overrides
5. For spaced thousands: Price Format → Thousands Separator → **Space (8 707)** → Save

If admin breaks after Refresh before the library file was deployed: copy `upload/system/library/cyberpunks_language_overrides.php` from the zip to `system/library/` on the server, then refresh again (or install v1.5.2+ which skips the helper until the file exists).

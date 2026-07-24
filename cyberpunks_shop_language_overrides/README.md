# Cyberpunks Shop Language Overrides

Module + OCMOD for managing checkout language strings and storefront price formatting from admin.

## What this module includes

- Admin page with keys from `catalog/language/en-gb/checkout/cart.php` and checkout placeholders
- Cart total label overrides by code
- Storefront thousands separator for prices (`currency->format()`), applied globally on startup
- Overridden cart/checkout keys are sorted to top
- Saves into `module_cyberpunks_language_overrides_*` settings

## Install / update

1. Build: `./build-ocmod.sh cyberpunks_shop_language_overrides`
2. Upload the zip in Extensions → Installer
3. Refresh Modifications
4. Open: Extensions → Modules → Cyberpunks Language Overrides
5. For spaced thousands: Price Format → Thousands Separator → **Space (8 707)** → Save

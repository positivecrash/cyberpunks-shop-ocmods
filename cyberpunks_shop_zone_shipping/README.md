# Cyberpunks Zone Shipping

Custom shipping extension for OpenCart 3.x.

## What it solves

Configures shipping as:

- shipping zones (custom names),
- countries assigned to each zone,
- multiple delivery types per zone (for example `standard`, `express`),
- optional delivery time text per method (for example `2-4 business days`),
- import/export of full module settings via JSON file in admin,
- individual price / status / sort order for each delivery type in each zone.

## Admin path

`Extensions -> Extensions -> Shipping -> Cyberpunks Zone Shipping`

## Data model (settings)

Stored in `oc_setting` under:

- `shipping_cyberpunks_zone_shipping_status`
- `shipping_cyberpunks_zone_shipping_sort_order`
- `shipping_cyberpunks_zone_shipping_zones` (array of zones with methods)

## Catalog API / product preview

On every save or import in admin, the module writes:

`catalog/view/theme/cybershops/js/zone-shipping-preview.json`

(from zone settings in the database).

The same data is also injected into the page header as `#cyberpunks-zone-shipping-preview` JSON (OCMOD patch on `common/header`).

Theme JS prefers the embedded JSON; if it is empty, it falls back to the JSON file.

Optional endpoint (needs upload files installed):

`index.php?route=extension/shipping/cyberpunks_zone_shipping/quote&country_id={id}`

## Install

Build the zip from the repo root (required — OpenCart Installer expects `upload/` inside the archive):

```bash
./build-ocmod.sh cyberpunks_shop_zone_shipping
```

Then in admin:

1. `Extensions -> Installer` — upload `cyberpunks_shop_zone_shipping_1_0_15.ocmod.zip`
2. `Extensions -> Modifications -> Refresh`
3. `Extensions -> Extensions -> Shipping` — enable **Cyberpunks Zone Shipping**, open Edit, **Save**

Do **not** zip only the contents of `upload/` at the archive root — Installer will register the modification but skip copying PHP/Twig files.

Important: **Refresh Modifications alone does not copy `upload/` files.** After install, confirm these exist:

- `admin/view/template/extension/shipping/cyberpunks_zone_shipping.twig`
- `admin/language/en-gb/extension/shipping/cyberpunks_zone_shipping.php`
- `catalog/model/extension/shipping/cyberpunks_zone_shipping.php`
- `system/library/cyberpunks_zone_shipping_preview.php` (from 1.0.14+)

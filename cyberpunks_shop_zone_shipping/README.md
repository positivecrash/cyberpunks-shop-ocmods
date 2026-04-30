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

## Install

Zip the `upload/` directory contents and install via:

`Extensions -> Installer`

Then enable in:

`Extensions -> Extensions -> Shipping`

# Cyberpunks Merchant Feed

Google Merchant XML feed built from **Cyberpunks Variant Identifiers** (one `<item>` per SKU mapping). Also injects PDP schema helpers (`sku`, `gtin`, `price_amount`, `special_amount`, `offer_url`).

## Requirements

1. Install & enable **Cyberpunks Variant Identifiers** and fill SKU/GTIN mappings.
2. Optional: **Cyberpunks Variant Images** — feed uses matching signature image when present.

## Install

1. Upload `cyberpunks_shop_merchant_feed_1_0_1.ocmod.zip` via Extensions → Installer.
2. Modifications → Refresh.
3. Extensions → Feeds → install/enable **Cyberpunks Merchant Feed**.
4. Open the feed settings, set currency (EUR), optional Google category ID, Enable, Save.
5. Copy the Data Feed URL into Google Merchant Center → Products → Feeds (Scheduled fetch).

Feed URL shape:

`https://YOUR-DOMAIN/index.php?route=extension/feed/cyberpunks_shop_merchant`

## Feed fields

| Google attr | Source |
|-------------|--------|
| `g:id` | Variant Identifiers SKU |
| `g:item_group_id` | OpenCart `product_id` |
| `g:gtin` | Variant GTIN when set |
| `g:mpn` | SKU (when GTIN empty but brand exists) |
| `g:identifier_exists` | `false` only if no GTIN and no brand |
| `g:image_link` | Variant Images (exact signature, or named/id subset match via `resolveCartImage`), else product image |
| `g:price` | Product base/special price (taxed) in feed currency |
| title | Product name + named-signature option values |

## Theme schema (after Refresh)

`github-templates` uses:

- `itemprop="sku"` / `gtin13` when `$sku` / `$gtin` set
- Offer `price` from numeric `$price_amount` / `$special_amount`
- Offer `url` from `$offer_url`

Parent-level identifiers on PDP; variant-level IDs live in the Merchant feed.

## Notes

- Price does not yet include option price modifiers — base product price only.
- Duplicate SKUs are skipped (first wins).
- Disabled identifier rows are skipped.

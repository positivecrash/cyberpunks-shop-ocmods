# Cyberpunks Merchant Feed

Google Merchant XML feed. **Only products with Custom checkbox `merchant_feed` = 1** are exported.

For each checked product:

1. If it has enabled **Variant Identifiers** rows → one `<item>` per SKU mapping.
2. Else → one `<item>` for the main product (model / price / manufacturer / image).

Also injects PDP schema helpers (`sku`, `gtin`, `price_amount`, `special_amount`, `offer_url`).

## Requirements

1. **Cyberpunks Shop Product Fields** — Checkbox field with key **`merchant_feed`** (gate for the whole feed).
2. Install & enable **Cyberpunks Variant Identifiers** (hydrate + variant SKUs when present).
3. Optional: **Cyberpunks Variant Images** — used for variant rows only.

## Install

1. Upload `cyberpunks_shop_merchant_feed_1_2_1.ocmod.zip` via Extensions → Installer.
2. Modifications → Refresh.
3. Extensions → Feeds → install/enable **Cyberpunks Merchant Feed**.
4. Open the feed settings, set currency (EUR), optional Google category ID, Enable, Save.
5. Copy the Data Feed URL into Google Merchant Center → Products → Feeds (Scheduled fetch).

Feed URL shape:

`https://YOUR-DOMAIN/index.php?route=extension/feed/cyberpunks_shop_merchant`

## Setup (every product in the feed)

1. Modules → **Cyberpunks Shop Product Fields** → add Checkbox, key `merchant_feed`, label e.g. “Add to Google Merchant / Meta catalog” (once).
2. On each product Custom tab, check it (Dual, Urban, Insight, Node, …).
3. **With variants:** fill Variant Identifiers (SKU/GTIN) as before — feed exports those rows.
4. **Without variants:** set Data tab **Model** (`g:id`; fallback `oc-{product_id}`), **Manufacturer** (brand), price, optional EAN/UPC/JAN/ISBN as GTIN.

## Feed fields

| Google attr | Variant Identifiers row | Main product (no VI) |
|-------------|-------------------------|----------------------|
| `g:id` | Variant SKU | Product `model`, else `oc-{product_id}` |
| `g:item_group_id` | OpenCart `product_id` | same |
| `g:gtin` | Variant GTIN when set | first of `ean` / `upc` / `jan` / `isbn` |
| `g:mpn` | SKU (when GTIN empty but brand exists) | same as `g:id` |
| `g:identifier_exists` | `false` only if no GTIN and no brand | same |
| `g:image_link` | Variant Images → product image | product main image |
| `g:color` / `g:pattern` | From option signature | — |
| `link` | Product URL + `?variant={SKU}` | Product URL only |
| `g:price` | Product base/special (taxed) | same |
| title | Product name + color/pattern | Product name |

## Changelog

### 1.2.1
- Checkbox `merchant_feed` is required for **all** feed products
- Checked + Variant Identifiers → export VI rows; checked + no VI → export main product

### 1.2.0
- Product-level items via Custom checkbox `merchant_feed=1`

### 1.1.3
- `g:availability` also respects Option Fields palette **In stock** (per color); product qty still required

### 1.1.2
- Skip feed items whose numeric option-value signature no longer exists on the product (deleted option values)

### 1.1.1
- Derive `g:color` / `g:pattern` from numeric option-value signatures (not only `n:` named signatures)

### 1.1.0
- Issue #29: `g:color`, `g:pattern`, per-variant title color, `?variant=SKU` deep links
- Theme: `product-oc.js` applies `?variant=` from Variant Identifiers mappings

### 1.0.1
- Initial Merchant feed from Variant Identifiers + Variant Images

## Theme schema (after Refresh)

`github-templates` uses:

- `itemprop="sku"` / `gtin13` when `$sku` / `$gtin` set
- Offer `price` from numeric `$price_amount` / `$special_amount`
- Offer `url` from `$offer_url`

Parent-level identifiers on PDP; variant-level IDs live in the Merchant feed.

## Notes

- Price does not yet include option price modifiers — base product price only.
- Duplicate SKUs / feed ids are skipped (first wins).
- Disabled identifier rows are skipped.
- After upgrading to 1.2.1, tick `merchant_feed` on Dual/Urban/Insight or they drop out of the feed.

# Cyberpunks Variant Images

Resolve cart item variant image by selected option combination.

## What it does

- Admin UI (module page + **Catalog → Product → Variant Images** tab).
- YAML import/export keyed by **product Model** (portable across local/server; `product_id` still accepted as legacy).
- Sets cart `product.thumb` from the matched variant (keeps themes that only read `thumb` working).
- Also exposes `product.variant_image` for theme-side priority:
  `variant_image` → `fields.category_image` → `thumb`
- Supports **named** signatures (`n:urban-color=green|…`) and legacy numeric id signatures.
- Admin rows stay compact; Edit options expands the builder on demand.
- Large catalogs (e.g. Dual ~1.4k rows) are stored as size-safe setting chunks (`mappings_{id}`, `mappings_{id}_c1`, …) so MySQL `TEXT` does not truncate them.

## Install

1. Upload `cyberpunks_shop_variant_images_1_4_7.ocmod.zip` in Extensions → Installer.
2. Modifications → Refresh.
3. Extensions → Modules → install/enable **Cyberpunks Variant Images**.

## Workflow (local → server)

1. On local product, open **Variant Images** → Export (`variant_images_altruist-dual.yaml`).
2. On server, create/open the product with the **same Model**.
3. Import the YAML on that product tab (or module tab Import next to Export).

## Mapping format (YAML)

```yaml
model: "altruist-dual"
media_path: "catalog/view/theme/cybershops/media/products/altruist-dual/product-previews"
items:
  - options:
      urban-emotion: smile
      urban-color: green
    image: "Altruist-Smile-Urban-Green.webp"
```

`media_path` is optional but recommended — set once per product (also editable in Catalog → Product → Variant Images). Image rows can then be filenames only.

Options are matched by **name**, not option value IDs.

## Changelog

### 1.4.9
- Fix: admin **Image filename** no longer re-expands stored names into full theme paths after import/save (cart still joins `media_path` at runtime).

### 1.4.8
- Import: same product + same option combination **updates Image filename** (no duplicate rows); stores basename only.

### 1.4.7
- Fix: YAML import with `media_path` / filename-only `image` rows (parser skipped rows → “Import format is invalid”).
- UI: real module **Status** Enabled/Disabled control (was mislabeled Product ID dropdown).

### 1.4.6
- Labels: **Full path to image** / **Image filename**.

### 1.4.5
- UI: **Full path to image** input at the top of each product tab on the module page (Extensions → Variant Images).

### 1.4.4
- Feature: per-product **Full path to image** (`media_path`) in admin + YAML — no hardcoded theme path.
- Fix: cart now applies matched `$cart_image` / `variant_image` (OCMOD used product main image only when the replace op was skipped).
- Fix: path resolve soft-rewrites legacy `media/{slug}/…` → `media/products/{slug}/…` without blanking URLs when `is_file` fails.

### 1.4.3
- Fix: cart/checkout variant previews looked for `media/{product}/product-previews/` but files live under `media/products/{product}/product-previews/`; resolve + skip missing files (fall back to product image).
- Fix: cart image block now keys off resolved `$cart_image` (variant or product), not only `$product['image']`.

### 1.4.2
- Fix: YAML import now stores named signatures (`n:opt=val|…`) and skips rows if any option fails to resolve — prevents Hood-* images matching “no hood” carts.
- Fix: equal-specificity cart matches prefer the later mapping (no-hood rows after Hood-* in Dual YAML).

### 1.4.1
- Fix: Dual-sized mapping sets exceeded `oc_setting.value` TEXT (65KB) and were truncated/lost on save; now chunked and other products are preserved when one product is saved.

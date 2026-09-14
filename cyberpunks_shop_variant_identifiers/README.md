# Cyberpunks Variant Identifiers

Map option combinations to **SKU** and optional **GTIN** (same signature model as Variant Images).

## What it does

- Admin UI (module page + **Catalog → Product → Variant SKU/GTIN** tab).
- YAML import/export keyed by **product Model** (portable across local/server; `product_id` still accepted as legacy).
- Storage: option combo signature → `sku` + optional `gtin`.
- Cart exposes `product.variant_sku` / `product.variant_gtin` when a mapping matches.
- PDP schema meta (`sku` / `gtin13`) updates live when options change.

## Install

1. Upload `cyberpunks_shop_variant_identifiers_1_1_1.ocmod.zip` in Extensions → Installer.
2. Modifications → Refresh.
3. Extensions → Modules → install/enable **Cyberpunks Variant Identifiers**.

## Workflow (local → server)

1. On local product, open **Variant SKU/GTIN** → Export (`variant_identifiers_altruist-dual.yaml`).
2. On server, create/open the product with the **same Model** (e.g. `altruist-dual`).
3. Import the YAML on that product tab (or via the module page — resolves by model).

## Mapping format (YAML)

```yaml
model: "altruist-dual"
items:
  - options:
      urban-emotion: enjoy
      urban-color: bright-green
    sku: "AD-ENJOY-BG-..."
    gtin: "1234567890123"
```

Options are matched by **name**, not option value IDs.

Compact storage row: `{ p, s, k, g, t }` (product, signature, sku, gtin, status).

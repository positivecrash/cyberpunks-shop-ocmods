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

## Install

1. Upload `cyberpunks_shop_variant_images_1_4_0.ocmod.zip` in Extensions → Installer.
2. Modifications → Refresh.
3. Extensions → Modules → install/enable **Cyberpunks Variant Images**.

## Workflow (local → server)

1. On local product, open **Variant Images** → Export (`variant_images_altruist-dual.yaml`).
2. On server, create/open the product with the **same Model**.
3. Import the YAML on that product tab (or module tab Import next to Export).

## Mapping format (YAML)

```yaml
model: "altruist-dual"
items:
  - options:
      urban-emotion: smile
      urban-color: green
    image: "altruist-dual/product-previews/Altruist-Smile-Urban-Green.webp"
```

Options are matched by **name**, not option value IDs.

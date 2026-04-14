# Cyberpunks Shop Product Templates

OpenCart 3.x OCMOD for per-product catalog view template selection.

## What it does

1. Adds `Catalog view template` field in product form (`Catalog -> Products -> Design`).
2. Uses per-product `load->view(...)` route from `cyberpunks_template`.
3. Calls `CyberpunksShopHeadIncludes::applyViewRules(...)` before rendering (if head-includes library exists).
4. Loads template suggestions from all themes: `catalog/view/theme/*/template/product/*.twig`.

## Database column

Since version `1.1.0`, the `cyberpunks_template` column is created automatically during first add/edit product call (`SHOW COLUMNS` + `ALTER TABLE`), without a separate module install step.

`install.sql` is included as a fallback for environments where `ALTER` is restricted.

## Build

From repository root:

```bash
./build-ocmod.sh cyberpunks_shop_product_templates
```

## Install

`Installer -> upload cyberpunks_shop_product_templates_*.ocmod.zip -> Modifications -> Refresh`

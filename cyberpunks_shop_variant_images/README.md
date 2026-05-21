# Cyberpunks Variant Images

Resolve cart item image by selected option combination.

## What it does

- Adds a universal resolver: `option_value_id` combination -> image.
- Applies only in cart item thumbnail rendering.
- Falls back to default product image if no mapping found.
- Provides admin UI to manage mappings.

## Install

1. Upload archive in `Extensions -> Installer`.
2. Open `Extensions -> Modifications` and click `Refresh`.
3. Clear theme cache.
4. Open module settings in `Extensions -> Extensions -> Modules -> Cyberpunks Variant Images`.

## Storage (v1.3.14+)

Mappings are stored per `product_id` in separate settings keys to avoid MySQL `TEXT` (64KB) truncation when importing large YAML files (e.g. 300+ rows).

On each catalog request, `startup/startup` hydrates `module_cyberpunks_variant_images_mappings` in config from all shards (cart/checkout review keep using `config->get` without extra OCMOD changes).

## Mapping format

- `product_id`
- `option_value_signature` (sorted option_value_id list joined by `-`, e.g. `45-91-107`)
- `image` (`catalog/...` path)
- `status`

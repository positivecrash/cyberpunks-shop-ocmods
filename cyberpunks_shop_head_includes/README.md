# Cyberpunks Shop Head Includes

OpenCart 3.x OCMOD for managing CSS/JS includes in `<head>` by controller route or view path.

## What it does

1. Adds module `Extensions -> Modules -> Cyberpunks Shop Head Includes`.
2. Supports include/exclude rules (one path per line).
3. Applies controller-phase matching: `*`, `route:...`, or exact route.
4. Applies view-phase matching (for custom product templates), for example `product/product_bundle`.
5. Uses `system/library/cyberpunks_shop_head_includes.php`.

## Migration note

If you previously used old asset rules in `cyberpunks_shop_features`, move settings from:

- `cyberpunks_shop_features_rules`
- to `cyberpunks_shop_head_includes_rules`

## Build

From repository root:

```bash
./build-ocmod.sh cyberpunks_shop_head_includes
```

## Install

`Installer -> upload .ocmod.zip -> Modifications -> Refresh -> Modules -> Install -> Edit`

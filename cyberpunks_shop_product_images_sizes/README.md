# Cyberpunks Shop Product Images Better Quality

OpenCart 3.x OCMOD.

## What it does

- Adds `urlNoResize()` to `catalog/model/tool/image.php`.
- Switches product image output in `catalog/controller/product/product.php` to original file URLs (no resize) for popup/thumb entries.

## Build

From repository root:

```bash
./build-ocmod.sh cyberpunks_shop_product_images_sizes
```

## Install

`Extensions -> Installer -> upload .ocmod.zip -> Modifications -> Refresh`

# Cyberpunks Shop Head Includes

CSS/JS in `<head>` by controller route.

## Product pages

One rule: match key **`product/product`**

```
catalog/view/theme/cybershops/js/product.js
catalog/view/theme/cybershops/js/product-oc.js
```

```
catalog/view/theme/cybershops/css/product.css
```

`product-oc.js` drives delivery text, add-to-cart AJAX, Amazon visibility. Without it the delivery block stays hidden.

Disable old `product/altruist_*` rules — they are unused with the shared template.

## Build / install

```bash
./build-ocmod.sh cyberpunks_shop_head_includes
```

Installer → upload zip → Modifications → **Refresh** (library under `upload/` is installed by the zip).

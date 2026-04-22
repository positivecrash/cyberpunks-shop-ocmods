# Cyberpunks Shop Cart Stock Sum By Product

OpenCart 3.x OCMOD.

## What it does

OpenCart compares main product **Quantity** only to **each cart line** separately. Two lines of the same product with different options (each qty 1) therefore both look “in stock” when product qty is 1.

This patch changes `system/library/cart/cart.php` so the stock check uses **`$discount_quantity`**, which the core already computes as the **sum of quantities for that `product_id` across all cart rows** (same session/cart query).

**OCMOD detail:** non-regex searches are matched **per single line** in OpenCart. The patch must be a **one-line** `search` (v1.0.1). A multi-line CDATA search is skipped and the file is never written to `storage/modification`.

## Install

From repo root:

```bash
./build-ocmod.sh cyberpunks_shop_cart_stock_sum_by_product
```

Then in admin: **Extensions → Installer** (upload zip) → **Extensions → Modifications → Refresh**.

## Caveat

If you intentionally sell the **same** `product_id` as **independent** stock pools per option (SKU per option with subtract), this rule is wrong for you. It matches the case “one physical device, options are configuration”.

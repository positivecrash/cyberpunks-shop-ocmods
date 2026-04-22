# Cyberpunks Shop Cart Empty State

OpenCart 3.x OCMOD.

## What it does

Changes empty cart behavior in `catalog/controller/checkout/cart.php`:

- when cart is empty, render `checkout/cart` instead of `error/not_found`
- initialize required cart template data (`products`, `modules`, `totals`, `action`, `checkout`)

This allows theme-level empty cart partials (for example `template/checkout/partials/cart_empty.twig`) to be displayed.

## Build

From repository root:

```bash
./build-ocmod.sh cyberpunks_shop_cart_empty_state
```

## Install

`Extensions -> Installer -> upload .ocmod.zip -> Modifications -> Refresh`

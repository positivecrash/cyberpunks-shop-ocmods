# Cyberpunks Shop Menu

Header mega menu — **data only** (admin + catalog model). Markup lives in the theme.

## Item config

Each top-level item:

| Field | Description |
|-------|-------------|
| Name | Label |
| Link | Top URL (also “View all” for product panels) |
| Panel | `none` / `products` / `links` |
| Category | For `products` — featured products in that category |
| Links | For `links` — name + href rows |
| Sort / Status | Order and enable |

Featured products need Product Fields: checkbox `featured`, optional `featured_order`, `category_title`, `category_image`, `featured_price_label`.

## Theme

- `template/common/partials/header_nav.twig`
- Uses `product/partials/product_cards.twig` for product panels

`$data['main_menu']` is injected into `common/header` via OCMOD.

## Install

```bash
./build-ocmod.sh cyberpunks_shop_menu
```

1. Extensions → Installer → upload zip  
2. Extensions → Modifications → **Refresh**  
3. Extensions → Modules → **Cyberpunks Shop Menu** → Install → Edit → configure items  

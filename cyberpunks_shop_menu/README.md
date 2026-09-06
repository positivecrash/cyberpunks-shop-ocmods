# Cyberpunks Shop Menu

Header mega menu — **data only** (admin + catalog model). Markup lives in the theme.

## Item config

Each top-level item:

| Field | Description |
|-------|-------------|
| Name | Label per **active store language** (admin inputs with language flags). Storefront shows the name for the current language. |
| Link | Top URL (also “View all” for product panels). With Category set, storefront builds the category URL for the current language. |
| Panel | `none` / `products` / `links` |
| Category | For `products` — featured products in that category; choosing a category prefills Name (all languages from category descriptions) and Link |
| Links | For `links` — multilingual Name + href rows |
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

# Cyberpunks Shop Menu

Header mega menu + footer links — **data only** (admin + catalog model). Markup lives in the theme.

Admin module has two tabs: **Header** and **Footer**.

## Header item config

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

## Footer item config

Simple flat links (no dropdown panels):

| Field | Description |
|-------|-------------|
| Name | Multilingual label |
| Link | Path or URL (`/shipping`, `https://…`) |
| Sort / Status | Order and enable |

If footer menu items are empty, the footer link row is empty (Information “Show in footer info links” is no longer used by the theme).

## Theme

- Header: `template/common/partials/header_nav.twig` — `$data['main_menu']`
- Footer: `template/common/footer.twig` — `$data['footer_menu']` (same shape as `footer_info_links`: `title`, `href`)

## Install

```bash
./build-ocmod.sh cyberpunks_shop_menu
```

1. Extensions → Installer → upload zip  
2. Extensions → Modifications → **Refresh**  
3. Extensions → Modules → **Cyberpunks Shop Menu** → Install/Edit → configure Header / Footer tabs  

## Changelog

### 1.2.0
- Admin tabs **Header** / **Footer**
- Footer links via `module_cyberpunks_shop_menu_footer_items` → storefront `footer_menu`
- Theme shows only `footer_menu` (no fallback to information footer flags)

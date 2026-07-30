# Cyberpunks Shop Product Fields

Custom product fields on the **Custom** tab (Catalog → Products → Edit).

## Field types

| Type | UI |
|------|-----|
| Text | single-line input |
| Textarea (HTML) | multiline HTML (icons via shortcodes) |
| Checkbox | yes/no |
| Select | dropdown (`value` or `value\|Label` per line) |
| Editor | Summernote WYSIWYG |

## Product page body (`page_content`)

1. Add field key `page_content`, type **Textarea (HTML)**
2. Paste HTML below the buy box (no `header` / `product_main` / `footer`)
3. Theme `product/product.twig` outputs it as `{{ fields.page_content|raw }}`

### Icon shortcodes

```
[[icon:yoga-male]]
[[icon:tick]]
[[icon:altruist-insight aria-hidden="false"]]
[[icon:altruist-urban-emotion-smile aria-hidden="false" data-urban-emotion="true"]]
```

Expanded on the catalog to the same SVG sprite markup as `icon.twig`.

Copy-paste starters: `github-templates/template/product/page-content-examples/*.html`

## Theme usage

```twig
{{ fields.amazon_link }}
{{ fields.page_content|raw }}
```

## Install / update

```bash
./build-ocmod.sh cyberpunks_shop_product_fields
```

1. Extensions → Installer → upload zip
2. Modifications → **Refresh**
3. Modules → Cyberpunks Shop Product Fields → configure

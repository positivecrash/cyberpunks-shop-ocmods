# Cyberpunks Shop Product Fields

Custom product fields on the **Custom** tab (Catalog → Products → Edit).

Field definitions (keys, types, labels, sections) are created in **Modules → Cyberpunks Shop Product Fields**. The OCMOD does not hardcode your field keys.

Sections are admin-only grouping: each section has its own sort order, and fields inside it have a separate sort order.

## Field types

| Type | UI |
|------|-----|
| Text | single-line input |
| Textarea (HTML) | multiline HTML (icons via shortcodes) |
| Image | OpenCart file manager thumb picker |
| Checkbox | yes/no |
| Checkbox list | multi-select checkboxes (`value` or `value\|Label` per line) |
| Select | dropdown (`value` or `value\|Label` per line) |
| Editor | Summernote WYSIWYG |

## Catalog usage

- Product page: `{{ fields.your_key }}` (image fields are resized URLs; checkbox list fields are arrays)
- Home / category cards: `product.fields.your_key` — see theme `product/partials/product_card.twig`

Home sections list categories that have products with checkbox key `featured` = yes (up to 3 newest per category). Card title/image/label keys are chosen in the theme, not in this OCMOD.

## Product page body (`page_content`)

Textarea HTML + `[[icon:name]]` shortcodes + `[[option:key]]` for Common Options values. Theme: `{{ fields.page_content|raw }}`.

## Install / update

```bash
./build-ocmod.sh cyberpunks_shop_product_fields
```

1. Extensions → Installer → upload zip  
2. Modifications → **Refresh**  
3. Modules → configure field definitions  

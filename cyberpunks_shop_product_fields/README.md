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
| **List (repeater)** | add/remove rows; each row has sub-fields defined in **Select values** |

### List (repeater) — sub-field schema

In **Select values**, one sub-field per line:

```
key|type|label
model|text|Model (.glb)
texture|text|Texture (.png)
model_show|select|Model type|full,urban-only,Insight-only
```

- **Types:** `text`, `textarea`, `select`, `checkbox`, `checkboxes`, `image`
- For `select` / `checkboxes`, add options after label: `key|select|label|val1,val2` or `val:Label`

Values are stored as JSON (one DB row per repeater field). In Twig: `fields.my_list[0].model`.

## Catalog usage

- Product page: `{{ fields.your_key }}` (image fields are resized URLs; checkbox list and list fields are arrays)
- Home / category cards: `product.fields.your_key` — see theme `product/partials/product_card.twig`
- Cart / checkout: `product.fields` is attached the same way; theme picks image via `variant_image` → `fields.category_image` → `thumb`

### `3d_scripts` (checkbox list)

Checked script paths are registered **once** in `<head>` via `registerProductScripts()` (product controller).  
Do **not** also loop them as `<script>` in product/gallery twig — that loads Three.js twice (`WARNING: Multiple instances of Three.js`).

Prefix a path with `module:` to emit `type="module"` (Head Includes script-type map).

### Multiple 3D models in gallery

Use a **List** field with key `3dmodels` in section **3D model** (sub-fields: `model`, `texture`, `model_show`, `script`).

The theme renders gallery tabs from `fields['3dmodels']` — see `github-templates/template/product/partials/gallery.twig`.  
Product-level **Scripts for this product** (`3d_scripts` checkbox list) loads shared JS once in the header.

Home sections list categories that have products with checkbox key `featured` = yes (up to 3 newest per category). Card title/image/label keys are chosen in the theme, not in this OCMOD.

## Product page body (`page_content`)

Textarea HTML + `[[icon:name]]` shortcodes + `[[option:key]]` for Common Options values. Theme: `{{ fields.page_content|raw }}`.

## Changelog

### 1.5.6
- Removed gallery-specific `model3d_gallery` builder from catalog model (theme reads `fields['3dmodels']` directly)

### 1.5.5
- (reverted) 3D gallery key fix — moved to theme

### 1.5.4
- New field type **List (repeater)** — rows with configurable sub-fields (text, select, image, etc.)

### 1.5.3
- Multiple 3D gallery slides: `model3d_extra_models` textarea (one `.glb` per line) + `fields.model3d_gallery`
- Theme: loop 3D views/tabs; `3d-product.js` inits every `[data-ui="model-viewer"]`

### 1.5.2
- Register `3d_scripts` checkbox paths in document **header** only (`registerProductScripts`), with path dedupe
- Prevents head+body double include when gallery also outputted those scripts

## Install / update

```bash
./build-ocmod.sh cyberpunks_shop_product_fields
```

1. Extensions → Installer → upload zip  
2. Modifications → **Refresh**  
3. Modules → configure field definitions  

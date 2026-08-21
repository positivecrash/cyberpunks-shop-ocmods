# Cyberpunks Shop Common Options

Store-wide custom options with the same admin layout as **Product Fields**: sections, field definitions, per-section sort order.

## Admin

**Extensions → Extensions → Modules → Cyberpunks Shop Common Options**

- **Field definitions** — sections + fields (key, type, label, hints)
- **Values** — edit current values for all active fields (grouped by section)

Field keys are not hardcoded in the OCMOD.

## Theme usage

```twig
{{ common_options.your_key }}
```

Available in both `header` and `footer` templates.

Image fields are resized URLs. HTML/textarea fields support `[[icon:name]]` shortcodes (same as product fields).

## Shortcodes in product Page Content

In Product Fields HTML/textarea (e.g. `page_content`):

```html
<a href="[[option:twitter]]">X</a>
```

Inserts the Common Options value for that key (escaped for text/url; raw for html/textarea fields).

## Build / install

```bash
./build-ocmod.sh cyberpunks_shop_common_options
```

1. Extensions → Installer → upload zip  
2. Modifications → **Refresh**  
3. Extensions → Modules → **Cyberpunks Shop Common Options** → configure fields and values

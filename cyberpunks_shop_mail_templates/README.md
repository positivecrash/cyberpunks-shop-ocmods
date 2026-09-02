# Cyberpunks Shop Mail Templates

OpenCart 3.x **Module + OCMOD**: customer email **subjects** + **HTML** per **order status**, with reusable **named layouts**.

## Tabs

1. **Templates** — create several layouts (name + HTML). Use `{content}` for the status body.
2. **Paid / Pending / Processing / Shipped / Canceled / Complete** — subject, pick a layout, status HTML body.

## How it works

Final HTML = layout HTML with `{content}` replaced by the status body (shortcodes resolved).  
No layout selected and empty status HTML → OpenCart stock mail.  
Custom HTML is used automatically when a **Template** is selected or the status HTML body is filled.

## Install

```bash
./build-ocmod.sh cyberpunks_shop_mail_templates
```

1. Installer → upload zip → Modifications → **Refresh**
2. Modules → **Cyberpunks Mail Templates** → Install → Edit → Enabled → Save

## Changelog

### 1.1.5
- `{order_products}` images use **Variant Images** OCMOD mapping when available (same as cart/checkout)

### 1.1.4
- HTML emails send **only** the admin template markup (no generated plain-text blurb that Proton showed above the layout)
- Text part is a single space solely to suppress OpenCart’s default “does not support HTML email” stub

### 1.1.3
- Fix broken “blank” emails: plain-text part is short again (full HTML dump made some clients show text-only)
- Safer absolute URL rewrite for `/catalog/...` images (failed regex no longer wipes HTML); prefer order `store_url` / HTTPS host

### 1.1.2
- Fix scary plain-text stub (“This is a HTML email and your email client…”) — always send a real text alternative with HTML
- Auto-rewrite root-relative `src`/`href` (`/catalog/…`) to absolute store URLs so social icons load in mail clients

### 1.1.1
- `{order_products}` option labels use Cyberpunks **Display Name** (not internal keys like `insight-desktop-stand-color`)

### 1.1.0
- `{order_products}` — products block (image, name, options, qty) matching store email layout
- Removed bulky shortcodes (`products_table`, `products_cards`, `totals_table`, `link`, `download`, …)
- Removed “Use custom HTML for this status” checkbox — custom mail when Template is selected or HTML body is filled

### 1.0.9
- `{comment}` — Add History → Comment (Notify Customer); safe HTML with line breaks; listed on Templates + status tabs

### 1.0.8
- Fix HTML emails showing raw tags in Gmail: OpenCart admin `Request::clean()` entity-encodes POST (`&lt;meta&gt;`); decode on save/send so `setHtml()` gets real markup

### 1.0.7
- Auto-restore `<!DOCTYPE html>`, `<html>`, `<head>`, `<body>` on layout save and when sending mail (Summernote-truncated templates)
- Explicitly disable Summernote on HTML fields (plain monospace textarea)

### 1.0.6
- Templates / status HTML: plain code textarea (Summernote was stripping html/head/body)

### 1.0.5
- Fix OCMOD: OpenCart only matches single-line `<search>` (multi-line searches were skipped)

### 1.0.4
- Templates tab: multiple named layouts with `{content}` shortcode
- Per-status layout selector (replaces header/footer)

### 1.0.3
- Main template header/footer (superseded)

### 1.0.2
- Tabs by order status

### 1.0.1
- Subjects in this module

### 1.0.0
- Initial HTML templates

# Cyberpunks Shop Marketing

OpenCart 3.x OCMOD + **Advertising** extension: **Google Tag Manager**, **GA4 dataLayer events**, and **Matomo Analytics**.

Replaces `cyberpunks_shop_gtm` and `cyberpunks_shop_matomo_analytics`.

Files live under `extension/advertise/` so the extension appears in **Extensions → Advertising** (next to Google Shopping).

## Features

- GTM container in `<head>` + noscript after `<body>`
- Matomo tracking snippet in `<head>`
- **Google cookie consent banner** (configurable text, buttons, expiry) + Consent Mode v2 defaults
- **purchase** dataLayer event + Matomo ecommerce order on checkout success
- **view_item** dataLayer event on product pages
- Single admin screen for GTM and Matomo settings

Google Merchant Center / product feed export is **not** included yet.

Do **not** add gtag.js or GA4 directly to the theme — configure GA4 as a tag inside GTM.

## Changelog

### 1.2.0
- Configurable Google cookie consent banner (admin: message, privacy link, button labels, expiry days)
- Consent choice stored in localStorage with expiry (default 30 days); banner HTML rendered by OCMOD
- Consent Mode v2 defaults only when consent banner is enabled

### 1.1.2
- Google Consent Mode v2 defaults injected before GTM when GTM is enabled

### 1.1.1
- Fix PHP 8 fatal error: `str_ireplace()` 4th argument is `$count` by reference, not a replace limit

### 1.1.0
- Move extension from Modules to **Advertising** (`extension/advertise/cyberpunks_shop_marketing`)
- Settings keys renamed to `advertise_cyberpunks_shop_marketing_*`
- Install migrates from old `module_cyberpunks_shop_marketing_*` keys and unregisters the module entry

### 1.0.0
- Initial release (GTM + Matomo merged)

## Install

```bash
./build-ocmod.sh cyberpunks_shop_marketing
```

1. Extensions → Installer → upload `.ocmod.zip`
2. Extensions → Modifications → **Refresh**
3. Extensions → **Advertising** → **Cyberpunks Shop Marketing** → **Install** (green +) → Edit
4. Configure GTM and/or Matomo

If upgrading from v1.0.0 (Modules):

1. Uninstall **Cyberpunks Shop Marketing** from Modules (if installed)
2. Upload new zip → Refresh modifications
3. Install from **Advertising**

Settings are migrated automatically on Install.

## How to verify events

### purchase (checkout success)

1. Place a test order — land on `/checkout/success` (do not refresh)
2. **View Page Source** → search `cyberpunks-marketing: purchase` before `</body>`

Refresh won't show the event — `order_id` is cleared from session on first load (by design).

### view_item (product page)

View Page Source → search `cyberpunks-marketing: view_item` before `</body>`.

## GTM setup (outside OpenCart)

1. GA4 Configuration tag in GTM (no gtag on site)
2. Custom Event trigger: `purchase` → GA4 Event tag
3. Custom Event trigger: `view_item` → GA4 Event tag

## Theme

Marketing snippets and the consent banner are injected via OCMOD (`header` + `footer`). Theme keeps banner styles in `main-oc.css` and consent logic in `main-oc.js`.

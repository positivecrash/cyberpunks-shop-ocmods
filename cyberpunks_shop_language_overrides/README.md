# Cyberpunks Shop Language Overrides

Minimal Dutch core (`nl-nl.php`), URL locales (`/en/`, `/nl/`), **cb_lang** theme strings (admin), shared SEO keywords, hreflang / `og:locale`, cart totals, thousand separator, **auto language pack stubs**.

## Install

1. `./build-ocmod.sh cyberpunks_shop_language_overrides`
2. Upload `cyberpunks_shop_language_overrides_1_9_1.ocmod.zip`
3. Extensions → Modifications → **Refresh**
4. Open **Extensions → Modules → Cyberpunks Language Overrides** once (tables + seed)
5. Clear Twig cache if needed: `system/storage/cache/template/`

## Adding a new store language

Stock OpenCart only lets you pick a Code from folders that already exist. This module:

1. Turns **Code** into a text field (`de-de`, `fr-fr`, …)
2. On save, creates minimal stubs if missing:
   - `catalog/language/{code}/{code}.php` (+ png)
   - `admin/language/{code}/{code}.php` (+ png)
   - PHP copied from `en-gb`, with `$_['code']` set to the short locale (`de`, `fr`, …)
   - Flag PNG downloaded from flagcdn (`de-de` → Germany, etc.); if download fails, falls back to en-gb placeholder. Re-saving a language replaces a leftover en-gb copy.

Full PHP language packs are still optional — `Language::load()` falls back to en-gb. Theme strings stay in **cb_lang**; Menu / Marketing already show fields for every enabled language.

Requires write access to `catalog/language/` and `admin/language/`.

## Why so few `nl-nl` files?

OpenCart `Language::load()` always loads **en-gb first**, then overlays `nl-nl` if the file exists. Missing Dutch files → English. Theme copy goes through `cb_lang` in admin, not PHP language packs.

Kept:

| Path | Why |
|------|-----|
| `catalog/language/nl-nl/nl-nl.php` (+ png) | language bootstrap / formats |
| `admin/language/nl-nl/nl-nl.php` (+ png) | optional Dutch admin |
| `admin/.../cyberpunks_language_overrides.php` | this module’s admin UI |

## Two kinds of text

1. **Theme Strings (cb_lang)** — marketing / theme UI. Add yourself. Original = EN.
2. **OpenCart Language Overrides** — stock keys from `checkout/cart`, `checkout/checkout`, `error/not_found`, checkout facade. Same map as before (`route:key`).

## Theme translations: `cb_lang`

```twig
{{ cb_lang('Buy now') }}
{{ cb_lang('Buy now — %s', heading_title) }}
```

Exact original string + current language. No translation → original text.

Admin: **Extensions → Modules → Cyberpunks Language Overrides** — original, comment, per-language translations.

**CSV import/export** (Theme Strings tab): download/upload all strings. Columns: `source_text`, `comment`, then one column per enabled non-English language (`nl-nl`, `de-de`, …). Import merges by Original EN; empty cells do not clear existing translations.

## URL locale / SEO

Same as 1.6.x: `/en/` `/nl/`, shared SEO keywords across languages, hreflang. Route SEO keywords auto-copy to new active languages. **Product / category / information / manufacturer SEO keywords** are also copied to every active language when a language is added (or when gaps are detected on storefront / module open).

## After refresh

`ocmod.log` should not show `NOT FOUND` for `twig.php` / `catalog.php`. Do **not** use a leading `\` before class names in OCMOD XML — OpenCart strips it and breaks namespaced files like `Template\Twig`. Use `call_user_func(array('CyberpunksCbLang', ...))` instead.

## No hardcoded theme strings

In this module we intentionally removed default hardcoded seeding of `cb_lang` theme strings.
Theme uses `cb_lang()` and you add/edit all translated strings manually in **Extensions → Modules → Cyberpunks Language Overrides**.

## Safety: missing Twig templates

In `cyberpunks_shop_language_overrides` we patch `system/library/template/twig.php` so missing Twig templates (like `cybershops/template/common/language.twig`) return an empty string instead of causing `exit()`/500/white screens.

## Half-finished installs

OpenCart's installer copies `upload/` with `rename()` and ignores failures, so an install can report success while `system/library/*.php` never lands on the server. Two safeguards:

- `twig.php` registers a pass-through `cb_lang` when `cyberpunks_cb_lang.php` is missing, so the storefront still renders instead of dying on `Unknown "cb_lang" function`.
- The module admin page lists any missing runtime files at the top.

If files are missing, upload them from the zip's `upload/` tree by SFTP and run Modifications → **Refresh**.

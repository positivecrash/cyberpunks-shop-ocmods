# Cyberpunks Shop Language Overrides

Minimal Dutch core (`nl-nl.php`), URL locales (`/en/`, `/nl/`), **cb_lang** theme strings (admin), shared SEO keywords, hreflang / `og:locale`, cart totals, thousand separator.

## Install

1. `./build-ocmod.sh cyberpunks_shop_language_overrides`
2. Upload `cyberpunks_shop_language_overrides_1_7_4.ocmod.zip`
3. Extensions → Modifications → **Refresh**
4. Open **Extensions → Modules → Cyberpunks Language Overrides** once (tables + seed)
5. Clear Twig cache if needed: `system/storage/cache/template/`

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

## URL locale / SEO

Same as 1.6.x: `/en/` `/nl/`, shared SEO keywords across languages, hreflang.

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

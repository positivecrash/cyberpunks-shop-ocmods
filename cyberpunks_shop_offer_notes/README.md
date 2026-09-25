# Cyberpunks Shop Offer Notes

Adds a multilingual **Note** on each row of product **Special** and **Discount** tabs. Notes are stored as JSON (`language_id` → text) on `product_special.note` / `product_discount.note` and shown on the product page for the current store language.

## Install

```bash
./build-ocmod.sh cyberpunks_shop_offer_notes
```

1. Extensions → Installer → upload zip (copies `upload/` models)  
2. Modifications → **Refresh**  
3. Catalog → Product → **Special** / **Discount** → fill **Note** per language → Save  

Schema columns are created automatically on product save (and on admin load of Special/Discount).

## Theme

`github-templates/template/product/partials/price.twig`:

- `special_note` — note from the active Special (current language)  
- `discounts[].note` — per quantity-discount row (current language)  

Deploy the theme after installing the OCMOD.

## Changelog

### 1.1.1
- Fix notes not saving: OpenCart Model has no `__isset`, so `isset($this->model_…)` skipped `encodeNote` / decode

### 1.1.0
- Fix save error: ensure `note` column exists before INSERT (not only on get)
- Multilingual notes: per-language inputs in admin; JSON storage; catalog resolves by `config_language_id`
- Column type upgraded to TEXT when previously VARCHAR(255)

### 1.0.2
- Fix Note column header (OpenCart OCMOD only matches single-line searches)

### 1.0.1
- Fix admin Special/Discount Note column (OCMOD searches were too large and skipped)

### 1.0.0
- Note column on Special and Discount admin rows  
- Catalog: `special_note` + `note` on each `discounts[]` item  

# Cyberpunks Shop Checkout

Unified checkout package (replaces `checkout_facade`, `checkout_hardening`, `checkout_review_data`, `checkout_success_review_data`).

## Includes

| Part | What |
|------|------|
| **Facade module** (upload) | Single-page checkout API, Revolut express, payment review |
| **Hardening** (OCMOD) | Safe `comment` handling in shipping/payment method saves |
| **Review data** (OCMOD) | `products` / `totals` on `checkout/checkout` for theme review column |
| **Success review** (OCMOD) | Order summary data on `checkout/success` |

Theme picks cart/checkout thumbs: `variant_image` → `fields.category_image` → `thumb`.

## Install / upgrade from split packages

1. In **Extensions → Modifications**, disable and **delete** (if present):
   - `cyberpunks_shop_checkout_facade`
   - `cyberpunks_shop_checkout_hardening`
   - `cyberpunks_shop_checkout_review_data`
   - `cyberpunks_shop_checkout_success_review_data`
2. Upload `cyberpunks_shop_checkout_1_0_19.ocmod.zip` via **Extensions → Installer**.
3. **Refresh** modifications.
4. Enable **Cyberpunks Checkout Facade** under **Extensions → Extensions → Modules** (same module as before — settings keys unchanged).

> Do not leave old modifications enabled alongside this one — patches would apply twice.

## Admin (Extensions → Extensions → Modules)

- **Auto-select single payment method** (default: Yes)  
  If only one payment method is available, it is written to the session and the payment block is re-rendered with that method already selected.

> OpenCart still needs a valid **payment address** in the session before it can list payment methods.

## Routes

Internal controller name stays `cyberpunks_checkout_facade` (theme + SEO URLs unchanged):

- `extension/module/cyberpunks_checkout_facade/section`
  - `?section=guest|payment_address|shipping_method|payment_method|confirm`
- `extension/module/cyberpunks_checkout_facade/save_guest`
- `extension/module/cyberpunks_checkout_facade/save_shipping`
- `extension/module/cyberpunks_checkout_facade/save_payment`
- `extension/module/cyberpunks_checkout_facade/confirm`
- `extension/module/cyberpunks_checkout_facade/payment` (payment review page)
- `extension/module/cyberpunks_checkout_facade/express_params`
- `extension/module/cyberpunks_checkout_facade/express_shipping`
- `extension/module/cyberpunks_checkout_facade/express_shipping_option`
- `extension/module/cyberpunks_checkout_facade/express_prepare_order`
- `extension/module/cyberpunks_checkout_facade/express_sync_from_revolut` (after Revolut Pay: pull customer/shipping from Merchant API)
- `extension/module/cyberpunks_checkout_facade/express_validate_address` (Revolut Pay Fast checkout synchronous webhook)

SEO alias: `extension/cyberpunks_checkout_facade/payment` → facade payment action.

## Revolut / Apple Pay notes

Checkout express UI (theme) uses Revolut **paymentRequest** (Apple Pay / Google Pay) + standalone **Revolut Pay** button. Card stays on the manual form → `/payment` path.

Revolut Pay Fast checkout needs a registered HTTPS synchronous webhook (`fast_checkout.validate_address`) pointing at `express_validate_address`.

**Local note:** Revolut Pay QR / mobile app checkout cannot complete on `cyber.local`.

**Apple Pay / Google Pay:** Wallet contact uses W3C `addressLine` / `recipient` / `email`. The express flow must not fall back to `express@cyberpunks.shop` placeholders when creating the OpenCart order.

**Revolut Pay:** Fast checkout webhook has shipping address only (no email). After payment, `express_sync_from_revolut` copies `customer` (+ shipping) from the Merchant API into the OC order before confirm.

**Apple Pay:** Register the domain once in admin: **Payments → Revolut Apple Pay / Google Pay → Save**.

## Guest save / address changes

After `save_guest`, the facade rebuilds shipping quotes and **re-applies the previous shipping selection** when still available.

## Payment gateways

`checkout/confirm` outputs `{{ payment }}` with gateway scripts. The storefront `checkout.twig` must not strip those scripts when injecting confirm HTML.

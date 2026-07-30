# Cyberpunks Shop Checkout Facade

Facade module for single-page checkout.

## Admin (Extensions → Extensions → Modules)

Install **Cyberpunks Checkout Facade** and open its settings.

- **Auto-select single payment method** (default: Yes)  
  If only one payment method is available, it is written to the session and the payment block is re-rendered with that method already selected. No extra JavaScript in the theme is required.

> OpenCart still needs a valid **payment address** in the session before it can list payment methods. The block fills in after guest/billing data is saved; with this option, a single method appears as already chosen.

## Routes

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
- `extension/module/cyberpunks_checkout_facade/express_validate_address` (Revolut Pay Fast checkout synchronous webhook)

Checkout express UI (theme) uses Revolut **paymentRequest** (Apple Pay / Google Pay) + standalone **Revolut Pay** button. Card stays on the manual form → `/payment` path.

The manual payment method list hides `revolut_prb` and `revolut_pay` (duplicates of top express). Card + COD remain.

Revolut Pay Fast checkout needs a registered HTTPS synchronous webhook (`fast_checkout.validate_address`) pointing at `express_validate_address`.

**Local note:** Revolut Pay QR / mobile app checkout cannot complete on `cyber.local` — Revolut’s servers and the phone app cannot reach localhost, and Fast checkout webhook requires public HTTPS. Test Revolut Pay on production.

**Apple Pay:** On iPhone only Apple Pay is expected (not Google Pay). Register the domain once in admin: **Payments → Revolut Apple Pay / Google Pay → Save** (needs the `.well-known/...` file on the server). Do not run domain registration from `express_params` — it blocked checkout in 1.3.6–1.3.7.

## SEO Url alias route

Some SEO URL modules map the `route` string **exactly** and don't match `extension/module/...`.
For that case the facade ships an alias controller in installer-allowed path:

- `extension/cyberpunks_checkout_facade/payment` → proxies to `extension/module/cyberpunks_checkout_facade/payment`

## Scope

- Keeps custom checkout UI talking to one controller namespace.
- Enforces billing=shipping for guest checkout.
- Returns refreshed section HTML payloads after save actions.

## Guest save / address changes

After `save_guest`, OpenCart traditionally clears `shipping_method` whenever the address changes. The facade rebuilds available quotes for the new address and **re-applies the previous shipping selection** if that option (`extension.quote_key`) is still returned — e.g. changing only Region/State usually keeps Standard/Express available.

## Payment gateways (e.g. Revolut)

`checkout/confirm` outputs the order table plus `{{ payment }}` (the active payment module’s HTML and **&lt;script&gt;** tags). Gateways like **Revolut Pay** load `revolut_helper.js` → Revolut `embed.js` → `revolut_pay.js`, which mounts the widget on `#revolut-pay-field`. There is **no** `#button-confirm` in default `confirm.twig`— that was an old bank-transfer pattern. The storefront `checkout.twig` must **not** strip those scripts when injecting confirm HTML, and should treat Revolut container divs as a successful bootstrap (see `injectHtmlWithScripts` in the theme).

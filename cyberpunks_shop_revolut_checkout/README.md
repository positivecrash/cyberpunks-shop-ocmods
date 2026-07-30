# Cyberpunks Shop Revolut Checkout

- **/payment** — classic Revolut **card field** (`revolut_card.cyberpunks.js`)
- **/checkout** express — theme `checkout-revolut-express.js` (Revolut Pay / Apple Pay / Google Pay)
- Adds `redirect_url` when creating Merchant API orders (required for Revolut Pay mobile return)
- **1.2.1** — ships fixed admin PHP/twig that actually register Apple Pay domain and show OK/FAILED after Save

Requires the official **Revolut Gateway for OpenCart** extension (backend).

## Install

1. Build and upload:

```bash
./build-ocmod.sh cyberpunks_shop_revolut_checkout
```

2. OpenCart admin → Extensions → Installer → upload `cyberpunks_shop_revolut_checkout_1_2_1.ocmod.zip`
3. Extensions → Modifications → **Refresh**
4. Keep **Checkout Facade** `1.3.8+`

## Apple Pay domain (required)

Console `applePay canMakePayment → null` means the domain is **not** registered with Revolut/Apple.

1. Confirm: `https://cyberpunks.shop/.well-known/apple-developer-merchantid-domain-association` → **200**
2. Admin → Extensions → Payments → **Revolut Payment Gateway - Apple Pay / Google Pay**
3. Leave **Status = Disabled** (express buttons are on `/checkout`, not this OC method)
4. Click **Save**
5. Green banner must say **Apple Pay domain: OK** (not FAILED)
6. The form shows Apple Pay domain status (OK / Not registered)

On **iPhone Safari**: Revolut Pay + Apple Pay (no Google Pay — normal).  
On **Mac Safari**: Apple Pay + Google Pay when Wallet/domain are OK.

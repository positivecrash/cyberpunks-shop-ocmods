# Cyberpunks Shop Checkout Hardening

Defensive OCMOD patch for legacy checkout payload assumptions.

## What it fixes

- Prevents PHP warnings when `comment` is absent in:
  - `catalog/controller/checkout/shipping_method.php`
  - `catalog/controller/checkout/payment_method.php`

Both assignments now safely fallback to an empty string:

```php
strip_tags(isset($this->request->post['comment']) ? $this->request->post['comment'] : '')
```

## Why this matters

Without this patch, missing `comment` can emit PHP warnings into AJAX output and break JSON parsing on checkout.


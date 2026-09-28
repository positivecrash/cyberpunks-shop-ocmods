# Cyberpunks Shop Visitor Country

Stores only the country ISO in browser `localStorage` (`cyberpunks_visitor_country`). **IP is not stored in the browser.**

## Why not “check IP locally”?

The browser cannot read the visitor’s public IP without a network call. So IP stays on **our shop server** (PHP session), not in `localStorage`.

## Flow

1. JS calls our endpoint `index.php?route=extension/module/cyberpunks_visitor_country`
2. Server sees current IP:
   - same IP as last time → return country from **session** (no geojs)
   - new IP / VPN → geojs once, save in session
3. JS updates `localStorage` with the ISO (for `get()` / UI)
4. Docker private IP → endpoint returns `local`, JS falls back to geojs in the browser once

Dev override: `?visitor_country=CY`

## API

```js
CyberpunksVisitorCountry.get()
CyberpunksVisitorCountry.set(iso, { manual: true })  // sticky header choice
CyberpunksVisitorCountry.ready()
CyberpunksVisitorCountry.isManual()
CyberpunksVisitorCountry.clearManual()
```

Manual country (header select) is stored with `cyberpunks_visitor_country_manual=1` and is **not** overwritten by geo/IP on later page loads.

## Install

```bash
./build-ocmod.sh cyberpunks_shop_visitor_country
```

Extensions → Installer → Modifications → Refresh.

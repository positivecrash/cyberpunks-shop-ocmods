# Order Admin Features

OpenCart 3.x OCMOD: **admin-only shipment photos** on **Sales → Orders → [order]**.

Photos are **not** emailed or shown to customers. Files live under `system/storage/order_shipment/{order_id}/` and are served only through the admin route (requires `sale/order` access).

## Features

- New tab **Shipment photos** on the order info page
- Upload PNG / JPG / HEIC / WebP (multi-file + drag-and-drop)
- On upload: convert to **WebP** (quality ~80), longest side max **2000px**, plus a small thumb
- Delete photos from the same tab
- HEIC needs **Imagick** with HEIC support on the server; otherwise use JPG/PNG/WebP

## Install

```bash
./build-ocmod.sh order_admin_features
```

1. Extensions → Installer → upload the zip  
2. Modifications → **Refresh**  
3. No separate module Install/Enable — the tab appears on order info after refresh

## Permissions

Uses existing **Sales → Orders** access / modify permissions.

## Storage

| What | Where |
|------|--------|
| Files | `DIR_UPLOAD/order_shipment/{order_id}/*.webp` (served only via admin route) |
| DB | `{prefix}order_shipment_photo` (created on first use) |

## Changelog

### 1.0.7
- Store files under `DIR_UPLOAD/order_shipment/` (writable) instead of a new folder at `DIR_STORAGE` root

### 1.0.6
- Surface real convert/storage errors in the admin alert (e.g. missing GD WebP, storage permissions)

### 1.0.5
- Browser prepares every photo before upload (HEIC→JPEG if needed, max side 2000px) — originals are not uploaded
- Server only converts that small file to WebP

### 1.0.4
- Permission: module route allowed when user has Sales → Orders

### 1.0.0
- Initial shipment photo tab

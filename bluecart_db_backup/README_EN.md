# 🧩 BlueCart DB Backup Manager — PHPsafe Edition v1.0

A complete **automatic database backup solution for OpenCart 3.x**, with built-in FTP upload, e-mail notifications, and auto-cleanup for old backups.  
Developed by **BlueCart DevTeam**, for store owners who value safety, automation, and full PHP 8 compatibility.

---

## 🚀 Key Features

✅ **Automatic Database Backup**
- Full SQL export (no `mysqldump` required)
- Fully compatible with PHP 7.4 – 8.3  
- Supports large tables and `utf8mb4` charset

✅ **Optional ZIP Compression**
- Creates `.zip` archives for smaller storage size
- Toggle on/off from the admin panel

✅ **Automatic Scheduling (CRON)**
- Set custom time, day, and frequency (daily / weekly)
- Cron command line automatically generated

✅ **Integrated FTP Upload**
- Secure FTP connection with host, port, user, password, and passive mode
- Test FTP connection directly from admin
- Works with DigiStorage, FileZilla FTP Server, and standard cPanel FTP

✅ **Smart E-mail Notifications**
- Send backup as attachment (optional)
- Fully multilingual subject and message (Romanian / English / Spanish)
- Dedicated “Test E-mail” button in admin

✅ **Automatic Backup Cleanup**
- Keep a set number of backups (e.g. last 5)
- Delete old backups manually from the list

✅ **Modern, Responsive Interface**
- Compatible with default OpenCart 3 theme and Journal 3
- Tooltips for all settings
- “Test FTP” and “Test Email” buttons integrated neatly

✅ **Detailed Logging**
- Every operation is logged (backup / delete / FTP / e-mail)
- Log file location: `/system/storage/logs/bluecart_db_backup.log`

---

## 🌐 Multilingual Support

This module includes full translations for:
- 🇷🇴 Romanian  
- 🇬🇧 English   

---

## ⚙️ System Requirements

- OpenCart 3.0.0.0 – 3.0.3.9  
- PHP 7.4 – 8.3  
- Enabled PHP extensions: `zip`, `mysqli`, `json`, `ftp`  
- Cron access (via cPanel or server)

---

## 📦 Installation

1. Go to **Extensions → Installer** in your admin panel.  
2. Upload the file `bluecart_db_backup.ocmod.zip`.  
3. Activate the module from **Extensions → Modules → BlueCart DB Backup**.  
4. Configure settings:
   - Enable automatic backups.
   - Set frequency and time.
   - Fill in FTP and e-mail settings (optional).
5. Copy the generated cron command and add it to your hosting control panel.

---

## 🔔 BlueCart Recommendations

- Enable ZIP compression for large databases.  
- Keep 5–10 backups for optimal performance.  
- Check the log file regularly for FTP or mail errors.  
- Test e-mail sending after each SMTP configuration change.  

---

## 🧠 Security

- Backups are stored in **`/system/storage/backup/`** (outside the public web directory).  
- Each backup file includes date and timestamp.  
- Direct browser access is restricted.  

---

## 💬 Support & Development

📧 **BlueCart DevTeam**  
✉️ dev@bluecart.ro  
 
© 2025 BlueCart.ro — eCommerce Software Development.  
*All rights reserved. Redistribution without permission is prohibited.*

---

## 📘 Upcoming Features (Roadmap)

- ☁️ **BlueCart Cloud Sync** integration (Google Drive / Dropbox / DigiStorage WebDAV)  
- 📲 Notifications via Telegram / Slack  
- 📊 Backup reports in dashboard  

---

### 🔖 Tags
`backup`, `database`, `ftp`, `email`, `cron`, `security`, `automation`, `BlueCart`, `OpenCart 3`, `cloud sync`

# 🧩 BlueCart DB Backup Manager — PHPsafe Edition v1.0

**Modul complet pentru backup automat al bazei de date OpenCart 3.x**, cu sincronizare FTP, notificări prin e-mail și curățare automată a fișierelor vechi.  
Dezvoltat de **BlueCart DevTeam**, pentru administratori care vor siguranță, automatizare și compatibilitate completă PHP 8.

---

## 🚀 Caracteristici principale

✅ **Backup automat al bazei de date**
- Export SQL complet (fără `mysqldump`)
- Compatibil 100% cu PHP 7.4 – PHP 8.3  
- Suport pentru tabele mari și charset `utf8mb4`

✅ **Compresie ZIP opțională**
- Creează fișiere `.zip` compacte pentru spațiu redus
- Activează/dezactivează din panoul admin

✅ **Planificare automată (cron)**
- Setează ziua, ora și frecvența (zilnic / săptămânal)
- Linie cron generată automat pentru server

✅ **Upload FTP integrat**
- Conectare sigură cu host, port, user, parolă și mod pasiv
- Testare conexiune direct din panoul admin
- Compatibil cu DigiStorage, FileZilla FTP Server, cPanel FTP etc.

✅ **Notificări e-mail inteligente**
- Trimite backupul atașat pe e-mail (opțional)
- Subiect și conținut complet multilingv (Română / Engleză / Spaniolă)
- Buton dedicat „Testează trimiterea e-mailului”

✅ **Curățare automată a backupurilor**
- Poți alege câte copii să păstreze (ex: ultimele 5)
- Ștergere manuală din listă direct în admin

✅ **Interfață modernă și responsive**
- Design compatibil cu tema standard OpenCart 3 și Journal 3
- Tooltip-uri de ajutor pentru fiecare setare
- Butoane „Test FTP” și „Test Email” integrate elegant

✅ **Loguri complete**
- Fișierele de log includ fiecare operațiune (backup / ștergere / e-mail / FTP)
- Localizare: `/system/storage/logs/bluecart_db_backup.log`

---

## 🌐 Multilingv

Modulul include traduceri complete pentru:
- 🇷🇴 Română  
- 🇬🇧 Engleză  

---

## ⚙️ Cerințe sistem

- OpenCart 3.0.0.0 – 3.0.3.9  
- PHP 7.4 – 8.3  
- Extensii PHP active: `zip`, `mysqli`, `json`, `ftp`  
- Acces cron (în cPanel sau server)

---

## 📦 Instalare

1. Accesează **Extensions → Installer** în admin.  
2. Încarcă fișierul `bluecart_db_backup.ocmod.zip`.  
3. Activează modulul din **Extensions → Modules → BlueCart DB Backup**.  
4. Configurează setările:
   - Activează backupul automat.
   - Alege frecvența și ora.
   - Completează setările FTP și e-mail (opțional).
5. Copiază linia cron generată și adaug-o în cPanel.

---

## 🔔 Recomandări BlueCart

- Activează compresia ZIP pentru baze de date mari.  
- Păstrează maxim 5–10 copii de backup pentru performanță.  
- Verifică periodic logurile pentru erori FTP sau e-mail.  
- Testează trimiterea e-mailului după fiecare actualizare de server SMTP.  

---

## 🧠 Securitate

- Backupurile sunt stocate în folderul **`/system/storage/backup/`** (în afara domeniului public).  
- Fiecare fișier include data și ora exactă a generării.  
- Accesul direct din browser este blocat.  

---

## 💬 Suport & Dezvoltare

📧 **BlueCart DevTeam**   
✉️ dev@bluecart.ro  

© 2025 BlueCart.ro — eCommerce Software Development.  
*Toate drepturile rezervate. Distribuirea fără acord este interzisă.*

---

## 📘 Viitoare actualizări (roadmap)

- ☁️ Integrare **BlueCart Cloud Sync** (Google Drive / Dropbox / DigiStorage WebDAV)  
- 📲 Notificări prin Telegram / Slack  
- 📊 Raport backupuri în dashboard  

---

### 🔖 Taguri
`backup`, `database`, `ftp`, `email`, `cron`, `security`, `automation`, `BlueCart`, `OpenCart 3`, `cloud sync`

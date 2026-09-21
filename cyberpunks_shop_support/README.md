# Cyberpunks Shop Support

Stores contact-form submissions as support tickets. Top-level admin left nav: **Cyberpunks Shop Support**.

## Features

- Ticket per form submit with request ID (same ID as email subject)
- Statuses: Open / In progress / Waiting for customer / Closed
- Stores visitor language / currency / country when available
- First-time email confirmation (via Information Additional Fields)
- Admin reply emails + editable signature in module settings
- Log customer reply / internal notes manually
- **IMAP inbound**: unread mail with support subject → ticket message (optional)
- **Contact-form protection**: email blacklist, max messages/day, append to open request, form token + minimum fill time, editable form messages in module settings

## Contact form protection

Order of checks on submit (themed Contact us form):

1. Honeypot field (Information Additional Fields) — silent success
2. Form token + minimum fill time (module settings) — error shows remaining seconds
3. **Blacklist** → refused, no ticket, no email
4. **Max messages / window** (default 2 per 24h) — refused with an error message, nothing stored
5. Open / In progress / Waiting request for that email → message **appended** to that thread
   (throttled to one append per 60 seconds; wait message shows remaining seconds)
6. Otherwise a new ticket is created

Closed requests always start a new ticket. Daily max `0` disables the count limit.

### Blacklist

Left nav → **Cyberpunks Shop Support**:

- Orange **ban** button in the list blacklists the emails of the selected requests
- **Blacklist** page: add emails manually (space/comma/newline separated) and remove them

Blacklisted addresses see a refusal message in the form and never reach the mailbox.
IMAP import skips mail from blacklisted senders too.

## IMAP security (important)

The poll does **not** pull the whole mailbox. It uses:

`UNSEEN SUBJECT "Cyberpunks.shop - Support request"`

Then additionally requires:

1. Request ID parsed from subject and an existing ticket  
2. Optional **From must equal ticket email** (on by default)  
3. Dedup by email `Message-ID` (stored on the message row)  
4. Cron URL protected by secret `key`

Best practice: use a **dedicated support mailbox** (or folder) that only receives support traffic.

Requires PHP **imap** extension (`php-imap`).

## Install

1. Upload `cyberpunks_shop_support_1_2_2.ocmod.zip` (Extensions → Installer).  
2. Extensions → Modifications → Refresh.  
3. Extensions → Modules → **Cyberpunks Shop Support** → Install / Edit → Save.  
4. Keep **Information Additional Fields** updated for the contact form.  

## IMAP setup

1. Module Edit → **Inbound email (IMAP)** → Enabled.  
2. Host / port / SSL / user / password / folder.  
3. Leave subject filter as `Cyberpunks.shop - Support request` unless you changed outbound subjects.  
4. Save, then use **Fetch IMAP now** to test.  
5. Cron every 5 minutes:

```text
curl -fsS 'https://YOUR-STORE/index.php?route=extension/module/cyberpunks_shop_support/cron&key=YOUR_SECRET'
```

(The exact URL is shown on the settings page.)

## Workflow

1. Customer submits Contact us.  
2. Manager replies from **Cyberpunks Shop Support**.  
3. Customer replies in their mail client (keep request ID in subject).  
4. Cron/IMAP imports the reply into the ticket thread.  

Manual fallback: **Log as customer message** still works.

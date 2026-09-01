# Deploy Project Tracker on Hostinger

## What you need

- Hostinger plan with **PHP 8.0+** and **MySQL**
- Your domain (or subdomain) pointed at Hostinger
- ~5 minutes in hPanel

---

## Step 1 — Create the database

1. Log in to **hPanel** → **Websites** → **Manage**
2. Go to **Databases** → **MySQL Databases**
3. Create a new database (e.g. `u123456789_tracker`)
4. Create a database user with a strong password
5. **Add user to database** with **All privileges**
6. Note these four values:

| Field | Example |
|-------|---------|
| Host | `localhost` |
| Database name | `u123456789_tracker` |
| Username | `u123456789_admin` |
| Password | (your password) |

---

## Step 2 — Upload files

### Option A — Zip upload (easiest)

On your Mac, from the project folder:

```bash
./scripts/package-hostinger.sh
```

This creates **`projecttracker-hostinger.zip`**.

1. hPanel → **Files** → **File Manager**
2. Open **`public_html`** (or your subdomain folder)
3. **Upload** the zip
4. Right-click → **Extract**
5. Make sure `index.php`, `login.php`, and `install/` are directly inside `public_html`  
   (not inside an extra `Project Tracker` subfolder unless you want `yoursite.com/Project%20Tracker/`)

### Option B — Git (Hostinger Git / import)

1. Connect repo: `https://github.com/Hantuhan/projecttracker`
2. When asked **static vs full import** → choose **full import** (repo now includes `package.json`)
3. Set **document root** to `public_html` (or repo root if files deploy to root)
4. **PHP 8.1+** required — this is **not** a static site; do not use “static website only”
5. Run installer: `https://yourdomain.com/install/install.php`

```bash
# Or SSH clone
cd ~/domains/yourdomain.com/public_html
git clone https://github.com/Hantuhan/projecttracker.git .
```

Do **not** upload `config/config.php` from your local machine.

---

## Step 3 — Run the installer

1. Open: **`https://yourdomain.com/install/install.php`**
2. Fill in:

| Installer field | Value |
|-----------------|-------|
| DB host | `localhost` |
| DB name | from Step 1 |
| DB user | from Step 1 |
| DB password | from Step 1 |
| App URL | `https://yourdomain.com` (no trailing slash) |
| Timezone | your timezone |
| Admin name / email / password | your choice |

3. Click **Install** → **Go to login**

---

## Step 4 — Secure after install

1. Enable **SSL** in hPanel if not already (Let’s Encrypt — free)
2. Log in → **Settings** → configure SMTP (see below)
3. **Team** → invite your members
4. Installer auto-blocks after setup (`config/config.php` exists). Optional: delete the `install/` folder in File Manager for extra safety.

---

## Step 5 — SMTP (email alerts)

hPanel → **Emails** → create a mailbox (e.g. `noreply@yourdomain.com`)

In app **Settings**:

| Setting | Value |
|---------|-------|
| Host | `smtp.hostinger.com` |
| Port | `465` (SSL) or `587` (TLS) |
| Username | full email address |
| Password | mailbox password |
| From email | same mailbox |
| Encryption | SSL for 465, TLS for 587 |

Click **Save & send test email**.

---

## Step 6 — Due-date reminders (cron)

After SMTP works:

1. Log in as admin → **Settings** (reminder options when enabled in UI)
2. Or run once manually in browser (get key from database `settings` table, key `reminder_cron_key`, or trigger cron once to generate)

hPanel → **Advanced** → **Cron Jobs** → add **daily** job:

```bash
curl -s "https://yourdomain.com/cron/reminders.php?key=YOUR_CRON_KEY"
```

Replace `YOUR_CRON_KEY` with the value from Settings or phpMyAdmin → `settings` → `reminder_cron_key`.

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Blank page / 500 | hPanel → PHP → use PHP 8.1 or 8.2 |
| Database connection failed | Double-check DB name/user; Hostinger prefixes with `u123456789_` |
| Installer won’t run | Ensure `config/` is writable; delete any old `config/config.php` only if reinstalling |
| CSS/JS missing | Files must be in `public_html`, not nested wrong folder |
| Emails not sending | Test SMTP in Settings; use Hostinger mailbox, not Gmail without app password |
| Subtasks/comments error on old DB | phpMyAdmin → run `install/migrate_subtasks.sql` and `install/migrate_reminders.sql` |

---

## Folder permissions (if installer can’t write config)

In File Manager, set **`config/`** to **755** and ensure it’s writable. The installer creates `config/config.php` automatically.

---

## Quick checklist

- [ ] MySQL database + user created
- [ ] Files in `public_html`
- [ ] `install/install.php` completed
- [ ] SSL enabled
- [ ] Admin logged in
- [ ] SMTP tested
- [ ] `install/` folder removed
- [ ] Cron job for reminders (optional)

# Project Tracker

Team project tracker for **Hostinger** (PHP + MySQL). Dashboard, list, kanban, subtasks, comments, login, invites with admin approval, and SMTP alerts.

## Features

- **Multiple projects** with member access
- **List view** — search, filters, subtask progress, **CSV export**
- **Kanban** — drag cards across To Do / In Progress / Review / Done
- **Subtasks** — checklist under each task
- **Comments** — discuss work on a task
- **Team invites** — invite or request access → **admin approve/reject**
- **Profile** — change name / password
- **SMTP** — email on assign, status change, invite, and approval

## Deploy on Hostinger

1. Create a MySQL database in **hPanel → Databases**.
2. Upload this folder to `public_html` (or a subdomain).
3. Open `https://yourdomain.com/install/install.php` and finish setup.
4. Log in as admin → **Settings** (SMTP) → **Team** (invite people).
5. Delete or protect the `install/` folder.

### Already installed? Run migrations

In phpMyAdmin, run `install/migrate_subtasks.sql` (adds subtasks, comments, user status). Skip any `ALTER` line that errors because the column already exists.

### Hostinger SMTP

| Setting | Typical value |
|--------|----------------|
| Host | `smtp.hostinger.com` |
| Port | `465` (SSL) or `587` (TLS) |
| Username / From | your mailbox address |
| Password | mailbox password |

## How team access works

1. Admin **invites** someone (Team), or they use **Request access** on the login page.
2. User stays **pending** until an admin **Approves** (or Rejects) under Team.
3. Pending users cannot log in; they see a clear message.
4. Sidebar shows a badge on **Team** when approvals are waiting.

## Requirements

- PHP 8.0+ (8.1/8.2 recommended)
- MySQL 5.7+ / MariaDB
- OpenSSL / sockets for SMTP (default on Hostinger)

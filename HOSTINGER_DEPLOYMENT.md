# Hostinger Deployment Guide
## CDRRMO Inventory Management System

This guide covers everything you need to change and set up when deploying this system to Hostinger shared hosting.

---

## Prerequisites

- A Hostinger account with at least the **Premium** or **Business** shared hosting plan
- Access to **hPanel** (Hostinger's control panel)
- An FTP client (e.g., FileZilla) or use Hostinger's built-in **File Manager**
- Your domain or subdomain pointed to Hostinger

---

## Step 1 — Create the Database

1. Log in to **hPanel** → go to **Databases** → **MySQL Databases**
2. Create a new database (e.g., `u123456789_cdrrmo`)
3. Create a database user and assign it to the database with **All Privileges**
4. Note down these three values — you'll need them in Step 3:
   - Database name
   - Database username
   - Database password

---

## Step 2 — Import the Database Schema

1. In hPanel, go to **Databases** → **phpMyAdmin**
2. Select your newly created database on the left panel
3. Click the **Import** tab
4. Click **Choose File** and select:
   ```
   database/database_complete.sql
   ```
5. Click **Go** to import

> **Note:** Use `database_complete.sql`, NOT `database_infinityFree.sql`. Hostinger supports stored procedures, triggers, and views — the InfinityFree version strips those out.

---

## Step 3 — Update `includes/config.php`

This is the most important file to update. Open `includes/config.php` and change the following:

### 3a. Database credentials

```php
// BEFORE (local)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cdrrmo_inventory');

// AFTER (Hostinger)
define('DB_HOST', 'localhost');           // keep as localhost
define('DB_USER', 'u123456789_youruser'); // your Hostinger DB username
define('DB_PASS', 'YourStrongPassword'); // your Hostinger DB password
define('DB_NAME', 'u123456789_cdrrmo');  // your Hostinger DB name
```

### 3b. Site URL

```php
// BEFORE
define('SITE_URL', 'http://localhost/cdrrmo_inventory_system');

// AFTER — use your actual domain (with https if SSL is active)
define('SITE_URL', 'https://yourdomain.com');
```

### 3c. Enable secure session cookie (HTTPS)

```php
// BEFORE
ini_set('session.cookie_secure', 0);

// AFTER — Hostinger provides free SSL, always use HTTPS
ini_set('session.cookie_secure', 1);
```

### 3d. Other system link (optional)

```php
// BEFORE
define('OTHER_SYSTEM_URL', 'https://example.com/replace-with-actual-link');

// AFTER — set the real URL, or leave as-is to keep the placeholder
define('OTHER_SYSTEM_URL', 'https://your-other-system.com');
```

---

## Step 4 — Upload the Project Files

1. In hPanel, go to **Files** → **File Manager**
2. Navigate to the `public_html` folder (or a subdirectory like `public_html/inventory`)
3. Upload all project files **except** the `database/` folder (it's only needed for initial setup)

Alternatively, use FTP:
- Host: `ftp.yourdomain.com`
- Username/Password: from hPanel → **FTP Accounts**
- Upload everything to `public_html/` or your chosen subdirectory

---

## Step 5 — Create the Upload Directories

The system saves uploaded item images and documents to these paths:

```
uploads/items/
uploads/documents/
```

These folders do **not** exist by default. Create them manually:

1. In **File Manager**, navigate to your project root
2. Create a folder named `uploads`
3. Inside `uploads`, create two subfolders: `items` and `documents`
4. Right-click each folder → **Permissions** → set to `755`

Final structure should look like:
```
public_html/
  uploads/
    items/        (chmod 755)
    documents/    (chmod 755)
```

---

## Step 6 — Set PHP Version

1. In hPanel, go to **Advanced** → **PHP Configuration**
2. Set PHP version to **8.1** or **8.2**
3. Save changes

---

## Step 7 — Enable SSL (HTTPS)

1. In hPanel, go to **Security** → **SSL**
2. Install the free **Let's Encrypt** SSL certificate for your domain
3. Enable **Force HTTPS** redirect

This is required for `session.cookie_secure = 1` to work properly.

---

## Step 8 — Change Default Passwords

After the system is live, **immediately** log in and change the default passwords.

Default credentials set by the SQL:
| Username | Password |
|----------|----------|
| admin    | admin123 |
| staff    | admin123 |

Change them via:
- Log in → top-right dropdown → **Change Password**
- Or go to **User Management** (admin only) to update other users

---

## Quick Checklist

- [ ] Database created in hPanel
- [ ] `database_complete.sql` imported via phpMyAdmin
- [ ] `DB_USER` updated in `config.php`
- [ ] `DB_PASS` updated in `config.php`
- [ ] `DB_NAME` updated in `config.php`
- [ ] `SITE_URL` updated in `config.php`
- [ ] `session.cookie_secure` set to `1` in `config.php`
- [ ] Project files uploaded to `public_html`
- [ ] `uploads/items/` folder created (chmod 755)
- [ ] `uploads/documents/` folder created (chmod 755)
- [ ] PHP version set to 8.1 or 8.2
- [ ] SSL certificate installed and HTTPS forced
- [ ] Default passwords changed after first login

---

## Troubleshooting

**Blank page or 500 error**
- Check PHP version is 8.1+
- Verify DB credentials in `config.php` are correct

**"Database connection error" on load**
- Double-check `DB_USER`, `DB_PASS`, `DB_NAME` — copy them exactly from hPanel
- `DB_HOST` should stay as `localhost` on Hostinger

**Login redirects back to login page**
- Make sure SSL is active and `session.cookie_secure` matches (1 for HTTPS, 0 for HTTP)
- Check that `SITE_URL` uses the correct protocol (`https://`)

**File uploads not working**
- Confirm `uploads/items/` and `uploads/documents/` exist
- Confirm folder permissions are set to `755`

**Stored procedures / triggers not importing**
- Make sure you're using `database_complete.sql`, not the InfinityFree version
- Hostinger Premium/Business plans support stored procedures; free plans do not

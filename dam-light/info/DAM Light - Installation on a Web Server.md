# DAM Light – Installation on a Web Server

*November 15, 2026 · @Tim Geurtzen*

## Introduction

With this guide, you can install DAM Light on a standard web host in 20 to 30 minutes, making it accessible through your own (sub)domain with HTTPS. This also lets you show the portal on networks that block your NAS address.

There are two options:

- **Option A – shared hosting (recommended):** a web hosting plan with a control panel such as cPanel, DirectAdmin or Plesk. No server knowledge required. Steps 1–6 cover this option.
- **Option B – your own VPS:** a virtual server on which you manage Nginx or Apache yourself. See **Alternative: your own VPS**.

**Tip:** use a subdomain, for example `brandportal.yourdomain.com`, with a valid HTTPS certificate. Corporate networks often block IP-address URLs, non-standard ports (such as `:8101`) or free dynamic DNS addresses. A regular domain with HTTPS is much less likely to be blocked.

## Requirements

Check the following items with your hosting provider before you begin. Almost any modern hosting plan will meet these requirements.

| Component | Requirement | Where to check |
|---|---|---|
| PHP | 8.2 or later (8.3 recommended) | Control panel: PHP version, MultiPHP Manager or PHP Selector |
| PHP extensions | `pdo_mysql`, `fileinfo`, `zip` (`zip` is only needed for “All (ZIP)”) | Same location; the installation page also checks these |
| Database | MySQL 5.7+ or MariaDB 10.4+ | Control panel: MySQL Databases or phpMyAdmin |
| Web server | Apache with `.htaccess`, or the ability to set the document root | Almost always available by default |
| HTTPS | Free certificate (Let’s Encrypt / AutoSSL) | Control panel: SSL/TLS |
| Storage | At least 500 MB; more as you upload more files | Hosting plan details |
| Upload limit | Preferably 256 MB per file for print-ready files | See Step 4 |

You will also need the latest `dam-light.zip`, access to your hosting control panel, and optionally an FTP program such as FileZilla.

## Step 1 – Prepare the files

Extract `dam-light.zip` on your computer and edit only `config.php`. Enter the database details after Step 2, once you have them.

```php
'demo_mode' => true,        // false = do not show demo accounts on the login screen

'db' => [
    'driver' => 'mysql', // important: not 'sqlite'
    'mysql' => [
        'host' => 'localhost',       // almost always localhost on shared hosting
        'port' => 3306,
        'dbname' => 'account_damlight', // full name, including the prefix
        'user' => 'account_damuser',
        'pass' => 'your-strong-password',
        'charset' => 'utf8mb4',
    ],
],
```

Leave `base_url` empty (`''`) if the portal will be installed in the root directory of a (sub)domain, as in this guide.

**What should you upload?**

- **Yes:** the entire folder, including `demo-content/` (for the demo content) and `public/install.php` (for installation).
- **No:** `config.local.php` (if you have one locally) and an existing `storage/dam.sqlite`. For a new installation, `storage/` should be empty.

Would you like to move your current NAS installation, including your own uploads and branding? That is also possible: see **Updating and migrating** at the end.

## Step 2 – Create the database

In your hosting control panel, create an empty database and a database user, then assign the user all privileges.

1. Open **MySQL Databases** (cPanel), **MySQL Management** (DirectAdmin) or **Databases** (Plesk) in the control panel.
2. Create a database, for example `damlight`. Many hosts prepend your account name, resulting in a name such as `account_damlight`.
3. Create a user, for example `damuser`, with a strong password. A prefix is often added here too.
4. Assign the user to the database with all privileges (**All privileges**).
5. Make a note of the full names (including prefixes), the password and the host. The host is almost always `localhost`; if the control panel specifies a different server name, use that.
6. Enter these details in `config.php` (Step 1).

You do not need to create the tables yourself; the installation page in Step 5 will do that. If the control panel asks for a collation, preferably use `utf8mb4_unicode_ci`.

## Step 3 – Upload the files and set the document root

Put the folder on the server and point the subdomain to the `public/` folder. Only that folder should be accessible over the internet; `app/`, `storage/` and `config.php` must not be publicly accessible.

### Uploading

- **Fastest:** upload `dam-light.zip` using the control panel’s File Manager and choose **Extract**. You can then rename the folder to `dam`, if desired.
- Alternatively, upload the extracted folder using FileZilla (FTP/SFTP).
- Put the folder alongside `public_html` (or `httpdocs`), not inside it; for example, `/home/account/dam/`. If your host does not allow this, see the alternative below.

### Create a subdomain with its document root set to `public/`

| Control panel | Where | Set the document root to |
|---|---|---|
| cPanel | Domains → Create a New Domain (or Subdomains) | `dam/public` |
| Plesk | Websites & Domains → Add Subdomain, then Hosting Settings | `dam/public` (or `httpdocs/dam/public`) |
| DirectAdmin | Subdomain Management | If a custom document root is not possible, use the alternative below |

### Alternative: the document root cannot be changed

Put the contents of the folder directly into the subdomain’s folder (for example, `public_html/brandportal/` or `domains/…/public_html/`). The supplied `.htaccess` redirects everything to `public/` and blocks access to `config.php`, `app/`, `storage/` and `bin/`. This works only on Apache (or LiteSpeed) with `.htaccess` support, which is available with almost all shared hosting plans. With this option, always install the portal in the root of a subdomain, not in a subfolder such as `yourdomain.com/dam/`.

**Check after uploading:** `https://brandportal.yourdomain.com/config.php` should show a blank page or an error message, and `https://brandportal.yourdomain.com/storage/` should return a 403 or 404. If you can see file contents or a directory listing, the document root is configured incorrectly.

## Step 4 – Permissions and PHP settings

On shared hosting, PHP usually runs under your own account. Changing permissions is therefore rarely necessary, but the PHP version and upload limit do need attention.

### Permissions

- `storage/` must be writable by PHP. Using File Manager or FileZilla (right-click → File Permissions), set the folder to `755`. If the installation page does not report it as **Writable**, try `775`.
- Never use `777`.
- Other folders: `755`. Files: `644`.

### PHP version and extensions

Select PHP 8.3 (or at least 8.2) for the subdomain in the control panel. Also enable `pdo_mysql`, `fileinfo` and `zip` if they are disabled. In cPanel, this is often called **MultiPHP Manager** or **Select PHP Version**.

### Upload limit

The default limit is often 2 to 64 MB; print-ready files are larger. In the control panel (MultiPHP INI Editor or PHP settings), change these values:

```ini
upload_max_filesize = 256M
post_max_size = 256M
memory_limit = 256M
max_execution_time = 300
```

If there is no control-panel option, put the same lines in a `.user.ini` file in the `public/` folder. It may take up to 5 minutes for the host to apply the changes. Some hosts enforce a fixed maximum; check their documentation.

## Step 5 – Install

The installation page creates the database tables and loads the demo content with one click.

1. Go to `https://brandportal.yourdomain.com/install.php`.
2. Check that it says **Database: mysql**. If it says `sqlite`, the `driver` setting in `config.php` has not been changed.
3. Go through the checklist: every item must show a green check mark. If an item shows a red cross, the explanation next to it tells you what is wrong; fix the issue and refresh the page.
4. Leave **Load demo content** checked and click **Install**.
5. You will see a summary: 11 demo users, 19 assets (including 3 stock photos) and the simulated download history.
6. Then delete `public/install.php` from the server.

You can then sign in using the demo accounts:

| Role | Email | Password |
|---|---|---|
| Administrator (HQ) | `admin@demo.local` | `Admin123!` |
| Branch office | `kantoor.de@demo.local` | `Demo123!` |
| Sales partner | `partner@demo.local` | `Demo123!` |

Next, under **Administration → Branding**, set your own company name, colours and logo. These settings are stored in the database and are not migrated from the NAS.

## Step 6 – Secure and finish

The portal is now online. Follow these steps to keep the demo secure.

- [ ] **Enable HTTPS:** enable a free certificate in the control panel (Let’s Encrypt / AutoSSL) and select **Enforce HTTPS** or **Force HTTPS**.
- [ ] **Delete `public/install.php`.**
- [ ] **Hide demo accounts:** set `'demo_mode' => false` in `config.php` as soon as anyone other than you receives the link. Otherwise, the passwords are displayed on the login screen.
- [ ] **Change passwords:** under **Administration → Users**, use the **Reset password** button for the administrator and any accounts you intend to show. A new temporary password will be displayed. This demo does not have a screen for changing your own password.
- [ ] **Repeat the checks from Step 3:** `config.php` and `storage/` must not be accessible through a browser.
- [ ] **Test from your workplace network:** open the link on your work laptop. If it is still blocked, see **Troubleshooting**.

Only share the link with people who need it. The demo does not have two-factor authentication and is not intended for real, confidential files.

## Alternative: your own VPS (Ubuntu 24.04)

On your own server, you install PHP, MariaDB and Nginx yourself; the application steps remain the same. Replace `brandportal.yourdomain.com` with your own subdomain and first point it to the server’s IP address using a DNS A record.

### 1. Install packages

```bash
sudo apt update
sudo apt install -y nginx mariadb-server php8.3-fpm php8.3-mysql php8.3-zip php8.3-mbstring
unzip certbot python3-certbot-nginx
```

### 2. Database

```bash
sudo mysql -e "CREATE DATABASE dam_light CHARACTER SET utf8mb4 COLLATE
utf8mb4_unicode_ci;
CREATE USER 'dam_user'@'localhost' IDENTIFIED BY 'strong-password';
GRANT ALL ON dam_light.* TO 'dam_user'@'localhost';"
```

### 3. Files and permissions

```bash
sudo unzip dam-light.zip -d /var/www/ && sudo mv /var/www/dam-light /var/www/dam
# Edit config.php (driver mysql, host localhost, details from Step 2)
sudo chown -R root:root /var/www/dam
sudo chown -R www-data:www-data /var/www/dam/storage
sudo chmod -R 775 /var/www/dam/storage
```

### 4. Nginx – file `/etc/nginx/sites-available/dam`

```nginx
server {
    listen 80;
    server_name brandportal.yourdomain.com;
    root /var/www/dam/public;

    index index.php;
    client_max_body_size 256M;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\. { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/dam /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d brandportal.yourdomain.com # HTTPS via Let's Encrypt
```

### 5. PHP upload limit

Put the settings from Step 4 in `/etc/php/8.3/fpm/conf.d/99-dam.ini` and run:

```bash
sudo systemctl restart php8.3-fpm
```

### 6. Install

Install through the browser (Step 5), or on the server:

```bash
sudo -u www-data php /var/www/dam/bin/install.php
```

Then delete `public/install.php`.

Using Apache instead of Nginx? Set `DocumentRoot /var/www/dam/public` in the virtual host, enable `AllowOverride All` and run `a2enmod rewrite`.

## Troubleshooting

Most problems are caused by the document root, database details or permissions on `storage/`. If in doubt, start with the checklist on `install.php` (while it is still present) or the error log in your hosting control panel.

| What you see | Likely cause | Solution |
|---|---|---|
| Installation page says “Database: sqlite” | `driver` is still set to `sqlite` | Set it to `mysql` in `config.php` |
| Red cross for database connection | Database name, username or password is missing its prefix, or the host is incorrect | Use the full names shown in the control panel; host is usually `localhost` |
| Red cross for “Writable: storage” | Folder is not writable by PHP | Set `storage/` to `755`, or `775` if necessary (Step 4) |
| HTTP 500 or blank page | PHP version is too old, or an extension is missing | Select PHP 8.2+ and enable `pdo_mysql`; check the control-panel error log |
| Page without styling, or 404 on `login.php` | Document root does not point to `public/` and `.htaccess` is not working | Set the document root to `…/public` (Step 3) |
| “Too many redirects” | Old `.htaccess` from an earlier version | Use the `.htaccess` file from the latest ZIP |
| `config.php` displays text, or `storage/` shows a directory listing | Entire folder is public without `.htaccess` protection | Set the document root to `public/`, then check again |
| Large file upload fails or returns “413” | PHP or web-server upload limit is too low | Apply the settings from Step 4; ask the host for a higher limit |
| “All (ZIP)” button is missing | PHP `zip` extension is disabled | Enable it in the PHP settings |
| Link is still blocked at work | The new domain has not yet been classified by the filter | Ask IT to allow the subdomain, or use a subdomain of the company domain |

## Updating and migrating

When a new version is released, replace only the application files; the database updates itself when the first page is loaded.

### Installing a new version

1. First make a backup: export the database through phpMyAdmin (**Export → Quick → SQL**) and download the `storage/` folder.
2. Upload everything from the new ZIP except `config.php`, the `storage/` folder and `public/install.php`.
3. Open the portal and sign in. That is all; you do not need to install it again.

### Migrating your NAS installation (including your own uploads and branding)

This is only necessary if you have added your own files or settings on the NAS that you want to keep. Otherwise, a fresh installation (Steps 1–6) is quicker.

1. Export the database on the NAS through phpMyAdmin (**Export → SQL**).
2. Copy the `storage/` folder from the NAS (including `files/`, `previews/` and `branding/`).
3. Complete Steps 1–4 on the new server, but skip the installation (Step 5).
4. Import the SQL file through phpMyAdmin (**Import**) into the new, empty database.
5. Upload the copied `storage/` folder and make sure it is writable (Step 4).
6. Delete `public/install.php`, open the portal and sign in using your existing accounts.

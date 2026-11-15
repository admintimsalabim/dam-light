# DAM Light – Brand Portal

A lightweight Digital Asset Management portal designed for smaller companies with a few subsidiaries or distributors.
Offices and sales partners log in, visually find the right asset (logo, roll-up banner, brochure, exhibition wall…)
and immediately download the correct file for **print (CMYK)**, **spot color (PMS)** or **digital (RGB)**.

The demo includes a fictional brand (**NORVIQ**) with real, working files: vector logos in CMYK/PMS/RGB,
print-ready roll-up banners measuring 850 × 2000 mm with bleed and crop marks, a brochure, flyer, exhibition wall, social banners and
a simulated download history, so the dashboard looks populated right away.

## What's included

**For users (offices and partners)**

- Homepage with search, popular shortcuts and category tiles
- Library with filters for category, application (CMYK/PMS/RGB/Office) and language, plus sorting
- Detail page with a large preview, an explanation of "Which file do I need?", downloads grouped by application and an option to download everything as a ZIP
- Files are given a clean name when downloaded, for example `norviq-roll-up-exhibition-benelux_print-ready-pdf-cmyk.pdf`

**Two languages (Dutch / English)**

- Each user has their own interface language (set by the administrator) and can switch themselves using **NL / EN** in the top-right corner; the selection is saved
- The content is also bilingual: titles, descriptions and file labels have an optional English field. If left blank, the Dutch text is displayed
- Downloaded filenames follow the user's language
- To add another language: copy `lang/en.php` to, for example, `lang/de.php`, translate the text and add the language to `languages` in `config.php`

**Usage rights (own imagery / stock photography)**

- For each asset, you can specify whether it is *own imagery* (anyone may use it) or a *stock photo* (only the user's own organization, including subsidiary offices, may use it)
- Distributors/sales partners can see stock photos with a watermark, but cannot download them. This is also enforced on the server, so even a direct download link will not work
- They receive a clear notification, the stock ID to copy and a button linking to the photo at the supplier (e.g. Adobe Stock)
- Offices see a notification that the photo may only be used internally and must not be forwarded
- Optional licence expiry date: after this date, only the administrator can download the photo. The dashboard issues a warning 90 days in advance
- "Usage rights" filter in the library

**For administrators (HQ)**

- Dashboard: downloads per day, per application, most downloaded assets, most active organizations and dormant accounts
- Manage assets: details, preview image, upload multiple files at once (CMYK/PMS/RGB is identified from the filename), draft/published status
- Visibility per asset: *everyone* or *own offices only*
- Users: roles (administrator, subsidiary office, sales partner), expiry date for temporary partners, activate/deactivate accounts, reset passwords
- Download log with export to Excel (CSV)
- Branding: customize company name, portal name, primary and accent colors, and the logo in the header bar, with a live preview and a button to restore the defaults

## Demo accounts

| Role             | Email                 | Password  |
|------------------|-----------------------|-----------|
| Administrator (HQ)| admin@demo.local      | Admin123! |
| Subsidiary office | kantoor.de@demo.local | Demo123!  |
| Sales partner    | partner@demo.local    | Demo123!  |

Tip for the presentation: first log in as a partner (who cannot see the exhibition wall because it is "offices only"),
then log in as a subsidiary office, and finish with the admin dashboard.

## Quick start on your own laptop (recommended for a demo)

You need PHP 8.2 or later, with the `pdo_sqlite` (or `pdo_mysql`), `fileinfo` and `zip` extensions.

```bash
cd dam-light
php bin/install.php          # creates the SQLite database and loads the demo content
php -S localhost:8000 -t public
```

Then open http://localhost:8000.

- Start over: `php bin/install.php --fresh`
- Without demo content, with just one administrator: `php bin/install.php --fresh --no-demo`

## On a web server (Apache/Nginx)

1. Upload the entire folder. Set the **document root to** `public/`. If that is not possible, the included `.htaccess` redirects everything to `public/`.
2. Make sure the web server has write access to `storage/`.
3. No SSH? Go to `/install.php`, click *Install* and **then delete** `public/install.php`.
4. In `config.php`, set the `demo_mode` option to `false` so the demo accounts no longer appear on the login screen.

Uploads larger than 2 MB? Increase `upload_max_filesize` and `post_max_size` in `php.ini`, for example to `256M`.

## MySQL / MariaDB instead of SQLite

Set `'driver' => 'mysql'` in `config.php` and fill in the connection details. First create an empty database:

```sql
CREATE DATABASE dam_light CHARACTER SET utf8mb4;
CREATE USER 'dam_user'@'localhost' IDENTIFIED BY 'strong-password';
GRANT ALL ON dam_light.* TO 'dam_user'@'localhost';
```

Then run `php bin/install.php`. The app has been tested with both SQLite and MariaDB.

## Folder structure

```
config.php          settings (database, storage, languages)
app/                logic: bootstrap, layout, files, database schema, installer, languages, branding
lang/               translations (en.php)
bin/install.php     command-line installation
public/             webroot: pages, admin/, assets/style.css
storage/            database (SQLite), uploaded files and previews (not publicly accessible)
demo-content/       source files for the demo brand NORVIQ
```

Files are stored outside the webroot and are only served through `download.php` after a permission check. Every download is logged.

## Custom branding

Everything is managed via **Admin → Branding**: name, colors and logo. Changes take effect immediately for everyone, including on the login page.
The logo is stored in `storage/branding/`. SVG logos are served in a restricted mode to prevent any scripts they may contain from being executed.

## Updating from an earlier version

Replace the program files, but leave `config.php` and the `storage/` folder in place. When the app is first opened, it updates the database itself
(new columns for language and English text, and a `settings` table). You do not need to reinstall anything.

## Deliberately not included in this light version

This is a demo model. For production use, you would expect (and these are standard features in Bynder, Canto and similar packages):

- MFA and/or single sign-on (Entra ID for own offices, guest accounts for partners)
- Automatic previews and conversion (for example, PDF → JPG, or exporting a logo directly in any format)
- Version control, expiry dates and licence rights per file
- Approval workflows, for example when a local office submits a customized roll-up banner for approval
- Templates that you can edit yourself ("brand templates"), such as a roll-up banner with your own stand number
- A multilingual interface, password reset by email and virus scanning of uploads
- Integrations with InDesign, Figma, Office, the CMS and the PIM

This list is particularly useful in discussions with management: the demo demonstrates the value, while the list shows what a professional package offers on top of that.

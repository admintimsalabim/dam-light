<?php
declare(strict_types=1);

/* ------------------------------------------------------------------ instellingen (huisstijl e.d.) */

function setting_defaults(): array
{
    return [
        'company'      => (string)config('company', 'NORVIQ'),
        'app_name'     => (string)config('app_name', 'Brand Portal'),
        'color_brand'  => '#0A3A6B',
        'color_accent' => '#F28C28',
        'logo_file'    => '',
        'logo_hide_name' => '0',   // 1 = alleen logo tonen (als het logo de naam al bevat)
    ];
}

function settings(bool $reload = false): array
{
    static $cache = null;
    if ($cache !== null && !$reload) return $cache;
    $cache = setting_defaults();
    try {
        foreach (q('SELECT k, v FROM settings')->fetchAll() as $row) {
            if (array_key_exists($row['k'], $cache) && $row['v'] !== null && $row['v'] !== '') $cache[$row['k']] = $row['v'];
        }
    } catch (Throwable) { /* nog niet geïnstalleerd */ }
    return $cache;
}

function setting(string $k): string { return (string)(settings()[$k] ?? ''); }

function save_setting(string $k, ?string $v): void
{
    q('DELETE FROM settings WHERE k = ?', [$k]);
    if ($v !== null) q('INSERT INTO settings (k, v) VALUES (?, ?)', [$k, $v]);
}

function company(): string { return setting('company'); }

/** Vervangt {bedrijf} / {company} in vrije teksten door de bedrijfsnaam uit Beheer → Huisstijl. */
function with_company(?string $text): string
{
    $c = company();
    $out = str_replace(['{bedrijf}', '{company}'], $c, (string)$text);
    // "B.V." gevolgd door een punt aan het eind van de zin: geen dubbele punt tonen
    return str_ends_with($c, '.') ? str_replace($c . '.', $c, $out) : $out;
}
function app_name(): string { return setting('app_name'); }

/* ------------------------------------------------------------------ kleuren */

function valid_hex(string $c): bool { return (bool)preg_match('/^#[0-9a-fA-F]{6}$/', $c); }

/** Mengt kleur $hex met $with (bv. wit) in verhouding $r (0..1). */
function hex_mix(string $hex, string $with, float $r): string
{
    $a = sscanf($hex, '#%02x%02x%02x');
    $b = sscanf($with, '#%02x%02x%02x');
    $c = [];
    for ($i = 0; $i < 3; $i++) $c[] = (int)round($a[$i] * (1 - $r) + $b[$i] * $r);
    return vsprintf('#%02X%02X%02X', $c);
}

/** Relatieve helderheid 0..1 (voor contrastwaarschuwing). */
function luminance(string $hex): float
{
    $v = array_map(function ($x) { $x /= 255; return $x <= 0.03928 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4; }, sscanf($hex, '#%02x%02x%02x'));
    return 0.2126 * $v[0] + 0.7152 * $v[1] + 0.0722 * $v[2];
}

function brand_css(?string $brand = null, ?string $accent = null): string
{
    $brand  = $brand  ?? setting('color_brand');
    $accent = $accent ?? setting('color_accent');
    if (!valid_hex($brand))  $brand = '#0A3A6B';
    if (!valid_hex($accent)) $accent = '#F28C28';
    return '<style>:root{--brand:' . $brand . ';--brand-2:' . hex_mix($brand, '#FFFFFF', .14)
        . ';--brand-soft:' . hex_mix($brand, '#FFFFFF', .9) . ';--accent:' . $accent
        . ';--accent-dark:' . hex_mix($accent, '#000000', .1) . '}</style>';
}

/* ------------------------------------------------------------------ databasemigraties */

const SCHEMA_VERSION = 5;

/** Werkt een bestaande database bij naar de nieuwste versie (veilig om vaak aan te roepen). */
function migrate(PDO $pdo): void
{
    try { $pdo->query('SELECT 1 FROM users LIMIT 1'); } catch (Throwable) { return; } // nog niet geïnstalleerd
    $my = config('db.driver') === 'mysql';
    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (k VARCHAR(64) NOT NULL PRIMARY KEY, v TEXT NULL)'
        . ($my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : ''));
    $start = $version = (int)($pdo->query("SELECT v FROM settings WHERE k = 'schema_version'")->fetchColumn() ?: 1);
    if ($version >= SCHEMA_VERSION) return;

    $add = function (string $table, string $col, string $def) use ($pdo): void {
        try { $pdo->query("SELECT $col FROM $table LIMIT 1"); }
        catch (Throwable) { $pdo->exec("ALTER TABLE $table ADD COLUMN $col $def"); }
    };

    if ($version < 2) {
        $add('users', 'locale', "VARCHAR(5) NOT NULL DEFAULT 'nl'");
        $add('assets', 'title_en', "VARCHAR(200) NOT NULL DEFAULT ''");
        $add('assets', 'description_en', 'TEXT');
        $add('categories', 'name_en', "VARCHAR(120) NOT NULL DEFAULT ''");
        $add('categories', 'description_en', "VARCHAR(255) NOT NULL DEFAULT ''");
        // Engelse namen voor de standaard demo-categorieën
        $en = ['logos' => ['Logos', 'All logo versions'], 'roll-ups' => ['Roll-up banners', 'Print-ready, per language'],
               'beurs-events' => ['Trade shows & events', 'Backwalls and promotional material'], 'brochures' => ['Brochures & flyers', 'Product and sales material'],
               'online' => ['Online & social media', 'Banners and posts'], 'fotografie' => ['Photography', 'Product and lifestyle images'],
               'huisstijl' => ['Brand guidelines', 'Colours, typography, rules']];
        $st = $pdo->prepare("UPDATE categories SET name_en = ?, description_en = ? WHERE slug = ? AND name_en = ''");
        foreach ($en as $slug => [$n, $d]) $st->execute([$n, $d, $slug]);
        $add('renditions', 'label_en', "VARCHAR(160) NOT NULL DEFAULT ''");
        // Engelse teksten voor de demo-inhoud invullen (alleen waar nog leeg)
        $demo = require __DIR__ . '/demo_en.php';
        $st = $pdo->prepare("UPDATE assets SET title_en = ?, description_en = ? WHERE title = ? AND title_en = ''");
        foreach ($demo['assets'] as $nl => [$t, $d]) $st->execute([$t, $d, $nl]);
        $st = $pdo->prepare("UPDATE renditions SET label_en = ? WHERE id = ? AND label_en = ''");
        foreach ($pdo->query('SELECT id, label FROM renditions')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $en = demo_label_en($r['label'], $demo['labels']);
            if ($en !== '') $st->execute([$en, $r['id']]);
        }
        // Demo-accounts buiten HQ krijgen Engels als interfacetaal
        $pdo->exec("UPDATE users SET locale = 'en' WHERE email LIKE '%@demo.local' AND email <> 'admin@demo.local'");
        $version = 2;
    }

    if ($version < 3) {
        // Rechten- en licentiebeheer
        $add('assets', 'license_type', "VARCHAR(20) NOT NULL DEFAULT 'own'");
        $add('assets', 'license_provider', "VARCHAR(120) NOT NULL DEFAULT ''");
        $add('assets', 'license_ref', "VARCHAR(120) NOT NULL DEFAULT ''");
        $add('assets', 'license_url', "VARCHAR(500) NOT NULL DEFAULT ''");
        $add('assets', 'license_expires', 'DATE NULL');
        $add('assets', 'license_note', 'TEXT');
        // Demo-installatie? Dan de voorbeeld-stockfoto's toevoegen
        try {
            $isDemo = (bool)$pdo->query("SELECT 1 FROM users WHERE email = 'admin@demo.local'")->fetchColumn();
            if ($isDemo && is_dir(APP_ROOT . '/demo-content/files')) {
                require_once __DIR__ . '/installer.php';
                seed_stock_demo();
            }
        } catch (Throwable) { /* demo-inhoud is optioneel */ }
        $version = 3;
    }

    if ($version < 4) {
        // Vaste merknaam in licentieteksten vervangen door de automatische bedrijfsnaam
        $pdo->exec("UPDATE assets SET license_note = REPLACE(license_note, 'NORVIQ', '{bedrijf}') WHERE license_note LIKE '%NORVIQ%'");
        $version = 4;
    }

    if ($version < 5) {
        // Engelse variant van de licentietoelichting
        $add('assets', 'license_note_en', 'TEXT');
        $st = $pdo->prepare("UPDATE assets SET license_note_en = ? WHERE license_note = ? AND (license_note_en IS NULL OR license_note_en = '')");
        $st->execute(['Own photography by {bedrijf}. Free to use by offices and distributors, including for their own campaigns.', 'Eigen fotografie van {bedrijf}. Vrij te gebruiken door kantoren en distributeurs, ook voor eigen campagnes.']);
        $st->execute(['Standard licence in the name of {bedrijf} HQ. Valid for all {bedrijf} locations; not transferable to distributors or other external parties.', 'Standaardlicentie op naam van {bedrijf} HQ. Geldt voor alle {bedrijf}-vestigingen; niet overdraagbaar aan distributeurs of andere externe partijen.']);
        $version = 5;
    }

    if ($version === $start) return;
    $pdo->prepare("DELETE FROM settings WHERE k = 'schema_version'")->execute();
    $pdo->prepare("INSERT INTO settings (k, v) VALUES ('schema_version', ?)")->execute([(string)$version]);
}

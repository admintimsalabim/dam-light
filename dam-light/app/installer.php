<?php
declare(strict_types=1);

function is_installed(): bool
{
    // Pas 'geïnstalleerd' als er echt gebruikers zijn; een halve/mislukte installatie kan dan opnieuw
    try { return (int)q('SELECT COUNT(*) FROM users')->fetchColumn() > 0; } catch (Throwable) { return false; }
}

/** Systeemcontrole voor de installatiepagina: [label, ok, toelichting] */
function install_checks(): array
{
    $uid = function_exists('posix_geteuid') ? posix_geteuid() : null;
    $user = ($uid !== null && function_exists('posix_getpwuid')) ? (posix_getpwuid($uid)['name'] ?? '?') : get_current_user();
    $st = rtrim(config('storage_path'), '/');
    $chk = [];
    $chk[] = ['PHP-versie ' . PHP_VERSION, version_compare(PHP_VERSION, '8.2', '>='), 'minimaal 8.2'];
    $chk[] = ['PHP draait als gebruiker', true, $user . ($uid !== null ? " (uid $uid)" : '')];
    $chk[] = ['Extensie pdo_' . config('db.driver'), extension_loaded('pdo_' . config('db.driver')), ''];
    $chk[] = ['Extensie zip (ZIP-downloads)', class_exists('ZipArchive'), 'optioneel'];
    try { db(); $chk[] = ['Databaseverbinding', true, '']; } catch (Throwable $e) { $chk[] = ['Databaseverbinding', false, $e->getMessage()]; }
    $chk[] = ['Demobestanden gevonden', is_readable(APP_ROOT . '/demo-content/files/logo-primary-cmyk.pdf'), APP_ROOT . '/demo-content'];
    foreach (['', '/files', '/previews'] as $sub) {
        $d = $st . $sub;
        if (!is_dir($d)) @mkdir($d, 0775, true);
        $owner = @fileowner($d);
        $chk[] = ["Schrijfbaar: storage$sub", is_dir($d) && is_writable($d), $owner !== false ? "eigenaar uid $owner, rechten " . substr(sprintf('%o', fileperms($d)), -4) : 'map bestaat niet'];
    }
    return $chk;
}

/** Installeert de database; optioneel met demo-inhoud. Geeft een log terug. */
function run_install(bool $fresh = false, bool $demo = true): array
{
    $log = [];
    $pdo = db();
    $driver = config('db.driver');
    if ($fresh) {
        foreach (['downloads', 'renditions', 'assets', 'categories', 'users', 'settings'] as $t) $pdo->exec("DROP TABLE IF EXISTS $t");
        foreach (['files', 'previews', 'branding'] as $sub) foreach (glob(storage($sub) . '/*') ?: [] as $f) if (is_file($f)) unlink($f);
        $log[] = 'Bestaande tabellen en bestanden verwijderd.';
    }
    create_schema($pdo, $driver);
    $log[] = "Tabellen aangemaakt ($driver).";

    if ((int)q('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
        $log[] = 'Er bestaan al gebruikers – demo-inhoud overgeslagen.';
        return $log;
    }

    if (!$demo) {
        q('INSERT INTO users (name, email, password_hash, role, organisation, country, is_active, created_at) VALUES (?,?,?,?,?,?,1,?)',
          ['Beheerder', 'admin@demo.local', password_hash('Admin123!', PASSWORD_DEFAULT), 'admin', 'HQ', '', now()]);
        $log[] = 'Beheerder aangemaakt: admin@demo.local / Admin123! (wijzig dit direct).';
        return $log;
    }

    // Vooraf controleren, zodat de installatie niet halverwege stukloopt
    $src = APP_ROOT . '/demo-content';
    if (!is_dir("$src/files") || !is_dir("$src/previews")) {
        throw new RuntimeException("Map met demobestanden niet gevonden: $src/files en $src/previews. Upload de map demo-content opnieuw.");
    }
    if (!is_readable("$src/files/logo-primary-cmyk.pdf")) {
        throw new RuntimeException("Demobestanden zijn niet leesbaar voor de webserver ($src/files). Controleer de rechten.");
    }
    foreach (['files', 'previews'] as $sub) {
        $dir = storage($sub);
        if (!is_writable($dir)) throw new RuntimeException("Map $dir is niet schrijfbaar voor de webserver. Controleer de rechten van storage/.");
    }
    $copy = function (string $from, string $to): void {
        if (!@copy($from, $to)) throw new RuntimeException("Kopiëren mislukt: $from → $to");
    };

    $ago = fn(string $rel) => date('Y-m-d H:i:s', strtotime($rel));

    /* ---------------- gebruikers ---------------- */
    $users = [
        // naam, e-mail, ww, rol, organisatie, land, actief, verloopt, laatste login
        ['Sanne de Vries',  'admin@demo.local',      'Admin123!', 'admin',   'NORVIQ HQ',                 'Nederland',          1, null, '-1 hour'],
        ['Jonas Weber',     'kantoor.de@demo.local', 'Demo123!',  'office',  'NORVIQ GmbH',               'Duitsland',          1, null, '-2 days'],
        ['Emily Carter',    'kantoor.us@demo.local', 'Demo123!',  'office',  'NORVIQ Inc.',               'Verenigde Staten',   1, null, '-5 days'],
        ['Camille Laurent', 'kantoor.fr@demo.local', 'Demo123!',  'office',  'NORVIQ France SAS',         'Frankrijk',          1, null, '-1 day'],
        ['Oliver Hughes',   'kantoor.uk@demo.local', 'Demo123!',  'office',  'NORVIQ UK Ltd.',            'Verenigd Koninkrijk', 1, null, '-9 days'],
        ['Lucía Romero',    'kantoor.es@demo.local', 'Demo123!',  'office',  'NORVIQ Ibérica S.L.',       'Spanje',             1, null, '-3 days'],
        ['Lucas Martins',   'partner@demo.local',    'Demo123!',  'partner', 'Atlântico Tech Supplies',   'Portugal',           1, null, '-4 days'],
        ['Erik Lindqvist',  'nordic@demo.local',     'Demo123!',  'partner', 'Nordic Industrial Partners', 'Zweden',            1, null, '-12 days'],
        ['Omar Haddad',     'gulf@demo.local',       'Demo123!',  'partner', 'Gulf Machinery Trading',    'Ver. Arabische Emiraten', 1, '+60 days', '-6 days'],
        ['Piotr Nowak',     'polska@demo.local',     'Demo123!',  'partner', 'Polska Automatyka',         'Polen',              1, null, null],
        ['Kenji Sato',      'japan@demo.local',      'Demo123!',  'partner', 'Sato Trading Co.',          'Japan',              0, null, '-120 days'],
    ];
    $uids = [];
    foreach ($users as [$n, $em, $pw, $role, $org, $country, $act, $exp, $last]) {
        q('INSERT INTO users (name, email, password_hash, role, organisation, country, locale, is_active, expires_at, last_login_at, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
          [$n, $em, password_hash($pw, PASSWORD_DEFAULT), $role, $org, $country, $role === 'admin' ? 'nl' : 'en', $act, $exp ? $ago($exp) : null, $last ? $ago($last) : null, $ago('-90 days')]);
        $uids[$em] = (int)$pdo->lastInsertId();
    }
    $log[] = count($users) . ' demo-gebruikers aangemaakt.';

    /* ---------------- categorieën ---------------- */
    $cats = [
        ['logos',        "Logo's",                  'Alle logovarianten',            'Logos',                 'All logo versions'],
        ['roll-ups',     'Roll-up banners',         'Drukklaar, per taal',           'Roll-up banners',       'Print-ready, per language'],
        ['beurs-events', 'Beurs & events',          'Beurswanden en actiemateriaal', 'Trade shows & events',  'Backwalls and promotional material'],
        ['brochures',    'Brochures & flyers',      'Product- en salesmateriaal',    'Brochures & flyers',    'Product and sales material'],
        ['online',       'Online & social media',   'Banners en posts',              'Online & social media', 'Banners and posts'],
        ['fotografie',   'Fotografie',              'Product- en sfeerbeelden',      'Photography',           'Product and lifestyle images'],
        ['huisstijl',    'Huisstijl & richtlijnen', 'Kleuren, typografie, regels',   'Brand guidelines',      'Colours, typography, rules'],
    ];
    $cid = [];
    foreach ($cats as $i => [$slug, $name, $desc, $nameEn, $descEn]) {
        q('INSERT INTO categories (name, slug, description, name_en, description_en, sort_order) VALUES (?,?,?,?,?,?)', [$name, $slug, $desc, $nameEn, $descEn, $i]);
        $cid[$slug] = (int)$pdo->lastInsertId();
    }

    /* ---------------- assets ---------------- */
    $logoR = fn(string $b, string $pms) => [
        ["$b-cmyk.pdf", 'Drukwerk – CMYK (PDF, vector)', 'print', 'CMYK'],
        ["$b-pms.pdf",  "Steunkleur – $pms (PDF, vector)", 'spot', 'PMS'],
        ["$b-rgb.png",  'Web & Office – PNG transparant (2400 px)', 'digital', 'RGB'],
        ["$b.svg",      'Web – SVG (vector, schaalbaar)', 'digital', 'RGB'],
    ];
    $rollR = fn(string $b) => [
        ["$b-cmyk.pdf",       'Drukklare PDF – CMYK, 5 mm afloop + snijtekens', 'print', 'CMYK'],
        ["$b-rgb-proef.jpg",  'Digitale proef – JPG (ter goedkeuring / social)', 'digital', 'RGB'],
    ];
    $assets = [
        ['logos', 'Logo – primair (full colour)', "Het standaardlogo. Gebruik dit op witte of lichte achtergronden.\nHoud rondom het logo minimaal de hoogte van de 'N' vrij.", 'logo, merk, full colour, primair', 'INT', 'Wereldwijd', '', 'all', 1, 'logo-primary.png', $logoR('logo-primary', 'PANTONE 2955 C + 1495 C'), '-40 days'],
        ['logos', 'Logo – wit (diapositief)', 'Voor gebruik op donkere achtergronden en foto\'s. Let op: het beeldmerk is wit met een navy N.', 'logo, wit, diapositief, donkere achtergrond', 'INT', 'Wereldwijd', '', 'all', 1, 'logo-white.png', $logoR('logo-white', 'PANTONE 2955 C + 1495 C'), '-40 days'],
        ['logos', 'Logo – zwart (monochroom)', 'Voor eenkleurig drukwerk, faxformulieren, gravures en stempels.', 'logo, zwart, monochroom, eenkleurig', 'INT', 'Wereldwijd', '', 'all', 1, 'logo-black.png', $logoR('logo-black', 'PANTONE Black 6 C'), '-40 days'],
        ['logos', 'Beeldmerk (icoon)', 'Alleen het beeldmerk, zonder woordmerk. Voor app-iconen, social-profielfoto\'s en kleine toepassingen.', 'logo, icoon, beeldmerk, favicon, profielfoto', 'INT', 'Wereldwijd', '', 'all', 1, 'logo-icon.png', $logoR('logo-icon', 'PANTONE 2955 C + 1495 C'), '-38 days'],
        ['roll-ups', 'Roll-up – Product range 2026', 'Algemene productroll-up voor beurzen en showrooms. Engelstalig, wereldwijd inzetbaar.', 'roll-up, banner, beurs, product, 2026', 'EN', 'Wereldwijd', '850 × 2000 mm', 'all', 1, 'rollup-product-range-2026-en.png', $rollR('rollup-product-range-2026-en'), '-6 days'],
        ['roll-ups', 'Roll-up – Service & Support', 'Duitstalige roll-up met de focus op service, onderhoud en training.', 'roll-up, banner, service, support, beurs', 'DE', 'DACH', '850 × 2000 mm', 'all', 1, 'rollup-service-support-de.png', $rollR('rollup-service-support-de'), '-14 days'],
        ['roll-ups', 'Roll-up – Beurs Benelux', 'Nederlandstalige beursroll-up met ruimte voor standnummer. Laat het standnummer aanpassen door HQ vóór je gaat drukken.', 'roll-up, banner, beurs, benelux', 'NL', 'Benelux', '850 × 2000 mm', 'all', 1, 'rollup-beurs-benelux-nl.png', $rollR('rollup-beurs-benelux-nl'), '-3 days'],
        ['roll-ups', 'Roll-up – Product range 2026 (FR)', 'Franse vertaling – wordt nog gecontroleerd door NORVIQ France.', 'roll-up, banner, beurs, frans', 'FR', 'Frankrijk', '850 × 2000 mm', 'all', 0, 'rollup-product-range-2026-en.png', [['rollup-product-range-2026-en-cmyk.pdf', 'Drukklare PDF – CMYK (concept)', 'print', 'CMYK']], '-1 days'],
        ['beurs-events', 'Beurswand 3×3 – Precision', 'Achterwand voor een 3×3 pop-up stand. Alleen voor eigen kantoren: beursdeelname door partners loopt via HQ.', 'beurs, achterwand, backwall, pop-up, stand', 'EN', 'Wereldwijd', '2230 × 2250 mm', 'internal', 1, 'backwall-3x3.png', [['backwall-3x3-cmyk.pdf', 'Drukklare PDF – CMYK, 5 mm afloop', 'print', 'CMYK'], ['backwall-3x3-rgb-proef.jpg', 'Digitale proef – JPG', 'digital', 'RGB']], '-20 days'],
        ['beurs-events', 'Flyer – Beursactie 2026', 'Tweezijdige A5-flyer voor beursbezoekers.', 'flyer, beurs, actie, a5', 'NL', 'Benelux', 'A5 (148 × 210 mm)', 'all', 1, 'flyer-beursactie-a5.png', [['flyer-beursactie-a5-cmyk.pdf', 'Drukklare PDF – CMYK, 3 mm afloop', 'print', 'CMYK']], '-8 days'],
        ['brochures', 'Productbrochure 2026', 'Vierpagina-brochure met het volledige programma. Drukklaar; laat eventueel je eigen contactgegevens op de achterzijde plaatsen via HQ.', 'brochure, product, a4, sales', 'NL', 'Benelux', 'A4 · 4 pagina\'s', 'all', 1, 'brochure-productlijn-a4.png', [['brochure-productlijn-a4-cmyk.pdf', 'Drukklare PDF – CMYK, 3 mm afloop', 'print', 'CMYK']], '-25 days'],
        ['online', 'LinkedIn-banner – Product range', 'Omslagfoto voor LinkedIn-bedrijfspagina\'s en persoonlijke profielen.', 'linkedin, social, banner, omslag', 'EN', 'Wereldwijd', '1584 × 396 px', 'all', 1, 'linkedin-banner-1584x396-preview.png', [['linkedin-banner-1584x396.png', 'LinkedIn-omslag – PNG', 'digital', 'RGB']], '-6 days'],
        ['online', 'Social post – Meet us at the fair', 'Vierkante post voor LinkedIn, Instagram en Facebook in de aanloop naar een beurs.', 'social, post, beurs, instagram, linkedin', 'EN', 'Wereldwijd', '1200 × 1200 px', 'all', 1, 'social-post-1200x1200-preview.png', [['social-post-1200x1200.png', 'Social post – PNG', 'digital', 'RGB']], '-4 days'],
        ['online', 'E-mailhandtekening banner', 'Banner onder je e-mailhandtekening in Outlook.', 'e-mail, handtekening, outlook, banner', 'EN', 'Wereldwijd', '600 × 150 px', 'all', 1, 'email-signature-banner-600x150-preview.png', [['email-signature-banner-600x150.png', 'Banner – PNG', 'digital', 'RGB']], '-30 days'],
        ['fotografie', 'Productfoto – X200', 'Vrijstaande productfoto. Vermeld geen fotografennaam; rechten zijn wereldwijd afgekocht.', 'foto, product, x200, packshot', 'INT', 'Wereldwijd', '3000 × 2000 px', 'all', 1, 'productfoto-x200.png', [['productfoto-x200-hires.jpg', 'Hoge resolutie – JPG 3000 px (drukwerk)', 'print', ''], ['productfoto-x200-web.jpg', 'Web – JPG 1200 px', 'digital', 'RGB']], '-18 days'],
        ['huisstijl', 'Huisstijlgids 2026', 'Kleuren (CMYK, PMS, RGB, HEX), logogebruik en typografie op één plek.', 'huisstijl, richtlijnen, kleuren, brand guide', 'NL', 'Wereldwijd', 'A4 liggend', 'all', 1, 'huisstijlgids-2026.png', [['huisstijlgids-2026.pdf', 'Huisstijlgids – PDF', 'digital', '']], '-45 days'],
    ];

    $demoEn = require APP_ROOT . '/app/demo_en.php';
    $assetIds = []; $rendIds = [];
    foreach ($assets as [$cat, $title, $desc, $tags, $lang, $region, $dims, $vis, $pub, $prev, $rends, $when]) {
        $pName = bin2hex(random_bytes(8)) . '.' . ext_of($prev);
        $copy("$src/previews/$prev", storage('previews') . "/$pName");
        [$titleEn, $descEn] = $demoEn['assets'][$title] ?? ['', ''];
        q('INSERT INTO assets (category_id, title, description, title_en, description_en, tags, language, region, dimensions, visibility, is_published, preview_file, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
          [$cid[$cat], $title, $desc, $titleEn, $descEn, $tags, $lang, $region, $dims, $vis, $pub, $pName, $uids['admin@demo.local'], $ago($when), $ago($when)]);
        $aid = (int)$pdo->lastInsertId();
        $assetIds[$aid] = ['cat' => $cat, 'vis' => $vis, 'pub' => $pub, 'rends' => []];
        foreach ($rends as $i => [$file, $label, $usage, $space]) {
            $stored = bin2hex(random_bytes(12)) . '.' . ext_of($file);
            $copy("$src/files/$file", storage('files') . "/$stored");
            $path = storage('files') . "/$stored";
            q('INSERT INTO renditions (asset_id, label, label_en, usage_type, color_space, format, file_name, original_name, file_size, mime_type, sort_order, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
              [$aid, $label, demo_label_en($label, $demoEn['labels']), $usage, $space, format_of($file), $stored, $file, filesize($path), mime_of($path), $i, $ago($when)]);
            $assetIds[$aid]['rends'][] = (int)$pdo->lastInsertId();
        }
    }
    $n = seed_stock_demo();
    foreach (q("SELECT id, category_id, visibility, is_published FROM assets WHERE license_type = 'stock'")->fetchAll() as $row) {
        $assetIds[(int)$row['id']] = ['cat' => 'fotografie', 'vis' => $row['visibility'], 'pub' => (int)$row['is_published'], 'stock' => true,
            'rends' => array_map('intval', q('SELECT id FROM renditions WHERE asset_id = ?', [$row['id']])->fetchAll(PDO::FETCH_COLUMN))];
    }
    $log[] = (count($assets) + $n) . ' demo-assets met bestanden aangemaakt (waarvan ' . $n . ' stockfoto\'s).';

    /* ---------------- gesimuleerde downloadhistorie (30 dagen) ---------------- */
    mt_srand(2026);
    $downloaders = array_filter($uids, fn($em) => !in_array($em, ['admin@demo.local', 'polska@demo.local', 'japan@demo.local'], true), ARRAY_FILTER_USE_KEY);
    $weights = ['logos' => 6, 'roll-ups' => 5, 'beurs-events' => 3, 'online' => 3, 'brochures' => 2, 'fotografie' => 2, 'huisstijl' => 1];
    $pool = [];
    foreach ($assetIds as $aid => $info) if ($info['pub']) for ($k = 0; $k < $weights[$info['cat']]; $k++) $pool[] = $aid;
    $n = 0;
    for ($i = 0; $i < 190; $i++) {
        $em = array_rand($downloaders);
        $aid = $pool[mt_rand(0, count($pool) - 1)];
        if (($assetIds[$aid]['vis'] === 'internal' || !empty($assetIds[$aid]['stock'])) && !str_starts_with($em, 'kantoor')) continue;
        $rs = $assetIds[$aid]['rends'];
        $zip = mt_rand(1, 12) === 1;
        $ts = date('Y-m-d H:i:s', time() - mt_rand(0, 29 * 86400) - mt_rand(0, 36000));
        q('INSERT INTO downloads (user_id, asset_id, rendition_id, is_zip, ip, created_at) VALUES (?,?,?,?,?,?)',
          [$downloaders[$em], $aid, $zip ? null : $rs[mt_rand(0, count($rs) - 1)], $zip ? 1 : 0, '192.0.2.' . mt_rand(1, 250), $ts]);
        $n++;
    }
    $log[] = "$n gesimuleerde downloads toegevoegd (voor het dashboard).";
    $log[] = 'Inloggen: admin@demo.local / Admin123!  ·  kantoor.de@demo.local / Demo123!  ·  partner@demo.local / Demo123!';
    return $log;
}

/**
 * Voegt drie voorbeeld-stockfoto's toe (alleen als ze nog niet bestaan).
 * Wordt gebruikt door de installatie én door de update van een bestaande demo.
 * @return int aantal toegevoegde assets
 */
function seed_stock_demo(): int
{
    $src = APP_ROOT . '/demo-content';
    $cat = q("SELECT id FROM categories WHERE slug = 'fotografie'")->fetchColumn();
    $admin = q("SELECT id FROM users WHERE email = 'admin@demo.local'")->fetchColumn() ?: null;
    if (!$cat) return 0;
    // de eigen productfoto expliciet als eigen beeld markeren
    q("UPDATE assets SET license_note = ?, license_note_en = ? WHERE title = 'Productfoto – X200' AND (license_note IS NULL OR license_note = '')",
      ['Eigen fotografie van {bedrijf}. Vrij te gebruiken door kantoren en distributeurs, ook voor eigen campagnes.', 'Own photography by {bedrijf}. Free to use by offices and distributors, including for their own campaigns.']);

    $items = [
        ['stock-engineer-productiehal', 'Sfeerbeeld – Engineer in productiehal', 'Stock image – Engineer on the factory floor',
         'Sfeerbeeld voor brochures, website en presentaties over productie en kwaliteit.', 'Lifestyle image for brochures, website and presentations about production and quality.',
         'foto, sfeerbeeld, productie, fabriek, engineer, stock', '000000001', 'https://stock.adobe.com/search?k=engineer+factory+hard+hat', '+14 months', '-12 days'],
        ['stock-zakelijk-overleg', 'Sfeerbeeld – Zakelijk overleg', 'Stock image – Business meeting',
         'Overlegsituatie, geschikt voor recruitment, service- en adviescommunicatie.', 'Meeting scene, suitable for recruitment, service and consultancy communication.',
         'foto, sfeerbeeld, overleg, team, kantoor, stock', '000000002', 'https://stock.adobe.com/search?k=business+meeting+team', '+45 days', '-9 days'],
        ['stock-logistiek-haven', 'Sfeerbeeld – Wereldwijde logistiek', 'Stock image – Global logistics',
         'Containerterminal bij zonsondergang, voor communicatie over levering en wereldwijde service.', 'Container terminal at sunset, for communication about delivery and worldwide service.',
         'foto, sfeerbeeld, logistiek, haven, containers, export, stock', '000000003', 'https://stock.adobe.com/search?k=container+port+sunset', '+2 years', '-20 days'],
    ];
    $n = 0;
    foreach ($items as [$file, $title, $titleEn, $desc, $descEn, $tags, $ref, $url, $expires, $when]) {
        if (q('SELECT 1 FROM assets WHERE title = ?', [$title])->fetchColumn()) continue;
        $prev = bin2hex(random_bytes(8)) . '.png';
        if (!@copy("$src/previews/$file.png", storage('previews') . "/$prev")) throw new RuntimeException("Kopiëren mislukt: $file.png");
        $ts = date('Y-m-d H:i:s', strtotime($when));
        q('INSERT INTO assets (category_id, title, description, title_en, description_en, tags, language, region, dimensions, visibility, is_published, preview_file,
             license_type, license_provider, license_ref, license_url, license_expires, license_note, license_note_en, created_by, created_at, updated_at)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
          [$cat, $title, $desc, $titleEn, $descEn, $tags, 'INT', 'Wereldwijd', '3000 × 2000 px', 'all', 1, $prev,
           'stock', 'Adobe Stock', $ref . ' (demo)', $url, date('Y-m-d', strtotime($expires)),
           'Standaardlicentie op naam van {bedrijf} HQ. Geldt voor alle {bedrijf}-vestigingen; niet overdraagbaar aan distributeurs of andere externe partijen.',
           'Standard licence in the name of {bedrijf} HQ. Valid for all {bedrijf} locations; not transferable to distributors or other external parties.',
           $admin, $ts, $ts]);
        $aid = (int)db()->lastInsertId();
        foreach ([["$file-hires.jpg", 'Hoge resolutie – JPG 3000 px (drukwerk)', 'High resolution – JPG 3000 px (print)', 'print', ''],
                  ["$file-web.jpg", 'Web – JPG 1200 px', '', 'digital', 'RGB']] as $i => [$f, $label, $labelEn, $usage, $space]) {
            $stored = bin2hex(random_bytes(12)) . '.jpg';
            $path = storage('files') . "/$stored";
            if (!@copy("$src/files/$f", $path)) throw new RuntimeException("Kopiëren mislukt: $f");
            q('INSERT INTO renditions (asset_id, label, label_en, usage_type, color_space, format, file_name, original_name, file_size, mime_type, sort_order, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
              [$aid, $label, $labelEn, $usage, $space, 'JPG', $stored, $f, filesize($path), mime_of($path), $i, $ts]);
        }
        $n++;
    }
    return $n;
}

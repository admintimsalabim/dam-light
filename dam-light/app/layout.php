<?php
declare(strict_types=1);

function default_mark(int $size = 34): string
{
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100" aria-hidden="true">'
        . '<rect width="100" height="100" rx="22" fill="#fff"/>'
        . '<polygon points="26,26 40,26 40,74 26,74" style="fill:var(--brand)"/>'
        . '<polygon points="26,26 40,26 74,74 60,74" style="fill:var(--brand)"/>'
        . '<polygon points="60,26 74,26 74,74 60,74" style="fill:var(--brand)"/>'
        . '<circle cx="82" cy="18" r="7" style="fill:var(--accent)"/></svg>';
}

/** Logo + naam zoals ingesteld onder Beheer → Huisstijl. */
function brand_block(int $size = 34): string
{
    $names = '<span><strong>' . e(company()) . '</strong> ' . e(app_name()) . '</span>';
    $logo = setting('logo_file');
    if ($logo !== '') {
        $img = '<img class="brand-logo" style="height:' . $size . 'px" src="' . url('logo.php?v=' . substr(md5($logo), 0, 8)) . '" alt="' . e(company()) . '">';
        return $img . (setting('logo_hide_name') === '1' ? '<span>' . e(app_name()) . '</span>' : $names);
    }
    return default_mark($size) . $names;
}

function icon(string $name): string
{
    $p = [
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'download' => '<path d="M12 3v12m0 0-5-5m5 5 5-5M4 21h16"/>',
        'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'back'     => '<path d="M19 12H5m0 0 7 7m-7-7 7-7"/>',
        'zip'      => '<path d="M21 8v13H3V8M1 3h22v5H1zM10 12h4"/>',
        'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4m0-4h.01"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'lock'     => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'users'    => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M17 3.5a4 4 0 0 1 0 9M22 21a7 7 0 0 0-4-6.3"/>',
        'chart'    => '<path d="M3 3v18h18M8 17V11m5 6V7m5 10v-4"/>',
        'palette'  => '<path d="M12 3a9 9 0 1 0 0 18c1.1 0 1.8-.8 1.8-1.8 0-.5-.2-.9-.5-1.2-.3-.3-.5-.7-.5-1.2 0-1 .8-1.8 1.8-1.8H17a4 4 0 0 0 4-4c0-4.4-4-8-9-8z"/><circle cx="7.5" cy="11" r="1"/><circle cx="10" cy="7" r="1"/><circle cx="15" cy="7.5" r="1"/>',
        'files'    => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/>',
    ][$name] ?? '';
    return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

function space_badges(string $csv): string
{
    $out = '';
    foreach (array_unique(array_filter(explode(',', $csv))) as $s) {
        $out .= '<span class="badge badge-' . strtolower(e($s)) . '">' . e($s) . '</span>';
    }
    return $out;
}

function layout_header(string $title, string $active = 'library', string $search = ''): void
{
    $u = current_user();
    ?><!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e(company()) ?> <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= url('assets/style.css?v=3') ?>">
<?= brand_css() ?>
</head>
<body class="<?= str_starts_with($active, 'admin') ? 'is-admin' : '' ?>">
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="<?= url('index.php') ?>"><?= brand_block() ?></a>
    <?php if ($u): ?>
    <form class="topsearch" action="<?= url('index.php') ?>" method="get" role="search">
      <?= icon('search') ?>
      <input type="search" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('Zoek op logo, roll-up, brochure, taal…')) ?>" aria-label="<?= e(t('Zoeken')) ?>">
    </form>
    <nav class="mainnav">
      <a href="<?= url('index.php') ?>" class="<?= $active === 'library' ? 'on' : '' ?>"><?= icon('grid') ?><?= e(t('Bibliotheek')) ?></a>
      <?php if ($u['role'] === 'admin'): ?>
      <a href="<?= url('admin/index.php') ?>" class="<?= str_starts_with($active, 'admin') ? 'on' : '' ?>"><?= icon('settings') ?><?= e(t('Beheer')) ?></a>
      <?php endif; ?>
    </nav>
    <?= lang_switch('on-dark') ?>
    <div class="usermenu">
      <div class="who"><span class="avatar"><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></span>
        <span class="who-txt"><strong><?= e($u['name']) ?></strong><small><?= e($u['organisation'] ?: t(ROLES[$u['role']])) ?></small></span></div>
      <a class="iconbtn" href="<?= url('logout.php') ?>" title="<?= e(t('Uitloggen')) ?>"><?= icon('logout') ?></a>
    </div>
    <?php endif; ?>
  </div>
</header>
<?php if (str_starts_with($active, 'admin')): ?>
<nav class="subnav"><div class="wrap">
  <a href="<?= url('admin/index.php') ?>" class="<?= $active === 'admin' ? 'on' : '' ?>"><?= icon('chart') ?><?= e(t('Dashboard')) ?></a>
  <a href="<?= url('admin/assets.php') ?>" class="<?= $active === 'admin-assets' ? 'on' : '' ?>"><?= icon('files') ?><?= e(t('Assets')) ?></a>
  <a href="<?= url('admin/users.php') ?>" class="<?= $active === 'admin-users' ? 'on' : '' ?>"><?= icon('users') ?><?= e(t('Gebruikers')) ?></a>
  <a href="<?= url('admin/downloads.php') ?>" class="<?= $active === 'admin-downloads' ? 'on' : '' ?>"><?= icon('download') ?><?= e(t('Downloadlog')) ?></a>
  <a href="<?= url('admin/branding.php') ?>" class="<?= $active === 'admin-branding' ? 'on' : '' ?>"><?= icon('palette') ?><?= e(t('Huisstijl')) ?></a>
</div></nav>
<?php endif; ?>
<main class="wrap">
<?php foreach (flashes() as [$type, $msg]): ?>
  <div class="flash flash-<?= e($type) ?>"><?= e($msg) ?></div>
<?php endforeach;
}

function layout_footer(): void
{
    ?></main>
<footer class="foot"><div class="wrap">
  <span><?= e(company()) ?> <?= e(app_name()) ?> · DAM Light demo</span>
  <span><?= e(t('Vragen over merkgebruik? Neem contact op met Marketing Communicatie (HQ).')) ?></span>
</div></footer>
</body>
</html><?php
}

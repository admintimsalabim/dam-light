<?php
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

$since = date('Y-m-d H:i:s', strtotime('-30 days'));
$stats = [
    'assets'    => (int)q('SELECT COUNT(*) FROM assets WHERE is_published = 1')->fetchColumn(),
    'files'     => (int)q('SELECT COUNT(*) FROM renditions')->fetchColumn(),
    'users'     => (int)q('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn(),
    'partners'  => (int)q("SELECT COUNT(*) FROM users WHERE is_active = 1 AND role = 'partner'")->fetchColumn(),
    'dl30'      => (int)q('SELECT COUNT(*) FROM downloads WHERE created_at >= ?', [$since])->fetchColumn(),
    'active30'  => (int)q('SELECT COUNT(DISTINCT user_id) FROM downloads WHERE created_at >= ?', [$since])->fetchColumn(),
];

$top = q('SELECT a.id, a.title, a.title_en, c.name AS cat, c.name_en AS cat_en, COUNT(*) AS n FROM downloads d JOIN assets a ON a.id = d.asset_id JOIN categories c ON c.id = a.category_id
          WHERE d.created_at >= ? GROUP BY a.id, a.title, a.title_en, c.name, c.name_en ORDER BY n DESC LIMIT 6', [$since])->fetchAll();
$orgs = q("SELECT u.organisation, u.country, u.role, COUNT(*) AS n FROM downloads d JOIN users u ON u.id = d.user_id
           WHERE d.created_at >= ? GROUP BY u.organisation, u.country, u.role ORDER BY n DESC LIMIT 8", [$since])->fetchAll();
$byUsage = q('SELECT r.usage_type, COUNT(*) AS n FROM downloads d JOIN renditions r ON r.id = d.rendition_id WHERE d.created_at >= ? GROUP BY r.usage_type ORDER BY n DESC', [$since])->fetchAll();

$days = [];
for ($i = 13; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i days"))] = 0;
foreach (q('SELECT SUBSTR(created_at, 1, 10) AS d, COUNT(*) AS n FROM downloads WHERE created_at >= ? GROUP BY SUBSTR(created_at, 1, 10)', [array_key_first($days) . ' 00:00:00'])->fetchAll() as $row) {
    if (isset($days[$row['d']])) $days[$row['d']] = (int)$row['n'];
}
$max = max(1, ...array_values($days));

$recent = q('SELECT d.created_at, d.is_zip, u.name, u.organisation, a.title, a.title_en, a.id AS aid, r.label, r.label_en FROM downloads d
             LEFT JOIN users u ON u.id = d.user_id LEFT JOIN assets a ON a.id = d.asset_id LEFT JOIN renditions r ON r.id = d.rendition_id
             ORDER BY d.created_at DESC, d.id DESC LIMIT 8')->fetchAll();
$dormant = q("SELECT name, organisation, last_login_at, expires_at FROM users WHERE is_active = 1 AND role <> 'admin'
              AND (last_login_at IS NULL OR last_login_at < ?) ORDER BY last_login_at LIMIT 5", [$since])->fetchAll();

$expiring = q("SELECT id, title, title_en, license_provider, license_expires FROM assets WHERE license_type = 'stock' AND license_expires IS NOT NULL AND license_expires <= ? ORDER BY license_expires",
               [date('Y-m-d', strtotime('+90 days'))])->fetchAll();

layout_header(t('Dashboard'), 'admin');
?>
<div class="page-head"><h1><?= e(t('Dashboard')) ?></h1><p class="muted"><?= e(t('Gebruik van het portaal in de afgelopen 30 dagen.')) ?></p></div>

<?php if ($expiring): ?>
<div class="card pad license-alert">
  <h2><?= icon('lock') ?> <?= e(t('Stocklicenties die binnen 90 dagen verlopen')) ?></h2>
  <table class="tbl">
    <?php foreach ($expiring as $x): $past = strtotime($x['license_expires'] . ' 23:59:59') < time(); ?>
    <tr><td><a href="<?= url('admin/asset_edit.php?id=' . (int)$x['id']) ?>"><?= e(loc($x, 'title')) ?></a> <small class="muted"><?= e($x['license_provider']) ?></small></td>
      <td class="num"><?= $past ? '<span class="pill pill-warn">' . e(t('Verlopen')) . '</span> ' : '' ?><?= e(t('tot %s', fdate($x['license_expires']))) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <p class="small muted"><?= e(t('Verleng de licentie of verwijder de foto. Na de vervaldatum kan alleen de beheerder hem nog downloaden.')) ?></p>
</div>
<?php endif; ?>

<div class="stats">
  <div class="stat card"><small><?= e(t('Gepubliceerde assets')) ?></small><strong><?= $stats['assets'] ?></strong><span><?= e(tn('%d bestand', '%d bestanden', $stats['files'])) ?></span></div>
  <div class="stat card"><small><?= e(t('Actieve accounts')) ?></small><strong><?= $stats['users'] ?></strong><span><?= e(t('waarvan %d salespartners', $stats['partners'])) ?></span></div>
  <div class="stat card"><small><?= e(t('Downloads (30 d)')) ?></small><strong><?= $stats['dl30'] ?></strong><span><?= e(t('door %d gebruikers', $stats['active30'])) ?></span></div>
  <div class="stat card accent"><small><?= e(t('Zelf gevonden (30 d)')) ?></small><strong><?= $stats['dl30'] ?></strong><span><?= e(t('keer een bestand opgehaald zonder tussenkomst van HQ')) ?></span></div>
</div>

<div class="cols-2">
  <div class="card pad">
    <h2><?= e(t('Downloads per dag')) ?></h2>
    <div class="bars">
      <?php foreach ($days as $d => $n): ?>
        <div class="bar" title="<?= fdate($d) ?>: <?= $n ?>"><span style="height: <?= round($n / $max * 100) ?>%"></span><em><?= (int)substr($d, 8, 2) ?></em></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card pad">
    <h2><?= e(t('Per toepassing')) ?></h2>
    <?php $tot = max(1, array_sum(array_column($byUsage, 'n'))); foreach ($byUsage as $b): $u = USAGES[$b['usage_type']] ?? ['label' => $b['usage_type'], 'space' => '']; ?>
      <div class="hbar"><span class="hbar-l"><?= e(t($u['label'])) ?> <?= $u['space'] ? '<em class="badge badge-' . strtolower($u['space']) . '">' . $u['space'] . '</em>' : '' ?></span>
        <span class="hbar-t"><span style="width: <?= round($b['n'] / $tot * 100) ?>%"></span></span><span class="hbar-n"><?= (int)$b['n'] ?></span></div>
    <?php endforeach; ?>
    <?php if (!$byUsage): ?><p class="muted"><?= e(t('Nog geen downloads.')) ?></p><?php endif; ?>
  </div>
</div>

<div class="cols-2">
  <div class="card pad">
    <h2><?= e(t('Meest gedownload')) ?></h2>
    <table class="tbl">
      <?php foreach ($top as $t): ?>
      <tr><td><a href="<?= url('asset.php?id=' . (int)$t['id']) ?>"><?= e(loc($t, 'title')) ?></a><br><small class="muted"><?= e(loc($t, 'cat')) ?></small></td><td class="num"><?= (int)$t['n'] ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div class="card pad">
    <h2><?= e(t('Actiefste organisaties')) ?></h2>
    <table class="tbl">
      <?php foreach ($orgs as $o): ?>
      <tr><td><?= e($o['organisation'] ?: '—') ?><br><small class="muted"><?= e($o['country']) ?> · <?= e(t(ROLES[$o['role']] ?? $o['role'])) ?></small></td><td class="num"><?= (int)$o['n'] ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<div class="cols-2">
  <div class="card pad">
    <h2><?= e(t('Laatste downloads')) ?> <a class="small" href="<?= url('admin/downloads.php') ?>"><?= e(t('alles bekijken →')) ?></a></h2>
    <table class="tbl">
      <?php foreach ($recent as $r): ?>
      <tr><td><strong><?= e($r['name'] ?? t('verwijderd')) ?></strong> <small class="muted"><?= e($r['organisation'] ?? '') ?></small><br>
        <small><?= e($r['title'] !== null ? loc($r, 'title') : '—') ?> · <?= $r['is_zip'] ? e(t('alle bestanden (ZIP)')) : e(loc($r, 'label')) ?></small></td>
        <td class="num muted small"><?= fdate($r['created_at'], true) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div class="card pad">
    <h2><?= e(t('Accounts zonder recente login')) ?></h2>
    <p class="muted small"><?= e(t('Kandidaten om op te volgen – of om te deactiveren.')) ?></p>
    <table class="tbl">
      <?php foreach ($dormant as $d): ?>
      <tr><td><?= e($d['name']) ?><br><small class="muted"><?= e($d['organisation']) ?></small></td><td class="num small"><?= $d['last_login_at'] ? e(t('laatst %s', fdate($d['last_login_at']))) : e(t('nooit ingelogd')) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$dormant): ?><tr><td class="muted"><?= e(t('Iedereen is recent actief geweest.')) ?></td></tr><?php endif; ?>
    </table>
  </div>
</div>
<?php layout_footer();

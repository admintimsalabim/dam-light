<?php
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

$from = (string)($_GET['from'] ?? date('Y-m-d', strtotime('-30 days')));
$to   = (string)($_GET['to'] ?? date('Y-m-d'));
$role = (string)($_GET['role'] ?? '');
$where = ['d.created_at >= ?', 'd.created_at <= ?'];
$params = [$from . ' 00:00:00', $to . ' 23:59:59'];
if (isset(ROLES[$role])) { $where[] = 'u.role = ?'; $params[] = $role; }

$rows = q('SELECT d.created_at, d.is_zip, d.ip, u.name, u.email, u.organisation, u.country, u.role, a.title, a.title_en, c.name AS cat, c.name_en AS cat_en, r.label, r.label_en, r.color_space, r.format
           FROM downloads d
           LEFT JOIN users u ON u.id = d.user_id
           LEFT JOIN assets a ON a.id = d.asset_id
           LEFT JOIN categories c ON c.id = a.category_id
           LEFT JOIN renditions r ON r.id = d.rendition_id
           WHERE ' . implode(' AND ', $where) . ' ORDER BY d.created_at DESC, d.id DESC', $params)->fetchAll();

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="downloads_' . $from . '_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM zodat Excel UTF-8 herkent
    fputcsv($out, array_map('t', ['Datum', 'Naam', 'E-mail', 'Organisatie', 'Land', 'Rol', 'Asset', 'Categorie', 'Bestand', 'Kleur', 'Formaat']), ';');
    foreach ($rows as $r) {
        fputcsv($out, [$r['created_at'], $r['name'], $r['email'], $r['organisation'], $r['country'], t(ROLES[$r['role']] ?? (string)$r['role']),
                       loc($r, 'title'), loc($r, 'cat'), $r['is_zip'] ? t('Alle bestanden (ZIP)') : loc($r, 'label'), $r['color_space'], $r['format']], ';', '"', '\\');
    }
    exit;
}

layout_header(t('Downloadlog'), 'admin-downloads');
?>
<div class="page-head row">
  <div><h1><?= e(t('Downloadlog')) ?></h1><p class="muted"><?= e(t('%d downloads in deze periode', count($rows))) ?></p></div>
  <a class="btn" href="<?= e(url('admin/downloads.php') . '?' . http_build_query(['from' => $from, 'to' => $to, 'role' => $role, 'export' => 'csv'])) ?>"><?= icon('download') ?> <?= e(t('Exporteer naar Excel (CSV)')) ?></a>
</div>

<form class="toolbar card" method="get">
  <label><?= e(t('Van')) ?> <input type="date" name="from" value="<?= e($from) ?>"></label>
  <label><?= e(t('Tot en met')) ?> <input type="date" name="to" value="<?= e($to) ?>"></label>
  <select name="role"><option value=""><?= e(t('Alle rollen')) ?></option><?php foreach (ROLES as $k => $l): ?><option value="<?= $k ?>" <?= $role === $k ? 'selected' : '' ?>><?= e(t($l)) ?></option><?php endforeach; ?></select>
  <button class="btn"><?= e(t('Toon')) ?></button>
</form>

<div class="card">
<table class="tbl tbl-list">
  <thead><tr><th><?= e(t('Datum')) ?></th><th><?= e(t('Gebruiker')) ?></th><th><?= e(t('Organisatie')) ?></th><th><?= e(t('Asset')) ?></th><th><?= e(t('Bestand')) ?></th></tr></thead>
  <tbody>
  <?php foreach (array_slice($rows, 0, 500) as $r): ?>
    <tr>
      <td class="small"><?= fdate($r['created_at'], true) ?></td>
      <td><?= e($r['name'] ?? '—') ?><br><small class="muted"><?= e(isset(ROLES[$r['role']]) ? t(ROLES[$r['role']]) : '') ?></small></td>
      <td><?= e($r['organisation'] ?? '') ?><br><small class="muted"><?= e($r['country'] ?? '') ?></small></td>
      <td><?= e($r['title'] !== null ? loc($r, 'title') : t('(verwijderd)')) ?><br><small class="muted"><?= e(loc($r, 'cat')) ?></small></td>
      <td><?= $r['is_zip'] ? e(t('Alle bestanden (ZIP)')) : e(loc($r, 'label')) ?> <?= $r['color_space'] ? '<span class="badge badge-' . strtolower(e($r['color_space'])) . '">' . e($r['color_space']) . '</span>' : '' ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="muted"><?= e(t('Geen downloads in deze periode.')) ?></td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php layout_footer();

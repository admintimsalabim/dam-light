<?php
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

$s = trim((string)($_GET['s'] ?? ''));
$cat = (int)($_GET['cat'] ?? 0);
$where = ['1=1']; $params = [];
if ($s !== '') { $where[] = '(LOWER(a.title) LIKE ? OR LOWER(a.tags) LIKE ?)'; $params[] = '%' . mb_strtolower($s) . '%'; $params[] = '%' . mb_strtolower($s) . '%'; }
if ($cat) { $where[] = 'a.category_id = ?'; $params[] = $cat; }

$rows = q('SELECT a.*, c.name AS cat_name, c.name_en AS cat_name_en,
             (SELECT COUNT(*) FROM renditions r WHERE r.asset_id = a.id) AS files,
             (SELECT COUNT(*) FROM downloads d WHERE d.asset_id = a.id) AS dls
           FROM assets a JOIN categories c ON c.id = a.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY a.updated_at DESC', $params)->fetchAll();
$cats = q('SELECT * FROM categories ORDER BY sort_order, name')->fetchAll();

layout_header(t('Assets beheren'), 'admin-assets');
?>
<div class="page-head row">
  <div><h1><?= e(t('Assets')) ?></h1><p class="muted"><?= e(tn('%d item', '%d items', count($rows))) ?></p></div>
  <a class="btn btn-primary" href="<?= url('admin/asset_edit.php') ?>"><?= icon('plus') ?> <?= e(t('Nieuw asset')) ?></a>
</div>

<form class="toolbar card" method="get">
  <input type="search" name="s" value="<?= e($s) ?>" placeholder="<?= e(t('Zoek op titel of tag')) ?>">
  <select name="cat"><option value="0"><?= e(t('Alle categorieën')) ?></option>
    <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e(loc($c, 'name')) ?></option><?php endforeach; ?>
  </select>
  <button class="btn"><?= e(t('Filter')) ?></button>
</form>

<div class="card">
<table class="tbl tbl-list">
  <thead><tr><th></th><th><?= e(t('Titel')) ?></th><th><?= e(t('Categorie')) ?></th><th><?= e(t('Bestanden')) ?></th><th><?= e(t('Zichtbaarheid')) ?></th><th><?= e(t('Status')) ?></th><th class="num"><?= e(t('Downloads')) ?></th><th><?= e(t('Bijgewerkt')) ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $a): ?>
    <tr>
      <td><img class="mini" src="<?= url('preview.php?id=' . (int)$a['id']) ?>" alt=""></td>
      <td><a href="<?= url('admin/asset_edit.php?id=' . (int)$a['id']) ?>"><strong><?= e(loc($a, 'title')) ?></strong></a><br><small class="muted"><?= e($a['language']) ?> <?= e(t($a['dimensions'])) ?></small></td>
      <td><?= e(loc($a, 'cat_name')) ?></td>
      <td><?= (int)$a['files'] ?></td>
      <td><?php if (is_stock($a)): ?><span class="pill pill-stock"><?= e(t('Stock')) ?></span><?= license_expired($a) ? ' <span class="pill pill-warn">' . e(t('Verlopen')) . '</span>' : '' ?><br><?php endif; ?><?= $a['visibility'] === 'internal' ? '<span class="pill">' . e(t('Alleen kantoren')) . '</span>' : '<span class="pill pill-ok">' . e(t('Iedereen')) . '</span>' ?></td>
      <td><?= (int)$a['is_published'] ? '<span class="pill pill-ok">' . e(t('Gepubliceerd')) . '</span>' : '<span class="pill pill-warn">' . e(t('Concept')) . '</span>' ?></td>
      <td class="num"><?= (int)$a['dls'] ?></td>
      <td class="small"><?= fdate($a['updated_at']) ?></td>
      <td class="actions"><a href="<?= url('asset.php?id=' . (int)$a['id']) ?>"><?= e(t('Bekijken')) ?></a> <a href="<?= url('admin/asset_edit.php?id=' . (int)$a['id']) ?>"><?= e(t('Bewerken')) ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php layout_footer();

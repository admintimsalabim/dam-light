<?php
require __DIR__ . '/../../app/bootstrap.php';
$admin = require_admin();

$id = (int)($_GET['id'] ?? 0);
$a = $id ? q('SELECT * FROM assets WHERE id = ?', [$id])->fetch() : null;
if ($id && !$a) redirect('admin/assets.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'save':
                $title = trim((string)$_POST['title']);
                if ($title === '') throw new RuntimeException(t('Titel is verplicht.'));
                $catId = (int)$_POST['category_id'];
                $newCat = trim((string)($_POST['new_category'] ?? ''));
                if ($newCat !== '') {
                    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($newCat)), '-');
                    $exists = q('SELECT id FROM categories WHERE slug = ?', [$slug])->fetchColumn();
                    if ($exists) $catId = (int)$exists;
                    else {
                        q('INSERT INTO categories (name, slug, description, name_en, description_en, sort_order) VALUES (?,?,?,?,?,?)',
                          [$newCat, $slug, '', trim((string)($_POST['new_category_en'] ?? '')), '', 99]);
                        $catId = (int)db()->lastInsertId();
                    }
                }
                $licType = isset(LICENSES[$_POST['license_type'] ?? '']) ? $_POST['license_type'] : 'own';
                $licUrl = trim((string)($_POST['license_url'] ?? ''));
                if ($licUrl !== '' && !preg_match('#^https?://#i', $licUrl)) throw new RuntimeException(t('De link naar de leverancier moet beginnen met https://'));
                $licExp = trim((string)($_POST['license_expires'] ?? ''));
                $lic = [$licType, trim((string)($_POST['license_provider'] ?? '')), trim((string)($_POST['license_ref'] ?? '')), $licUrl,
                        preg_match('/^\d{4}-\d{2}-\d{2}$/', $licExp) ? $licExp : null, trim((string)($_POST['license_note'] ?? '')), trim((string)($_POST['license_note_en'] ?? ''))];
                $data = [
                    $catId, $title, trim((string)$_POST['description']),
                    trim((string)($_POST['title_en'] ?? '')), trim((string)($_POST['description_en'] ?? '')),
                    implode(', ', array_filter(array_map('trim', explode(',', (string)$_POST['tags'])))),
                    strtoupper(trim((string)$_POST['language'])), trim((string)$_POST['region']), trim((string)$_POST['dimensions']),
                    isset(VISIBILITY[$_POST['visibility'] ?? '']) ? $_POST['visibility'] : 'all',
                    isset($_POST['is_published']) ? 1 : 0, now(),
                ];
                if ($a) {
                    q('UPDATE assets SET category_id=?, title=?, description=?, title_en=?, description_en=?, tags=?, language=?, region=?, dimensions=?, visibility=?, is_published=?, updated_at=? WHERE id=?', [...$data, $id]);
                } else {
                    q('INSERT INTO assets (category_id, title, description, title_en, description_en, tags, language, region, dimensions, visibility, is_published, updated_at, created_at, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [...$data, now(), $admin['id']]);
                    $id = (int)db()->lastInsertId();
                }
                q('UPDATE assets SET license_type=?, license_provider=?, license_ref=?, license_url=?, license_expires=?, license_note=?, license_note_en=? WHERE id=?', [...$lic, $id]);
                if (!empty($_FILES['preview']['name'])) {
                    $up = store_upload($_FILES['preview'], 'previews', PREVIEW_EXT);
                    $old = q('SELECT preview_file FROM assets WHERE id = ?', [$id])->fetchColumn();
                    delete_stored('previews', $old ?: null);
                    q('UPDATE assets SET preview_file = ? WHERE id = ?', [$up['file_name'], $id]);
                }
                flash($a ? t('Wijzigingen opgeslagen.') : t('Asset aangemaakt – voeg nu de bestanden toe.'));
                break;

            case 'add_files':
                $files = $_FILES['files'] ?? null;
                $n = 0;
                if ($files && is_array($files['name'])) {
                    $count = count(array_filter($files['name']));
                    foreach ($files['name'] as $i => $name) {
                        if ($name === '') continue;
                        $one = ['name' => $name, 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                        $up = store_upload($one, 'files');
                        [$usage, $space] = guess_usage($name);
                        if (($_POST['usage_type'] ?? 'auto') !== 'auto' && isset(USAGES[$_POST['usage_type']])) { $usage = $_POST['usage_type']; $space = USAGES[$usage]['space'] ?: $space; }
                        $label = trim((string)($_POST['label'] ?? ''));
                        $labelEn = trim((string)($_POST['label_en'] ?? ''));
                        if ($label === '' || $count > 1) { $label = ucfirst(str_replace(['-', '_'], ' ', pathinfo($name, PATHINFO_FILENAME))); $labelEn = ''; }
                        q('INSERT INTO renditions (asset_id, label, label_en, usage_type, color_space, format, file_name, original_name, file_size, mime_type, sort_order, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                          [$id, $label, $labelEn, $usage, $space, format_of($name), $up['file_name'], $up['original_name'], $up['size'], $up['mime'], 10, now()]);
                        $n++;
                    }
                }
                q('UPDATE assets SET updated_at = ? WHERE id = ?', [now(), $id]);
                flash($n ? t('%d bestand(en) toegevoegd. Controleer label en toepassing.', $n) : t('Geen bestanden gekozen.'), $n ? 'ok' : 'warn');
                break;

            case 'update_rendition':
                $usage = isset(USAGES[$_POST['usage_type'] ?? '']) ? $_POST['usage_type'] : 'print';
                q('UPDATE renditions SET label = ?, label_en = ?, usage_type = ?, color_space = ?, sort_order = ? WHERE id = ? AND asset_id = ?',
                  [trim((string)$_POST['label']), trim((string)($_POST['label_en'] ?? '')), $usage, (string)$_POST['color_space'], (int)$_POST['sort_order'], (int)$_POST['rid'], $id]);
                flash(t('Bestand bijgewerkt.'));
                break;

            case 'delete_rendition':
                $r = q('SELECT * FROM renditions WHERE id = ? AND asset_id = ?', [(int)$_POST['rid'], $id])->fetch();
                if ($r) { delete_stored('files', $r['file_name']); q('DELETE FROM renditions WHERE id = ?', [$r['id']]); }
                flash(t('Bestand verwijderd.'));
                break;

            case 'delete_asset':
                foreach (q('SELECT file_name FROM renditions WHERE asset_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN) as $fn) delete_stored('files', $fn);
                delete_stored('previews', $a['preview_file'] ?? null);
                q('DELETE FROM renditions WHERE asset_id = ?', [$id]);
                q('DELETE FROM assets WHERE id = ?', [$id]);
                flash(t('Asset verwijderd.'));
                redirect('admin/assets.php');
        }
    } catch (Throwable $ex) {
        flash($ex->getMessage(), 'err');
    }
    redirect('admin/asset_edit.php?id=' . $id);
}

$cats = q('SELECT * FROM categories ORDER BY sort_order, name')->fetchAll();
$rends = $a ? q('SELECT * FROM renditions WHERE asset_id = ? ORDER BY sort_order, id', [$id])->fetchAll() : [];
$v = $a ?? ['license_type' => 'own', 'license_provider' => '', 'license_ref' => '', 'license_url' => '', 'license_expires' => null, 'license_note' => '', 'license_note_en' => '',
            'title' => '', 'description' => '', 'title_en' => '', 'description_en' => '', 'tags' => '', 'language' => '', 'region' => 'Wereldwijd', 'dimensions' => '', 'visibility' => 'all', 'is_published' => 1, 'category_id' => 0];

layout_header($a ? t('Bewerk: %s', $a['title']) : t('Nieuw asset'), 'admin-assets');
?>
<div class="page-head row">
  <div><p class="crumbs"><a href="<?= url('admin/assets.php') ?>"><?= e(t('Assets')) ?></a> <span>/</span> <?= e($a ? t('Bewerken') : t('Nieuw')) ?></p><h1><?= e($a ? loc($a, 'title') : t('Nieuw asset')) ?></h1></div>
  <?php if ($a): ?><a class="btn" href="<?= url('asset.php?id=' . $id) ?>"><?= e(t('Bekijk zoals gebruiker')) ?></a><?php endif; ?>
</div>

<div class="edit-layout">
  <form class="card pad form" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <h2><?= e(t('Gegevens')) ?></h2>

    <div class="langtabs" role="tablist">
      <button type="button" class="on" data-pane="pane-nl">Nederlands</button>
      <button type="button" data-pane="pane-en">English <small class="muted"><?= e(t('(optioneel)')) ?></small></button>
    </div>
    <div class="langpane" id="pane-nl">
      <label><?= e(t('Titel')) ?><input name="title" value="<?= e($v['title']) ?>" required></label>
      <label><?= e(t('Omschrijving')) ?><textarea name="description" rows="4" placeholder="<?= e(t('Waarvoor gebruik je dit? Waar moet de gebruiker op letten?')) ?>"><?= e($v['description']) ?></textarea></label>
    </div>
    <div class="langpane" id="pane-en" hidden>
      <label><?= e(t('Titel (Engels)')) ?><input name="title_en" value="<?= e($v['title_en']) ?>" placeholder="<?= e(t('Leeg = Nederlandse titel wordt getoond')) ?>"></label>
      <label><?= e(t('Omschrijving (Engels)')) ?><textarea name="description_en" rows="4"><?= e($v['description_en']) ?></textarea></label>
    </div>

    <div class="row2">
      <label><?= e(t('Categorie')) ?><select name="category_id">
        <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$v['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e(loc($c, 'name')) ?></option><?php endforeach; ?>
      </select></label>
      <label><?= e(t('…of nieuwe categorie')) ?><input name="new_category" placeholder="<?= e(t('optioneel')) ?>"></label>
    </div>
    <label><?= e(t('Tags')) ?> <small class="muted"><?= e(t('(komma-gescheiden, worden meegenomen in zoeken)')) ?></small><input name="tags" value="<?= e($v['tags']) ?>" placeholder="beurs, roll-up, 2026"></label>
    <div class="row3">
      <label><?= e(t('Taal')) ?><input name="language" value="<?= e($v['language']) ?>" placeholder="NL, EN, DE, INT" maxlength="10"></label>
      <label><?= e(t('Regio')) ?><input name="region" value="<?= e($v['region']) ?>"></label>
      <label><?= e(t('Formaat')) ?><input name="dimensions" value="<?= e($v['dimensions']) ?>" placeholder="850 × 2000 mm"></label>
    </div>
    <div class="row2">
      <label><?= e(t('Zichtbaar voor')) ?><select name="visibility">
        <?php foreach (VISIBILITY as $k => $l): ?><option value="<?= $k ?>" <?= $v['visibility'] === $k ? 'selected' : '' ?>><?= e(t($l)) ?></option><?php endforeach; ?>
      </select></label>
      <label class="check"><input type="checkbox" name="is_published" <?= (int)$v['is_published'] ? 'checked' : '' ?>> <?= e(t('Gepubliceerd')) ?></label>
    </div>
    <fieldset class="rights">
      <legend><?= icon('lock') ?> <?= e(t('Gebruiksrechten')) ?></legend>
      <div class="radio-cards">
        <?php foreach (LICENSES as $k => $l): ?>
        <label class="radio-card"><input type="radio" name="license_type" value="<?= $k ?>" <?= ($v['license_type'] ?? 'own') === $k ? 'checked' : '' ?>>
          <span><strong><?= e(t($l)) ?></strong><small><?= e($k === 'own' ? t('Kantoren én distributeurs mogen dit downloaden en gebruiken.') : t('Kantoren mogen downloaden. Distributeurs zien het wel, maar kunnen niet downloaden en krijgen een link naar de leverancier.')) ?></small></span></label>
        <?php endforeach; ?>
      </div>
      <div id="stockfields">
        <div class="row2">
          <label><?= e(t('Leverancier')) ?><input name="license_provider" value="<?= e($v['license_provider']) ?>" placeholder="Adobe Stock, Getty Images, Shutterstock…"></label>
          <label><?= e(t('Stock-ID')) ?><input name="license_ref" value="<?= e($v['license_ref']) ?>" placeholder="bv. 123456789"></label>
        </div>
        <div class="row2">
          <label><?= e(t('Link naar de foto bij de leverancier')) ?><input name="license_url" value="<?= e($v['license_url']) ?>" placeholder="https://stock.adobe.com/…"></label>
          <label><?= e(t('Licentie geldig tot')) ?> <small class="muted"><?= e(t('(optioneel)')) ?></small><input type="date" name="license_expires" value="<?= e((string)($v['license_expires'] ?? '')) ?>"></label>
        </div>
      </div>
      <label><?= e(t('Toelichting op de licentie')) ?> <small class="muted"><?= e(t('(optioneel, zichtbaar voor gebruikers)')) ?></small><textarea name="license_note" rows="2" placeholder="<?= e(t('bv. Standaardlicentie op naam van {bedrijf} HQ')) ?>"><?= e((string)($v['license_note'] ?? '')) ?></textarea></label>
      <label><?= e(t('Toelichting op de licentie (Engels)')) ?> <small class="muted"><?= e(t('(optioneel)')) ?></small><textarea name="license_note_en" rows="2" placeholder="e.g. Standard licence in the name of {bedrijf} HQ"><?= e((string)($v['license_note_en'] ?? '')) ?></textarea></label>
      <p class="muted small hint"><?= e(t('Tip: schrijf {bedrijf} in plaats van de bedrijfsnaam. Die wordt dan automatisch vervangen door de naam uit Beheer → Huisstijl (nu: %s).', company())) ?></p>
    </fieldset>
    <label><?= e(t('Voorbeeldafbeelding')) ?> <small class="muted"><?= e(t('(PNG/JPG, liefst 1200 × 900 px)')) ?></small><input type="file" name="preview" accept=".png,.jpg,.jpeg,.webp"></label>
    <button class="btn btn-primary"><?= e($a ? t('Opslaan') : t('Aanmaken')) ?></button>
  </form>

  <div>
    <?php if ($a): ?>
    <div class="card"><img class="edit-preview" src="<?= url('preview.php?id=' . $id) ?>" alt=""></div>

    <div class="card pad">
      <h2><?= e(t('Bestanden (%d)', count($rends))) ?></h2>
      <?php foreach ($rends as $r): ?>
      <form class="rend-row" method="post">
        <?= csrf_field() ?><input type="hidden" name="rid" value="<?= (int)$r['id'] ?>">
        <span class="fmt fmt-<?= strtolower(e($r['format'])) ?>"><?= e($r['format']) ?></span>
        <input name="label" value="<?= e($r['label']) ?>" title="<?= e(t('Label dat de gebruiker ziet')) ?>" placeholder="NL">
        <input name="label_en" value="<?= e($r['label_en']) ?>" title="<?= e(t('Label in het Engels (optioneel)')) ?>" placeholder="EN <?= e(t('(optioneel)')) ?>">
        <select name="usage_type"><?php foreach (USAGES as $k => $u): ?><option value="<?= $k ?>" <?= $r['usage_type'] === $k ? 'selected' : '' ?>><?= e(t($u['label'])) ?></option><?php endforeach; ?></select>
        <select name="color_space"><?php foreach (['', 'CMYK', 'PMS', 'RGB'] as $cs): ?><option <?= $r['color_space'] === $cs ? 'selected' : '' ?> value="<?= $cs ?>"><?= $cs ?: '—' ?></option><?php endforeach; ?></select>
        <input name="sort_order" type="number" value="<?= (int)$r['sort_order'] ?>" class="w-num" title="<?= e(t('Volgorde')) ?>">
        <small class="muted"><?= human_size((int)$r['file_size']) ?></small>
        <button class="btn btn-small" name="action" value="update_rendition"><?= e(t('Opslaan')) ?></button>
        <button class="btn btn-small btn-danger" name="action" value="delete_rendition" onclick="return confirm('<?= e(t('Bestand verwijderen?')) ?>')">×</button>
      </form>
      <?php endforeach; ?>

      <form class="upload-box" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_files">
        <h3><?= icon('plus') ?> <?= e(t('Bestanden toevoegen')) ?></h3>
        <p class="muted small"><?= t('Tip: zet <code>cmyk</code>, <code>pms</code> of <code>rgb</code> in de bestandsnaam – de toepassing wordt dan automatisch herkend.') ?></p>
        <input type="file" name="files[]" multiple required>
        <div class="row2">
          <label><?= e(t('Label')) ?> <small class="muted"><?= e(t('(bij één bestand)')) ?></small><input name="label" placeholder="<?= e(t('bv. Drukklare PDF – CMYK, 5 mm afloop')) ?>"></label>
          <label><?= e(t('Label (Engels)')) ?> <small class="muted"><?= e(t('(optioneel)')) ?></small><input name="label_en" placeholder="Print-ready PDF – CMYK, 5 mm bleed"></label>
        </div>
        <label><?= e(t('Toepassing')) ?><select name="usage_type"><option value="auto"><?= e(t('Automatisch herkennen')) ?></option><?php foreach (USAGES as $k => $u): ?><option value="<?= $k ?>"><?= e(t($u['label'])) ?></option><?php endforeach; ?></select></label>
        <button class="btn btn-primary"><?= e(t('Uploaden')) ?></button>
      </form>
    </div>

    <form class="card pad danger-zone" method="post" onsubmit="return confirm('<?= e(t('Dit asset en alle bestanden definitief verwijderen?')) ?>')">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete_asset">
      <strong><?= e(t('Asset verwijderen')) ?></strong><span class="muted small"><?= e(t('Verwijdert ook alle bestanden. Downloadhistorie blijft bewaard.')) ?></span>
      <button class="btn btn-danger btn-small"><?= e(t('Verwijderen')) ?></button>
    </form>
    <?php else: ?>
      <div class="card pad muted"><?= e(t('Sla eerst de gegevens op; daarna kun je bestanden (CMYK, PMS, RGB…) toevoegen.')) ?></div>
    <?php endif; ?>
  </div>
</div>
<script>
(function () {
  function sync() { var st = document.querySelector('input[name=license_type][value=stock]'); document.getElementById('stockfields').hidden = !(st && st.checked); }
  document.querySelectorAll('input[name=license_type]').forEach(function (r) { r.addEventListener('change', sync); }); sync();
})();
document.querySelectorAll('.langtabs button').forEach(function (b) {
  b.addEventListener('click', function () {
    document.querySelectorAll('.langtabs button').forEach(function (x) { x.classList.toggle('on', x === b); });
    document.querySelectorAll('.langpane').forEach(function (p) { p.hidden = p.id !== b.dataset.pane; });
  });
});
</script>
<?php layout_footer();

<?php
require __DIR__ . '/../app/bootstrap.php';
$user = require_login();

$id = (int)($_GET['id'] ?? 0);
$a = q('SELECT a.*, c.name AS cat_name, c.name_en AS cat_name_en, c.slug AS cat_slug FROM assets a JOIN categories c ON c.id = a.category_id WHERE a.id = ?', [$id])->fetch();
if (!$a || !can_view_asset($a)) { http_response_code(404); layout_header(t('Niet gevonden')); echo '<div class="card pad"><h2>' . e(t('Dit item bestaat niet of je hebt er geen toegang toe.')) . '</h2><p><a href="' . url('index.php') . '">' . e(t('Terug naar de bibliotheek')) . '</a></p></div>'; layout_footer(); exit; }

$rends = q('SELECT * FROM renditions WHERE asset_id = ? ORDER BY sort_order, id', [$id])->fetchAll();
$groups = [];
foreach ($rends as $r) $groups[$r['usage_type']][] = $r;
uksort($groups, fn($x, $y) => array_search($x, array_keys(USAGES)) <=> array_search($y, array_keys(USAGES)));

$dl = (int)q('SELECT COUNT(*) FROM downloads WHERE asset_id = ?', [$id])->fetchColumn();
$related = q("SELECT a.id, a.title, a.title_en, a.language FROM assets a WHERE a.category_id = ? AND a.id <> ? AND " . visibility_sql('a') . " ORDER BY a.updated_at DESC LIMIT 4", [$a['category_id'], $id])->fetchAll();
$role = $user['role'];
$stock = is_stock($a);
$expired = license_expired($a);
$canDl = can_download_asset($a);
$tags = array_filter(array_map('trim', explode(',', (string)$a['tags'])));

layout_header(loc($a, 'title'));
?>
<nav class="crumbs"><a href="<?= url('index.php') ?>"><?= e(t('Bibliotheek')) ?></a> <span>/</span> <a href="<?= url('index.php?cat=' . urlencode($a['cat_slug'])) ?>"><?= e(loc($a, 'cat_name')) ?></a> <span>/</span> <?= e(loc($a, 'title')) ?></nav>

<div class="asset-layout">
  <section>
    <div class="preview-box card <?= !$canDl ? 'is-locked' : '' ?>" <?= !$canDl ? 'oncontextmenu="return false"' : '' ?>>
      <img src="<?= url('preview.php?id=' . $id) ?>" alt="<?= e(t('Voorbeeld van %s', loc($a, 'title'))) ?>" draggable="<?= $canDl ? 'true' : 'false' ?>">
      <?php if ($stock && $role === 'partner'): ?>
        <div class="preview-badge"><?= icon('lock') ?> <?= e(t('Alleen ter inzage')) ?></div>
      <?php endif; ?>
    </div>
    <div class="card pad helpbox">
      <h3><?= icon('info') ?> <?= e(t('Welk bestand heb ik nodig?')) ?></h3>
      <dl>
        <dt><span class="badge badge-cmyk">CMYK</span></dt><dd><?= e(t('Voor alles wat gedrukt wordt: roll-ups, flyers, brochures, beurswanden. Stuur de drukklare PDF direct naar je drukker.')) ?></dd>
        <dt><span class="badge badge-pms">PMS</span></dt><dd><?= e(t('Voor steunkleuren: zeefdruk, borduren, folie-belettering en relatiegeschenken. Vraag je leverancier welke hij wil.')) ?></dd>
        <dt><span class="badge badge-rgb">RGB</span></dt><dd><?= e(t('Voor schermen: website, social media, e-mail, PowerPoint en Word. Gebruik PNG voor transparantie, SVG voor scherpte op elk formaat.')) ?></dd>
      </dl>
    </div>
  </section>

  <aside class="asset-side">
    <div class="card pad">
      <p class="eyebrow"><?= e(loc($a, 'cat_name')) ?></p>
      <h1 class="asset-title"><?= e(loc($a, 'title')) ?></h1>
      <?php if (!(int)$a['is_published']): ?><p class="flash flash-warn"><?= e(t('Concept – alleen zichtbaar voor beheerders.')) ?></p><?php endif; ?>
      <?php if (loc($a, 'description') !== ''): ?><p><?= nl2br(e(loc($a, 'description'))) ?></p><?php endif; ?>
      <table class="facts">
        <?php if ($a['dimensions']): ?><tr><th><?= e(t('Formaat')) ?></th><td><?= e(t($a['dimensions'])) ?></td></tr><?php endif; ?>
        <?php if ($a['language']): ?><tr><th><?= e(t('Taal')) ?></th><td><?= e($a['language']) ?></td></tr><?php endif; ?>
        <?php if ($a['region']): ?><tr><th><?= e(t('Regio')) ?></th><td><?= e(t($a['region'])) ?></td></tr><?php endif; ?>
        <tr><th><?= e(t('Bijgewerkt')) ?></th><td><?= fdate($a['updated_at']) ?></td></tr>
        <tr><th><?= e(t('Zichtbaar voor')) ?></th><td><?= e(t(VISIBILITY[$a['visibility']] ?? $a['visibility'])) ?></td></tr>
        <tr><th><?= e(t('Gebruiksrechten')) ?></th><td><?php if ($stock): ?><span class="pill pill-stock"><?= e(t('Stocklicentie')) ?></span> <?= e($a['license_provider']) ?><?php else: ?><span class="pill pill-ok"><?= e(t('Eigen beeld')) ?></span><?php endif; ?></td></tr>
        <tr><th><?= e(t('Downloads')) ?></th><td><?= $dl ?>×</td></tr>
      </table>
      <?php if ($tags): ?><div class="tags"><?php foreach ($tags as $t): ?><a href="<?= url('index.php?q=' . urlencode($t)) ?>">#<?= e($t) ?></a><?php endforeach; ?></div><?php endif; ?>
      <?php if (is_admin()): ?><p><a class="btn btn-small" href="<?= url('admin/asset_edit.php?id=' . $id) ?>"><?= icon('settings') ?> <?= e(t('Bewerken')) ?></a></p><?php endif; ?>
    </div>

    <?php if ($expired && $role !== 'admin'): ?>
    <div class="card pad license license-block">
      <h2><?= icon('lock') ?> <?= e(t('Licentie verlopen')) ?></h2>
      <p><?= e(t('De licentie voor deze foto is verlopen op %s. De foto mag niet meer worden gebruikt en is daarom niet te downloaden.', fdate($a['license_expires']))) ?></p>
    </div>
    <?php elseif ($stock && $role === 'partner'): ?>
    <div class="card pad license license-block">
      <h2><?= icon('lock') ?> <?= e(t('Niet beschikbaar voor distributeurs')) ?></h2>
      <p><?= e(t('Dit is een stockfoto. %s heeft hiervoor een licentie die alleen geldt voor de eigen organisatie, inclusief de dochterkantoren. Als distributeur of salespartner mag je deze foto niet downloaden of gebruiken – ook niet via een collega.', company())) ?></p>
      <p><strong><?= e(t('Wil je dezelfde foto gebruiken?')) ?></strong> <?= e(t('Koop dan zelf een licentie bij de leverancier. Met het stock-ID vind je de foto direct terug.')) ?></p>
      <table class="facts">
        <?php if ($a['license_provider']): ?><tr><th><?= e(t('Leverancier')) ?></th><td><?= e($a['license_provider']) ?></td></tr><?php endif; ?>
        <?php if ($a['license_ref']): ?><tr><th><?= e(t('Stock-ID')) ?></th><td><code id="stockref"><?= e($a['license_ref']) ?></code> <button type="button" class="linkbtn" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('stockref').textContent); this.textContent='<?= e(t('Gekopieerd')) ?>'"><?= e(t('Kopieer')) ?></button></td></tr><?php endif; ?>
      </table>
      <?php if ($a['license_url']): ?>
        <a class="btn btn-accent btn-block" href="<?= e($a['license_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('Bekijk bij %s', $a['license_provider'] ?: t('de leverancier'))) ?> ↗</a>
      <?php endif; ?>
      <p class="small center"><a href="<?= url('index.php?cat=fotografie&lic=own') ?>"><?= e(t('Of bekijk onze eigen foto\'s – die mag je wél gebruiken')) ?> →</a></p>
    </div>
    <?php elseif ($stock): ?>
    <div class="card pad license license-info">
      <h2><?= icon('info') ?> <?= e(t('Stockfoto – alleen voor intern gebruik')) ?></h2>
      <p><?= e(t('Je mag deze foto gebruiken voor communicatie van %s en de dochterkantoren. Stuur hem niet door aan distributeurs of andere externe partijen; zij moeten zelf een licentie kopen.', company())) ?></p>
      <table class="facts">
        <?php if ($a['license_provider']): ?><tr><th><?= e(t('Leverancier')) ?></th><td><?php if ($a['license_url']): ?><a href="<?= e($a['license_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($a['license_provider']) ?> ↗</a><?php else: ?><?= e($a['license_provider']) ?><?php endif; ?></td></tr><?php endif; ?>
        <?php if ($a['license_ref']): ?><tr><th><?= e(t('Stock-ID')) ?></th><td><code><?= e($a['license_ref']) ?></code></td></tr><?php endif; ?>
        <?php if ($a['license_expires']): ?><tr><th><?= e(t('Licentie geldig tot')) ?></th><td><?= fdate($a['license_expires']) ?><?= $expired ? ' <span class="pill pill-warn">' . e(t('Verlopen')) . '</span>' : '' ?></td></tr><?php endif; ?>
      </table>
      <?php if (loc($a, 'license_note') !== ''): ?><p class="small muted"><?= nl2br(e(with_company(loc($a, 'license_note')))) ?></p><?php endif; ?>
      <?php if ($role === 'admin'): ?><p class="small note"><?= e(t('Distributeurs zien deze foto wel, maar kunnen hem niet downloaden. Zij krijgen een melding en de link naar de leverancier.')) ?></p><?php endif; ?>
    </div>
    <?php elseif (loc($a, 'license_note') !== ''): ?>
    <div class="card pad license license-own">
      <h2><?= icon('info') ?> <?= e(t('Eigen beeld – vrij te gebruiken')) ?></h2>
      <p class="small"><?= nl2br(e(with_company(loc($a, 'license_note')))) ?></p>
    </div>
    <?php endif; ?>

    <div class="card pad downloads <?= $canDl ? '' : 'is-locked' ?>">
      <div class="dl-head">
        <h2><?= e(t('Downloaden')) ?></h2>
        <?php if ($canDl && count($rends) > 1 && class_exists('ZipArchive')): ?>
          <a class="btn btn-small" href="<?= url('download.php?zip=' . $id) ?>"><?= icon('zip') ?> <?= e(t('Alles (ZIP)')) ?></a>
        <?php endif; ?>
      </div>
      <?php if (!$rends): ?><p class="muted"><?= e(t('Er zijn nog geen bestanden toegevoegd.')) ?></p><?php endif; ?>
      <?php foreach ($groups as $usage => $list): $u = USAGES[$usage] ?? ['label' => $usage, 'space' => '', 'hint' => '']; ?>
        <div class="dl-group">
          <h3><?= e(t($u['label'])) ?> <?php if ($u['space']): ?><span class="badge badge-<?= strtolower($u['space']) ?>"><?= $u['space'] ?></span><?php endif; ?></h3>
          <p class="muted small"><?= e(t($u['hint'])) ?></p>
          <?php foreach ($list as $r): ?>
          <?php if ($canDl): ?>
          <a class="dl-row" href="<?= url('download.php?id=' . (int)$r['id']) ?>">
          <?php else: ?>
          <div class="dl-row locked" title="<?= e(t('Niet beschikbaar vanwege de licentievoorwaarden')) ?>">
          <?php endif; ?>
            <span class="fmt fmt-<?= strtolower(e($r['format'])) ?>"><?= e($r['format']) ?></span>
            <span class="dl-txt"><strong><?= e(loc($r, 'label')) ?></strong><small><?= human_size((int)$r['file_size']) ?><?= $r['color_space'] ? ' · ' . e($r['color_space']) : '' ?></small></span>
            <span class="dl-btn"><?= icon($canDl ? 'download' : 'lock') ?></span>
          <?= $canDl ? '</a>' : '</div>' ?>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </aside>
</div>

<?php if ($related): ?>
<h2 class="section-title"><?= e(t('Meer in %s', loc($a, 'cat_name'))) ?></h2>
<div class="grid grid-small">
  <?php foreach ($related as $r): ?>
  <a class="asset-card" href="<?= url('asset.php?id=' . (int)$r['id']) ?>">
    <div class="thumb"><img src="<?= url('preview.php?id=' . (int)$r['id']) ?>" alt="" loading="lazy"></div>
    <div class="meta"><strong><?= e(loc($r, 'title')) ?></strong><?php if ($r['language']): ?><small><?= e($r['language']) ?></small><?php endif; ?></div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php layout_footer();

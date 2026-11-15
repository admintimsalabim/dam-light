<?php
require __DIR__ . '/../app/bootstrap.php';
$user = require_login();

$qStr = trim((string)($_GET['q'] ?? ''));
$cat  = (string)($_GET['cat'] ?? '');
$lang = (string)($_GET['lang'] ?? '');
$use  = (string)($_GET['use'] ?? '');
$sort = (string)($_GET['sort'] ?? 'new');
$lic  = (string)($_GET['lic'] ?? '');
$filtered = $qStr !== '' || $cat !== '' || $lang !== '' || $use !== '' || $lic !== '' || isset($_GET['all']);

$vis = visibility_sql('a');
$where = [$vis];
$params = [];
if ($cat !== '')  { $where[] = 'c.slug = ?'; $params[] = $cat; }
if ($lang !== '') { $where[] = 'a.language = ?'; $params[] = $lang; }
if (isset(LICENSES[$lic])) { $where[] = 'a.license_type = ?'; $params[] = $lic; }
if (isset(USAGES[$use])) { $where[] = 'EXISTS (SELECT 1 FROM renditions r2 WHERE r2.asset_id = a.id AND r2.usage_type = ?)'; $params[] = $use; }
foreach (array_slice(preg_split('/\s+/', mb_strtolower($qStr), -1, PREG_SPLIT_NO_EMPTY), 0, 6) as $term) {
    $where[] = '(LOWER(a.title) LIKE ? OR LOWER(a.title_en) LIKE ? OR LOWER(a.description) LIKE ? OR LOWER(a.description_en) LIKE ? OR LOWER(a.tags) LIKE ? OR LOWER(c.name) LIKE ? OR LOWER(c.name_en) LIKE ? OR LOWER(a.region) LIKE ?)';
    array_push($params, ...array_fill(0, 8, '%' . $term . '%'));
}
$order = match ($sort) {
    'title'   => (locale() === 'nl' ? 'a.title' : "COALESCE(NULLIF(a.title_en, ''), a.title)") . ' ASC',
    'popular' => 'dl_count DESC, a.updated_at DESC',
    default   => 'a.updated_at DESC, a.id DESC',
};

$sql = "SELECT a.*, c.name AS cat_name, c.name_en AS cat_name_en, c.slug AS cat_slug,
          (SELECT GROUP_CONCAT(DISTINCT r.color_space) FROM renditions r WHERE r.asset_id = a.id AND r.color_space <> '') AS spaces,
          (SELECT COUNT(*) FROM renditions r WHERE r.asset_id = a.id) AS rend_count,
          (SELECT COUNT(*) FROM downloads d WHERE d.asset_id = a.id) AS dl_count
        FROM assets a JOIN categories c ON c.id = a.category_id
        WHERE " . implode(' AND ', $where) . " ORDER BY $order";
$assets = q($sql, $params)->fetchAll();

$cats = q("SELECT c.*, (SELECT COUNT(*) FROM assets a WHERE a.category_id = c.id AND $vis) AS n,
                 (SELECT a.id FROM assets a WHERE a.category_id = c.id AND $vis ORDER BY a.updated_at DESC LIMIT 1) AS cover_id
           FROM categories c ORDER BY c.sort_order, c.name")->fetchAll();
$langs = q("SELECT DISTINCT a.language FROM assets a WHERE $vis AND a.language <> '' ORDER BY a.language")->fetchAll(PDO::FETCH_COLUMN);
$total = array_sum(array_column($cats, 'n'));
$catName = '';

function qs(array $over): string
{
    $p = array_merge(['q' => $_GET['q'] ?? '', 'cat' => $_GET['cat'] ?? '', 'lang' => $_GET['lang'] ?? '', 'use' => $_GET['use'] ?? '', 'lic' => $_GET['lic'] ?? '', 'sort' => $_GET['sort'] ?? ''], $over);
    return url('index.php') . '?' . http_build_query(array_filter($p, fn($v) => $v !== ''));
}

const LANG_NAMES = ['NL' => 'Nederlands', 'EN' => 'Engels', 'DE' => 'Duits', 'FR' => 'Frans', 'ES' => 'Spaans', 'INT' => 'Taalonafhankelijk'];
foreach ($cats as $c) if ($c['slug'] === $cat) $catName = loc($c, 'name');

layout_header($catName ?: t('Bibliotheek'), 'library', $qStr);
?>
<?php if (!$filtered): ?>
<section class="hero">
  <div>
    <p class="eyebrow"><?= e(t('Welkom, %s', explode(' ', $user['name'])[0])) ?></p>
    <h1><?= e(t('Waar ben je naar op zoek?')) ?></h1>
    <p class="lead"><?= e(t('Vind de officiële %s-middelen voor jouw beurs, campagne of document – en download direct het juiste bestand voor drukwerk of digitaal gebruik.', company())) ?></p>
    <form class="hero-search" action="<?= url('index.php') ?>" method="get">
      <?= icon('search') ?><input type="search" name="q" placeholder="<?= e(t('Bijv. roll-up, logo wit, brochure Duits…')) ?>">
      <button class="btn btn-accent"><?= e(t('Zoeken')) ?></button>
    </form>
    <div class="quick">
      <span><?= e(t('Populair:')) ?></span>
      <a href="<?= qs(['cat' => 'logos']) ?>"><?= e(t("Logo's")) ?></a>
      <a href="<?= qs(['cat' => 'roll-ups']) ?>"><?= e(t('Roll-up banners')) ?></a>
      <a href="<?= qs(['q' => 'beurs']) ?>"><?= e(t('Beurs')) ?></a>
      <a href="<?= qs(['use' => 'print']) ?>"><?= e(t("Drukklare PDF's")) ?></a>
    </div>
  </div>
</section>

<section>
  <h2 class="section-title"><?= e(t('Bladeren per categorie')) ?></h2>
  <div class="cat-tiles">
  <?php foreach ($cats as $c): if (!$c['n']) continue; ?>
    <a class="cat-tile" href="<?= qs(['cat' => $c['slug']]) ?>">
      <div class="cat-img"><?php if ($c['cover_id']): ?><img src="<?= url('preview.php?id=' . (int)$c['cover_id']) ?>" alt="" loading="lazy"><?php endif; ?></div>
      <div class="cat-txt"><strong><?= e(loc($c, 'name')) ?></strong><small><?= e(tn('%d item', '%d items', (int)$c['n'])) ?> · <?= e(loc($c, 'description')) ?></small></div>
    </a>
  <?php endforeach; ?>
  </div>
</section>
<h2 class="section-title"><?= e(t('Recent bijgewerkt')) ?></h2>
<?php endif; ?>

<div class="library <?= $filtered ? '' : 'nosidebar' ?>">
  <?php if ($filtered): ?>
  <aside class="filters">
    <h3><?= e(t('Categorie')) ?></h3>
    <a class="<?= $cat === '' ? 'on' : '' ?>" href="<?= qs(['cat' => '', 'all' => '1']) ?>"><?= e(t('Alles')) ?> <span><?= $total ?></span></a>
    <?php foreach ($cats as $c): ?>
      <a class="<?= $cat === $c['slug'] ? 'on' : '' ?>" href="<?= qs(['cat' => $c['slug']]) ?>"><?= e(loc($c, 'name')) ?> <span><?= (int)$c['n'] ?></span></a>
    <?php endforeach; ?>
    <h3><?= e(t('Toepassing')) ?></h3>
    <a class="<?= $use === '' ? 'on' : '' ?>" href="<?= qs(['use' => '']) ?>"><?= e(t('Alle toepassingen')) ?></a>
    <?php foreach (USAGES as $k => $u): ?>
      <a class="<?= $use === $k ? 'on' : '' ?>" href="<?= qs(['use' => $k]) ?>"><?= e(t($u['label'])) ?> <?php if ($u['space']): ?><em class="badge badge-<?= strtolower($u['space']) ?>"><?= $u['space'] ?></em><?php endif; ?></a>
    <?php endforeach; ?>
    <h3><?= e(t('Gebruiksrechten')) ?></h3>
    <a class="<?= $lic === '' ? 'on' : '' ?>" href="<?= qs(['lic' => '']) ?>"><?= e(t('Alle')) ?></a>
    <a class="<?= $lic === 'own' ? 'on' : '' ?>" href="<?= qs(['lic' => 'own']) ?>"><?= e(t('Eigen beeld')) ?></a>
    <a class="<?= $lic === 'stock' ? 'on' : '' ?>" href="<?= qs(['lic' => 'stock']) ?>"><?= e(t('Stockfoto')) ?> <?php if ($user['role'] === 'partner'): ?><span><?= icon('lock') ?></span><?php endif; ?></a>
    <h3><?= e(t('Taal')) ?></h3>
    <a class="<?= $lang === '' ? 'on' : '' ?>" href="<?= qs(['lang' => '']) ?>"><?= e(t('Alle talen')) ?></a>
    <?php foreach ($langs as $l): ?>
      <a class="<?= $lang === $l ? 'on' : '' ?>" href="<?= qs(['lang' => $l]) ?>"><?= e(t(LANG_NAMES[$l] ?? $l)) ?></a>
    <?php endforeach; ?>
  </aside>
  <?php endif; ?>

  <section class="results">
    <?php if ($filtered): ?>
    <div class="results-head">
      <div>
        <h1><?= e($catName ?: ($qStr !== '' ? '“' . $qStr . '”' : t('Resultaten'))) ?></h1>
        <p class="muted"><?= e(tn('%d resultaat', '%d resultaten', count($assets))) ?>
          <?php if ($filtered): ?> · <a href="<?= url('index.php') ?>"><?= e(t('filters wissen')) ?></a><?php endif; ?></p>
      </div>
      <form method="get" class="sortform">
        <?php foreach (['q' => $qStr, 'cat' => $cat, 'lang' => $lang, 'use' => $use, 'lic' => $lic] as $k => $v) if ($v !== '') echo '<input type="hidden" name="' . $k . '" value="' . e($v) . '">'; ?>
        <label><?= e(t('Sorteer')) ?> <select name="sort" onchange="this.form.submit()">
          <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>><?= e(t('Nieuwste eerst')) ?></option>
          <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>><?= e(t('Meest gedownload')) ?></option>
          <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>><?= e(t('Titel A–Z')) ?></option>
        </select></label>
      </form>
    </div>
    <?php endif; ?>

    <?php if (!$assets): ?>
      <div class="empty card pad">
        <h3><?= e(t('Niets gevonden')) ?></h3>
        <p class="muted"><?= e(t('Probeer een andere zoekterm of wis de filters. Mis je iets? Laat het Marketing Communicatie weten.')) ?></p>
      </div>
    <?php endif; ?>

    <div class="grid">
    <?php foreach (($filtered ? $assets : array_slice($assets, 0, 8)) as $a): ?>
      <a class="asset-card" href="<?= url('asset.php?id=' . (int)$a['id']) ?>">
        <div class="thumb">
          <img src="<?= url('preview.php?id=' . (int)$a['id']) ?>" alt="" loading="lazy">
          <?php if (is_stock($a) && $user['role'] === 'partner'): ?><span class="flag flag-lock"><?= icon('lock') ?> <?= e(t('Niet voor distributeurs')) ?></span>
          <?php elseif (license_expired($a)): ?><span class="flag flag-draft"><?= e(t('Licentie verlopen')) ?></span>
          <?php elseif (is_stock($a)): ?><span class="flag flag-stock"><?= e(t('Stockfoto · intern')) ?></span>
          <?php elseif (!(int)$a['is_published']): ?><span class="flag flag-draft"><?= e(t('Concept')) ?></span>
          <?php elseif ($a['visibility'] === 'internal' && $user['role'] !== 'partner'): ?><span class="flag"><?= e(t('Alleen kantoren')) ?></span><?php endif; ?>
        </div>
        <div class="meta">
          <small><?= e(loc($a, 'cat_name')) ?><?= $a['language'] ? ' · ' . e($a['language']) : '' ?></small>
          <strong><?= e(loc($a, 'title')) ?></strong>
          <div class="badges"><?= space_badges((string)$a['spaces']) ?><span class="count"><?= e(tn('%d bestand', '%d bestanden', (int)$a['rend_count'])) ?></span></div>
        </div>
      </a>
    <?php endforeach; ?>
    </div>
    <?php if (!$filtered && count($assets) > 8): ?>
      <p class="center"><a class="btn" href="<?= url('index.php?all=1') ?>"><?= e(t('Alle %d items bekijken', count($assets))) ?></a></p>
    <?php endif; ?>
  </section>
</div>
<?php layout_footer();

<?php
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

const LOGO_EXT = ['png', 'jpg', 'jpeg', 'webp', 'svg'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';
    try {
        if ($action === 'reset') {
            delete_stored('branding', setting('logo_file') ?: null);
            foreach (array_keys(setting_defaults()) as $k) save_setting($k, null);
            flash(t('Huisstijl teruggezet naar de standaardinstellingen.'));
        } else {
            $company = trim((string)($_POST['company'] ?? ''));
            $app     = trim((string)($_POST['app_name'] ?? ''));
            $brand   = strtoupper(trim((string)($_POST['color_brand'] ?? '')));
            $accent  = strtoupper(trim((string)($_POST['color_accent'] ?? '')));
            if ($company === '' || $app === '') throw new RuntimeException(t('Bedrijfsnaam en portaalnaam zijn verplicht.'));
            if (!valid_hex($brand) || !valid_hex($accent)) throw new RuntimeException(t('Gebruik kleurcodes in de vorm #RRGGBB.'));
            save_setting('company', mb_substr($company, 0, 60));
            save_setting('app_name', mb_substr($app, 0, 60));
            save_setting('color_brand', $brand);
            save_setting('color_accent', $accent);
            save_setting('logo_hide_name', isset($_POST['logo_hide_name']) ? '1' : '0');

            if (isset($_POST['remove_logo'])) {
                delete_stored('branding', setting('logo_file') ?: null);
                save_setting('logo_file', null);
            } elseif (!empty($_FILES['logo']['name'])) {
                if ($_FILES['logo']['size'] > 2 * 1024 * 1024) throw new RuntimeException(t('Het logo mag maximaal 2 MB zijn.'));
                if (ext_of($_FILES['logo']['name']) === 'svg' && !str_contains((string)file_get_contents($_FILES['logo']['tmp_name']), '<svg')) {
                    throw new RuntimeException(t('Dit SVG-bestand lijkt niet geldig.'));
                }
                $up = store_upload($_FILES['logo'], 'branding', LOGO_EXT);
                delete_stored('branding', setting('logo_file') ?: null);
                save_setting('logo_file', $up['file_name']);
            }
            flash(t('Huisstijl opgeslagen.'));
        }
    } catch (Throwable $ex) {
        flash($ex->getMessage(), 'err');
    }
    redirect('admin/branding.php');
}

$s = settings(true);
$def = setting_defaults();
$lightBrand = luminance($s['color_brand']) > 0.35;

layout_header(t('Huisstijl'), 'admin-branding');
?>
<div class="page-head"><h1><?= e(t('Huisstijl')) ?></h1><p class="muted"><?= e(t('Pas naam, kleuren en logo van het portaal aan. Wijzigingen zijn direct zichtbaar voor alle gebruikers.')) ?></p></div>

<div class="edit-layout">
  <form class="card pad form" method="post" enctype="multipart/form-data" id="brandform">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <h2><?= e(t('Naam')) ?></h2>
    <div class="row2">
      <label><?= e(t('Bedrijfsnaam')) ?><input name="company" id="f-company" value="<?= e($s['company']) ?>" maxlength="60" required></label>
      <label><?= e(t('Naam van het portaal')) ?><input name="app_name" id="f-app" value="<?= e($s['app_name']) ?>" maxlength="60" required></label>
    </div>

    <h2 class="mt"><?= e(t('Kleuren')) ?></h2>
    <div class="row2">
      <label><?= e(t('Hoofdkleur')) ?> <small class="muted"><?= e(t('(kopbalk, knoppen)')) ?></small>
        <span class="colorpick"><input type="color" id="c-brand" value="<?= e($s['color_brand']) ?>"><input name="color_brand" id="h-brand" value="<?= e($s['color_brand']) ?>" pattern="#[0-9A-Fa-f]{6}" maxlength="7"></span></label>
      <label><?= e(t('Accentkleur')) ?> <small class="muted"><?= e(t('(zoekknop, accenten)')) ?></small>
        <span class="colorpick"><input type="color" id="c-accent" value="<?= e($s['color_accent']) ?>"><input name="color_accent" id="h-accent" value="<?= e($s['color_accent']) ?>" pattern="#[0-9A-Fa-f]{6}" maxlength="7"></span></label>
    </div>
    <p class="flash flash-warn small" id="contrast-warn" <?= $lightBrand ? '' : 'hidden' ?>><?= e(t('Let op: deze hoofdkleur is erg licht. Witte tekst in de kopbalk is dan slecht leesbaar – kies liever een donkere kleur.')) ?></p>

    <h2 class="mt"><?= e(t('Logo in de kopbalk')) ?></h2>
    <p class="muted small"><?= e(t('PNG, SVG, JPG of WEBP, maximaal 2 MB. De kopbalk heeft de hoofdkleur als achtergrond: gebruik dus een witte (diapositieve) versie of een logo met eigen achtergrond. Ideaal: liggend formaat, transparante achtergrond.')) ?></p>
    <label><?= e(t('Nieuw logo uploaden')) ?><input type="file" name="logo" id="f-logo" accept=".png,.svg,.jpg,.jpeg,.webp"></label>
    <label class="check plain"><input type="checkbox" name="logo_hide_name" id="f-hide" <?= $s['logo_hide_name'] === '1' ? 'checked' : '' ?>> <?= e(t('Het logo bevat de bedrijfsnaam al – toon alleen de portaalnaam ernaast')) ?></label>
    <?php if ($s['logo_file'] !== ''): ?>
      <label class="check plain"><input type="checkbox" name="remove_logo" id="f-remove"> <?= e(t('Eigen logo verwijderen (standaardbeeldmerk gebruiken)')) ?></label>
    <?php endif; ?>

    <div class="form-actions">
      <button class="btn btn-primary"><?= e(t('Opslaan')) ?></button>
    </div>
  </form>

  <div>
    <div class="card pad">
      <h2><?= e(t('Voorbeeld')) ?></h2>
      <div class="brand-preview" id="pv">
        <div class="pv-bar">
          <span class="brand" id="pv-brand"><?= brand_block(30) ?></span>
          <span class="pv-search"><?= icon('search') ?> <?= e(t('Zoeken')) ?>…</span>
          <span class="pv-avatar">J</span>
        </div>
        <div class="pv-body">
          <div class="pv-hero">
            <small><?= e(t('Welkom, %s', 'Jonas')) ?></small>
            <strong><?= e(t('Waar ben je naar op zoek?')) ?></strong>
            <span class="pv-btn-accent"><?= e(t('Zoeken')) ?></span>
          </div>
          <div class="pv-row">
            <span class="pv-btn"><?= e(t('Opslaan')) ?></span>
            <span class="pv-link"><?= e(t('Bibliotheek')) ?></span>
            <span class="pv-chip"><?= e(t('Logo\'s')) ?></span>
          </div>
        </div>
      </div>
      <p class="muted small"><?= e(t('Het voorbeeld werkt direct bij terwijl je kleuren of het logo wijzigt; pas na Opslaan geldt het voor iedereen.')) ?></p>
    </div>

    <form class="card pad danger-zone" method="post" onsubmit="return confirm('<?= e(t('Naam, kleuren en logo terugzetten naar de standaardinstellingen?')) ?>')">
      <?= csrf_field() ?><input type="hidden" name="action" value="reset">
      <strong><?= e(t('Standaard herstellen')) ?></strong>
      <span class="muted small"><?= e(t('Terug naar %s, %s en %s met het standaardbeeldmerk.', $def['company'] . ' ' . $def['app_name'], $def['color_brand'], $def['color_accent'])) ?></span>
      <button class="btn btn-danger btn-small"><?= e(t('Herstellen')) ?></button>
    </form>
  </div>
</div>

<script>
(function () {
  var root = document.getElementById('pv');
  function mix(hex, w, r) {
    var a = [1, 3, 5].map(function (i) { return parseInt(hex.substr(i, 2), 16); });
    var b = [1, 3, 5].map(function (i) { return parseInt(w.substr(i, 2), 16); });
    return '#' + a.map(function (v, i) { return ('0' + Math.round(v * (1 - r) + b[i] * r).toString(16)).slice(-2); }).join('');
  }
  function lum(hex) {
    var c = [1, 3, 5].map(function (i) { var x = parseInt(hex.substr(i, 2), 16) / 255; return x <= 0.03928 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4); });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  }
  function apply() {
    var b = document.getElementById('h-brand').value, a = document.getElementById('h-accent').value;
    if (/^#[0-9a-f]{6}$/i.test(b)) {
      root.style.setProperty('--brand', b);
      root.style.setProperty('--brand-2', mix(b, '#ffffff', .14));
      root.style.setProperty('--brand-soft', mix(b, '#ffffff', .9));
      document.getElementById('contrast-warn').hidden = lum(b) <= 0.35;
    }
    if (/^#[0-9a-f]{6}$/i.test(a)) root.style.setProperty('--accent', a);
  }
  [['c-brand', 'h-brand'], ['c-accent', 'h-accent']].forEach(function (p) {
    var c = document.getElementById(p[0]), h = document.getElementById(p[1]);
    c.addEventListener('input', function () { h.value = c.value.toUpperCase(); apply(); });
    h.addEventListener('input', function () { if (/^#[0-9a-f]{6}$/i.test(h.value)) c.value = h.value; apply(); });
  });
  function names() {
    var span = document.querySelector('#pv-brand > span');
    var company = document.getElementById('f-company').value, app = document.getElementById('f-app').value;
    var hasLogo = !!document.querySelector('#pv-brand img');
    span.innerHTML = '';
    if (!(hasLogo && document.getElementById('f-hide').checked)) {
      var s = document.createElement('strong'); s.textContent = company; span.appendChild(s); span.appendChild(document.createTextNode(' '));
    }
    span.appendChild(document.createTextNode(app));
  }
  ['f-company', 'f-app', 'f-hide'].forEach(function (id) { document.getElementById(id).addEventListener('input', names); document.getElementById(id).addEventListener('change', names); });
  document.getElementById('f-logo').addEventListener('change', function (ev) {
    var f = ev.target.files[0]; if (!f) return;
    var r = new FileReader();
    r.onload = function () {
      var brand = document.getElementById('pv-brand'), img = brand.querySelector('img'), svg = brand.querySelector('svg');
      if (!img) { img = document.createElement('img'); img.className = 'brand-logo'; img.style.height = '30px'; brand.insertBefore(img, brand.firstChild); }
      if (svg) svg.remove();
      img.src = r.result; names();
    };
    r.readAsDataURL(f);
  });
  apply();
})();
</script>
<?php layout_footer();

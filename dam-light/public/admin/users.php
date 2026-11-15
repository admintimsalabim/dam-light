<?php
require __DIR__ . '/../../app/bootstrap.php';
$admin = require_admin();

function random_password(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $p = '';
    for ($i = 0; $i < 12; $i++) $p .= $chars[random_int(0, strlen($chars) - 1)];
    return $p;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $uid = (int)($_POST['uid'] ?? 0);
    try {
        if ($action === 'save') {
            $email = strtolower(trim((string)$_POST['email']));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException(t('Ongeldig e-mailadres.'));
            $role = isset(ROLES[$_POST['role'] ?? '']) ? $_POST['role'] : 'partner';
            $loc = valid_locale($_POST['locale'] ?? null) ? $_POST['locale'] : (string)config('default_language', 'nl');
            $exp = trim((string)($_POST['expires_at'] ?? '')) ?: null;
            $fields = [trim((string)$_POST['name']), $email, $role, trim((string)$_POST['organisation']), trim((string)$_POST['country']), $loc, $exp ? $exp . ' 23:59:59' : null];
            if ($uid) {
                if ($uid === (int)$admin['id'] && $role !== 'admin') throw new RuntimeException(t('Je kunt je eigen beheerrechten niet intrekken.'));
                q('UPDATE users SET name=?, email=?, role=?, organisation=?, country=?, locale=?, expires_at=? WHERE id=?', [...$fields, $uid]);
                flash(t('Gebruiker bijgewerkt.'));
            } else {
                if (q('SELECT 1 FROM users WHERE email = ?', [$email])->fetchColumn()) throw new RuntimeException(t('Dit e-mailadres bestaat al.'));
                $pw = trim((string)$_POST['password']) ?: random_password();
                if (strlen($pw) < 8) throw new RuntimeException(t('Wachtwoord moet minimaal 8 tekens zijn.'));
                q('INSERT INTO users (name, email, role, organisation, country, locale, expires_at, password_hash, is_active, created_at) VALUES (?,?,?,?,?,?,?,?,1,?)',
                  [...$fields, password_hash($pw, PASSWORD_DEFAULT), now()]);
                flash(t('Account aangemaakt. Tijdelijk wachtwoord: %s – deel dit veilig met de gebruiker.', $pw));
            }
        } elseif ($action === 'toggle' && $uid !== (int)$admin['id']) {
            q('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$uid]);
            flash(t('Status aangepast.'));
        } elseif ($action === 'reset') {
            $pw = random_password();
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $uid]);
            flash(t('Nieuw tijdelijk wachtwoord: %s – deel dit veilig met de gebruiker.', $pw));
        }
    } catch (Throwable $ex) {
        flash($ex->getMessage(), 'err');
    }
    redirect('admin/users.php');
}

$role = (string)($_GET['role'] ?? '');
$users = q("SELECT u.*, (SELECT COUNT(*) FROM downloads d WHERE d.user_id = u.id) AS dls FROM users u "
         . (isset(ROLES[$role]) ? 'WHERE u.role = ? ' : '') . 'ORDER BY u.role, u.organisation, u.name', isset(ROLES[$role]) ? [$role] : [])->fetchAll();
$edit = isset($_GET['edit']) ? q('SELECT * FROM users WHERE id = ?', [(int)$_GET['edit']])->fetch() : null;
$v = $edit ?: ['id' => 0, 'name' => '', 'email' => '', 'role' => 'partner', 'organisation' => '', 'country' => '', 'locale' => 'en', 'expires_at' => null];

layout_header(t('Gebruikers'), 'admin-users');
?>
<div class="page-head"><h1><?= e(t('Gebruikers')) ?></h1><p class="muted"><?= e(t('Accounts staan los van Microsoft 365 – externe partners hebben geen M365-toegang nodig.')) ?></p></div>

<div class="edit-layout reverse">
  <div class="card users-card">
    <div class="tabs">
      <a class="<?= $role === '' ? 'on' : '' ?>" href="<?= url('admin/users.php') ?>"><?= e(t('Alle')) ?></a>
      <?php foreach (ROLES as $k => $l): ?><a class="<?= $role === $k ? 'on' : '' ?>" href="<?= url('admin/users.php?role=' . $k) ?>"><?= e(t($l)) ?></a><?php endforeach; ?>
    </div>
    <table class="tbl tbl-list">
      <thead><tr><th><?= e(t('Naam')) ?></th><th><?= e(t('Organisatie')) ?></th><th><?= e(t('Rol')) ?></th><th><?= e(t('Laatste login')) ?></th><th class="num"><?= e(t('Downloads')) ?></th><th><?= e(t('Status')) ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u):
          $expired = $u['expires_at'] && strtotime($u['expires_at']) < time(); ?>
        <tr class="<?= !(int)$u['is_active'] || $expired ? 'dim' : '' ?>">
          <td><strong><?= e($u['name']) ?></strong><br><small class="muted"><?= e($u['email']) ?></small></td>
          <td><?= e($u['organisation']) ?><br><small class="muted"><?= e($u['country']) ?></small></td>
          <td><span class="pill pill-<?= e($u['role']) ?>"><?= e(t(ROLES[$u['role']] ?? $u['role'])) ?></span> <span class="pill" title="<?= e(t('Taal')) ?>"><?= e(strtoupper((string)$u['locale'])) ?></span></td>
          <td class="small"><?= $u['last_login_at'] ? fdate($u['last_login_at']) : '<span class="muted">' . e(t('nooit')) . '</span>' ?></td>
          <td class="num"><?= (int)$u['dls'] ?></td>
          <td class="small"><?php if (!(int)$u['is_active']): ?><span class="pill pill-warn"><?= e(t('Gedeactiveerd')) ?></span>
            <?php elseif ($expired): ?><span class="pill pill-warn"><?= e(t('Verlopen')) ?></span>
            <?php elseif ($u['expires_at']): ?><?= e(t('Tot %s', fdate($u['expires_at']))) ?>
            <?php else: ?><span class="pill pill-ok"><?= e(t('Actief')) ?></span><?php endif; ?></td>
          <td class="actions stack">
            <a href="<?= url('admin/users.php?edit=' . (int)$u['id']) ?>"><?= e(t('Bewerken')) ?></a>
            <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
              <button class="linkbtn" name="action" value="toggle"><?= e((int)$u['is_active'] ? t('Deactiveren') : t('Activeren')) ?></button>
              <button class="linkbtn" name="action" value="reset" onclick="return confirm('<?= e(t('Nieuw wachtwoord genereren?')) ?>')"><?= e(t('Reset ww')) ?></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <form class="card pad form" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="uid" value="<?= (int)$v['id'] ?>">
    <h2><?= e($edit ? t('Gebruiker bewerken') : t('Nieuwe gebruiker')) ?></h2>
    <label><?= e(t('Naam')) ?><input name="name" value="<?= e($v['name']) ?>" required></label>
    <label><?= e(t('E-mailadres')) ?><input type="email" name="email" value="<?= e($v['email']) ?>" required></label>
    <div class="row2">
      <label><?= e(t('Rol')) ?><select name="role"><?php foreach (ROLES as $k => $l): ?><option value="<?= $k ?>" <?= $v['role'] === $k ? 'selected' : '' ?>><?= e(t($l)) ?></option><?php endforeach; ?></select></label>
      <label><?= e(t('Taal van het portaal')) ?><select name="locale"><?php foreach (languages() as $k => $l): ?><option value="<?= e($k) ?>" <?= ($v['locale'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    </div>
    <label><?= e(t('Organisatie')) ?><input name="organisation" value="<?= e($v['organisation']) ?>" placeholder="<?= e(t('bv. NORVIQ GmbH of Partner Ltd.')) ?>"></label>
    <label><?= e(t('Land')) ?><input name="country" value="<?= e($v['country']) ?>"></label>
    <label><?= e(t('Toegang tot en met')) ?> <small class="muted"><?= e(t('(optioneel – handig voor tijdelijke partners)')) ?></small><input type="date" name="expires_at" value="<?= $v['expires_at'] ? substr($v['expires_at'], 0, 10) : '' ?>"></label>
    <?php if (!$edit): ?><label><?= e(t('Wachtwoord')) ?> <small class="muted"><?= e(t('(leeg = genereren)')) ?></small><input name="password" autocomplete="new-password"></label><?php endif; ?>
    <button class="btn btn-primary"><?= e($edit ? t('Opslaan') : t('Account aanmaken')) ?></button>
    <?php if ($edit): ?> <a class="btn" href="<?= url('admin/users.php') ?>"><?= e(t('Annuleren')) ?></a><?php endif; ?>
    <div class="note small">
      <strong><?= e(t('Rollen')) ?></strong><br>
      <b><?= e(t('Dochterkantoor')) ?></b> <?= e(t('ziet alle gepubliceerde assets, ook interne.')) ?><br>
      <b><?= e(t('Salespartner')) ?></b> <?= e(t('ziet alleen assets die voor iedereen zijn vrijgegeven.')) ?><br>
      <b><?= e(t('Beheerder')) ?></b> <?= e(t('beheert assets, gebruikers en ziet statistieken.')) ?><br><br>
      <strong><?= e(t('Taal')) ?></strong><br>
      <?= e(t('Gebruikers kunnen hun taal ook zelf wisselen via NL / EN rechtsboven; die keuze wordt bewaard.')) ?>
    </div>
  </form>
</div>
<?php layout_footer();

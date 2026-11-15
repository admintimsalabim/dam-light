<?php
require __DIR__ . '/../app/bootstrap.php';

if (current_user()) redirect('index.php');

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');

    // eenvoudige rem op brute-force (per sessie)
    $_SESSION['fails'] = $_SESSION['fails'] ?? 0;
    if ($_SESSION['fails'] >= 5) sleep(min(10, $_SESSION['fails']));

    $u = q('SELECT * FROM users WHERE LOWER(email) = LOWER(?)', [$email])->fetch();
    if ($u && password_verify($pass, $u['password_hash'])) {
        if (!user_may_login($u)) {
            $error = t('Dit account is niet (meer) actief. Neem contact op met het hoofdkantoor.');
        } else {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            $_SESSION['fails'] = 0;
            unset($_SESSION['locale']);   // vanaf nu geldt de taal van het account
            q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $u['id']]);
            $to = $_SESSION['after_login'] ?? '';
            unset($_SESSION['after_login']);
            if ($to && str_starts_with($to, '/') && !str_starts_with($to, '//')) { header('Location: ' . $to); exit; }
            redirect('index.php');
        }
    } else {
        $_SESSION['fails']++;
        $error = t('E-mailadres of wachtwoord klopt niet.');
    }
}
?><!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t('Inloggen')) ?> · <?= e(company()) ?> <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= url('assets/style.css?v=3') ?>">
<?= brand_css() ?>
</head>
<body class="login-page">
<div class="login-split">
  <section class="login-brand">
    <div class="brand big"><?= brand_block(48) ?></div>
    <h1><?= e(t('Alle merkmiddelen.')) ?><br><?= e(t('Eén plek.')) ?></h1>
    <p><?= e(t('Logo\'s, roll-ups, brochures, beursmateriaal en templates – altijd de nieuwste versie, direct in het juiste formaat voor drukwerk of digitaal gebruik.')) ?></p>
    <ul class="login-points">
      <li><?= icon('search') ?> <?= e(t('Visueel zoeken in plaats van mappen doorklikken')) ?></li>
      <li><?= icon('download') ?> <?= e(t('Kies CMYK, PMS of RGB – het portaal legt uit welke je nodig hebt')) ?></li>
      <li><?= icon('lock') ?> <?= e(t('Aparte toegang voor partners, los van Microsoft 365')) ?></li>
    </ul>
  </section>
  <section class="login-form">
    <div class="login-lang"><?= lang_switch() ?></div>
    <form method="post" class="card pad" autocomplete="on">
      <h2><?= e(t('Inloggen')) ?></h2>
      <p class="muted"><?= e(t('Voor medewerkers van dochterkantoren en salespartners.')) ?></p>
      <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
      <?= csrf_field() ?>
      <label><?= e(t('E-mailadres')) ?><input type="email" name="email" value="<?= e($email) ?>" required autofocus></label>
      <label><?= e(t('Wachtwoord')) ?><input type="password" name="password" required></label>
      <button class="btn btn-primary btn-block" type="submit"><?= e(t('Inloggen')) ?></button>
      <p class="muted small"><?= e(t('Nog geen toegang? Vraag een account aan bij het hoofdkantoor.')) ?></p>
    </form>
    <?php if (config('demo_mode')): ?>
    <div class="demo-logins">
      <strong><?= e(t('Demo-accounts')) ?></strong>
      <table>
        <tr><td><?= e(t('Beheerder (HQ)')) ?></td><td><code>admin@demo.local</code></td><td><code>Admin123!</code></td></tr>
        <tr><td><?= e(t('Dochterkantoor')) ?></td><td><code>kantoor.de@demo.local</code></td><td><code>Demo123!</code></td></tr>
        <tr><td><?= e(t('Salespartner')) ?></td><td><code>partner@demo.local</code></td><td><code>Demo123!</code></td></tr>
      </table>
    </div>
    <?php endif; ?>
  </section>
</div>
</body>
</html>

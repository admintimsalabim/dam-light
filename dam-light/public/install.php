<?php
/**
 * Web-installer voor hosting zonder commandline-toegang.
 * Werkt alleen zolang er nog geen database is. Verwijder dit bestand na installatie.
 */
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/installer.php';

$done = is_installed();
$log = [];
$error = '';
if (!$done && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $log = run_install(false, isset($_POST['demo']));
        $done = true;
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}
?><!doctype html>
<html lang="nl"><head><meta charset="utf-8"><title>Installatie · DAM Light</title>
<link rel="stylesheet" href="<?= url('assets/style.css') ?>"></head>
<body class="login-page"><div class="login-form" style="margin:60px auto;max-width:560px">
<div class="card pad">
  <h2>DAM Light – installatie</h2>
  <?php if ($error): ?>
    <div class="flash flash-err"><?= e($error) ?></div>
    <p class="small">Los dit op en wis daarna de tabellen in phpMyAdmin (of draai <code>php bin/install.php --fresh</code>) voordat je opnieuw installeert.</p>
  <?php endif; ?>
  <?php if ($log): ?>
    <ul><?php foreach ($log as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul>
    <div class="flash flash-warn">Verwijder <code>public/install.php</code> nu van de server.</div>
    <a class="btn btn-primary" href="<?= url('login.php') ?>">Naar inloggen</a>
  <?php elseif ($done): ?>
    <p>De database is al geïnstalleerd. Verwijder dit bestand van de server.</p>
    <a class="btn btn-primary" href="<?= url('login.php') ?>">Naar inloggen</a>
  <?php else: ?>
    <p>Database: <strong><?= e(config('db.driver')) ?></strong>. Controleer eerst <code>config.php</code>.</p>
    <?php $checks = install_checks(); $allOk = !in_array(false, array_column($checks, 1), true); ?>
    <table class="tbl small" style="margin-bottom:16px">
      <?php foreach ($checks as [$label, $ok, $info]): ?>
      <tr><td><?= $ok ? '✅' : '❌' ?> <?= e($label) ?></td><td class="muted"><?= e($info) ?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php if (!$allOk): ?><div class="flash flash-warn">Los eerst de punten met ❌ op en ververs deze pagina.</div><?php endif; ?>
    <form method="post"><?= csrf_field() ?>
      <label class="check"><input type="checkbox" name="demo" checked> Demo-inhoud laden (merk NORVIQ, gebruikers, downloadhistorie)</label>
      <button class="btn btn-primary">Installeren</button>
    </form>
  <?php endif; ?>
</div></div></body></html>

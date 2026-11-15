<?php
/** Wisselt de interfacetaal. Ingelogd: wordt opgeslagen als voorkeur van het account. */
require __DIR__ . '/../app/bootstrap.php';
csrf_check();

$l = (string)($_POST['l'] ?? '');
if (valid_locale($l)) {
    $u = current_user();
    if ($u) q('UPDATE users SET locale = ? WHERE id = ?', [$l, $u['id']]);
    else $_SESSION['locale'] = $l;
}
$back = (string)($_POST['back'] ?? '');
if ($back === '' || !str_starts_with($back, '/') || str_starts_with($back, '//')) $back = url('index.php');
header('Location: ' . $back);
exit;

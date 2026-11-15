<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();

$a = q('SELECT * FROM assets WHERE id = ?', [(int)($_GET['id'] ?? 0)])->fetch();
$file = ($a && can_view_asset($a) && $a['preview_file']) ? storage('previews') . '/' . basename($a['preview_file']) : null;

if (!$file || !is_file($file)) {
    header('Content-Type: image/svg+xml');
    header('Cache-Control: private, max-age=600');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"><rect width="400" height="300" fill="#EEF2F7"/>'
       . '<text x="200" y="158" text-anchor="middle" font-family="sans-serif" font-size="16" fill="#8795A8">Geen voorbeeld</text></svg>';
    exit;
}
$ext = ext_of($file);
stream_file($file, 'preview.' . $ext, $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg'), true);

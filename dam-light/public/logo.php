<?php
/** Levert het eigen logo uit Beheer → Huisstijl (ook op de inlogpagina, dus zonder login). */
require __DIR__ . '/../app/bootstrap.php';

$file = setting('logo_file');
$path = $file !== '' ? storage('branding') . '/' . basename($file) : '';
if ($path === '' || !is_file($path)) { http_response_code(404); exit; }

$types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'];
$ext = ext_of($path);
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
// SVG kan scripts bevatten: in een sandbox serveren zodat er niets kan worden uitgevoerd
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($path));
readfile($path);

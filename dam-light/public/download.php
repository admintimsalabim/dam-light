<?php
require __DIR__ . '/../app/bootstrap.php';
$user = require_login();

/* ---------- Alle bestanden van één asset als ZIP ---------- */
if (isset($_GET['zip'])) {
    $assetId = (int)$_GET['zip'];
    $a = q('SELECT * FROM assets WHERE id = ?', [$assetId])->fetch();
    if (!$a || !can_view_asset($a)) { http_response_code(404); exit(t('Niet gevonden.')); }
    if (!can_download_asset($a)) { http_response_code(403); exit(t('Dit bestand mag je niet downloaden vanwege de licentievoorwaarden.')); }
    $rends = q('SELECT * FROM renditions WHERE asset_id = ? ORDER BY sort_order, id', [$assetId])->fetchAll();

    $tmp = tempnam(sys_get_temp_dir(), 'dam');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    foreach ($rends as $r) {
        $p = storage('files') . '/' . $r['file_name'];
        if (is_file($p)) $zip->addFile($p, t(USAGES[$r['usage_type']]['label'] ?? 'Overig') . '/' . download_name($a, $r));
    }
    $zip->close();
    q('INSERT INTO downloads (user_id, asset_id, rendition_id, is_zip, ip, created_at) VALUES (?,?,?,?,?,?)',
      [$user['id'], $assetId, null, 1, client_ip(), now()]);
    register_shutdown_function(fn() => @unlink($tmp));
    $name = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(company() . ' ' . loc($a, 'title'))), '-') . '.zip';
    stream_file($tmp, $name, 'application/zip');
}

/* ---------- Eén bestand ---------- */
$id = (int)($_GET['id'] ?? 0);
$r = q('SELECT r.*, a.title, a.title_en, a.visibility, a.is_published, a.license_type, a.license_expires FROM renditions r JOIN assets a ON a.id = r.asset_id WHERE r.id = ?', [$id])->fetch();
if (!$r || !can_view_asset($r)) { http_response_code(404); exit(t('Niet gevonden.')); }
if (!can_download_asset($r)) { http_response_code(403); exit(t('Dit bestand mag je niet downloaden vanwege de licentievoorwaarden.')); }

q('INSERT INTO downloads (user_id, asset_id, rendition_id, is_zip, ip, created_at) VALUES (?,?,?,?,?,?)',
  [$user['id'], $r['asset_id'], $r['id'], 0, client_ip(), now()]);

stream_file(storage('files') . '/' . basename($r['file_name']), download_name($r, $r), $r['mime_type']);

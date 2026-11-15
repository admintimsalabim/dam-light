<?php
declare(strict_types=1);

const PREVIEW_EXT = ['png', 'jpg', 'jpeg', 'webp'];

function ext_of(string $name): string { return strtolower(pathinfo($name, PATHINFO_EXTENSION)); }

function format_of(string $name): string
{
    $e = ext_of($name);
    return match ($e) { 'jpeg' => 'JPG', 'tiff' => 'TIF', default => strtoupper($e) };
}

function mime_of(string $path): string
{
    $m = function_exists('mime_content_type') ? (mime_content_type($path) ?: '') : '';
    return $m !== '' ? $m : 'application/octet-stream';
}

/** Raadt toepassing + kleurruimte op basis van bestandsnaam (handig bij uploaden). */
function guess_usage(string $name): array
{
    $n = strtolower($name);
    $e = ext_of($n);
    return match (true) {
        str_contains($n, 'pms') || str_contains($n, 'pantone') => ['spot', 'PMS'],
        str_contains($n, 'cmyk') || in_array($e, ['eps', 'ai'], true) => ['print', 'CMYK'],
        in_array($e, ['pptx', 'potx', 'docx', 'dotx', 'xlsx'], true) => ['office', 'RGB'],
        in_array($e, ['indd', 'idml', 'zip'], true) => ['source', ''],
        $e === 'pdf' => ['print', 'CMYK'],
        default => ['digital', 'RGB'],
    };
}

/**
 * Slaat een upload op in storage/<sub>/ met een willekeurige naam.
 * @return array{file_name:string, original_name:string, size:int, mime:string}
 */
function store_upload(array $file, string $sub, ?array $allowed = null): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(t('Upload mislukt (code %s).', (string)($file['error'] ?? '?')));
    }
    $ext = ext_of($file['name']);
    $allowed ??= config('allowed_extensions');
    if (!in_array($ext, $allowed, true)) throw new RuntimeException(t('Bestandstype .%s is niet toegestaan.', $ext));
    if ($file['size'] > config('max_upload_mb') * 1024 * 1024) throw new RuntimeException(t('Bestand is te groot.'));

    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    $dest = storage($sub) . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest) && !rename($file['tmp_name'], $dest)) {
        throw new RuntimeException(t('Kon bestand niet opslaan.'));
    }
    return ['file_name' => $name, 'original_name' => basename($file['name']), 'size' => (int)filesize($dest), 'mime' => mime_of($dest)];
}

function delete_stored(string $sub, ?string $name): void
{
    if (!$name) return;
    $p = storage($sub) . '/' . basename($name);
    if (is_file($p)) unlink($p);
}

/** Bestandsnaam voor de gebruiker: merk-titel-label.ext */
function download_name(array $asset, array $rend): string
{
    $slug = fn(string $s) => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s)), '-');
    return $slug(company() . ' ' . loc($asset, 'title')) . '_' . $slug(loc($rend, 'label')) . '.' . ext_of($rend['file_name']);
}

function stream_file(string $path, string $downloadName, string $mime, bool $inline = false): never
{
    if (!is_file($path)) { http_response_code(404); exit(t('Bestand niet gevonden.')); }
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . addcslashes($downloadName, '"\\') . '"');
    header('Cache-Control: private, max-age=' . ($inline ? 3600 : 0));
    readfile($path);
    exit;
}

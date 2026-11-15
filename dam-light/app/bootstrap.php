<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$cfgFile = file_exists(APP_ROOT . '/config.local.php') ? APP_ROOT . '/config.local.php' : APP_ROOT . '/config.php';
$GLOBALS['config'] = require $cfgFile;
date_default_timezone_set($GLOBALS['config']['timezone'] ?? 'Europe/Amsterdam');

if (PHP_SAPI !== 'cli') {
    session_name('damlight');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

require __DIR__ . '/schema.php';
require __DIR__ . '/settings.php';
require __DIR__ . '/i18n.php';
require __DIR__ . '/files.php';
require __DIR__ . '/layout.php';

/* ------------------------------------------------------------------ constanten */

const ROLES = [
    'admin'   => 'Beheerder (HQ)',
    'office'  => 'Dochterkantoor',
    'partner' => 'Salespartner',
];

const USAGES = [
    'print'   => ['label' => 'Drukwerk',          'space' => 'CMYK', 'hint' => 'Offset- en digitaal drukwerk: roll-ups, brochures, flyers, beurswanden.'],
    'spot'    => ['label' => 'Steunkleur',        'space' => 'PMS',  'hint' => 'Zeefdruk, borduren, belettering en relatiegeschenken.'],
    'digital' => ['label' => 'Digitaal & web',    'space' => 'RGB',  'hint' => 'Website, social media, e-mail, PowerPoint en Word.'],
    'office'  => ['label' => 'Office-template',   'space' => 'RGB',  'hint' => 'Direct te bewerken in PowerPoint, Word of Excel.'],
    'source'  => ['label' => 'Bronbestand',       'space' => '',     'hint' => 'Open bestand voor je reclamebureau of vormgever.'],
];

const VISIBILITY = [
    'all'      => 'Iedereen (kantoren + partners)',
    'internal' => 'Alleen eigen kantoren',
];

const LICENSES = [
    'own'   => 'Eigen beeld – vrij te gebruiken',
    'stock' => 'Stocklicentie – alleen eigen organisatie',
];

/* ------------------------------------------------------------------ helpers */

function config(string $key, mixed $default = null): mixed
{
    $val = $GLOBALS['config'];
    foreach (explode('.', $key) as $k) {
        if (!is_array($val) || !array_key_exists($k, $val)) return $default;
        $val = $val[$k];
    }
    return $val;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $driver = config('db.driver');
    if ($driver === 'mysql') {
        $m = config('db.mysql');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $m['host'], $m['port'], $m['dbname'], $m['charset']);
        $pdo = new PDO($dsn, $m['user'], $m['pass']);
    } else {
        $path = config('db.sqlite_path');
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
        $pdo = new PDO('sqlite:' . $path);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    migrate($pdo);
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function now(): string { return date('Y-m-d H:i:s'); }

function e(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function url(string $path = ''): string { return rtrim((string)config('base_url', ''), '/') . '/' . ltrim($path, '/'); }

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function storage(string $sub = ''): string
{
    $dir = rtrim(config('storage_path'), '/') . ($sub !== '' ? '/' . $sub : '');
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    return $dir;
}

function human_size(int|float|null $bytes): string
{
    $bytes = (float)$bytes;
    foreach (['B', 'KB', 'MB', 'GB'] as $u) {
        if ($bytes < 1024 || $u === 'GB') return ($u === 'B' ? (int)$bytes : number_format($bytes, 1, ',', '.')) . ' ' . $u;
        $bytes /= 1024;
    }
    return '';
}

/* ------------------------------------------------------------------ flash & csrf */

function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'][] = [$type, $msg]; }

function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }

function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit(t('Ongeldige sessie – ververs de pagina en probeer het opnieuw.'));
    }
}

/* ------------------------------------------------------------------ auth */

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) return $user;
    $user = null;
    if (!empty($_SESSION['uid'])) {
        $u = q('SELECT * FROM users WHERE id = ?', [$_SESSION['uid']])->fetch();
        if ($u && user_may_login($u)) $user = $u;
        else unset($_SESSION['uid']);
    }
    return $user;
}

function user_may_login(array $u): bool
{
    if (!(int)$u['is_active']) return false;
    if (!empty($u['expires_at']) && strtotime($u['expires_at']) < time()) return false;
    return true;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') { http_response_code(403); exit(t('Geen toegang.')); }
    return $u;
}

function is_admin(): bool { return (current_user()['role'] ?? '') === 'admin'; }

/** SQL-fragment dat assets filtert op wat de huidige gebruiker mag zien. */
function visibility_sql(string $alias = 'a'): string
{
    $role = current_user()['role'] ?? 'partner';
    return match ($role) {
        'admin'  => '1=1',
        'office' => "$alias.is_published = 1",
        default  => "$alias.is_published = 1 AND $alias.visibility = 'all'",
    };
}

function can_view_asset(array $asset): bool
{
    $role = current_user()['role'] ?? '';
    if ($role === 'admin') return true;
    if (!(int)$asset['is_published']) return false;
    return $role === 'office' || $asset['visibility'] === 'all';
}

function is_stock(array $asset): bool { return ($asset['license_type'] ?? 'own') === 'stock'; }

function license_expired(array $asset): bool
{
    return !empty($asset['license_expires']) && strtotime($asset['license_expires'] . ' 23:59:59') < time();
}

/**
 * Mag de huidige gebruiker de bestanden van dit asset downloaden?
 * - beheerder: altijd
 * - verlopen licentie: niemand anders
 * - stockfoto: wel eigen kantoren, niet distributeurs/salespartners
 */
function can_download_asset(array $asset): bool
{
    $role = current_user()['role'] ?? '';
    if ($role === 'admin') return true;
    if (license_expired($asset)) return false;
    if (is_stock($asset) && $role === 'partner') return false;
    return true;
}

function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45); }

<?php
declare(strict_types=1);

/*
 * Meertaligheid.
 * Teksten in de code staan in het Nederlands en gaan door t().
 * lang/en.php bevat de Engelse vertaling (Nederlandse tekst => Engelse tekst).
 * Een nieuwe taal toevoegen: kopieer lang/en.php naar bv. lang/de.php, vertaal,
 * en voeg de taal toe aan 'languages' in config.php.
 */

function languages(): array
{
    return config('languages', ['nl' => 'Nederlands', 'en' => 'English']);
}

function valid_locale(?string $l): bool { return $l !== null && isset(languages()[$l]); }

/** Bepaalt de taal: gebruiker → sessie → browser → standaard. */
function locale(): string
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $u = function_exists('current_user') ? current_user() : null;
    if ($u && valid_locale($u['locale'] ?? null)) return $cache = $u['locale'];
    if (valid_locale($_SESSION['locale'] ?? null)) return $_SESSION['locale'];
    $accept = strtolower(substr((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 2));
    if (valid_locale($accept)) return $accept;
    return (string)config('default_language', 'nl');
}

function t(string $text, mixed ...$args): string
{
    static $dict = [];
    $l = locale();
    if ($l !== 'nl') {
        if (!isset($dict[$l])) {
            $f = APP_ROOT . "/lang/$l.php";
            $dict[$l] = is_file($f) ? require $f : [];
        }
        $text = $dict[$l][$text] ?? $text;
    }
    return $args ? vsprintf($text, $args) : $text;
}

/** Enkelvoud/meervoud: tn('%d bestand', '%d bestanden', $n) */
function tn(string $one, string $many, int $n): string { return t($n === 1 ? $one : $many, $n); }

function fdate(?string $dt, bool $time = false): string
{
    if (!$dt) return '–';
    $months = [
        'nl' => ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'],
        'en' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    ];
    $m = $months[locale()] ?? $months['en'];
    $ts = strtotime($dt);
    return date('j', $ts) . ' ' . $m[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts) . ($time ? ', ' . date('H:i', $ts) : '');
}

/** Taalschakelaar (formulier) voor header en inlogpagina. */
function lang_switch(string $class = ''): string
{
    $out = '<form method="post" action="' . url('lang.php') . '" class="langswitch ' . e($class) . '">' . csrf_field()
         . '<input type="hidden" name="back" value="' . e($_SERVER['REQUEST_URI'] ?? '') . '">';
    foreach (languages() as $code => $name) {
        $out .= '<button name="l" value="' . e($code) . '" title="' . e($name) . '" class="' . ($code === locale() ? 'on' : '') . '">' . strtoupper(e($code)) . '</button>';
    }
    return $out . '</form>';
}

/**
 * Inhoud (titels, omschrijvingen, categorieën) kan een Engelse variant hebben in kolom <veld>_en.
 * Is die leeg, dan wordt de Nederlandse tekst getoond.
 */
function loc(array $row, string $field): string
{
    if (locale() !== 'nl' && !empty($row[$field . '_en'])) return (string)$row[$field . '_en'];
    return (string)($row[$field] ?? '');
}

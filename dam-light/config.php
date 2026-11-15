<?php
/**
 * DAM Light – configuratie
 * Kopieer eventueel naar config.local.php; die wordt dan voorrang gegeven.
 */
return [
    // Naam, kleuren en logo stel je in via Beheer → Huisstijl. Dit zijn alleen de beginwaarden.
    'app_name'  => 'Brand Portal',
    'company'   => 'NORVIQ',            // fictief demo-merk
    'base_url'  => '',                  // bv. '/dam' als de app in een submap draait
    'timezone'  => 'Europe/Amsterdam',
    'demo_mode' => true,

    // Talen van de interface. Standaardtaal voor nieuwe gebruikers en de inlogpagina:
    'languages'        => ['nl' => 'Nederlands', 'en' => 'English'],
    'default_language' => 'nl',                // toont de demo-accounts op het inlogscherm

    'db' => [
        // 'sqlite' = nul installatie (ideaal voor een demo), 'mysql' = MySQL / MariaDB
        'driver'      => 'sqlite',
        'sqlite_path' => __DIR__ . '/storage/dam.sqlite',
        'mysql' => [
            'host'    => '127.0.0.1',
            'port'    => 3306,
            'dbname'  => 'dam_light',
            'user'    => 'dam_user',
            'pass'    => 'wijzig-mij',
            'charset' => 'utf8mb4',
        ],
    ],

    // Bestanden staan BUITEN de webroot (public/) en worden alleen via download.php uitgeleverd
    'storage_path' => __DIR__ . '/storage',

    'max_upload_mb' => 250,
    'allowed_extensions' => ['pdf', 'png', 'jpg', 'jpeg', 'svg', 'eps', 'ai', 'zip', 'pptx', 'potx', 'docx', 'dotx', 'xlsx', 'mp4', 'mov', 'indd', 'idml', 'tif', 'tiff', 'webp'],
];

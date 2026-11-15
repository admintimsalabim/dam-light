<?php
declare(strict_types=1);

/** Maakt alle tabellen aan; werkt voor zowel SQLite als MySQL/MariaDB. */
function create_schema(PDO $pdo, string $driver): void
{
    $my  = $driver === 'mysql';
    $pk  = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $fk  = $my ? 'INT UNSIGNED' : 'INTEGER';
    $end = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    $sql = [
        "CREATE TABLE IF NOT EXISTS users (
            id $pk,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'partner',
            organisation VARCHAR(150) NOT NULL DEFAULT '',
            country VARCHAR(80) NOT NULL DEFAULT '',
            locale VARCHAR(5) NOT NULL DEFAULT 'nl',
            is_active TINYINT NOT NULL DEFAULT 1,
            expires_at DATETIME NULL,
            last_login_at DATETIME NULL,
            created_at DATETIME NOT NULL
        )$end",

        "CREATE TABLE IF NOT EXISTS categories (
            id $pk,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(120) NOT NULL UNIQUE,
            description VARCHAR(255) NOT NULL DEFAULT '',
            name_en VARCHAR(120) NOT NULL DEFAULT '',
            description_en VARCHAR(255) NOT NULL DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0
        )$end",

        "CREATE TABLE IF NOT EXISTS assets (
            id $pk,
            category_id $fk NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            title_en VARCHAR(200) NOT NULL DEFAULT '',
            description_en TEXT,
            tags VARCHAR(500) NOT NULL DEFAULT '',
            language VARCHAR(10) NOT NULL DEFAULT '',
            region VARCHAR(80) NOT NULL DEFAULT '',
            dimensions VARCHAR(120) NOT NULL DEFAULT '',
            visibility VARCHAR(20) NOT NULL DEFAULT 'all',
            license_type VARCHAR(20) NOT NULL DEFAULT 'own',
            license_provider VARCHAR(120) NOT NULL DEFAULT '',
            license_ref VARCHAR(120) NOT NULL DEFAULT '',
            license_url VARCHAR(500) NOT NULL DEFAULT '',
            license_expires DATE NULL,
            license_note TEXT,
            license_note_en TEXT,
            is_published TINYINT NOT NULL DEFAULT 1,
            preview_file VARCHAR(255) NULL,
            created_by $fk NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (category_id) REFERENCES categories(id)
        )$end",

        "CREATE TABLE IF NOT EXISTS renditions (
            id $pk,
            asset_id $fk NOT NULL,
            label VARCHAR(160) NOT NULL,
            label_en VARCHAR(160) NOT NULL DEFAULT '',
            usage_type VARCHAR(20) NOT NULL DEFAULT 'print',
            color_space VARCHAR(20) NOT NULL DEFAULT '',
            format VARCHAR(20) NOT NULL DEFAULT '',
            file_name VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_size BIGINT NOT NULL DEFAULT 0,
            mime_type VARCHAR(120) NOT NULL DEFAULT 'application/octet-stream',
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
        )$end",

        "CREATE TABLE IF NOT EXISTS downloads (
            id $pk,
            user_id $fk NULL,
            asset_id $fk NULL,
            rendition_id $fk NULL,
            is_zip TINYINT NOT NULL DEFAULT 0,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL
        )$end",

        "CREATE TABLE IF NOT EXISTS settings (
            k VARCHAR(64) NOT NULL PRIMARY KEY,
            v TEXT NULL
        )$end",
    ];
    foreach ($sql as $s) $pdo->exec($s);
    $pdo->exec("DELETE FROM settings WHERE k = 'schema_version'");
    $pdo->exec("INSERT INTO settings (k, v) VALUES ('schema_version', '" . SCHEMA_VERSION . "')");

    foreach (['CREATE INDEX idx_assets_cat ON assets(category_id)',
              'CREATE INDEX idx_rend_asset ON renditions(asset_id)',
              'CREATE INDEX idx_dl_created ON downloads(created_at)',
              'CREATE INDEX idx_dl_asset ON downloads(asset_id)'] as $idx) {
        try { $pdo->exec($idx); } catch (PDOException) { /* bestaat al */ }
    }
}

<?php
/**
 * Installatie via de commandline:
 *   php bin/install.php            → tabellen + demo-inhoud
 *   php bin/install.php --fresh    → alles wissen en opnieuw opbouwen
 *   php bin/install.php --no-demo  → alleen tabellen + één beheerder
 */
if (PHP_SAPI !== 'cli') exit("Alleen via de commandline.\n");
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/installer.php';

foreach (run_install(in_array('--fresh', $argv, true), !in_array('--no-demo', $argv, true)) as $line) echo "• $line\n";
echo "Klaar.\n";

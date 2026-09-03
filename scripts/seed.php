<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Support\Database;

$config = require __DIR__ . '/../config/app.php';
$db = Database::connection($config['db']);

if (!$db instanceof PDO) {
    /*
     * Ieșire cu cod de eroare, nu cu zero.
     *
     * „exit(mesaj)" returnează 0, deci un lanț „git pull && seed && seed" mergea
     * mai departe și raporta succes chiar dacă baza de date era căzută și nu se
     * scrisese nimic. Codul 1 oprește lanțul acolo unde trebuie.
     */
    fwrite(STDERR, "Conexiunea la baza de date nu este disponibilă.\n");
    exit(1);
}

$seedPath = __DIR__ . '/../database/seed.sql';
$seedSql = file_get_contents($seedPath);

if ($seedSql === false) {
    exit("Nu am putut citi fișierul seed.sql.\n");
}

$db->exec($seedSql);
echo "Seed finalizat cu succes.\n";

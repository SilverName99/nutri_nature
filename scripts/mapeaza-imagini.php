<?php

declare(strict_types=1);

/*
 * Dă imaginilor din galerie numele pe care le folosesc paginile.
 *
 * Clientul a încărcat fișiere cu nume de lucru: „untitled-27", „cutie-123".
 * Paginile cer nume stabile, care spun ce e în imagine: „client-lidl",
 * „serviciu-ambalaje". Scriptul copiază fiecare sursă sub numele așteptat și
 * adaugă intrarea în galerie, ca imaginea să rămână editabilă din dashboard.
 *
 * Se rulează DUPĂ optimizeaza-imagini.php, fiindcă lucrează cu fișiere .webp.
 *
 *   php scripts/mapeaza-imagini.php              // raport
 *   php scripts/mapeaza-imagini.php --aplica     // execută
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Support\Database;

/** nume-sursă (fără extensie) => [nume-destinație, titlu afișat în galerie] */
const MAPARE = [
    // Produse
    'prajituri' => ['produse-alimentare', 'Ambalaje alimentare'],
    'cosmetic2' => ['produse-cosmetice', 'Ambalaje cosmetice'],
    'ambalaj-farma-3' => ['produse-farma', 'Ambalaje farmaceutice'],
    'geam9' => ['produse-textile', 'Ambalaje textile și încălțăminte'],
    'revista2' => ['produse-tiparituri', 'Cataloage, reviste, agende'],
    'etichete3' => ['produse-etichete', 'Etichete și mape'],

    // Servicii
    'cart1' => ['serviciu-tipar-offset', 'Tipar offset'],
    'mapa' => ['serviciu-tipar-digital', 'Tipar digital'],
    'box2' => ['serviciu-ambalaje', 'Ambalaje'],
    'geam5' => ['serviciu-servicii-de-stantare', 'Ștanțare'],
    'box3' => ['serviciu-inscriptionare-folio-emboss', 'Inscripționare folio și emboss'],
    'agenda3' => ['serviciu-creatie-si-design', 'Creație și design'],

    // Clienți
    'untitled-27' => ['client-lidl', 'Lidl'],
    'untitled-30' => ['client-petrom', 'Petrom'],
    'untitled-31' => ['client-regina-maria', 'Regina Maria'],
    'untitled-13' => ['client-selgros', 'Selgros'],
    'afi' => ['client-afi-europe', 'AFI Europe România'],
    'untitled-22' => ['client-aeroporturi-bucuresti', 'Aeroporturi București'],
    'untitled-23' => ['client-apa-nova', 'Apa Nova'],
    'untitled-24' => ['client-conpet', 'Conpet'],
    'untitled-29' => ['client-pandora', 'Pandora'],
    'untitled-20' => ['client-arabesque', 'Arabesque'],
    'untitled-19' => ['client-upetrom', 'Upetrom'],
    'untitled-16' => ['client-electrica', 'Electrica Furnizare'],
    'untitled-15' => ['client-bricostore', 'Bricostore'],
    'untitled-14' => ['client-biovitality', 'BioVitality'],
    '19' => ['client-gfr', 'Grup Feroviar Român'],
    '27' => ['client-gvs', 'GVS'],
    'untitled-25' => ['client-delice', 'Delice'],
    'untitled-26' => ['client-muzeul-bucovinei', 'Muzeul Național al Bucovinei'],
    'untitled-12' => ['client-corpul-politistilor', 'Corpul Național al Polițiștilor'],

    // Autorități — merg în subsol, nu la certificări
    'anpc' => ['autoritate-anpc', 'ANPC'],
    'info-cons' => ['autoritate-infocons', 'InfoCons'],
    'images' => ['autoritate-iscir', 'ISCIR'],
];

/*
 * „untitled-21" este același logo GFR ca „19", încărcat de două ori. Nu îl
 * mapăm: ar apărea de două ori în banda de clienți.
 */
const DUPLICATE = ['untitled-21'];

$aplica = in_array('--aplica', $argv, true);
$dir = __DIR__ . '/../public/uploads/gallery';

if (!is_dir($dir)) {
    exit("Directorul galeriei nu există: {$dir}\n");
}

$db = $aplica ? Database::connection((require __DIR__ . '/../config/app.php')['db']) : null;
$adauga = null;
$exista = null;
if ($db instanceof PDO) {
    $exista = $db->prepare('SELECT id FROM gallery_images WHERE image_url = ? LIMIT 1');
    $adauga = $db->prepare(
        'INSERT INTO gallery_images (title, media_type, image_url, folder_id, alt_text, sort_order, is_active)
         VALUES (?, "image", ?, NULL, ?, 0, 1)'
    );
}

$facute = 0;
$lipsa = [];

foreach (MAPARE as $sursa => [$destinatie, $titlu]) {
    $gasit = null;
    foreach (['webp', 'png', 'jpg', 'jpeg'] as $ext) {
        if (is_file("{$dir}/{$sursa}.{$ext}")) {
            $gasit = "{$sursa}.{$ext}";
            $extensie = $ext;
            break;
        }
    }

    if ($gasit === null) {
        $lipsa[] = $sursa;
        continue;
    }

    $numeNou = "{$destinatie}.{$extensie}";

    if (!$aplica) {
        printf("  %-24s → %-42s %s\n", $gasit, $numeNou, $titlu);
        $facute++;
        continue;
    }

    copy("{$dir}/{$gasit}", "{$dir}/{$numeNou}");

    if ($exista instanceof PDOStatement && $adauga instanceof PDOStatement) {
        $adresa = '/uploads/gallery/' . $numeNou;
        $exista->execute([$adresa]);
        if ($exista->fetchColumn() === false) {
            $adauga->execute([$titlu, $adresa, $titlu]);
        }
    }

    printf("  %-24s → %-42s %s\n", $gasit, $numeNou, $titlu);
    $facute++;
}

printf("\n%d imagini mapate.\n", $facute);

if ($lipsa !== []) {
    printf("\n%d surse negăsite în galerie:\n", count($lipsa));
    foreach ($lipsa as $s) {
        echo "  {$s}\n";
    }
}

echo "\nIgnorate ca duplicate: " . implode(', ', DUPLICATE) . "\n";

if (!$aplica) {
    echo "\nNimic nu a fost modificat. Adăugați --aplica pentru a executa.\n";
} else {
    echo "\nSursele au fost păstrate; imaginile noi sunt copii, nu mutări.\n";
}

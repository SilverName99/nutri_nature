<?php

declare(strict_types=1);

/*
 * Încarcă în tabela `pages` paginile scrise în database/pagini/.
 *
 * Fiecare pagină este un fișier .html, opțional însoțit de un .css și un .js cu
 * același nume. Conținutul ajunge în Dashboard → Pagini, de unde poate fi
 * editat mai departe.
 *
 * Implicit scriptul adaugă doar paginile care lipsesc, ca o rulare repetată să
 * nu șteargă modificările făcute din dashboard. Suprascrierea se cere explicit:
 *
 *   php scripts/seed-pagini.php                 // adaugă ce lipsește
 *   php scripts/seed-pagini.php --suprascrie    // rescrie și paginile existente
 *   php scripts/seed-pagini.php --doar=acasa    // limitează la un slug
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Support\Database;
use App\Support\ResponseCache;

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

/**
 * Numele fisierului => titlul afisat in dashboard si in meniu.
 *
 * Slug-ul se obtine din numele fisierului inlocuind „__" cu „/", fiindca un
 * slug poate contine cale (ruta publica /{slug*} accepta si bara oblica), dar
 * un nume de fisier nu.
 */
const PAGINI = [
    'acasa' => 'Acasă',
    'despre-noi' => 'Despre noi',
    'servicii' => 'Servicii',
    'servicii__nutritie-personalizata' => 'Nutriție personalizată',
    'servicii__psihonutritie' => 'Psihonutriție',
    'servicii__biorezonanta' => 'Biorezonanță',
    'servicii__chiropractica-si-masaj-terapeutic' => 'Chiropractică și masaj terapeutic',
    'servicii__medicina-traditionala' => 'Medicină tradițională indiană și românească',
    'servicii__terapii-complementare' => 'Terapii complementare',
    'servicii__echilibrare-energetica' => 'Echilibrare energetică',
    'servicii__consiliere-si-dezvoltare-personala' => 'Consiliere și dezvoltare personală',
    'programare' => 'Programare',
    'contact' => 'Contact',
];

$suprascrie = in_array('--suprascrie', $argv, true);
$doar = '';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--doar=')) {
        $doar = substr($arg, strlen('--doar='));
    }
}

/**
 * Rândurile din „pages" în care trebuie scrisă o pagină.
 *
 * Pentru pagini obișnuite este unul singur, găsit după slug. Pagina de start
 * face excepție: SiteController o caută cu
 *
 *     WHERE slug = '' OR LOWER(slug) IN ('acasa', 'home')
 *
 * și o preferă pe cea cu slug gol. O editare din dashboard care golește slug-ul
 * creează astfel o a doua pagină de start — iar scriptul, care căuta doar după
 * „acasa", scria într-un rând pe care site-ul nu îl mai afișa. Se vedea exact
 * ca o implementare care „nu a ajuns pe server": git pull mergea, seed-ul
 * raporta succes, pagina rămânea veche.
 *
 * De aceea, pentru pagina de start scriem în toate rândurile pe care le-ar
 * putea alege site-ul. Nu ștergem nimic: dublura rămâne, dar cu același
 * conținut, deci oricare ar fi aleasă, vizitatorul vede pagina din git.
 *
 * @return list<int>
 */
function idsDeScris(PDO $db, string $slug): array
{
    if ($slug === 'acasa') {
        $stmt = $db->query(
            "SELECT id FROM pages WHERE slug = '' OR LOWER(slug) IN ('acasa', 'home')"
        );

        return $stmt ? array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) : [];
    }

    $stmt = $db->prepare('SELECT id FROM pages WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $id = $stmt->fetchColumn();

    return $id === false ? [] : [(int) $id];
}

$dir = __DIR__ . '/../database/pagini';
$adaugate = 0;
$actualizate = 0;
$sarite = 0;

foreach (PAGINI as $fisier => $titlu) {
    $slug = str_replace('__', '/', $fisier);
    if ($doar !== '' && $doar !== $slug && $doar !== $fisier) {
        continue;
    }

    $caleHtml = $dir . '/' . $fisier . '.html';
    if (!is_file($caleHtml)) {
        continue;
    }

    $html = (string) file_get_contents($caleHtml);
    $css = is_file($dir . '/' . $fisier . '.css') ? (string) file_get_contents($dir . '/' . $fisier . '.css') : null;
    $js = is_file($dir . '/' . $fisier . '.js') ? (string) file_get_contents($dir . '/' . $fisier . '.js') : null;

    $ids = idsDeScris($db, $slug);

    if ($ids === []) {
        $db->prepare(
            'INSERT INTO pages (title, slug, html_content, css_content, js_content, is_published)
             VALUES (?, ?, ?, ?, ?, 1)'
        )->execute([$titlu, $slug, $html, $css, $js]);
        echo "adăugat:     /{$slug}\n";
        $adaugate++;
        continue;
    }

    if (!$suprascrie) {
        echo "sărit:       /{$slug} (există deja; folosiți --suprascrie ca să îl rescrieți)\n";
        $sarite++;
        continue;
    }

    $update = $db->prepare(
        'UPDATE pages
         SET title = ?, html_content = ?, css_content = ?, js_content = ?, deleted_at = NULL
         WHERE id = ?'
    );
    foreach ($ids as $id) {
        $update->execute([$titlu, $html, $css, $js, $id]);
    }
    $inPlus = count($ids) > 1 ? ' (' . count($ids) . ' rânduri)' : '';
    echo "actualizat:  /{$slug}{$inPlus}\n";
    $actualizate++;
}

/*
 * Golim cache-ul de pagini.
 *
 * Site-ul poate servi HTML salvat pe disc. Fără golire, o implementare corectă
 * ar rămâne nevăzută până la expirarea cache-ului — încă un mod în care „am dat
 * git pull și seed, dar tot nu se vede".
 */
$golite = ResponseCache::purgePageCache();
if ($golite > 0) {
    echo "\ncache: {$golite} pagini golite din cache.\n";
}

echo "\nGata: {$adaugate} adăugate, {$actualizate} actualizate, {$sarite} sărite.\n";
if ($sarite > 0 && !$suprascrie) {
    echo "Paginile existente au fost lăsate neatinse, ca să nu se piardă editările din dashboard.\n";
}

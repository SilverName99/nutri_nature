<?php

declare(strict_types=1);

/*
 * Redimensionează și convertește imaginile din galerie în WebP.
 *
 * Mockup-urile de produs vin din programe de tipar: PNG-uri de mai mulți
 * megaocteți, la rezoluții cu mult peste ce afișează un browser. O pagină cu
 * opt astfel de imagini trage zeci de megaocteți, ceea ce pe conexiune mobilă
 * înseamnă că pagina nu se încarcă.
 *
 * Ce face:
 *   - micșorează imaginea la LATIME_MAXIMA pe latura lungă (dacă e mai mare);
 *   - o salvează ca WebP, format acceptat azi de toate browserele;
 *   - mută originalul în uploads/gallery/originale/, nu îl șterge;
 *   - actualizează adresele din tabela gallery_images, ca galeria să nu se rupă.
 *
 * Rulează implicit în gol, doar raportează. Ca să schimbe ceva, cere --aplica:
 *
 *   php scripts/optimizeaza-imagini.php                    // raport
 *   php scripts/optimizeaza-imagini.php --aplica           // execută
 *   php scripts/optimizeaza-imagini.php --aplica --latime=1200 --calitate=78
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Support\Database;

const LATIME_MAXIMA_IMPLICITA = 1600;
const CALITATE_IMPLICITA = 82;

if (!extension_loaded('gd')) {
    exit("Extensia GD nu este activă. Optimizarea are nevoie de ea.\n");
}
$info = gd_info();
if (empty($info['WebP Support'])) {
    exit("GD este activ, dar fără suport WebP. Activați-l sau folosiți alt server.\n");
}

$aplica = in_array('--aplica', $argv, true);
$latimeMaxima = LATIME_MAXIMA_IMPLICITA;
$calitate = CALITATE_IMPLICITA;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--latime=')) {
        $latimeMaxima = max(400, (int) substr($arg, 9));
    }
    if (str_starts_with($arg, '--calitate=')) {
        $calitate = min(100, max(40, (int) substr($arg, 11)));
    }
}

$dir = __DIR__ . '/../public/uploads/gallery';
$dirOriginale = $dir . '/originale';

if (!is_dir($dir)) {
    exit("Directorul galeriei nu există: {$dir}\n");
}

$fisiere = array_values(array_filter(
    scandir($dir) ?: [],
    static function (string $f) use ($dir): bool {
        if (!is_file($dir . '/' . $f)) {
            return false;
        }
        return in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg'], true);
    }
));

if ($fisiere === []) {
    exit("Nu am găsit imagini PNG sau JPEG în {$dir}.\n");
}

printf("%d imagini găsite. Latura lungă maximă: %dpx, calitate WebP: %d.\n", count($fisiere), $latimeMaxima, $calitate);
echo $aplica ? "Mod: APLICĂ.\n\n" : "Mod: raport (adăugați --aplica pentru a executa).\n\n";

$db = $aplica ? Database::connection((require __DIR__ . '/../config/app.php')['db']) : null;
$actualizeazaAdresa = null;
if ($db instanceof PDO) {
    $actualizeazaAdresa = $db->prepare('UPDATE gallery_images SET image_url = :nou WHERE image_url = :vechi');
}

$inainte = 0;
$dupa = 0;
$convertite = 0;
$sarite = [];

foreach ($fisiere as $fisier) {
    $cale = $dir . '/' . $fisier;
    $marime = filesize($cale) ?: 0;
    $inainte += $marime;

    $dimensiuni = @getimagesize($cale);
    if ($dimensiuni === false) {
        $sarite[] = "{$fisier} (nu este o imagine validă)";
        $dupa += $marime;
        continue;
    }
    [$latime, $inaltime] = $dimensiuni;

    /*
     * O imagine decodată ocupă aproximativ 4 octeți pe pixel. Verificăm
     * dinainte, fiindcă un PNG de 16 MB poate depăși memoria alocată lui PHP,
     * iar procesul ar muri fără mesaj în loc să sară peste fișier.
     */
    $memorieNecesara = $latime * $inaltime * 4 * 2;
    $limita = ini_get('memory_limit');
    $limitaOcteti = $limita === '-1' ? PHP_INT_MAX : (int) $limita * 1024 * 1024;
    if ($limita !== '-1' && $memorieNecesara > $limitaOcteti * 0.8) {
        @ini_set('memory_limit', (string) (int) ceil($memorieNecesara / 1024 / 1024 * 1.4) . 'M');
        $limitaNoua = ini_get('memory_limit');
        if ($limitaNoua === $limita) {
            $sarite[] = sprintf('%s (%dx%d, ar cere ~%d MB, limita este %s)',
                $fisier, $latime, $inaltime, (int) ($memorieNecesara / 1024 / 1024), $limita);
            $dupa += $marime;
            continue;
        }
    }

    $numeNou = pathinfo($fisier, PATHINFO_FILENAME) . '.webp';
    $caleNoua = $dir . '/' . $numeNou;

    $scara = $latime > $latimeMaxima || $inaltime > $latimeMaxima
        ? $latimeMaxima / max($latime, $inaltime)
        : 1.0;
    $latimeNoua = (int) round($latime * $scara);
    $inaltimeNoua = (int) round($inaltime * $scara);

    if (!$aplica) {
        printf("  %-34s %5dx%-5d → %4dx%-4d  %7s\n",
            $fisier, $latime, $inaltime, $latimeNoua, $inaltimeNoua, formateaza($marime));
        // Estimare conservatoare pentru raport: WebP la aceste dimensiuni.
        $dupa += (int) ($latimeNoua * $inaltimeNoua * 0.25);
        $convertite++;
        continue;
    }

    $sursa = match (strtolower(pathinfo($fisier, PATHINFO_EXTENSION))) {
        'png' => @imagecreatefrompng($cale),
        'jpg', 'jpeg' => @imagecreatefromjpeg($cale),
        default => false,
    };
    if ($sursa === false) {
        $sarite[] = "{$fisier} (nu a putut fi deschisă)";
        $dupa += $marime;
        continue;
    }

    $destinatie = imagecreatetruecolor($latimeNoua, $inaltimeNoua);

    /*
     * Logo-urile de firme sunt PNG-uri cu fundal transparent; dacă le turnăm
     * alb dedesubt, ajung dreptunghiuri albe pe orice fundal colorat. WebP
     * păstrează canalul alfa, deci transparența se poate duce mai departe.
     * Mockup-urile de produs sunt opace și nu sunt afectate de ramura asta.
     */
    if (areTransparenta($cale, $dimensiuni)) {
        imagealphablending($destinatie, false);
        imagesavealpha($destinatie, true);
        $transparent = imagecolorallocatealpha($destinatie, 0, 0, 0, 127);
        imagefilledrectangle($destinatie, 0, 0, $latimeNoua, $inaltimeNoua, $transparent);
    } else {
        $alb = imagecolorallocate($destinatie, 255, 255, 255);
        imagefilledrectangle($destinatie, 0, 0, $latimeNoua, $inaltimeNoua, $alb);
    }

    imagecopyresampled($destinatie, $sursa, 0, 0, 0, 0, $latimeNoua, $inaltimeNoua, $latime, $inaltime);

    $scris = imagewebp($destinatie, $caleNoua, $calitate);
    imagedestroy($sursa);
    imagedestroy($destinatie);

    if (!$scris || !is_file($caleNoua)) {
        $sarite[] = "{$fisier} (scrierea WebP a eșuat)";
        $dupa += $marime;
        continue;
    }

    if (!is_dir($dirOriginale)) {
        mkdir($dirOriginale, 0775, true);
    }
    rename($cale, $dirOriginale . '/' . $fisier);

    if ($actualizeazaAdresa instanceof PDOStatement) {
        $actualizeazaAdresa->execute([
            'nou' => '/uploads/gallery/' . $numeNou,
            'vechi' => '/uploads/gallery/' . $fisier,
        ]);
    }

    $marimeNoua = filesize($caleNoua) ?: 0;
    $dupa += $marimeNoua;
    $convertite++;
    printf("  %-34s %7s → %7s  (%.0f%% mai mic)\n",
        $fisier, formateaza($marime), formateaza($marimeNoua),
        $marime > 0 ? (1 - $marimeNoua / $marime) * 100 : 0);
}

echo "\n";
printf("%d imagini procesate.\n", $convertite);
printf("Înainte: %s   După: %s   Reducere: %.1f×\n",
    formateaza($inainte), formateaza($dupa), $dupa > 0 ? $inainte / $dupa : 0);

if ($sarite !== []) {
    printf("\n%d sărite:\n", count($sarite));
    foreach ($sarite as $s) {
        echo "  {$s}\n";
    }
}

if ($aplica) {
    echo "\nOriginalele au fost mutate în uploads/gallery/originale/, nu șterse.\n";
    echo "Verificați site-ul, apoi le puteți șterge manual dacă totul arată bine.\n";
} else {
    echo "\nNimic nu a fost modificat. Adăugați --aplica pentru a executa.\n";
}

/**
 * Spune dacă imaginea are canal alfa, adică dacă transparența ei contează.
 *
 * getimagesize dă tipul; pentru PNG citim octetul de „color type" din antetul
 * IHDR: valorile 4 și 6 înseamnă tonuri de gri cu alfa, respectiv RGB cu alfa.
 */
function areTransparenta(string $cale, array $dimensiuni): bool
{
    if (($dimensiuni[2] ?? 0) !== IMAGETYPE_PNG) {
        return false;
    }
    $antet = @file_get_contents($cale, false, null, 0, 26);
    if ($antet === false || strlen($antet) < 26) {
        return false;
    }
    $tipCuloare = ord($antet[25]);
    return $tipCuloare === 4 || $tipCuloare === 6;
}

function formateaza(int $octeti): string
{
    if ($octeti >= 1048576) {
        return sprintf('%.1f MB', $octeti / 1048576);
    }
    if ($octeti >= 1024) {
        return sprintf('%.0f KB', $octeti / 1024);
    }
    return $octeti . ' B';
}

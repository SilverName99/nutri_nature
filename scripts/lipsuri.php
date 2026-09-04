<?php

declare(strict_types=1);

/*
 * Inventarul lucrurilor care lipsesc din site.
 *
 * Caută în paginile din database/pagini două feluri de goluri:
 *   - marcajele [DE COMPLETAT ...], [AN] și [N] din text;
 *   - fișierele de imagine sau film la care trimit paginile și care nu există.
 *
 * Din el se scrie documentul trimis clientului, ca acela să nu fie ținut minte
 * de nimeni: dacă apare un marcaj nou în pagini, apare și în document.
 *
 *   php scripts/lipsuri.php                       # raport citibil
 *   php scripts/lipsuri.php --json                # pentru alte scripturi
 *   php scripts/lipsuri.php --existente=lista.txt # când uploads/ nu e local
 */

$dir = __DIR__ . '/../database/pagini';
$radacinaPublica = __DIR__ . '/../public';

/*
 * De unde se știe că un fișier există.
 *
 * Implicit de pe disc, ceea ce este corect pe server. Rulat de pe altă mașină,
 * public/uploads este gol — nu intră în git — deci raportul ar cere clientului
 * fișiere pe care le-a trimis deja. Pentru cazul acela, --existente=FIȘIER
 * primește lista căilor care există, una pe rând.
 */
$existente = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--existente=')) {
        $cale = substr($arg, strlen('--existente='));
        if (!is_file($cale)) {
            fwrite(STDERR, "Lista de fișiere existente nu a fost găsită: {$cale}\n");
            exit(1);
        }
        $existente = array_flip(array_filter(array_map('trim', file($cale) ?: [])));
    }
}

$exista = static function (string $url) use ($existente, $radacinaPublica): bool {
    return $existente !== null
        ? isset($existente[$url])
        : is_file($radacinaPublica . $url);
};

/* Titlurile paginilor, ca în scriptul de seed. */
/*
 * Paginile le ia din scripts/seed-pagini.php, nu dintr-o listă scrisă aici.
 *
 * La proiectul anterior lista era ținută de mână în amândouă fișierele, și s-a
 * întâmplat ce era de așteptat: s-au adăugat pagini în seed și inventarul le-a
 * sărit, deci raporta că nu lipsește nimic pe pagini care erau pline de
 * marcaje. Un singur loc adevărat.
 */
$seed = (string) file_get_contents(__DIR__ . '/seed-pagini.php');
$titluriPagini = [];
if (preg_match('/const PAGINI = \[(.*?)\];/s', $seed, $bloc)) {
    preg_match_all("/'([^']+)'\s*=>\s*'([^']*)'/u", $bloc[1], $randuri, PREG_SET_ORDER);
    foreach ($randuri as $rand) {
        $titluriPagini[$rand[1]] = $rand[2];
    }
}
if ($titluriPagini === []) {
    fwrite(STDERR, "Nu am putut citi lista de pagini din seed-pagini.php.\n");
    exit(1);
}

/**
 * Unde se află un marcaj, spus în cuvintele paginii.
 *
 * Într-o grilă de carduri titlul stă după imagine, într-un bloc de text stă
 * înaintea ei. De aceea se caută întâi în față, în apropiere, și abia apoi
 * înapoi: altfel fotografia unui card ar fi fost trecută la titlul cardului
 * precedent, iar clientul ar fi căutat-o în locul greșit.
 */
function sectiunePentru(string $html, int $pozitie): string
{
    $curata = static function (string $brut): string {
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $brut)), ENT_QUOTES, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    };

    /*
     * Privirea înainte se oprește la capătul secțiunii. Fără oprire, un marcaj
     * aflat spre sfârșitul unei secțiuni împrumuta titlul secțiunii următoare:
     * fotografia editurii ajungea trecută la „Echipa".
     */
    $dupa = substr($html, $pozitie, 400);
    $capat = stripos($dupa, '</section>');
    if ($capat !== false) {
        $dupa = substr($dupa, 0, $capat);
    }

    if (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $dupa, $titlu)) {
        $text = $curata($titlu[1]);
        if ($text !== '' && !str_contains($text, '[DE COMPLETAT')) {
            return $text;
        }
    }

    $inainte = substr($html, 0, $pozitie);
    if (preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $inainte, $toate, PREG_SET_ORDER)) {
        for ($i = count($toate) - 1; $i >= 0; $i--) {
            $text = $curata($toate[$i][1]);
            if ($text !== '' && !str_contains($text, '[DE COMPLETAT')) {
                return $text;
            }
        }
    }

    if (preg_match_all('/<(?:section|header)\b[^>]*id="([^"]+)"/i', $inainte, $ids)) {
        return (string) end($ids[1]);
    }

    return 'începutul paginii';
}

$rezultat = [];

foreach ($titluriPagini as $fisier => $titlu) {
    $cale = $dir . '/' . $fisier . '.html';
    if (!is_file($cale)) {
        continue;
    }

    /*
     * Comentariile explică marcajele, deci conțin chiar cuvintele căutate. Se
     * șterg, dar se înlocuiesc cu spații de aceeași lungime, ca pozițiile din
     * fișier să rămână valabile pentru găsirea secțiunii.
     */
    $html = (string) preg_replace_callback(
        '/<!--.*?-->/s',
        static fn (array $m): string => str_repeat(' ', strlen($m[0])),
        (string) file_get_contents($cale)
    );

    $pagina = [
        'slug' => $fisier === 'acasa' ? '/' : '/' . str_replace('__', '/', $fisier),
        'titlu' => $titlu,
        'texte' => [],
        'fisiere' => [],
    ];

    if (preg_match_all('/\[(?:DE COMPLETAT[^\]]*|AN|N)\]/u', $html, $marcaje, PREG_OFFSET_CAPTURE)) {
        foreach ($marcaje[0] as [$marcaj, $pozitie]) {
            $pagina['texte'][] = [
                'sectiune' => sectiunePentru($html, (int) $pozitie),
                'marcaj' => trim($marcaj, '[]'),
            ];
        }
    }

    /*
     * Se prinde eticheta întreagă, nu doar adresa.
     *
     * Textul alternativ spune ce trebuie să arate fotografia, deci merge în
     * document. Căutat într-o fereastră în jurul adresei, se nimerea textul
     * fotografiei precedente: în lista de echipă, fiecare fotografie primea
     * numele omului de dinaintea ei. Luat din aceeași etichetă, nu are cum.
     */
    $tipar = '/<(?:img|source|video)\b[^>]*(?:src|poster)="(\/uploads\/[^"]+)"[^>]*>/i';
    if (preg_match_all($tipar, $html, $gasite, PREG_OFFSET_CAPTURE)) {
        $vazute = [];
        foreach ($gasite[1] as $index => [$url, $pozitie]) {
            if (isset($vazute[$url]) || $exista($url)) {
                continue;
            }
            $vazute[$url] = true;

            $descriere = '';
            if (preg_match('/alt="([^"]*)"/i', $gasite[0][$index][0], $alt)) {
                $descriere = trim($alt[1]);
            }

            $pagina['fisiere'][] = [
                'sectiune' => sectiunePentru($html, (int) $pozitie),
                'fisier' => basename($url),
                'tip' => str_ends_with($url, '.mp4') ? 'film' : 'imagine',
                'descriere' => $descriere,
            ];
        }
    }

    if ($pagina['texte'] !== [] || $pagina['fisiere'] !== []) {
        $rezultat[] = $pagina;
    }
}

/*
 * Un marcaj se cere o singură dată, oricâte pagini l-ar avea.
 *
 * „[DE COMPLETAT: telefon]" apare de cincisprezece ori — în antet, în subsol și
 * la fiecare îndemn de pe fiecare pagină. Clientul are un singur număr de dat.
 * Un document care i-l cere de cincisprezece ori nu se completează, se aruncă.
 *
 * Deci: se păstrează prima apariție, iar restul devin o notă lângă ea. La fel
 * pentru fișiere, care oricum sunt reutilizate între pagini.
 */
$numaraSiStrange = static function (array &$rezultat, string $cheieLista, callable $amprenta): void {
    $vazute = [];
    foreach ($rezultat as $iPagina => $pagina) {
        foreach ($pagina[$cheieLista] as $iRand => $rand) {
            $cheie = $amprenta($rand);
            if (isset($vazute[$cheie])) {
                [$pPrima, $rPrima] = $vazute[$cheie];
                $rezultat[$pPrima][$cheieLista][$rPrima]['alte_locuri'][] =
                    $pagina['titlu'] . ' → ' . $rand['sectiune'];
                unset($rezultat[$iPagina][$cheieLista][$iRand]);
                continue;
            }
            $vazute[$cheie] = [$iPagina, $iRand];
        }
    }

    /*
     * Renumerotarea se face abia la final, după ce s-au parcurs toate paginile.
     * Făcută în interiorul buclei, muta rândurile de sub picioarele hărții
     * „$vazute", care ține indici: o apariție ulterioară ajungea să scrie nota
     * pe alt rând, sau pe unul care nu mai exista.
     */
    foreach ($rezultat as $iPagina => $pagina) {
        $rezultat[$iPagina][$cheieLista] = array_values($pagina[$cheieLista]);
    }
};

$numaraSiStrange($rezultat, 'texte', static fn (array $r): string => $r['marcaj']);
$numaraSiStrange($rezultat, 'fisiere', static fn (array $r): string => $r['fisier']);

/* Paginile rămase fără nimic ies din listă. */
$rezultat = array_values(array_filter(
    $rezultat,
    static fn (array $p): bool => $p['texte'] !== [] || $p['fisiere'] !== []
));

if (in_array('--json', $argv, true)) {
    echo json_encode($rezultat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}

$totalTexte = 0;
$totalFisiere = 0;
foreach ($rezultat as $pagina) {
    echo "\n{$pagina['titlu']}  ({$pagina['slug']})\n";
    foreach ($pagina['texte'] as $t) {
        $alte = count($t['alte_locuri'] ?? []);
        $nota = $alte > 0 ? "  (și în alte {$alte} locuri)" : '';
        echo "  text     {$t['sectiune']}: {$t['marcaj']}{$nota}\n";
        $totalTexte++;
    }
    foreach ($pagina['fisiere'] as $f) {
        $alte = count($f['alte_locuri'] ?? []);
        $nota = $alte > 0 ? "  (și în alte {$alte} locuri)" : '';
        echo "  {$f['tip']}  {$f['sectiune']}: {$f['fisier']}{$nota}\n";
        $totalFisiere++;
    }
}
echo "\nTotal: {$totalTexte} texte, {$totalFisiere} fișiere.\n";

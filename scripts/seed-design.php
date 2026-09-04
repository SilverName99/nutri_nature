<?php

declare(strict_types=1);

/*
 * Configurează Design Site (header, meniu, footer) și oprește elementele de
 * magazin moștenite din proiectul anterior.
 *
 * Conținutul rămâne editabil din Dashboard → Design Site. Ca și la pagini,
 * scriptul nu suprascrie ce există deja decât dacă i se cere explicit:
 *
 *   php scripts/seed-design.php
 *   php scripts/seed-design.php --suprascrie
 *
 * Suprascrierea poate fi limitată la o singură setare, ca schimbarea unui rând
 * din subsol să nu ceară rescrierea antetului și a meniului odată cu el:
 *
 *   php scripts/seed-design.php --suprascrie --doar=design_footer_html
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Support\Database;
use App\Support\ResponseCache;
use App\Support\Settings;

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

$suprascrie = in_array('--suprascrie', $argv, true);

/*
 * Setarea pe care o vizează rularea, dacă s-a cerut una anume.
 *
 * Fără ea, singura cale de a corecta un rând din subsol pe un site pornit era
 * „--suprascrie" peste tot — adică peste antetul și meniul pe care clientul
 * poate să le fi ajustat între timp din Design Site.
 */
$doar = '';
foreach ($argv as $arg) {
    if (str_starts_with((string) $arg, '--doar=')) {
        $doar = substr((string) $arg, strlen('--doar='));
    }
}

$meniu = [
    ['Acasă', '/'],
    ['Despre noi', '/despre-noi'],
    ['Servicii', '/servicii'],
    ['Programare', '/programare'],
    ['Blog', '/blog'],
    ['Contact', '/contact'],
];

$linkuriMeniu = '';
foreach ($meniu as [$eticheta, $url]) {
    $linkuriMeniu .= sprintf(
        '        <li class="nav-item"><a class="nav-link" href="%s">%s</a></li>' . "\n",
        htmlspecialchars($url, ENT_QUOTES),
        htmlspecialchars($eticheta, ENT_QUOTES)
    );
}

$header = <<<HTML
<!--
  Antetul.

  Modelul trimis de client (sananobilis.ro) ține numărul de telefon ca buton, în
  dreapta meniului, pe toate paginile. Aici la fel: acțiunea principală a
  site-ului este sunatul, nu un formular.

  Meniul se desface de la 992px: șase intrări plus butonul de telefon încap.
-->
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top" aria-label="Navigare principală">
  <div class="container">
    <a class="navbar-brand me-0" href="/">
      <!--
        Sigla este rotundă, deci pătrată ca fișier: 60px înălțime înseamnă și
        60px lățime. Rândul „centru de medicină integrativă" din ea nu se poate
        citi la mărimea asta — de aceea textul alternativ îl spune, iar antetul
        nu se bazează pe el.
      -->
      <img src="/uploads/gallery/nutri-nature-logo.png" alt="NutriNature — centru de medicină integrativă"
           width="60" height="60"
           onerror="this.replaceWith(document.createTextNode('NutriNature'))">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#meniu-principal"
            aria-controls="meniu-principal" aria-expanded="false" aria-label="Deschide meniul">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="meniu-principal">
      <ul class="navbar-nav">
{$linkuriMeniu}      </ul>
      <a href="tel:[DE COMPLETAT: telefon]" class="btn btn-primary fw-bold ms-lg-3 mt-2 mt-lg-0 text-nowrap">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="me-1" style="vertical-align:-2px">
          <path d="M6.6 10.8c1.4 2.8 3.8 5.2 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.2.4 2.4.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.6 21 3 13.4 3 4c0-.6.4-1 1-1h3.4c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.4 0 .8-.2 1l-2.2 2.2z"/>
        </svg>
        [DE COMPLETAT: telefon]
      </a>
    </div>
  </div>
</nav>
HTML;

$footer = <<<'HTML'
<footer class="bg-brand py-5 mt-5">
  <div class="container">
    <div class="row g-4">
      <div class="col-12 col-md-5">
        <p class="h4 mb-2" style="color: var(--accent-pe-inchis);">NutriNature</p>
        <p class="mb-3">Centru de medicină integrativă. Nutriție personalizată, terapii
        naturale și echilibru între corp, minte și emoții.</p>
        <p class="fst-italic mb-1" style="color: var(--accent-pe-inchis);">Hrana pentru corp. Claritate pentru minte. Energie pentru viață.</p>
        <!-- A doua semnătură a brandului, cea de pe afișele de servicii. -->
        <p class="fst-italic mb-0" style="color: var(--accent-pe-inchis);">Be beautiful… be you.</p>
      </div>
      <div class="col-6 col-md-3">
        <p class="fw-bold mb-2">Navigare</p>
        <ul class="list-unstyled mb-0">
          <li><a href="/despre-noi">Despre noi</a></li>
          <li><a href="/servicii">Servicii</a></li>
          <li><a href="/programare">Programare</a></li>
          <li><a href="/blog">Blog</a></li>
          <li><a href="/contact">Contact</a></li>
        </ul>
      </div>
      <div class="col-12 col-md-4">
        <p class="fw-bold mb-2">Contact</p>
        <p class="mb-1">[DE COMPLETAT: adresa centrului]</p>
        <p class="mb-1"><a href="tel:[DE COMPLETAT: telefon]">[DE COMPLETAT: telefon]</a></p>
        <p class="mb-1"><a class="email-lung" href="mailto:[DE COMPLETAT: email]">[DE COMPLETAT: email]</a></p>
        <p class="mb-0">[DE COMPLETAT: programul de lucru]</p>
      </div>
    </div>
    <hr class="my-4" style="border-color: var(--verde-600);">
    <!--
      Terapiile de aici sunt complementare. Nota nu este mărunțiș juridic: fără
      ea, o pagină care vorbește despre „identificarea dezechilibrelor" poate fi
      citită ca promisiune de diagnostic, ceea ce nu este nici adevărat, nici
      permis în publicitate.
    -->
    <p class="small mb-3">Serviciile prezentate pe acest site sunt terapii complementare și servicii
    de consiliere nutrițională. Ele nu înlocuiesc consultul, diagnosticul și tratamentul medical
    și nu vindecă boli. Pentru orice problemă de sănătate, adresați-vă medicului dumneavoastră.</p>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
      <p class="small mb-0">&copy; {{an}} NutriNature. Toate drepturile rezervate.</p>
      <div class="d-flex align-items-center gap-3 small">
        <a href="/termeni-si-conditii">Termeni și condiții</a>
        <a href="/politica-de-confidentialitate">Confidențialitate</a>
        <a href="/politica-de-cookies">Cookies</a>
      </div>
    </div>
  </div>
</footer>

<!--
  Butonul de WhatsApp, prezent pe toate paginile.

  Clientul a ales telefonul și WhatsApp-ul în locul unui formular de programare,
  deci acestea două trebuie să fie mereu la îndemână, nu doar pe pagina de
  contact. Ținta de atingere are 56px, peste minimul de 44px.
-->
<a class="whatsapp-plutitor" href="https://wa.me/[DE COMPLETAT: telefon în format 407xxxxxxxx]"
   target="_blank" rel="noopener" aria-label="Scrie-ne pe WhatsApp">
  <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" width="30" height="30">
    <path d="M12.04 2c-5.5 0-9.96 4.46-9.96 9.96 0 1.76.46 3.48 1.34 5L2 22l5.2-1.36a9.9 9.9 0 0 0 4.84 1.24h.01c5.5 0 9.96-4.46 9.96-9.96 0-2.66-1.04-5.16-2.92-7.04A9.88 9.88 0 0 0 12.04 2zm0 18.16h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.11.82.83-3.03-.2-.31a8.22 8.22 0 0 1-1.26-4.38c0-4.55 3.7-8.25 8.25-8.25 2.2 0 4.27.86 5.83 2.42a8.2 8.2 0 0 1 2.41 5.84c0 4.55-3.7 8.22-8.25 8.22zm4.52-6.16c-.25-.13-1.47-.72-1.69-.8-.23-.09-.39-.13-.56.12-.16.25-.64.8-.79.97-.14.16-.29.19-.54.06-.25-.12-1.05-.38-1.99-1.23-.74-.65-1.23-1.46-1.38-1.71-.14-.25-.01-.38.11-.51.11-.11.25-.29.37-.43.13-.15.17-.25.25-.41.08-.17.04-.31-.02-.44-.06-.12-.56-1.35-.77-1.84-.2-.48-.4-.42-.56-.43h-.47c-.16 0-.42.06-.64.31-.22.25-.84.82-.84 2s.86 2.32.98 2.48c.12.16 1.69 2.58 4.1 3.62.57.25 1.02.39 1.37.5.58.19 1.1.16 1.51.1.46-.07 1.42-.58 1.62-1.15.2-.56.2-1.05.14-1.15-.06-.1-.22-.16-.47-.29z"/>
  </svg>
</a>
HTML;

$headerJs = <<<'JS'
/*
 * Marcheaza in meniu pagina pe care se afla vizitatorul.
 *
 * Headerul este HTML salvat in setari, deci nu poate decide singur ce pagina
 * este activa. Comparatia se face pe calea din adresa, iar potrivirea pe
 * prefix acopera si subpaginile: /servicii/tipar-offset activeaza „Servicii".
 */
(function () {
  var cale = window.location.pathname.replace(/\/+$/, '') || '/';
  var linkuri = document.querySelectorAll('.navbar-nav .nav-link');
  var potrivit = null;

  linkuri.forEach(function (link) {
    var href = (link.getAttribute('href') || '').replace(/\/+$/, '') || '/';
    if (href === cale) {
      potrivit = link;
    } else if (href !== '/' && cale.indexOf(href + '/') === 0 && !potrivit) {
      potrivit = link;
    }
  });

  if (potrivit) {
    potrivit.classList.add('active');
    potrivit.setAttribute('aria-current', 'page');
  }
})();
JS;

/*
 * Continut editabil de client: aici protectia are sens, ca o rulare repetata
 * sa nu stearga ce a schimbat el din Design Site.
 */
$continut = [
    /*
     * Textul bannerului de cookies.
     *
     * Nu are valoare implicita in cod: views/layout.php afiseaza bannerul doar
     * daca textul nu e gol, deci fara randul de aici site-ul punea cookie de
     * sesiune fara sa spuna nimanui. Linkul catre politica il adauga tot
     * layout-ul, dupa text.
     */
    'cookie_banner_text' => 'Folosim doar cookie-urile fără de care site-ul nu funcționează. '
        . 'Nu măsurăm trafic și nu urmărim pe nimeni. Detaliile sunt în',
    'cookie_banner_policy_url' => '/politica-de-cookies',
    'design_header_html' => $header,
    'design_menu_html' => '<a href="/">Acasă</a><a href="/despre-noi">Despre noi</a><a href="/servicii">Servicii</a><a href="/programare">Programare</a><a href="/blog">Blog</a><a href="/contact">Contact</a>',
    'design_footer_html' => $footer,
    'design_header_js' => $headerJs,
];

/*
 * Comutatoare de magazin mostenite din proiectul anterior. Se scriu mereu.
 *
 * Nu sunt continut, ci decizii care tin de tipul site-ului: un site de
 * prezentare nu are cos si nu are banda de oferte. In plus, scripts/seed.php
 * le lasa pe „1", asa ca la o instalare noua protectia de mai sus le-ar
 * confunda cu editari facute de client si le-ar sari, lasand cosul flotant
 * si tabul de oferte vizibile pe site.
 */
$comutatoare = [
    'floating_cart_enabled' => '0',
    'store_bbd_sidebar_enabled' => '0',
    /*
     * Site de prezentare: coșul și checkout-ul răspund 404. Codul rămâne, deci
     * trecerea la magazin online cere doar stingerea comutatorului.
     */
    'presentation_mode_enabled' => '1',
];

if ($doar !== '' && !array_key_exists($doar, $continut) && !array_key_exists($doar, $comutatoare)) {
    fwrite(STDERR, "Setarea „{$doar}” nu este scrisă de acest script.\n");
    exit(1);
}

if ($doar !== '') {
    $continut = array_intersect_key($continut, [$doar => true]);
    $comutatoare = array_intersect_key($comutatoare, [$doar => true]);
}

$existente = Settings::all($db);
$deScris = $comutatoare;
foreach ($comutatoare as $cheie => $valoare) {
    echo "scris:       {$cheie}\n";
}
foreach ($continut as $cheie => $valoare) {
    $curent = (string) ($existente[$cheie] ?? '');
    if ($curent !== '' && !$suprascrie) {
        echo "sărit:       {$cheie} (are deja valoare; folosiți --suprascrie)\n";
        continue;
    }
    $deScris[$cheie] = $valoare;
    echo "scris:       {$cheie}\n";
}

if ($deScris !== []) {
    Settings::save($db, $deScris);
}

/* Antetul și subsolul intră în HTML-ul salvat pe disc, deci și el trebuie golit. */
$golite = ResponseCache::purgePageCache();
if ($golite > 0) {
    echo "cache:       {$golite} pagini golite din cache.\n";
}

echo "\nGata: " . count($deScris) . " setări scrise.\n";

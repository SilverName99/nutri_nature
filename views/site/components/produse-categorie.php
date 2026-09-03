<?php
/**
 * Produsele executate cu un serviciu.
 *
 * Cardurile duc la pagina produsului, unde stă sertarul cu cererea de ofertă.
 * Nu au preț și nici buton de coș: site-ul nu vinde, ci ofertează.
 */
$produse = is_array($produse ?? null) ? $produse : [];
$numeCategorie = trim((string) ($numeCategorie ?? ''));
$titlu = trim((string) ($titlu ?? 'Ce executăm cu acest serviciu'));
$subtitlu = trim((string) ($subtitlu ?? 'Alegeți un produs și cereți mostre și ofertă de preț.'));
$fundal = trim((string) ($fundal ?? 'bg-body-tertiary'));
$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

if ($produse === []) {
    return;
}
?>
<section id="produse-serviciu" class="py-5 <?= $e($fundal) ?>">
  <div class="container">
    <h2 class="display-5 fw-normal mb-2"><?= $e($titlu) ?></h2>
    <p class="lead mb-4"><?= $e($subtitlu) ?></p>

    <div class="row g-4">
      <?php foreach ($produse as $produs): ?>
        <div class="col-12 col-md-6 col-lg-4">
          <a class="card h-100 text-decoration-none border-0 shadow-sm categorie"
             href="/produs/<?= $e((string) ($produs['slug'] ?? '')) ?>">
            <div class="ratio ratio-4x3 overflow-hidden">
              <img src="<?= $e((string) ($produs['image_url'] ?? '')) ?>"
                   alt="<?= $e((string) ($produs['name'] ?? '')) ?>"
                   class="object-fit-cover" loading="lazy">
            </div>
            <div class="card-body">
              <h3 class="h5 mb-2"><?= $e((string) ($produs['name'] ?? '')) ?></h3>
              <p class="text-secondary small mb-3"><?= $e((string) ($produs['short_description'] ?? '')) ?></p>
              <span class="btn btn-link px-0 fw-semibold text-uppercase banda-alternanta__actiune">
                Cere ofertă
                <svg class="sageata" width="18" height="14" viewBox="0 0 18 14" aria-hidden="true" focusable="false"><path d="M10.5 1 16.5 7l-6 6M0 7h16" fill="none" stroke="currentColor" stroke-width="2"/></svg>
              </span>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

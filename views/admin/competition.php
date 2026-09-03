<?php
$partners = is_array($partners ?? null) ? $partners : [];
// În tabel intră doar partenerii activi; cardurile de mai sus îi arată pe toți,
// ca un partener scos temporar din comparație să poată fi repus.
$partnersInTable = is_array($partnersInTable ?? null) ? $partnersInTable : $partners;
$rows = is_array($rows ?? null) ? $rows : [];
$editing = is_array($editing ?? null) ? $editing : null;
$today = (string) ($today ?? date('Y-m-d'));
$editId = (int) ($editing['id'] ?? 0);

$esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);
$bani = static fn (float $v): string => number_format($v, 2);
// Valoarea „brută”, fără separator de mii: intră în `value` de input number
// și în atributele citite de JS, unde „1,234.56” ar fi ilizibil.
$brut = static fn (float $v): string => number_format($v, 2, '.', '');
$zi = static function (?string $v): string {
    $v = trim((string) $v);
    if ($v === '') {
        return '';
    }
    $ts = strtotime($v);
    return $ts !== false ? date('d.m.Y', $ts) : $v;
};
$perioada = static function (?string $de, ?string $pana) use ($zi): string {
    $d = $zi($de);
    $p = $zi($pana);
    if ($d === '' && $p === '') {
        return 'fără perioadă';
    }
    if ($d === '') {
        return 'până la ' . $p;
    }
    if ($p === '') {
        return 'din ' . $d;
    }
    return $d . ' – ' . $p;
};

// Rândul de linkuri se întinde peste toate coloanele în afară de cea fixă.
$coloaneRamase = 1 + (count($partnersInTable) * 2);
?>
<style>
/* Regulile de mai jos dau `display` explicit unor elemente pe care le ascundem
   cu atributul `hidden`; fără asta ele ar bate regula implicită a browserului
   și etichetele goale ar rămâne vizibile. */
.panel [hidden] { display:none !important; }
/* Tabelul e lat cât numărul de parteneri: derulare orizontală, produsul rămâne fix. */
.cmp-box { background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px 20px;margin:0 0 20px; }
.cmp-box h3 { margin:0 0 4px; }
.cmp-hint { margin:0 0 14px;color:#6b7280;font-size:13px; }
.cmp-partner-form { display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end; }
.cmp-partner-form .field { margin:0; }
.cmp-checks { display:flex;gap:16px;align-items:center;padding-bottom:8px; }
.cmp-checks label { display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#374151;margin:0; }
.cmp-form-actions { display:flex;gap:8px;align-items:center; }
.cmp-partner-list { display:flex;flex-wrap:wrap;gap:10px;margin-top:16px; }
.cmp-partner-card { border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;min-width:200px;background:#f9fafb; }
.cmp-partner-card strong { display:block;font-size:14px; }
.cmp-partner-card a { font-size:12px;color:#2563eb;word-break:break-all; }
.cmp-partner-card-actions { display:flex;gap:6px;margin-top:8px; }
.cmp-partner-card-actions .btn { padding:4px 10px;font-size:12px; }

.cmp-toolbar { display:flex;flex-wrap:wrap;gap:14px;align-items:center;margin:0 0 14px; }
.cmp-toolbar input[type="search"] { min-width:260px;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px; }
.cmp-sim { display:flex;align-items:center;gap:8px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:7px 12px;font-size:13px;font-weight:600;color:#1e3a8a; }
.cmp-sim input { width:74px;padding:6px 8px;border:1px solid #93c5fd;border-radius:6px; }
.cmp-sim small { font-weight:500;color:#3b82f6; }
.cmp-count { font-size:13px;color:#6b7280; }

.cmp-table-wrap { overflow-x:auto;border:1px solid #e5e7eb;border-radius:12px;background:#fff; }
.cmp-table { border-collapse:separate;border-spacing:0;font-size:13px;width:max-content;min-width:100%; }
.cmp-table th, .cmp-table td { border-bottom:1px solid #eef0f3;padding:10px 12px;vertical-align:top;text-align:left; }
.cmp-table thead th { background:#f8fafc;color:#374151;font-size:12px;text-transform:uppercase;letter-spacing:.02em; }
.cmp-table thead th.cmp-sub { text-transform:none;font-size:11px;color:#6b7280;background:#f1f5f9;min-width:210px; }
.cmp-partner-head { border-left:2px solid #e2e8f0; }
.cmp-partner-head small { display:block;font-weight:500;text-transform:none;color:#6b7280;font-size:11px;margin-top:2px; }
/* Produsul și prețul nostru rămân pe loc: sunt reperul oricărei comparații,
   iar tabelul se derulează orizontal pe sub ele. Lățimile sunt fixe pentru că
   a doua coloană lipită are nevoie de un `left` exact. */
.cmp-sticky { position:sticky;left:0;z-index:3;background:#fff;width:260px;min-width:260px;max-width:260px; }
.cmp-our, .cmp-our-cell { position:sticky;left:260px;z-index:3;background:#fff;width:172px;min-width:172px;box-shadow:1px 0 0 #e5e7eb; }
.cmp-table thead th.cmp-sticky, .cmp-table thead th.cmp-our { background:#f8fafc; }
.cmp-row:hover td { background:#fcfdff; }
.cmp-row:hover td.cmp-sticky, .cmp-row:hover td.cmp-our-cell { background:#fcfdff; }

.cmp-prod-toggle { display:block;width:100%;text-align:left;background:none;border:0;padding:0;cursor:pointer;font:inherit;color:inherit; }
.cmp-prod-name { display:block;font-weight:600;color:#111827;line-height:1.3; }
.cmp-prod-meta { display:block;color:#9ca3af;font-size:11px;margin-top:2px; }
.cmp-prod-hint { display:inline-block;margin-top:5px;font-size:11px;color:#2563eb; }
.cmp-our-price { display:block;font-weight:700;font-size:14px;color:#111827; }
.cmp-our-sim { display:block;margin-top:3px;font-size:11px;font-weight:700;color:#1d4ed8; }

.cmp-price-line { display:flex;align-items:center;gap:6px; }
.cmp-input { width:96px;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:13px; }
.cmp-unit { color:#9ca3af;font-size:11px; }
.cmp-saved { font-size:11px;font-weight:700;color:#16a34a;opacity:0;transition:opacity .2s; }
.cmp-saved.on { opacity:1; }
.cmp-saved.err { color:#dc2626; }
.cmp-oos { display:inline-flex;align-items:center;gap:6px;margin-top:8px;padding:4px 9px;border:1px solid #e5e7eb;border-radius:999px;font-size:11px;font-weight:600;color:#6b7280;cursor:pointer;background:#fff; }
.cmp-oos.on { border-color:#fecaca;background:#fef2f2;color:#b91c1c; }
.cmp-campaign-fields { margin-top:8px;padding:8px;border:1px dashed #d8b4fe;border-radius:8px;background:#faf5ff;display:flex;flex-wrap:wrap;gap:6px;align-items:center; }
.cmp-mini-label { font-size:11px;font-weight:700;color:#7e22ce;text-transform:uppercase;width:100%; }
.cmp-campaign-fields input { padding:5px 7px;border:1px solid #d8b4fe;border-radius:6px;font-size:12px; }
.cmp-campaign-fields input[type="number"] { width:92px; }

.cmp-diff-cell { min-width:190px; }
.cmp-diff-ron { font-weight:700;font-size:14px; }
.cmp-diff-pct { display:block;font-size:12px;margin-top:1px; }
.cmp-diff--good .cmp-diff-ron, .cmp-diff--good .cmp-diff-pct { color:#15803d; }
.cmp-diff--bad .cmp-diff-ron, .cmp-diff--bad .cmp-diff-pct { color:#b91c1c; }
.cmp-diff--none .cmp-diff-ron { color:#9ca3af;font-weight:500;font-size:13px; }
.cmp-chip { display:inline-block;margin-top:6px;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;line-height:1.5; }
.cmp-chip--campaign { background:#f3e8ff;color:#6b21a8;max-width:260px;white-space:normal; }
.cmp-chip--oos { background:#fee2e2;color:#b91c1c; }
.cmp-chip--sale { background:#dcfce7;color:#166534;font-size:10px;margin-top:4px; }

.cmp-links-row td { background:#f8fafc;border-bottom:2px solid #e5e7eb; }
.cmp-links-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:10px; }
.cmp-link-item { display:flex;flex-direction:column;gap:4px; }
.cmp-link-item label { font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase; }
.cmp-link-row { display:flex;gap:6px;align-items:center; }
.cmp-link-row input { flex:1;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:12px; }
.cmp-link-open { padding:5px 10px;font-size:12px;white-space:nowrap; }

.cmp-footer { display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:16px; }
.cmp-footer p { margin:0;color:#6b7280;font-size:12px; }
.cmp-empty { padding:24px;color:#6b7280;font-style:italic; }
</style>

<section class="panel">
    <div class="section-head">
        <div>
            <h1>Analiza competiție</h1>
            <p>Prețurile noastre față de cele ale partenerilor, produs cu produs. Prețurile se salvează singure, pe măsură ce le completezi.</p>
        </div>
        <div style="display:flex;gap:8px;">
            <a class="btn btn-secondary" href="/admin/competition/export"
               title="Descarcă toată situația curentă: produse, parteneri, prețuri, diferențe, campanii, stoc și linkuri">⭳ Export Excel</a>
        </div>
    </div>

    <!-- Parteneri: fiecare adaugă în tabel o pereche de coloane -->
    <div class="cmp-box">
        <h3><?= $editId > 0 ? 'Editează partenerul' : 'Adaugă partener' ?></h3>
        <p class="cmp-hint">Fiecare partener primește în tabel două coloane: prețul lui și diferența față de prețul nostru. Bifează „Campanie” ca să poți completa, la fiecare produs, un preț promoțional și perioada lui.</p>
        <form method="post" action="/admin/competition/partners" class="cmp-partner-form">
            <input type="hidden" name="id" value="<?= $editId ?>">
            <div class="field">
                <label>Nume partener *</label>
                <input type="text" name="name" required maxlength="190" value="<?= $esc((string) ($editing['name'] ?? '')) ?>" placeholder="ex: Farmacia X">
            </div>
            <div class="field">
                <label>Site (opțional)</label>
                <input type="text" name="website" maxlength="255" value="<?= $esc((string) ($editing['website'] ?? '')) ?>" placeholder="ex: farmacia-x.ro">
            </div>
            <div class="field">
                <label>Campanie de la</label>
                <input type="date" name="campaign_start" value="<?= $esc((string) ($editing['campaign_start'] ?? '')) ?>">
            </div>
            <div class="field">
                <label>Campanie până la</label>
                <input type="date" name="campaign_end" value="<?= $esc((string) ($editing['campaign_end'] ?? '')) ?>">
            </div>
            <div class="field">
                <label>Ordine afișare</label>
                <input type="number" step="1" name="sort_order" value="<?= (int) ($editing['sort_order'] ?? 0) ?>">
            </div>
            <div class="field cmp-checks">
                <label>
                    <input type="checkbox" name="has_campaign" value="1" <?= ((int) ($editing['has_campaign'] ?? 0)) === 1 ? 'checked' : '' ?>>
                    Campanie
                </label>
                <label>
                    <input type="checkbox" name="is_active" value="1" <?= ((int) ($editing['is_active'] ?? 1)) === 1 ? 'checked' : '' ?>>
                    Activ
                </label>
            </div>
            <div class="cmp-form-actions">
                <button class="btn" type="submit"><?= $editId > 0 ? 'Salvează' : 'Adaugă' ?></button>
                <?php if ($editId > 0): ?>
                    <a class="btn btn-secondary" href="/admin/competition">Anulează</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($partners !== []): ?>
            <div class="cmp-partner-list">
                <?php foreach ($partners as $partner): ?>
                    <?php $pid = (int) $partner['id']; ?>
                    <div class="cmp-partner-card">
                        <strong><?= $esc((string) $partner['name']) ?></strong>
                        <?php if ((string) $partner['website'] !== ''): ?>
                            <a href="<?= $esc((string) $partner['website']) ?>" target="_blank" rel="noreferrer noopener"><?= $esc((string) $partner['website']) ?></a>
                        <?php endif; ?>
                        <div style="margin-top:6px;">
                            <?php if ($partner['has_campaign'] === 1): ?>
                                <span class="cmp-chip cmp-chip--campaign">Campanie · <?= $esc($perioada($partner['campaign_start'], $partner['campaign_end'])) ?></span>
                            <?php endif; ?>
                            <?php if ($partner['is_active'] !== 1): ?>
                                <span class="cmp-chip cmp-chip--oos">Inactiv</span>
                            <?php endif; ?>
                        </div>
                        <div class="cmp-partner-card-actions">
                            <a class="btn btn-secondary" href="/admin/competition?edit=<?= $pid ?>">Editează</a>
                            <form method="post" action="/admin/competition/partners/<?= $pid ?>/delete" onsubmit="return confirm('Ștergi partenerul „<?= $esc((string) $partner['name']) ?>” și toate prețurile lui?');">
                                <button class="btn btn-secondary" type="submit">Șterge</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($partners === []): ?>
        <p class="cmp-empty">Adaugă primul partener ca să apară coloanele de comparație.</p>
    <?php elseif ($rows === []): ?>
        <p class="cmp-empty">Nu există produse în catalog.</p>
    <?php else: ?>

    <div class="cmp-toolbar">
        <input type="search" id="cmp-search" placeholder="Caută produs după denumire, SKU sau categorie…" autocomplete="off" aria-label="Caută produs">
        <span class="cmp-count" id="cmp-count"><?= count($rows) ?> produse</span>
        <label class="cmp-sim">
            Simulează o reducere la prețul nostru
            <input type="number" id="cmp-discount" min="0" max="95" step="0.5" placeholder="0"> %
            <small>doar în tabel, nu schimbă prețurile de pe site</small>
        </label>
    </div>

    <form method="post" action="/admin/competition/save-all" id="cmp-form">
        <div class="cmp-table-wrap">
            <table class="cmp-table" id="cmp-table">
                <thead>
                    <tr>
                        <th class="cmp-sticky" rowspan="2">Produs</th>
                        <th class="cmp-our" rowspan="2">Prețul nostru</th>
                        <?php foreach ($partnersInTable as $partner): ?>
                            <th class="cmp-partner-head" colspan="2">
                                <?= $esc((string) $partner['name']) ?>
                                <?php if ($partner['has_campaign'] === 1): ?>
                                    <small>campanie: <?= $esc($perioada($partner['campaign_start'], $partner['campaign_end'])) ?></small>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($partnersInTable as $partner): ?>
                            <th class="cmp-sub">Preț la partener (RON)</th>
                            <th class="cmp-sub">Diferență preț</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $product = $row['product'];
                    $productId = (int) $product['id'];
                    $pretulNostru = (float) $product['price'];
                    $cautare = implode(' ', array_filter([
                        (string) $product['name'],
                        (string) $product['sku'],
                        (string) $product['category_name'],
                    ], static fn (string $bucata): bool => trim($bucata) !== ''));
                    ?>
                    <tr class="cmp-row" data-search="<?= $esc($cautare) ?>" data-product="<?= $productId ?>">
                        <td class="cmp-sticky">
                            <button type="button" class="cmp-prod-toggle" data-toggle-links="<?= $productId ?>"
                                    aria-expanded="false" title="Deschide câmpurile de link către pagina produsului la fiecare partener">
                                <span class="cmp-prod-name"><?= $esc((string) $product['name']) ?></span>
                                <span class="cmp-prod-meta">
                                    <?= $esc((string) $product['sku']) !== '' ? $esc((string) $product['sku']) : 'fără SKU' ?>
                                    <?= (string) $product['category_name'] !== '' ? ' · ' . $esc((string) $product['category_name']) : '' ?>
                                </span>
                                <span class="cmp-prod-hint">🔗 Linkuri la parteneri</span>
                            </button>
                        </td>
                        <td class="cmp-our-cell">
                            <strong class="cmp-our-price" data-our-base="<?= $brut($pretulNostru) ?>"><?= $bani($pretulNostru) ?> RON</strong>
                            <span class="cmp-our-sim" hidden></span>
                            <?php if (($product['has_sale_price'] ?? false) === true): ?>
                                <span class="cmp-chip cmp-chip--sale">redus de la <?= $bani((float) $product['base_price']) ?></span>
                            <?php endif; ?>
                        </td>

                        <?php foreach ($partnersInTable as $partner): ?>
                            <?php
                            $partnerId = (int) $partner['id'];
                            $date = $row['cells'][$partnerId] ?? ['cell' => [], 'effective' => []];
                            $cell = is_array($date['cell'] ?? null) ? $date['cell'] : [];
                            $efectiv = is_array($date['effective'] ?? null) ? $date['effective'] : [];
                            $numeCamp = 'cells[' . $partnerId . '][' . $productId . ']';
                            $pretPartener = $efectiv['price'] ?? null;
                            $outOfStock = ((int) ($cell['out_of_stock'] ?? 0)) === 1;
                            $pretCampanie = $cell['campaign_price'] ?? null;

                            $clasaDiff = 'cmp-diff--none';
                            $textRon = 'fără preț';
                            $textPct = '';
                            if ($pretPartener !== null && $pretPartener > 0.0) {
                                $diferenta = $pretulNostru - (float) $pretPartener;
                                $procent = ($diferenta / (float) $pretPartener) * 100;
                                $clasaDiff = $diferenta < 0 ? 'cmp-diff--good' : ($diferenta > 0 ? 'cmp-diff--bad' : 'cmp-diff--none');
                                $semn = $diferenta > 0 ? '+' : ($diferenta < 0 ? '−' : '');
                                $textRon = $semn . $bani(abs($diferenta)) . ' RON';
                                $textPct = $semn . number_format(abs($procent), 1, '.', '') . '%' . ($diferenta < 0 ? ' mai ieftin' : ($diferenta > 0 ? ' mai scump' : ''));
                            }

                            $textCampanie = '';
                            if ($partner['has_campaign'] === 1 && $pretCampanie !== null && (float) $pretCampanie > 0.0) {
                                $vechi = $cell['price'] ?? null;
                                $textCampanie = 'Campanie: '
                                    . ($vechi !== null ? $bani((float) $vechi) . ' → ' : '')
                                    . $bani((float) $pretCampanie) . ' RON'
                                    . (($efectiv['discount'] ?? null) !== null ? ' (−' . number_format((float) $efectiv['discount'], 1, '.', '') . '%)' : '')
                                    . ' · ' . $perioada($efectiv['start'] ?? null, $efectiv['end'] ?? null)
                                    . (($efectiv['is_campaign'] ?? false) ? '' : ' · inactivă azi');
                            }
                            ?>
                            <td class="cmp-price-cell"
                                data-partner-start="<?= $esc((string) ($partner['campaign_start'] ?? '')) ?>"
                                data-partner-end="<?= $esc((string) ($partner['campaign_end'] ?? '')) ?>"
                                data-has-campaign="<?= $partner['has_campaign'] ?>">
                                <div class="cmp-price-line">
                                    <input type="number" step="0.01" min="0" class="cmp-input" placeholder="—"
                                           name="<?= $numeCamp ?>[price]"
                                           value="<?= isset($cell['price']) && $cell['price'] !== null ? $brut((float) $cell['price']) : '' ?>"
                                           data-cell="price" data-partner="<?= $partnerId ?>" data-product="<?= $productId ?>"
                                           aria-label="Preț la <?= $esc((string) $partner['name']) ?>">
                                    <span class="cmp-unit">RON</span>
                                    <span class="cmp-saved" aria-live="polite"></span>
                                </div>

                                <!-- Comutator informativ: marchează produsul ca indisponibil la partenerul ăsta. -->
                                <label class="cmp-oos <?= $outOfStock ? 'on' : '' ?>">
                                    <input type="hidden" name="<?= $numeCamp ?>[out_of_stock]" value="0">
                                    <input type="checkbox" name="<?= $numeCamp ?>[out_of_stock]" value="1" <?= $outOfStock ? 'checked' : '' ?>
                                           data-cell="out_of_stock" data-partner="<?= $partnerId ?>" data-product="<?= $productId ?>">
                                    <span>Out of stock</span>
                                </label>

                                <?php if ($partner['has_campaign'] === 1): ?>
                                    <div class="cmp-campaign-fields">
                                        <span class="cmp-mini-label">Campanie</span>
                                        <input type="number" step="0.01" min="0" placeholder="preț nou"
                                               name="<?= $numeCamp ?>[campaign_price]"
                                               value="<?= $pretCampanie !== null ? $brut((float) $pretCampanie) : '' ?>"
                                               data-cell="campaign_price" data-partner="<?= $partnerId ?>" data-product="<?= $productId ?>"
                                               aria-label="Preț de campanie la <?= $esc((string) $partner['name']) ?>">
                                        <input type="date" name="<?= $numeCamp ?>[campaign_start]"
                                               value="<?= $esc((string) ($cell['campaign_start'] ?? '')) ?>"
                                               data-cell="campaign_start" data-partner="<?= $partnerId ?>" data-product="<?= $productId ?>"
                                               aria-label="Campania începe">
                                        <input type="date" name="<?= $numeCamp ?>[campaign_end]"
                                               value="<?= $esc((string) ($cell['campaign_end'] ?? '')) ?>"
                                               data-cell="campaign_end" data-partner="<?= $partnerId ?>" data-product="<?= $productId ?>"
                                               aria-label="Campania se termină">
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="cmp-diff-cell <?= $clasaDiff ?>">
                                <span class="cmp-diff-ron"><?= $esc($textRon) ?></span>
                                <span class="cmp-diff-pct"><?= $esc($textPct) ?></span>
                                <span class="cmp-chip cmp-chip--campaign" <?= $textCampanie === '' ? 'hidden' : '' ?>><?= $esc($textCampanie) ?></span>
                                <span class="cmp-chip cmp-chip--oos" <?= $outOfStock ? '' : 'hidden' ?>>Indisponibil la partener</span>
                            </td>
                        <?php endforeach; ?>
                    </tr>

                    <!-- Linkurile către pagina produsului la fiecare partener, ascunse până la click pe produs. -->
                    <tr class="cmp-links-row" data-links-for="<?= $productId ?>" hidden>
                        <td class="cmp-sticky"><strong style="font-size:12px;color:#6b7280;">Linkuri către produs</strong></td>
                        <td colspan="<?= $coloaneRamase ?>">
                            <div class="cmp-links-grid">
                                <?php foreach ($partnersInTable as $partner): ?>
                                    <?php
                                    $partnerId = (int) $partner['id'];
                                    $cell = $row['cells'][$partnerId]['cell'] ?? [];
                                    $link = (string) ($cell['product_url'] ?? '');
                                    $numeCamp = 'cells[' . $partnerId . '][' . $productId . ']';
                                    ?>
                                    <div class="cmp-link-item">
                                        <label for="cmp-url-<?= $partnerId ?>-<?= $productId ?>"><?= $esc((string) $partner['name']) ?></label>
                                        <div class="cmp-link-row">
                                            <input type="url" id="cmp-url-<?= $partnerId ?>-<?= $productId ?>"
                                                   name="<?= $numeCamp ?>[product_url]" value="<?= $esc($link) ?>"
                                                   placeholder="https://…"
                                                   data-cell="product_url" data-partner="<?= $partnerId ?>" data-product="<?= $productId ?>">
                                            <a class="btn btn-secondary cmp-link-open" href="<?= $esc($link !== '' ? $link : '#') ?>"
                                               target="_blank" rel="noreferrer noopener" <?= $link === '' ? 'hidden' : '' ?>>Deschide</a>
                                            <span class="cmp-saved" aria-live="polite"></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="cmp-footer">
            <button class="btn btn-secondary" type="submit">Salvează tot</button>
            <a class="btn" href="/admin/competition/export">⭳ Descarcă Excel cu situația curentă</a>
            <p>Fiecare câmp se salvează singur când ieși din el; „Salvează tot” e doar plasa de siguranță.</p>
        </div>
    </form>
    <?php endif; ?>
</section>

<script>
(() => {
    const tabel = document.getElementById('cmp-table');
    if (!tabel) {
        return;
    }
    const azi = '<?= $esc($today) ?>';

    const numar = (valoare) => {
        const text = String(valoare ?? '').trim().replace(',', '.');
        if (text === '') {
            return null;
        }
        const n = Number.parseFloat(text);
        return Number.isFinite(n) && n > 0 ? n : null;
    };
    // Aceeași formă ca `number_format($v, 2)` din PHP, ca valorile recalculate
    // în browser să arate identic cu cele venite de la server.
    const lei = (n) => n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const ziRo = (iso) => {
        if (!iso) {
            return '';
        }
        const [a, l, z] = iso.split('-');
        return `${z}.${l}.${a}`;
    };
    const perioadaText = (de, pana) => {
        if (!de && !pana) {
            return 'fără perioadă';
        }
        if (!de) {
            return 'până la ' + ziRo(pana);
        }
        if (!pana) {
            return 'din ' + ziRo(de);
        }
        return ziRo(de) + ' – ' + ziRo(pana);
    };

    // ── Recalcul diferențe ────────────────────────────────────
    // Diferența = prețul nostru − prețul de la partener. Procentul se
    // raportează la prețul partenerului: „cu cât suntem sub/peste el”.
    const inputReducere = document.getElementById('cmp-discount');
    const reducereSimulata = () => {
        const n = Number.parseFloat(String(inputReducere?.value ?? '').replace(',', '.'));
        return Number.isFinite(n) && n > 0 ? Math.min(n, 95) : 0;
    };

    const recalculeazaCelula = (celulaDiff, pretulNostru) => {
        const celulaPret = celulaDiff.previousElementSibling;
        if (!celulaPret) {
            return;
        }
        const camp = (nume) => celulaPret.querySelector('[data-cell="' + nume + '"]');
        const pretNormal = numar(camp('price')?.value);
        const pretCampanie = numar(camp('campaign_price')?.value);
        const start = camp('campaign_start')?.value || celulaPret.dataset.partnerStart || '';
        const final = camp('campaign_end')?.value || celulaPret.dataset.partnerEnd || '';
        const areCampanie = celulaPret.dataset.hasCampaign === '1';

        const campanieActiva = areCampanie && pretCampanie !== null
            && (!start || start <= azi) && (!final || final >= azi);
        const pretPartener = campanieActiva ? pretCampanie : pretNormal;

        const textRon = celulaDiff.querySelector('.cmp-diff-ron');
        const textPct = celulaDiff.querySelector('.cmp-diff-pct');
        celulaDiff.classList.remove('cmp-diff--good', 'cmp-diff--bad', 'cmp-diff--none');

        if (pretPartener === null || pretulNostru <= 0) {
            celulaDiff.classList.add('cmp-diff--none');
            textRon.textContent = 'fără preț';
            textPct.textContent = '';
        } else {
            const diferenta = pretulNostru - pretPartener;
            const procent = (diferenta / pretPartener) * 100;
            const semn = diferenta > 0 ? '+' : (diferenta < 0 ? '−' : '');
            celulaDiff.classList.add(diferenta < 0 ? 'cmp-diff--good' : (diferenta > 0 ? 'cmp-diff--bad' : 'cmp-diff--none'));
            textRon.textContent = semn + lei(Math.abs(diferenta)) + ' RON';
            textPct.textContent = semn + Math.abs(procent).toFixed(1) + '%'
                + (diferenta < 0 ? ' mai ieftin' : (diferenta > 0 ? ' mai scump' : ''));
        }

        // Eticheta de campanie se rescrie aici ca să nu ceară reîncărcarea paginii.
        const eticheta = celulaDiff.querySelector('.cmp-chip--campaign');
        if (eticheta) {
            if (areCampanie && pretCampanie !== null) {
                const discount = pretNormal !== null && pretCampanie < pretNormal
                    ? ' (−' + (((pretNormal - pretCampanie) / pretNormal) * 100).toFixed(1) + '%)'
                    : '';
                eticheta.textContent = 'Campanie: '
                    + (pretNormal !== null ? lei(pretNormal) + ' → ' : '')
                    + lei(pretCampanie) + ' RON' + discount
                    + ' · ' + perioadaText(start, final)
                    + (campanieActiva ? '' : ' · inactivă azi');
                eticheta.hidden = false;
            } else {
                eticheta.hidden = true;
            }
        }
    };

    const recalculeazaRand = (rand) => {
        const bazaText = rand.querySelector('[data-our-base]')?.dataset.ourBase ?? '0';
        const baza = Number.parseFloat(bazaText) || 0;
        const reducere = reducereSimulata();
        const pretulNostru = reducere > 0 ? baza * (1 - reducere / 100) : baza;

        const simulat = rand.querySelector('.cmp-our-sim');
        if (simulat) {
            simulat.hidden = reducere <= 0;
            simulat.textContent = reducere > 0 ? '−' + reducere + '% → ' + lei(pretulNostru) + ' RON' : '';
        }
        rand.querySelectorAll('.cmp-diff-cell').forEach((celula) => recalculeazaCelula(celula, pretulNostru));
    };

    const randuri = Array.from(tabel.querySelectorAll('tr.cmp-row'));
    const recalculeazaTot = () => randuri.forEach(recalculeazaRand);
    inputReducere?.addEventListener('input', recalculeazaTot);

    // ── Salvare pe celulă ─────────────────────────────────────
    const arataStare = (input, text, eroare) => {
        const indicator = input.closest('.cmp-price-cell, .cmp-link-row')?.querySelector('.cmp-saved');
        if (!indicator) {
            return;
        }
        indicator.textContent = text;
        indicator.classList.toggle('err', Boolean(eroare));
        indicator.classList.add('on');
        window.setTimeout(() => indicator.classList.remove('on'), 2000);
    };

    const salveaza = async (input) => {
        const valoare = input.type === 'checkbox' ? (input.checked ? '1' : '0') : input.value;
        try {
            const raspuns = await fetch('/admin/competition/cell', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    partner_id: Number(input.dataset.partner),
                    product_id: Number(input.dataset.product),
                    field: input.dataset.cell,
                    value: valoare,
                }),
            });
            const date = await raspuns.json();
            if (date && date.ok) {
                arataStare(input, '✓ salvat', false);
            } else {
                arataStare(input, '✕ eroare', true);
            }
        } catch (e) {
            arataStare(input, '✕ offline', true);
        }
    };

    tabel.addEventListener('change', (eveniment) => {
        const input = eveniment.target;
        if (!(input instanceof HTMLElement) || !input.dataset.cell) {
            return;
        }
        salveaza(input);

        if (input.dataset.cell === 'out_of_stock') {
            input.closest('.cmp-oos')?.classList.toggle('on', input.checked);
            const celulaDiff = input.closest('.cmp-price-cell')?.nextElementSibling;
            const eticheta = celulaDiff?.querySelector('.cmp-chip--oos');
            if (eticheta) {
                eticheta.hidden = !input.checked;
            }
            return;
        }

        if (input.dataset.cell === 'product_url') {
            const buton = input.parentElement?.querySelector('.cmp-link-open');
            if (buton) {
                buton.href = input.value.trim() !== '' ? input.value.trim() : '#';
                buton.hidden = input.value.trim() === '';
            }
            return;
        }

        const rand = input.closest('tr.cmp-row');
        if (rand) {
            recalculeazaRand(rand);
        }
    });

    // ── Click pe produs: câmpurile de link ────────────────────
    tabel.addEventListener('click', (eveniment) => {
        const buton = eveniment.target.closest('[data-toggle-links]');
        if (!buton) {
            return;
        }
        const randLinkuri = tabel.querySelector('tr[data-links-for="' + buton.dataset.toggleLinks + '"]');
        if (!randLinkuri) {
            return;
        }
        randLinkuri.hidden = !randLinkuri.hidden;
        buton.setAttribute('aria-expanded', randLinkuri.hidden ? 'false' : 'true');
    });

    // ── Căutare ───────────────────────────────────────────────
    const cautare = document.getElementById('cmp-search');
    const contor = document.getElementById('cmp-count');
    if (cautare) {
        // „Căști” trebuie găsit și scriind „casti”.
        const faraDiacritice = (text) => text
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
        const indexate = randuri.map((rand) => ({
            rand,
            randLinkuri: tabel.querySelector('tr[data-links-for="' + rand.dataset.product + '"]'),
            text: faraDiacritice(rand.dataset.search || ''),
        }));

        cautare.addEventListener('input', () => {
            const cuvinte = faraDiacritice(cautare.value).split(/\s+/).filter(Boolean);
            let vizibile = 0;
            indexate.forEach(({ rand, randLinkuri, text }) => {
                const potrivit = cuvinte.every((cuvant) => text.includes(cuvant));
                rand.hidden = !potrivit;
                if (randLinkuri && !potrivit) {
                    randLinkuri.hidden = true;
                }
                if (potrivit) {
                    vizibile++;
                }
            });
            if (contor) {
                contor.textContent = cuvinte.length === 0
                    ? `${indexate.length} produse`
                    : `${vizibile} din ${indexate.length} produse`;
            }
        });
    }

    recalculeazaTot();
})();
</script>

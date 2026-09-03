<?php
/**
 * Cererile de ofertă.
 *
 * Lista este căsuța de intrare a unui site care nu vinde: fiecare rând este un
 * client potențial. Starea se schimbă din rând, fără pagină separată — o cerere
 * trece prin patru stări și nu are nevoie de un ecran propriu.
 */
$cereri = is_array($cereri ?? null) ? $cereri : [];
$numarPeStari = is_array($numarPeStari ?? null) ? $numarPeStari : [];
$stareCurenta = (string) ($stareCurenta ?? '');
$etichete = \App\Support\CereriOferta::ETICHETE_STARI;
$total = array_sum($numarPeStari);
$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<h1>Cereri de ofertă</h1>
<p class="muted">Cererile trimise din sertarul „Solicită mostre și ofertă de preț" de pe paginile de produs.</p>

<div class="panel" style="margin-bottom:16px;">
    <a class="btn<?= $stareCurenta === '' ? '' : ' btn-secondary' ?>" href="/admin/cereri-oferta">Toate (<?= (int) $total ?>)</a>
    <?php foreach ($etichete as $cheie => $eticheta): ?>
        <a class="btn<?= $stareCurenta === $cheie ? '' : ' btn-secondary' ?>"
           href="/admin/cereri-oferta?stare=<?= $e($cheie) ?>">
            <?= $e($eticheta) ?> (<?= (int) ($numarPeStari[$cheie] ?? 0) ?>)
        </a>
    <?php endforeach; ?>
</div>

<?php if ($cereri === []): ?>
    <div class="panel">
        <p>Nicio cerere<?= $stareCurenta !== '' ? ' în această stare' : '' ?>.</p>
    </div>
<?php else: ?>
    <div class="panel" style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Primită</th>
                    <th>Cine</th>
                    <th>Produs</th>
                    <th>Mesaj</th>
                    <th>Stare</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cereri as $cerere): ?>
                <tr>
                    <td style="white-space:nowrap;">
                        <?= $e(date('d.m.Y', strtotime((string) ($cerere['created_at'] ?? 'now')))) ?><br>
                        <small class="muted"><?= $e(date('H:i', strtotime((string) ($cerere['created_at'] ?? 'now')))) ?></small>
                    </td>
                    <td>
                        <strong><?= $e($cerere['name'] ?? '') ?></strong>
                        <?php if (trim((string) ($cerere['company'] ?? '')) !== ''): ?>
                            <br><small class="muted"><?= $e($cerere['company']) ?></small>
                        <?php endif; ?>
                        <br><a href="mailto:<?= $e($cerere['email'] ?? '') ?>"><?= $e($cerere['email'] ?? '') ?></a>
                        <?php if (trim((string) ($cerere['phone'] ?? '')) !== ''): ?>
                            <br><a href="tel:<?= $e(preg_replace('/\s+/', '', (string) $cerere['phone'])) ?>"><?= $e($cerere['phone']) ?></a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (trim((string) ($cerere['product_name'] ?? '')) !== ''): ?>
                            <a href="/produs/<?= $e($cerere['product_slug'] ?? '') ?>" target="_blank" rel="noopener">
                                <?= $e($cerere['product_name']) ?>
                            </a>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="max-width:380px;">
                        <?= nl2br($e($cerere['message'] ?? '')) ?: '<span class="muted">—</span>' ?>
                    </td>
                    <td style="min-width:230px;">
                        <form method="post" action="/admin/cereri-oferta/<?= (int) ($cerere['id'] ?? 0) ?>/stare">
                            <input type="hidden" name="stare_filtru" value="<?= $e($stareCurenta) ?>">
                            <select name="stare" class="form-control" style="margin-bottom:6px;">
                                <?php foreach ($etichete as $cheie => $eticheta): ?>
                                    <option value="<?= $e($cheie) ?>"<?= ($cerere['status'] ?? '') === $cheie ? ' selected' : '' ?>>
                                        <?= $e($eticheta) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input class="form-control" type="text" name="nota" placeholder="Notă internă"
                                   value="<?= $e($cerere['admin_note'] ?? '') ?>" style="margin-bottom:6px;">
                            <button class="btn" type="submit">Salvează</button>
                        </form>
                        <form method="post" action="/admin/cereri-oferta/<?= (int) ($cerere['id'] ?? 0) ?>/sterge"
                              onsubmit="return confirm('Ștergeți cererea de la <?= $e($cerere['name'] ?? '') ?>?');"
                              style="margin-top:6px;">
                            <input type="hidden" name="stare_filtru" value="<?= $e($stareCurenta) ?>">
                            <button class="btn btn-secondary" type="submit">Șterge</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

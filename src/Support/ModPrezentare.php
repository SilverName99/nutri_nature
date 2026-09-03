<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Mod prezentare: site-ul nu vinde, ci primește cereri de ofertă.
 *
 * Aplicația a fost preluată dintr-un magazin online, deci coșul, checkout-ul și
 * contul de client există în cod. Pe un site de prezentare nu se folosesc, dar
 * rutele rămân vii: nimeni nu le vede în meniu, însă un motor de căutare le
 * poate indexa, iar un vizitator poate ajunge într-un checkout care nu duce
 * nicăieri. De aceea comutatorul le închide, în loc doar să le ascundă.
 *
 * Codul nu se șterge — clientul poate trece oricând la magazin online, iar
 * atunci se stinge comutatorul și totul revine.
 *
 * Nu se blochează:
 *   - /produs/... și /magazin, care sunt catalogul de prezentare;
 *   - administrarea, unde comenzile vechi trebuie să rămână vizibile;
 *   - rutele tehnice, ca la mentenanță.
 */
final class ModPrezentare
{
    /** Prefixe de cale care țin de vânzarea propriu-zisă. */
    private const CAI_INCHISE = [
        '/cos',
        '/checkout',
        '/api/cart/',
        '/api/checkout/',
        '/api/fan/',
        '/contul-meu',
        '/comenzile-mele',
    ];

    /** Ce s-a citit ultima dată, ca șabloanele să nu care setările după ele. */
    private static ?bool $memorat = null;

    public static function activ(array $settings): bool
    {
        self::$memorat = (string) ($settings['presentation_mode_enabled'] ?? '0') === '1';

        return self::$memorat;
    }

    /**
     * Pentru șabloane, care nu primesc setările în date.
     *
     * Răspunde „false" până când cineva citește setările măcar o dată; pe
     * traseul public asta se întâmplă în „index.php", înaintea oricărei
     * afișări, deci un șablon nu apucă să întrebe mai devreme.
     */
    public static function esteActiv(): bool
    {
        return self::$memorat === true;
    }

    /**
     * Spune dacă o cale ține de vânzare. Potrivirea este pe segment întreg:
     * „/cos" prinde „/cos" și „/cos/adauga/1", dar nu „/cosmetice", care ar fi
     * un slug de categorie perfect legitim.
     */
    public static function caleInchisa(string $cale): bool
    {
        foreach (self::CAI_INCHISE as $prefix) {
            if ($cale === $prefix || str_starts_with($cale, rtrim($prefix, '/') . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dacă e cazul, răspunde cu 404 și oprește cererea.
     * Întoarce true dacă cererea a fost tratată aici.
     */
    public static function intercepteaza(array $settings, string $uri): bool
    {
        if (!self::activ($settings)) {
            return false;
        }

        $cale = (string) (parse_url($uri, PHP_URL_PATH) ?? '/');
        if ($cale !== '/') {
            $cale = rtrim($cale, '/');
            if ($cale === '') {
                $cale = '/';
            }
        }

        if (!self::caleInchisa($cale)) {
            return false;
        }

        http_response_code(404);
        /*
         * „noindex" pe lângă 404: un motor care are deja adresa în index o
         * scoate mai repede dacă i se spune explicit, nu doar prin cod de stare.
         */
        header('X-Robots-Tag: noindex, nofollow');

        View::render('site/not-found', ['title' => 'Pagina nu a fost găsită']);

        return true;
    }
}

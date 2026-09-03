<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use Throwable;

/**
 * Filtru anti-spam pentru recenziile de produs.
 *
 * Recenziile suspecte nu se pierd: intră în baza de date marcate ca spam, ca
 * să poată fi verificate și, la nevoie, recuperate. Ce se schimbă e că nu mai
 * ajung în lista de „Pending", unde se amestecau cu recenziile reale.
 */
final class ReviewGuard
{
    /** Câte recenzii acceptăm de la aceeași adresă IP într-o oră. */
    private const LIMITA_PE_IP_PE_ORA = 4;

    /** Sub atâtea secunde de la deschiderea formularului, e robot. */
    private const SECUNDE_MINIME = 3;

    /**
     * Tipare de scanare automată. Sunt bucăți de payload — nu apar în text
     * scris de om, așa că nu riscăm recenzii reale marcate greșit.
     */
    private const TIPARE_ATAC = [
        '/\bpg_sleep\s*\(/i',
        '/\bdbms_pipe\.receive_message\b/i',
        '/\bwaitfor\s+delay\b/i',
        '/\bbenchmark\s*\(\s*\d+/i',
        '/\bsleep\s*\(\s*\d+\s*\)/i',
        '/\bunion\s+(all\s+)?select\b/i',
        '/\binformation_schema\b/i',
        '/\bxp_cmdshell\b/i',
        '/\bselect\b.{0,40}\bfrom\b.{0,40}\bwhere\b/i',
        '/\bor\s+\d+\s*=\s*\(\s*select\b/i',
        '/\bchr\s*\(\s*\d+\s*\)\s*\|\|/i',
        '/<\s*script\b/i',
        '/\bon(error|load)\s*=/i',
        '/\$\{\s*jndi\s*:/i',
        '/\.\.\/\.\.\//',
        // Injecții „boolean-based", cele mai des întâlnite în valurile de spam:
        //   -1" OR 2+312-312-1=0+0+0+1 --
        //   -1' OR 2+150-150-1=0+0+0+1 or 'H3CkKkLG'='
        // Nu conțin `union`, `sleep` sau `select`, deci treceau de tiparele
        // clasice de mai sus.
        '/\b(or|and)\s+[\d\s()+-]{1,40}=[\d\s()+-]{1,40}/i',
        '/[\'"]\s*=\s*[\'"]/',
        '/^\s*-\s*\d+\s*[\'"]?\s*(or|and)\b/i',
        '/[\'"]\s*(--|#)\s*$/m',
        '/;\s*(select|insert|update|delete|drop|alter)\b/i',
        '/\bor\s+[\'"][^\'"]{2,}[\'"]\s*=/i',
    ];

    /** Domenii de test sau de email temporar: nimeni real nu lasă recenzii de pe ele. */
    private const DOMENII_SUSPECTE = [
        'example.com', 'example.org', 'example.net', 'test.com', 'mailinator.com',
        'yopmail.com', 'guerrillamail.com', 'sharklasers.com', 'tempmail.com',
        'temp-mail.org', 'trashmail.com', '10minutemail.com', 'dispostable.com',
    ];

    public static function ensureSchema(PDO $db): void
    {
        foreach ([
            'ALTER TABLE product_reviews ADD COLUMN ip_address VARCHAR(64) DEFAULT NULL',
            'ALTER TABLE product_reviews ADD COLUMN is_spam TINYINT(1) NOT NULL DEFAULT 0',
            'ALTER TABLE product_reviews ADD COLUMN spam_reason VARCHAR(190) DEFAULT NULL',
            'ALTER TABLE product_reviews ADD INDEX idx_product_reviews_spam (is_spam)',
        ] as $sql) {
            try {
                $db->exec($sql);
            } catch (Throwable) {
                // Coloana există deja.
            }
        }
    }

    /** Adresa IP a vizitatorului, așa cum ajunge prin proxy-ul gazdei. */
    public static function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $cheie) {
            $valoare = trim((string) ($_SERVER[$cheie] ?? ''));
            if ($valoare === '') {
                continue;
            }
            if (str_contains($valoare, ',')) {
                $valoare = trim(explode(',', $valoare)[0]);
            }
            if (filter_var($valoare, FILTER_VALIDATE_IP) !== false) {
                return substr($valoare, 0, 64);
            }
        }

        return '';
    }

    /**
     * Motivul pentru care recenzia arată a spam, sau null dacă e curată.
     *
     * `$db` e opțional: fără el se sar verificările care au nevoie de istoric
     * (limita pe IP și textul duplicat).
     */
    public static function motiv(array $date, ?PDO $db = null): ?string
    {
        $capcana = trim((string) ($date['honeypot'] ?? ''));
        if ($capcana !== '') {
            return 'câmp-capcană completat';
        }

        $deschisLa = (int) ($date['opened_at'] ?? 0);
        if ($deschisLa > 0 && (time() - $deschisLa) < self::SECUNDE_MINIME) {
            return 'formular trimis prea repede';
        }

        $text = (string) ($date['text'] ?? '');
        $nume = (string) ($date['name'] ?? '');
        $motivContinut = self::motivDinContinut($nume, $text, (string) ($date['email'] ?? ''));
        if ($motivContinut !== null) {
            return $motivContinut;
        }

        if (!$db instanceof PDO) {
            return null;
        }

        $ip = trim((string) ($date['ip'] ?? ''));
        if ($ip !== '') {
            try {
                $stmt = $db->prepare(
                    'SELECT COUNT(*) FROM product_reviews
                     WHERE ip_address = :ip AND created_at >= (NOW() - INTERVAL 1 HOUR)'
                );
                $stmt->execute(['ip' => $ip]);
                if ((int) $stmt->fetchColumn() >= self::LIMITA_PE_IP_PE_ORA) {
                    return 'prea multe recenzii de la aceeași adresă IP';
                }
            } catch (Throwable) {
            }
        }

        $textCurat = trim($text);
        if ($textCurat !== '') {
            try {
                $stmt = $db->prepare(
                    'SELECT COUNT(*) FROM product_reviews
                     WHERE review_text = :text AND created_at >= (NOW() - INTERVAL 30 DAY)'
                );
                $stmt->execute(['text' => $textCurat]);
                if ((int) $stmt->fetchColumn() > 0) {
                    return 'text identic cu o recenzie trimisă recent';
                }
            } catch (Throwable) {
            }
        }

        return null;
    }

    /** Doar regulile care se pot aplica pe o recenzie deja salvată. */
    public static function motivDinContinut(string $nume, string $text, string $email = ''): ?string
    {
        $totul = $nume . ' ' . $text;

        foreach (self::TIPARE_ATAC as $tipar) {
            if (preg_match($tipar, $totul) === 1) {
                return 'tipar de atac automat';
            }
        }

        if (preg_match_all('#https?://|www\.#i', $text) >= 2) {
            return 'prea multe linkuri';
        }
        if (preg_match('/\[url[=\]]|\[link[=\]]/i', $text) === 1) {
            return 'cod BBCode de spam';
        }

        $emailCurat = mb_strtolower(trim($email));
        if ($emailCurat !== '' && str_contains($emailCurat, '@')) {
            $domeniu = substr((string) strrchr($emailCurat, '@'), 1);
            if (in_array($domeniu, self::DOMENII_SUSPECTE, true)) {
                return 'adresă de email de test sau temporară';
            }
        }

        if (self::numePareGenerat($nume)) {
            return 'nume generat automat';
        }

        // Text alcătuit mai mult din semne decât din cuvinte, cu semnul egal
        // în el: forma tipică a unui payload, nu a unei păreri despre produs.
        $lungime = mb_strlen(trim($text));
        if ($lungime >= 15 && str_contains($text, '=')) {
            $litere = preg_match_all('/\p{L}/u', $text);
            if ($litere !== false && $litere / max(1, $lungime) < 0.5) {
                return 'text fără cuvinte, doar semne';
            }
        }

        return null;
    }

    /**
     * Nume care nu au fost tastate de un om: „xsjyBldb", „fnfOzvSR fnfOzvSR".
     * Semnalele sunt lipsa vocalelor și majusculele împrăștiate prin cuvânt.
     * Numele românești reale au vocale din belșug, deci pragul nu le atinge.
     */
    private static function numePareGenerat(string $nume): bool
    {
        foreach (preg_split('/\s+/', trim($nume)) ?: [] as $bucata) {
            $curat = preg_replace('/[^a-zA-Z]/', '', $bucata) ?? '';
            $lungime = strlen($curat);
            if ($lungime < 6) {
                continue;
            }

            $vocale = preg_match_all('/[aeiouy]/i', $curat);
            if ($vocale === 0) {
                return true;
            }

            // Majuscule apărute după prima literă — un nume scris normal n-are.
            $majusculeInterioare = preg_match_all('/(?<!^)[A-Z]/', $curat);
            if ($vocale / $lungime < 0.3 && $majusculeInterioare >= 2) {
                return true;
            }
        }

        return false;
    }

    /**
     * Trece prin recenziile deja existente și le marchează pe cele care se
     * potrivesc regulilor de conținut. Întoarce câte au fost marcate.
     */
    public static function marcheazaExistente(PDO $db, bool $doarNeaprobate = true): int
    {
        self::ensureSchema($db);

        $unde = 'is_spam = 0';
        if ($doarNeaprobate) {
            $unde .= ' AND is_approved = 0';
        }

        try {
            $randuri = $db->query(
                'SELECT id, user_name, user_email, review_text FROM product_reviews WHERE ' . $unde
            )->fetchAll();
        } catch (Throwable) {
            return 0;
        }

        $marcate = 0;
        $update = $db->prepare('UPDATE product_reviews SET is_spam = 1, spam_reason = :motiv WHERE id = :id');
        foreach ((array) $randuri as $rand) {
            if (!is_array($rand)) {
                continue;
            }
            $motiv = self::motivDinContinut(
                (string) ($rand['user_name'] ?? ''),
                (string) ($rand['review_text'] ?? ''),
                (string) ($rand['user_email'] ?? '')
            );
            if ($motiv === null) {
                continue;
            }
            $update->execute([
                'motiv' => $motiv,
                'id' => (int) ($rand['id'] ?? 0),
            ]);
            $marcate++;
        }

        return $marcate;
    }

    /** Câmpurile ascunse care trebuie puse în orice formular de recenzie. */
    public static function campuriFormular(): string
    {
        return '<input type="text" name="review_website" value="" tabindex="-1" autocomplete="off"'
            . ' aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">'
            . '<input type="hidden" name="review_opened_at" value="' . time() . '">';
    }
}

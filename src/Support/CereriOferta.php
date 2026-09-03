<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use Throwable;

/**
 * Cererile de ofertă venite din sertarul de pe pagina de produs.
 *
 * Site-ul nu vinde, ci primește cereri: în loc de „adaugă în coș", fiecare
 * produs are „solicită mostre și ofertă de preț". Cererea este un client
 * potențial, nu un mesaj, deci se păstrează în baza de date și are stare —
 * un e-mail se pierde, o listă nu.
 *
 * Formularul de contact are deja tabelul lui, „contact_form_messages", dar
 * acolo mesajele nu au produs și nu au stare, iar lista stă sub Emailuri →
 * Newsletter. Cererile de ofertă își primesc deci tabelul și pagina lor.
 */
final class CereriOferta
{
    public const STARI = ['noua', 'in_lucru', 'trimisa', 'inchisa'];

    public const ETICHETE_STARI = [
        'noua' => 'Nouă',
        'in_lucru' => 'În lucru',
        'trimisa' => 'Ofertă trimisă',
        'inchisa' => 'Închisă',
    ];

    /**
     * Creează tabelul dacă lipsește.
     *
     * Numele produsului se păstrează pe cerere, nu doar legătura: un produs
     * șters sau redenumit nu trebuie să lase cererea fără obiect. Adresa IP nu
     * se stochează — ar fi un dat personal în plus, fără folos real aici.
     */
    public static function pregatesteSchema(PDO $db): void
    {
        try {
            $db->exec(
                'CREATE TABLE IF NOT EXISTS quote_requests (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    product_id INT UNSIGNED NULL,
                    product_name VARCHAR(255) NULL,
                    product_slug VARCHAR(255) NULL,
                    name VARCHAR(160) NOT NULL,
                    company VARCHAR(160) NULL,
                    email VARCHAR(190) NOT NULL,
                    phone VARCHAR(60) NULL,
                    message TEXT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT "noua",
                    admin_note TEXT NULL,
                    consent_at DATETIME NULL,
                    source_url VARCHAR(500) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at DATETIME NULL,
                    INDEX idx_quote_status (status),
                    INDEX idx_quote_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable) {
            /* Lipsa tabelului se vede la prima scriere; nu oprim cererea aici. */
        }
    }

    /** @param array<string, mixed> $date */
    public static function adauga(PDO $db, array $date): ?int
    {
        self::pregatesteSchema($db);

        try {
            $stmt = $db->prepare(
                'INSERT INTO quote_requests
                    (product_id, product_name, product_slug, name, company, email, phone,
                     message, consent_at, source_url)
                 VALUES
                    (:product_id, :product_name, :product_slug, :name, :company, :email, :phone,
                     :message, :consent_at, :source_url)'
            );
            $stmt->execute([
                'product_id' => $date['product_id'] ?? null,
                'product_name' => $date['product_name'] ?? null,
                'product_slug' => $date['product_slug'] ?? null,
                'name' => $date['name'],
                'company' => $date['company'] ?? null,
                'email' => $date['email'],
                'phone' => $date['phone'] ?? null,
                'message' => $date['message'] ?? null,
                'consent_at' => $date['consent_at'] ?? null,
                'source_url' => $date['source_url'] ?? null,
            ]);

            return (int) $db->lastInsertId();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function lista(PDO $db, string $stare = '', int $limita = 100): array
    {
        self::pregatesteSchema($db);

        $conditii = ['deleted_at IS NULL'];
        $parametri = [];
        if ($stare !== '' && in_array($stare, self::STARI, true)) {
            $conditii[] = 'status = :status';
            $parametri['status'] = $stare;
        }

        try {
            $stmt = $db->prepare(
                'SELECT * FROM quote_requests
                 WHERE ' . implode(' AND ', $conditii) . '
                 ORDER BY created_at DESC
                 LIMIT ' . max(1, min(500, $limita))
            );
            $stmt->execute($parametri);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<string, int> */
    public static function numarPeStari(PDO $db): array
    {
        self::pregatesteSchema($db);

        $rezultat = array_fill_keys(self::STARI, 0);
        try {
            $stmt = $db->query(
                'SELECT status, COUNT(*) AS n FROM quote_requests
                 WHERE deleted_at IS NULL GROUP BY status'
            );
            foreach ($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $rand) {
                $stare = (string) ($rand['status'] ?? '');
                if (isset($rezultat[$stare])) {
                    $rezultat[$stare] = (int) $rand['n'];
                }
            }
        } catch (Throwable) {
            /* Zerourile de mai sus sunt un răspuns acceptabil. */
        }

        return $rezultat;
    }

    public static function schimbaStarea(PDO $db, int $id, string $stare, string $nota = ''): bool
    {
        if (!in_array($stare, self::STARI, true)) {
            return false;
        }

        self::pregatesteSchema($db);

        try {
            $stmt = $db->prepare(
                'UPDATE quote_requests SET status = :status, admin_note = :nota WHERE id = :id'
            );

            return $stmt->execute([
                'status' => $stare,
                'nota' => $nota !== '' ? $nota : null,
                'id' => $id,
            ]);
        } catch (Throwable) {
            return false;
        }
    }

    public static function sterge(PDO $db, int $id): bool
    {
        self::pregatesteSchema($db);

        try {
            return $db->prepare('UPDATE quote_requests SET deleted_at = NOW() WHERE id = :id')
                ->execute(['id' => $id]);
        } catch (Throwable) {
            return false;
        }
    }
}

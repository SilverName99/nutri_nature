-- Date inițiale pentru grafoanaytis.ro
--
-- Fișierul conține doar structura de care are nevoie un site de prezentare.
-- Datele demo din proiectul anterior — categoriile „Uleiuri / Miere /
-- Suplimente", cele trei produse și pagina „Despre noi" cu text de exemplu —
-- au fost eliminate: erau ale altui client și, în cazul paginii, intrau în
-- conflict cu varianta reală încărcată de scripts/seed-pagini.php.
--
-- Fiecare inserare rulează o singură dată, la prima instalare.

INSERT INTO gallery_folders (name, slug)
SELECT seed.name, seed.slug
FROM (
    SELECT 'General' AS name, 'general' AS slug
    UNION ALL
    SELECT 'Utilaje' AS name, 'utilaje' AS slug
    UNION ALL
    SELECT 'Produse' AS name, 'produse' AS slug
    UNION ALL
    SELECT 'Certificări' AS name, 'certificari' AS slug
    UNION ALL
    SELECT 'Video' AS name, 'video' AS slug
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM gallery_folders LIMIT 1);

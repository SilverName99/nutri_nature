-- Date inițiale pentru NutriNature
--
-- Doar folderele de galerie, ca imaginile clientului să aibă unde fi puse.
-- Numele lor sunt cele ale acestui proiect, nu cele moștenite din tipografie.
--
-- Structura tabelelor și contul de administrator le face scripts/install.php.
--
-- Fiecare inserare rulează o singură dată, la prima instalare.

INSERT INTO gallery_folders (name, slug)
SELECT seed.name, seed.slug
FROM (
    SELECT 'General' AS name, 'general' AS slug
    UNION ALL
    SELECT 'Centru' AS name, 'centru' AS slug
    UNION ALL
    SELECT 'Servicii' AS name, 'servicii' AS slug
    UNION ALL
    SELECT 'Echipă' AS name, 'echipa' AS slug
    UNION ALL
    SELECT 'Video' AS name, 'video' AS slug
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM gallery_folders LIMIT 1);

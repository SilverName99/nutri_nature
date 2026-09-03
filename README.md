# NutriNature — site de prezentare

Site pentru **NutriNature, centru de medicină integrativă**: nutriție
personalizată, biorezonanță, terapii complementare, chiropractică și masaj,
medicină tradițională, psihonutriție, consiliere.

Este un **site de prezentare**, nu un magazin. Nu se vinde nimic online și nu
se ține un calendar de programări: vizitatorul sună sau scrie pe WhatsApp.

## De unde vine codul

Aplicația este aceeași platformă PHP folosită la `Site-nou-grafoanaytis.ro`
(la rândul ei pornită dintr-un magazin online). De acolo vin, gata făcute:

- dashboard-ul (`/admin`): pagini, galerie, blog, e-mailuri, utilizatori;
- editorul de Design Site (antet, meniu, subsol);
- modulul GDPR și formularul de contact;
- **modul prezentare**, care închide cu 404 rutele de vânzare (`/cos`,
  `/checkout`, `/contul-meu`). Codul de magazin rămâne în proiect, dar nu este
  accesibil. Dacă vreodată se dorește vânzare online, se stinge comutatorul.

Ce **nu** s-a adus de la proiectul anterior: paginile, produsele, imaginile și
datele de contact ale celuilalt client.

## Identitatea vizuală

Culorile nu sunt alese, ci **măsurate din sigla trimisă de client**:

| rol | culoare | contrast |
|---|---|---|
| verde de brand | `#183018` | 14,24:1 pe alb · 12,26:1 pe crem |
| auriu de accent | `#B48430` | 4,26:1 pe verde — decor, **nu text** |
| auriu de text | `#7D5C21` | 6,13:1 pe alb · 5,28:1 pe crem |
| crem de fundal | `#F6EDDE` | — |

Capcana paletei: auriul siglei dă **2,88:1 pe crem**, deci nu poate fi culoare
de text. Umplerea, accentul și textul sunt roluri separate în
`public/assets/css/tokens.css`, cu contrastele scrise lângă fiecare.

Fonturi: **Cormorant Garamond** la titluri (seria siglei) și **Lato** la text.
Amândouă servite de pe serverul nostru — nicio cerere către Google, deci nicio
adresă IP de vizitator trimisă în afară.

## Instalare locală

```bash
cp .env.example .env          # completează datele bazei
mysql -u root nutrinature < database/schema.sql
mysql -u root nutrinature < database/seed.sql
php scripts/seed-design.php   # antet, meniu, subsol
php scripts/seed-pagini.php   # paginile din database/pagini/
php -S 127.0.0.1:8080 -t public
```

`.env` nu intră niciodată în git.

## Structura conținutului

Paginile se scriu ca fișiere în `database/pagini/` (`nume.html`, plus `.css` și
`.js` opționale) și se încarcă în baza de date cu `scripts/seed-pagini.php`.
Numele fișierului devine slug: `__` se traduce în `/`, deci
`servicii__biorezonanta.html` ajunge la `/servicii/biorezonanta`.

Seed-ul **nu suprascrie** ce a fost editat din dashboard. Pentru a forța:

```bash
php scripts/seed-pagini.php --suprascrie --doar=acasa
```

## Ce lipsește

`scripts/lipsuri.php` scoate lista locurilor marcate `[DE COMPLETAT]` și a
fișierelor care nu există încă, iar `scripts/document-client.js` o transformă
în documentul de completat pentru client:

```bash
php scripts/lipsuri.php
php scripts/lipsuri.php --json > lipsuri.json
node scripts/document-client.js lipsuri.json NutriNature-de-completat.docx
```

## Materialele clientului

`poze de referinta/` conține cele 9 afișe primite pe WhatsApp. Sunt materiale
de rețele sociale (format portret, text lipit în imagine), deci **nu se pot
folosi ca imagini de site** — dar sunt sursa textelor: fiecare afiș descrie un
serviciu, cu beneficii și public-țintă, scrise chiar de client.

Pentru site avem nevoie de fotografii reale din centru și de siglă separat, pe
fundal transparent.

## O notă despre formulări

Serviciile sunt terapii complementare. Textele evită deliberat verbe de tip
„diagnostichează", „tratează", „vindecă", iar în subsol există o notă că
acestea nu înlocuiesc consultul și tratamentul medical. Nu este mărunțiș
juridic: fără ea, o pagină care vorbește despre „identificarea dezechilibrelor"
poate fi citită ca promisiune de diagnostic.

/*
 * Documentul de completat, trimis clientului.
 *
 * Se scrie din inventarul de goluri, nu de mână: dacă apare un marcaj nou în
 * pagini, apare și în document, fără să-l țină minte nimeni.
 *
 *   php scripts/lipsuri.php --json > lipsuri.json
 *   npm install docx && node scripts/document-client.js
 *
 * Rulat de pe altă mașină decât serverul, inventarul are nevoie de lista
 * fișierelor care există acolo — vezi --existente în scripts/lipsuri.php.
 */
const fs = require('fs');
const {
  Document, Packer, Paragraph, TextRun, HeadingLevel, AlignmentType,
  Table, TableRow, TableCell, WidthType, ShadingType, BorderStyle, PageBreak,
} = require('docx');

const caleIntrare = process.argv[2] || 'lipsuri.json';
const caleIesire = process.argv[3] || 'de-completat.docx';

/*
 * Numele firmei și domeniul vin din linia de comandă, nu scrise în cod.
 *
 * Scriptul a fost folosit întâi la un singur client, deci numele lui era bătut
 * în trei locuri. La al doilea client, documentul a ieșit cu antetul primului —
 * exact felul de greșeală pe care o vezi abia după ce ai trimis fișierul.
 *
 *   node scripts/document-client.js lipsuri.json iesire.docx "NutriNature" nutrinature.ro
 */
const marca = process.argv[4] || 'Site';
const domeniu = process.argv[5] || '';
const date = JSON.parse(fs.readFileSync(caleIntrare, 'utf8'));

const PORTOCALIU = 'FFB877';
const INCHIS = '1E1A17';
const GRI = 'F3F4F6';
const LATIME = 9360;            // lățimea utilă a paginii A4, în DXA

const p = (text, opt = {}) => new Paragraph({
  spacing: { after: opt.after ?? 120 },
  alignment: opt.alignment,
  children: [new TextRun({ text, bold: opt.bold, size: opt.size ?? 22, color: opt.color, italics: opt.italics })],
});

const celula = (copii, opt = {}) => new TableCell({
  width: { size: opt.latime, type: WidthType.DXA },
  shading: opt.fundal ? { type: ShadingType.CLEAR, fill: opt.fundal, color: 'auto' } : undefined,
  margins: { top: 100, bottom: 100, left: 120, right: 120 },
  children: copii,
});

/* Un rând de completat: ce lipsește | unde | loc de scris. */
function randDeCompletat(unde, ce, exemplu, alteLocuri) {
  return new TableRow({
    /*
     * Un rând nu se rupe între pagini. Fără asta, la salt rămânea pe pagina
     * următoare doar linia de explicație, iar coloanele „Secțiunea" și
     * numele fișierului apăreau goale — arăta a document stricat.
     */
    cantSplit: true,
    children: [
      celula([
        p(unde, { bold: true, size: 20, after: alteLocuri ? 40 : 0 }),
        /*
         * Cât timp același lucru se cere o singură dată, omul trebuie totuși să
         * știe unde se va vedea răspunsul lui — altfel pare că e doar pentru
         * secțiunea din stânga.
         */
        ...(alteLocuri
          ? [p(alteLocuri === 1
                ? 'apare și într-un alt loc pe site'
                : 'apare și în alte ' + alteLocuri + ' locuri pe site',
                { size: 16, italics: true, color: '6B7280', after: 0 })]
          : []),
      ], { latime: 2400 }),
      celula([
        p(ce, { size: 20, after: exemplu ? 60 : 0 }),
        ...(exemplu ? [p(exemplu, { size: 18, italics: true, color: '6B7280', after: 0 })] : []),
      ], { latime: 3600 }),
      celula([p('', { size: 20 }), p('', { size: 20 }), p('', { size: 20 })], { latime: 3360, fundal: 'FFFFFF' }),
    ],
  });
}

/*
 * Rândurile de text, cu anii lipiți de evenimentul lor.
 *
 * În cronologie fiecare oprire este un an plus o descriere, deci în pagină sunt
 * două marcaje separate. Lăsate așa, clientul primea un rând „anul" și, sub el,
 * un rând cu ce s-a întâmplat, fără să fie limpede că merg împreună.
 */
function perechiDeCompletat(texte) {
  const randuri = [];

  for (let i = 0; i < texte.length; i++) {
    const t = texte[i];
    const curat = t.marcaj.replace(/^DE COMPLETAT:?\s*/i, '');
    const ce = curat === 'DE COMPLETAT' || curat === ''
      ? 'text liber'
      : (t.marcaj === 'AN' ? 'anul' : (t.marcaj === 'N' ? 'un număr' : curat));

    const urmatorul = texte[i + 1];
    if (t.marcaj === 'AN' && urmatorul && urmatorul.sectiune === t.sectiune && urmatorul.marcaj !== 'AN') {
      const descriere = urmatorul.marcaj.replace(/^DE COMPLETAT:?\s*/i, '');
      randuri.push(randDeCompletat(t.sectiune, 'anul și ce s-a întâmplat atunci', 'Sugestia noastră: ' + descriere));
      i++;
      continue;
    }

    randuri.push(randDeCompletat(t.sectiune, ce, null, (t.alte_locuri || []).length));
  }

  return randuri;
}

function capDeTabel(a, b, c) {
  return new TableRow({
    tableHeader: true,
    cantSplit: true,
    children: [
      celula([p(a, { bold: true, size: 20, after: 0 })], { latime: 2400, fundal: PORTOCALIU }),
      celula([p(b, { bold: true, size: 20, after: 0 })], { latime: 3600, fundal: PORTOCALIU }),
      celula([p(c, { bold: true, size: 20, after: 0 })], { latime: 3360, fundal: PORTOCALIU }),
    ],
  });
}

const tabel = (randuri) => new Table({
  columnWidths: [2400, 3600, 3360],
  width: { size: LATIME, type: WidthType.DXA },
  rows: randuri,
});

const copii = [];

/* ── Coperta ──────────────────────────────────────────────────────── */
copii.push(
  new Paragraph({
    spacing: { after: 80 },
    border: { bottom: { style: BorderStyle.SINGLE, size: 18, color: PORTOCALIU } },
    children: [new TextRun({ text: marca.toUpperCase(), bold: true, size: 44, color: INCHIS })],
  }),
  p('Ce mai avem nevoie pentru site', { size: 32, bold: true, after: 240 }),
  p('Site-ul nou este construit și funcționează. Mai jos sunt locurile unde am lăsat spațiu pentru textele și fotografiile dumneavoastră. Nu trebuie completate toate deodată — fiecare rând completat intră pe site.', { after: 160 }),
  p('Cum se completează:', { bold: true, after: 80 }),
  p('•  Textele: scrieți direct în coloana din dreapta, „Ce scriem pe site”.', { after: 60 }),
  p('•  Fotografiile: trimiteți fișierele cu numele din coloana din mijloc. Dacă preferați alte nume, scrieți în dreapta cum se numește fișierul pe care îl trimiteți.', { after: 60 }),
  p('•  Fotografiile se trimit la rezoluție mare, așa cum ies din aparat; ne ocupăm noi de micșorare și optimizare.', { after: 240 }),
);

const totalTexte = date.reduce((s, x) => s + x.texte.length, 0);
/*
 * Fișierele se numără o singură dată, chiar dacă apar pe mai multe pagini:
 * aceeași fotografie de utilaj stă și pe „Acasă", și pe „Utilaje". Numărate ca
 * rânduri, clientul credea că are de trimis 21 de poze în loc de 16.
 */
const totalFisiere = new Set(
  date.flatMap((x) => x.fisiere.map((f) => f.fisier))
).size;
copii.push(
  tabel([
    capDeTabel('Ce lipsește', 'Câte', 'Unde'),
    new TableRow({ cantSplit: true, children: [
      celula([p('Texte de scris', { size: 20, after: 0 })], { latime: 2400 }),
      celula([p(String(totalTexte), { size: 20, after: 0 })], { latime: 3600 }),
      celula([p('pe ' + date.filter(x => x.texte.length).length + ' pagini', { size: 20, after: 0 })], { latime: 3360 }),
    ] }),
    new TableRow({ cantSplit: true, children: [
      celula([p('Fotografii de trimis', { size: 20, after: 0 })], { latime: 2400 }),
      celula([p(String(totalFisiere), { size: 20, after: 0 })], { latime: 3600 }),
      celula([p('pe ' + date.filter(x => x.fisiere.length).length + ' pagini', { size: 20, after: 0 })], { latime: 3360 }),
    ] }),
  ]),
  new Paragraph({ children: [new PageBreak()] }),
);

/* ── Câte o secțiune pentru fiecare pagină ────────────────────────── */
date.forEach((pagina, i) => {
  if (i > 0) copii.push(new Paragraph({ children: [new PageBreak()] }));

  copii.push(
    new Paragraph({
      heading: HeadingLevel.HEADING_1,
      spacing: { after: 60 },
      children: [new TextRun({ text: pagina.titlu, bold: true, size: 30, color: INCHIS })],
    }),
    p(domeniu + pagina.slug, { size: 18, color: '6B7280', after: 200 }),
  );

  if (pagina.texte.length) {
    copii.push(p('Texte', { bold: true, size: 24, after: 120 }));
    copii.push(tabel([
      capDeTabel('Secțiunea', 'Ce ne trebuie', 'Ce scriem pe site'),
      ...perechiDeCompletat(pagina.texte),
    ]));
    copii.push(p('', { after: 200 }));
  }

  if (pagina.fisiere.length) {
    copii.push(p('Fotografii și filme', { bold: true, size: 24, after: 120 }));
    copii.push(tabel([
      capDeTabel('Secțiunea', 'Numele fișierului și ce arată', 'Cum se numește fișierul trimis'),
      ...pagina.fisiere.map(f => randDeCompletat(
        f.sectiune,
        f.fisier,
        f.descriere ? 'Arată: ' + f.descriere : null,
        (f.alte_locuri || []).length,
      )),
    ]));
  }
});

/* ── Ultima pagină: altceva ───────────────────────────────────────── */
copii.push(
  new Paragraph({ children: [new PageBreak()] }),
  new Paragraph({
    heading: HeadingLevel.HEADING_1,
    spacing: { after: 200 },
    children: [new TextRun({ text: 'Altceva', bold: true, size: 30, color: INCHIS })],
  }),
  p('Lucruri care nu apar în tabelele de mai sus, dar de care avem nevoie:', { after: 160 }),
  tabel([
    capDeTabel('Ce', 'Detalii', 'Răspuns'),
    randDeCompletat('Telefoane', 'Cui aparțin numerele 0722 374 275, 0723 256 946 și 0746 244 067 — Cornel Moise, Sorin Dumitrașcu, Cristina Moise?', null),
    randDeCompletat('Clienți', 'Câți clienți ați deservit până acum? Cifra apare pe prima pagină.', null),
    randDeCompletat('Sigle clienți', 'Trei sigle nu au fost identificate: două steme de oraș și o episcopie ortodoxă. Care sunt?', null),
    randDeCompletat('Certificări', 'Aveți și alte certificări în afară de AFAS și ISCIR?', null),
    randDeCompletat('Program', 'Programul de lucru este Luni–Vineri, 08:00–16:00. Este corect?', null),
  ]),
  p('', { after: 240 }),
  p('Mulțumim. Orice ne trimiteți intră pe site în aceeași zi.', { italics: true, color: '6B7280' }),
);

const doc = new Document({
  creator: 'Andaxi Web Solutions',
  title: marca + ' — ce mai avem nevoie pentru site',
  styles: { default: { document: { run: { font: 'Calibri', size: 22 } } } },
  sections: [{
    properties: { page: { margin: { top: 1000, right: 1000, bottom: 1000, left: 1000 } } },
    children: copii,
  }],
});

Packer.toBuffer(doc).then(b => {
  fs.writeFileSync(caleIesire, b);
  console.log('scris: ' + caleIesire + ' (' + b.length + ' octeți)');
});

#!/usr/bin/env node
/**
 * Fahrstuhl — neues Dashboard für Admins scharf schalten.
 *
 * Ändert drei Dinge:
 *   1. Nach dem Login landen Admins auf /ui/ statt /pages/cockpit.php
 *      (normale User bleiben unverändert auf portal.php)
 *   2. Der Link "Altes Dashboard" im neuen Menü führt auf das alte Cockpit
 *      statt auf die Startseite — sonst wäre er eine Sackgasse.
 *   3. Das alte Menü bekommt einen Eintrag zurück zum neuen Dashboard.
 *
 * Textmarken statt Zeilennummern, alles-oder-nichts pro Datei, .bak-Sicherung.
 * Mehrfach ausführen ist ungefährlich.
 *
 *   cd /home/marvin/fahrstuhl && node dashboard-umschalten.js
 *   Rückgängig:  node dashboard-umschalten.js --zurueck
 */
const fs = require('fs');
const path = require('path');

const UNDO = process.argv.includes('--zurueck');
const ROOT = process.cwd();
const P = f => path.join(ROOT, 'dashboard/public', f);

const INDEX  = P('index.php');
const LAYOUT = P('ui/_layout.php');
const SIDE   = P('includes/sidebar.php');

for (const f of [INDEX, LAYOUT, SIDE]) {
    if (!fs.existsSync(f)) {
        console.error(`? ${path.relative(ROOT, f)} nicht gefunden.`);
        console.error('  Wechsle vorher ins Repo:  cd /home/marvin/fahrstuhl');
        process.exit(1);
    }
}

const NEW_ENTRY = `['page' => '__ui__', 'icon' => '?', 'label' => 'Neues Dashboard', 'description' => 'Aufgeräumte Oberfläche', 'href' => BASE_URL . '/ui/'],\n        `;

const CHANGES = [
    {
        file: INDEX,
        name: 'Admins nach dem Login auf /ui/ leiten',
        from: "(isAdmin() ? '/pages/cockpit.php' : '/pages/portal.php')",
        to:   "(isAdmin() ? '/ui/' : '/pages/portal.php')",
        all: true,
    },
    {
        file: LAYOUT,
        name: '"Altes Dashboard" auf das alte Cockpit zeigen lassen',
        from: `<a class="rail-item" href="<?= BASE_URL ?>/index.php"><span class="rail-icon" aria-hidden="true">?</span> Altes Dashboard</a>`,
        to:   `<a class="rail-item" href="<?= BASE_URL ?>/pages/cockpit.php"><span class="rail-icon" aria-hidden="true">?</span> Altes Dashboard</a>`,
    },
    {
        file: SIDE,
        name: 'Link zum neuen Dashboard ins alte Menü',
        from: `['page' => 'cockpit', 'icon' => '???',`,
        to:   NEW_ENTRY + `['page' => 'cockpit', 'icon' => '???',`,
        // Der neue Text ENTHÄLT den alten — "schon angewendet?" lässt sich hier
        // nicht aus from/to ableiten, sonst verdoppelt jeder Lauf den Eintrag.
        marker: `'label' => 'Neues Dashboard'`,
    },
];

const byFile = new Map();
for (const c of CHANGES) {
    if (!byFile.has(c.file)) byFile.set(c.file, []);
    byFile.get(c.file).push(c);
}

const report = [];
let failed = false;

for (const [file, changes] of byFile) {
    const label = path.relative(ROOT, file);
    let src = fs.readFileSync(file, 'utf8');
    const before = src;
    let fileFailed = false;

    for (const c of changes) {
        const from = UNDO ? c.to : c.from;
        const to   = UNDO ? c.from : c.to;

        // "Ist der Schritt schon erledigt?" — über einen eindeutigen Marker,
        // sonst über das Zielstück selbst.
        const applied = c.marker ? src.includes(c.marker) : (src.includes(c.to) && !src.includes(c.from));
        if (UNDO ? !applied : applied) {
            report.push(['skip', label, c.name]);
            continue;
        }
        if (!src.includes(from)) {
            report.push(['FAIL', label, c.name]);
            failed = true; fileFailed = true;
            continue;
        }
        src = c.all ? src.split(from).join(to) : src.replace(from, to);
        report.push(['ok', label, c.name]);
    }

    if (fileFailed) {
        console.log(`? ${label} NICHT verändert (ein Schritt schlug fehl).`);
        continue;
    }
    if (src !== before) {
        fs.copyFileSync(file, file + '.bak');
        fs.writeFileSync(file, src);
        console.log(`? ${label} geschrieben (Sicherung: ${label}.bak)`);
    }
}

console.log('\n=== Ergebnis ===');
for (const [s, f, n] of report) {
    console.log(`${s === 'ok' ? '?' : s === 'skip' ? '·' : '?'} ${f}: ${n}${s === 'skip' ? ' (war schon so)' : ''}`);
}

if (failed) {
    console.log('\n? Textmarke nicht gefunden — schick mir die Ausgabe, dann passe ich es an.');
    process.exit(2);
}

console.log(UNDO ? '\n? Zurückgesetzt. Jetzt:' : '\n? Umgeschaltet. Jetzt:');
console.log('    cd ' + ROOT + ' && docker compose up -d --build dashboard-php');
console.log('  (docker restart reicht NICHT — der PHP-Code steckt im Image.)');
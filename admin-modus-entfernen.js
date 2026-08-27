#!/usr/bin/env node
/**
 * Fahrstuhl — Admin-Modus entfernen.
 *
 * Danach verhält sich das Dashboard für dein Konto wie für jeden anderen User.
 *
 * Drei Eingriffe:
 *   1. isAdmin() gibt immer false zurück
 *   2. Der "Normal View" / "Admin Mode"-Umschalter verschwindet aus der Kopfzeile
 *   3. Die 32 reinen Admin-Seiten und /ui/ wandern aus dem Web-Verzeichnis
 *
 * WICHTIG: Punkt 3 VERSCHIEBT, es löscht nicht. Alles landet in
 * dashboard/_admin-entfernt/. Von der Website aus ist damit nichts mehr
 * erreichbar. Willst du es endgültig weghaben:
 *     rm -rf dashboard/_admin-entfernt
 *
 * Komplett rückgängig:  node admin-modus-entfernen.js --zurueck
 */
const fs = require('fs');
const path = require('path');

const UNDO = process.argv.includes('--zurueck');
const ROOT = process.cwd();
const PUB  = path.join(ROOT, 'dashboard/public');
const ATTIC = path.join(ROOT, 'dashboard/_admin-entfernt');

if (!fs.existsSync(PUB)) {
    console.error('? dashboard/public nicht gefunden.');
    console.error('  Wechsle vorher ins Repo:  cd /home/marvin/fahrstuhl');
    process.exit(1);
}

const ADMIN_PAGES = [
    'analytics.php','audit.php','backups.php','blacklist.php','cockpit.php','console.php',
    'deploys.php','eselmusic.php','flags.php','fun-hub.php','guild-premium-api.php',
    'guild-premium.php','guilds.php','logs.php','members-hub.php','monetization-health.php',
    'monetization.php','operations.php','ops-health.php','premium-api.php','premium-hub.php',
    'premium.php','rewards-hub.php','security.php','shield-api.php','status.php','tools.php',
    'ueberwachung.php','user-detail.php','users.php','voicetroll.php','webhooks.php',
];

const OLD_ISADMIN = `function isAdmin() {
    return isOwner() && dashboardViewMode() === 'admin';
}`;

const NEW_ISADMIN = `// Admin-Modus entfernt — das Dashboard verhält sich für jedes Konto gleich.
// Wiederherstellen mit:  node admin-modus-entfernen.js --zurueck
function isAdmin() {
    return false;
}`;

const OLD_TOGGLE = `            <?php if (isOwner()): ?>
                <?php if (isAdmin()): ?>
                    <a href="<?= BASE_URL ?>/pages/portal.php?view_mode=user" class="btn-view-mode">Normal View</a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/pages/cockpit.php?view_mode=admin" class="btn-view-mode">Admin Mode</a>
                <?php endif; ?>
            <?php endif; ?>
`;

const NEW_TOGGLE = `            <?php /* Umschalter Admin/Normal entfernt — siehe admin-modus-entfernen.js */ ?>
`;

const EDITS = [
    { file: path.join(PUB, 'includes/config.php'), name: 'isAdmin() dauerhaft auf false', from: OLD_ISADMIN, to: NEW_ISADMIN },
    { file: path.join(PUB, 'includes/header.php'), name: 'Umschalter aus der Kopfzeile', from: OLD_TOGGLE, to: NEW_TOGGLE },
];

let failed = false;
const report = [];

// -- Textänderungen -----------------------------------------------------------
for (const e of EDITS) {
    const label = path.relative(ROOT, e.file);
    if (!fs.existsSync(e.file)) { report.push(['FAIL', label, e.name + ' (Datei fehlt)']); failed = true; continue; }
    let src = fs.readFileSync(e.file, 'utf8');
    const from = UNDO ? e.to : e.from;
    const to   = UNDO ? e.from : e.to;

    if (src.includes(to) && !src.includes(from)) { report.push(['skip', label, e.name]); continue; }
    if (!src.includes(from)) { report.push(['FAIL', label, e.name]); failed = true; continue; }

    fs.copyFileSync(e.file, e.file + '.bak');
    fs.writeFileSync(e.file, src.replace(from, to));
    report.push(['ok', label, e.name]);
}

if (failed) {
    console.log('=== Ergebnis ===');
    for (const [s, f, n] of report) console.log(`${s === 'ok' ? '?' : s === 'skip' ? '·' : '?'} ${f}: ${n}`);
    console.log('\n? Textmarke nicht gefunden. Es wurden KEINE Dateien verschoben.');
    console.log('  Schick mir die Ausgabe, dann passe ich es an.');
    process.exit(2);
}

// -- Dateien verschieben ------------------------------------------------------
function move(src, dst) {
    fs.mkdirSync(path.dirname(dst), { recursive: true });
    fs.renameSync(src, dst);
}

let moved = 0, skipped = 0;

if (!UNDO) {
    for (const p of ADMIN_PAGES) {
        const src = path.join(PUB, 'pages', p);
        if (!fs.existsSync(src)) { skipped++; continue; }
        move(src, path.join(ATTIC, 'pages', p));
        moved++;
    }
    const uiSrc = path.join(PUB, 'ui');
    if (fs.existsSync(uiSrc)) { move(uiSrc, path.join(ATTIC, 'ui')); moved++; }
} else {
    const atticPages = path.join(ATTIC, 'pages');
    if (fs.existsSync(atticPages)) {
        for (const p of fs.readdirSync(atticPages)) {
            move(path.join(atticPages, p), path.join(PUB, 'pages', p));
            moved++;
        }
        fs.rmdirSync(atticPages);
    }
    const uiAttic = path.join(ATTIC, 'ui');
    if (fs.existsSync(uiAttic)) { move(uiAttic, path.join(PUB, 'ui')); moved++; }
    if (fs.existsSync(ATTIC) && fs.readdirSync(ATTIC).length === 0) fs.rmdirSync(ATTIC);
}

console.log('=== Ergebnis ===');
for (const [s, f, n] of report) console.log(`${s === 'ok' ? '?' : s === 'skip' ? '·' : '?'} ${f}: ${n}`);
console.log(`${UNDO ? '?' : '?'} ${moved} Einträge ${UNDO ? 'zurückgeholt' : 'nach dashboard/_admin-entfernt/ verschoben'}${skipped ? ` (${skipped} waren schon weg)` : ''}`);

console.log(UNDO ? '\n? Admin-Modus wiederhergestellt. Jetzt:' : '\n? Admin-Modus entfernt. Jetzt:');
console.log('    cd ' + ROOT + ' && docker compose up -d --build dashboard-php');
console.log('  (docker restart reicht NICHT — der PHP-Code steckt im Image.)');
if (!UNDO) {
    console.log('\n  Die Seiten liegen in dashboard/_admin-entfernt/ — nicht gelöscht.');
    console.log('  Endgültig weg:  rm -rf ' + path.relative(ROOT, ATTIC));
    console.log('  Alles zurück:   node admin-modus-entfernen.js --zurueck');
}
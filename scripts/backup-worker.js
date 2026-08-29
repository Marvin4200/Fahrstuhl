#!/usr/bin/env node
/* eslint-disable no-console */

const { runBackup } = require('./backup-all');


// ── Admin-Log ─────────────────────────────────────────────────────────────────
// Faengt praktisch alles ab: nicht nur Abstuerze, sondern auch die vielen
// try/catch-Stellen im Code, die einen Fehler bisher nur lokal geloggt haben.
// console.error/console.warn werden global umgeleitet - jeder Aufruf,
// egal wo im Prozess, geht jetzt zusaetzlich an admin.eselbande.com.
const ADMIN_LOG_URL = (process.env.ADMIN_LOG_URL || '').replace(/\/+$/, '');
const LOG_INGEST_TOKEN = process.env.LOG_INGEST_TOKEN || '';

// Ratenbegrenzung: waehrend eines Fehlersturms (z.B. eine haengende
// Verbindung, die minuetlich denselben Fehler wirft) soll admin-dashboard
// nicht mit hunderten Anfragen pro Minute geflutet werden. Token-Bucket:
// 30 Log-Sendungen sofort verfuegbar, danach eine neue alle 2 Sekunden.
let _logTokens = 30;
setInterval(() => { _logTokens = Math.min(30, _logTokens + 1); }, 2000);
let _logSuppressedSince = 0;

async function logAdmin(type, title, description, color, fields) {
    if (!ADMIN_LOG_URL || !LOG_INGEST_TOKEN) return;
    if (_logTokens <= 0) { _logSuppressedSince++; return; }
    _logTokens--;
    if (_logSuppressedSince > 0) {
        const n = _logSuppressedSince;
        _logSuppressedSince = 0;
        logAdmin('SYSTEM', '\u{1F507} Logs gedrosselt', `${n} weitere Meldungen in kurzer Zeit wurden nicht einzeln gesendet (Ratenbegrenzung).`, 0xF59E0B);
    }
    try {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 5000);
        await fetch(`${ADMIN_LOG_URL}/api/logs/ingest`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Log-Token': LOG_INGEST_TOKEN },
            body: JSON.stringify({ source: 'backup-worker', type, title, description, color, fields }),
            signal: controller.signal,
        }).catch(() => {});
        clearTimeout(timer);
    } catch { /* ein Log-Sendefehler darf den Dienst selbst nie beeintraechtigen */ }
}

function _fmtConsoleArgs(args) {
    return args.map(a => {
        if (a instanceof Error) return a.stack || a.message;
        if (a && typeof a === 'object') { try { return JSON.stringify(a); } catch { return String(a); } }
        return String(a);
    }).join(' ').slice(0, 4000);
}

const _origConsoleError = console.error.bind(console);
const _origConsoleWarn = console.warn.bind(console);
console.error = (...args) => {
    _origConsoleError(...args);
    logAdmin('ERRORS', '\u{26A0}\u{FE0F} Fehler', _fmtConsoleArgs(args), 0xED4245);
};
console.warn = (...args) => {
    _origConsoleWarn(...args);
    logAdmin('WARNINGS', '\u{26A0}\u{FE0F} Warnung', _fmtConsoleArgs(args), 0xF59E0B);
};

process.on('uncaughtException', (err) => {
    console.error('[uncaughtException]', err);
});
process.on('unhandledRejection', (reason) => {
    console.error('[unhandledRejection]', reason);
});
logAdmin('SYSTEM', '\u{1F680} backup-worker gestartet', `Prozess laeuft, PID ${process.pid}.`, 0x57F287);



const intervalMinutes = Math.max(5, Number(process.env.BACKUP_INTERVAL_MINUTES || 60));
const runOnStart = String(process.env.BACKUP_RUN_ON_START || 'true').toLowerCase() !== 'false';

let running = false;

async function tick(reason) {
    if (running) return;
    running = true;
    try {
        const status = runBackup();
        console.log(`[backup-worker] ${reason}: snapshot=${status.snapshot} ok=${status.ok} copied=${status.copied}`);
    } catch (error) {
        console.error(`[backup-worker] ${reason} failed:`, error.message);
    } finally {
        running = false;
    }
}

if (runOnStart) {
    tick('startup').catch(() => {});
}

setInterval(() => {
    tick('interval').catch(() => {});
}, intervalMinutes * 60 * 1000);

console.log(`[backup-worker] started (interval=${intervalMinutes}m, runOnStart=${runOnStart})`);

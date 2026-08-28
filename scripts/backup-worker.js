#!/usr/bin/env node
/* eslint-disable no-console */

const { runBackup } = require('./backup-all');


// ── Admin-Log ─────────────────────────────────────────────────────────────────
const ADMIN_LOG_URL = (process.env.ADMIN_LOG_URL || '').replace(/\/+$/, '');
const LOG_INGEST_TOKEN = process.env.LOG_INGEST_TOKEN || '';

async function logAdmin(type, title, description, color, fields) {
    if (!ADMIN_LOG_URL || !LOG_INGEST_TOKEN) return;
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
    } catch { /* siehe oben */ }
}

process.on('uncaughtException', (err) => {
    console.error('[uncaughtException]', err);
    logAdmin('ERRORS', '\u{1F4A5} Uncaught Exception', `${err?.message || err}\n\`\`\`${String(err?.stack || '').slice(0, 1500)}\`\`\``, 0xED4245);
});
process.on('unhandledRejection', (reason) => {
    console.error('[unhandledRejection]', reason);
    logAdmin('ERRORS', '\u{1F4A5} Unhandled Rejection', String(reason?.stack || reason).slice(0, 1500), 0xED4245);
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

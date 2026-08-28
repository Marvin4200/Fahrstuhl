'use strict';

/**
 * GitHub Webhook Auto-Deploy for musikbot + website.
 * Same hardening pattern as deploy-webhook.js (lock, delivery dedup,
 * dirty-tree guard, health-gated restart with rollback, Discord notify),
 * generalized over multiple named targets instead of a single one.
 */

const fs = require('fs');
const http = require('http');
const https = require('https');
const crypto = require('crypto');
const path = require('path');
const { execFileSync } = require('child_process');
require('dotenv').config({ path: path.join(__dirname, '../.env') });


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
            body: JSON.stringify({ source: 'deploy-extra', type, title, description, color, fields }),
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
logAdmin('SYSTEM', '\u{1F680} deploy-extra gestartet', `Prozess laeuft, PID ${process.pid}.`, 0x57F287);


const PORT = parseInt(process.env.EXTRA_DEPLOY_PORT || '9012', 10);
const DATA_DIR = path.join(__dirname, '..', 'data');
const MAX_BODY_BYTES = 1024 * 1024;
const DISCORD_DEPLOY_WEBHOOK = process.env.DISCORD_DEPLOY_WEBHOOK_URL || '';
const COMPOSE_FILE = process.env.COMPOSE_FILE || '/home/marvin/fahrstuhl/docker-compose.yml';

const TARGETS = {
  musikbot: {
    name: 'musikbot',
    secret: process.env.SECRET_MUSIKBOT || '',
    repoDir: '/home/marvin/musikbot',
    branch: 'master',
    build: true,
    composeService: 'musikbot-docker',
    healthUrl: 'http://musikbot-docker:3020/health',
    healthRetries: 10,
    healthDelayMs: 3000,
  },
  website: {
    name: 'website',
    secret: process.env.SECRET_WEBSITE || '',
    repoDir: '/home/marvin',
    branch: 'main',
    build: false,
  },
  esel: {
    name: 'esel',
    secret: process.env.SECRET_ESEL || '',
    repoDir: '/home/marvin/esel',
    branch: 'master',
    build: true,
    composeService: 'esel',
    healthUrl: 'http://esel:3015/health',
    healthRetries: 10,
    healthDelayMs: 3000,
  },
  '404': {
    name: '404',
    secret: process.env.SECRET_404 || '',
    repoDir: '/home/marvin/404',
    branch: 'master',
    build: false,
  },
};

for (const t of Object.values(TARGETS)) {
  if (!t.secret) console.warn(`[deploy-extra] WARNUNG: kein Secret fuer "${t.name}" gesetzt - Webhook lehnt alles ab.`);
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function ensureDataDir() {
  fs.mkdirSync(DATA_DIR, { recursive: true });
}

function safeReadJson(file, fallback) {
  try {
    if (!fs.existsSync(file)) return fallback;
    return JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch {
    return fallback;
  }
}

function appendAudit(target, entry) {
  ensureDataDir();
  fs.appendFileSync(
    path.join(DATA_DIR, `${target}-deploy-audit.log`),
    JSON.stringify({ at: new Date().toISOString(), ...entry }) + '\n'
  );
}

function verifySignature(secret, payload, signature) {
  if (!signature || !secret) return false;
  const expected = 'sha256=' + crypto.createHmac('sha256', secret).update(payload).digest('hex');
  try {
    return crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(signature));
  } catch {
    return false;
  }
}

function isDuplicateDelivery(target, deliveryId) {
  if (!deliveryId) return false;
  ensureDataDir();
  const file = path.join(DATA_DIR, `${target}-deploy-deliveries.json`);
  const list = safeReadJson(file, []);
  if (list.includes(deliveryId)) return true;
  fs.writeFileSync(file, JSON.stringify([...list, deliveryId].slice(-500), null, 2));
  return false;
}

function acquireLock(target) {
  ensureDataDir();
  const file = path.join(DATA_DIR, `${target}-deploy.lock`);
  const fd = fs.openSync(file, 'wx');
  fs.writeFileSync(fd, JSON.stringify({ pid: process.pid, startedAt: new Date().toISOString() }));
  return { fd, file };
}

function releaseLock(lock) {
  try { fs.closeSync(lock.fd); } catch { /* noop */ }
  try { fs.unlinkSync(lock.file); } catch { /* noop */ }
}

function run(cmd, args, options = {}) {
  return execFileSync(cmd, args, { encoding: 'utf8', ...options }).trim();
}

function assertCleanGitState(repoDir) {
  const dirty = run('git', ['-C', repoDir, 'status', '--porcelain', '-uno']);
  if (dirty) throw new Error(`Repo ${repoDir} hat uncommittete Aenderungen - Deploy abgebrochen.`);
}

function sendDiscord(payload) {
  if (!DISCORD_DEPLOY_WEBHOOK) return;
  try {
    const data = JSON.stringify(payload);
    const url = new URL(DISCORD_DEPLOY_WEBHOOK);
    const req = https.request(
      {
        hostname: url.hostname,
        path: url.pathname + url.search,
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(data) },
      },
      (res) => console.log(`[discord] webhook status ${res.statusCode}`)
    );
    req.on('error', (err) => console.warn('[discord] request failed:', err.message));
    req.write(data);
    req.end();
  } catch (err) {
    console.warn('[discord] send failed:', err.message);
  }
}

async function checkHealth(target) {
  if (!target.healthUrl) return { ok: true, skipped: true };
  for (let i = 1; i <= target.healthRetries; i++) {
    try {
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), 5000);
      const res = await fetch(target.healthUrl, { signal: controller.signal });
      clearTimeout(timer);
      if (res.ok) return { ok: true, attempt: i };
    } catch { /* retry */ }
    if (i < target.healthRetries) await sleep(target.healthDelayMs);
  }
  return { ok: false, attempt: target.healthRetries };
}

async function deployTarget(target, ctx) {
  const previousCommit = run('git', ['-C', target.repoDir, 'rev-parse', 'HEAD']);
  appendAudit(target.name, { deploymentId: ctx.deploymentId, action: 'deploy-start', previousCommit });
  sendDiscord({
    embeds: [{
      color: 0x5865f2,
      title: `Deploy gestartet: ${target.name}`,
      fields: [
        { name: 'Pusher', value: ctx.pusher || 'unbekannt', inline: true },
        { name: 'Branch', value: target.branch, inline: true },
      ],
      timestamp: new Date().toISOString(),
    }],
  });

  try {
    assertCleanGitState(target.repoDir);
    const gitOut = run('git', ['-C', target.repoDir, 'pull', '--ff-only', 'origin', target.branch]);
    const currentCommit = run('git', ['-C', target.repoDir, 'rev-parse', 'HEAD']);

    let dockerOut = '';
    if (target.build) {
      dockerOut = run('docker', ['compose', '-f', COMPOSE_FILE, 'up', '-d', '--build', '--no-deps', target.composeService]);
      const health = await checkHealth(target);
      if (!health.ok) throw new Error(`Healthcheck fehlgeschlagen (${target.healthUrl})`);
    }

    appendAudit(target.name, { deploymentId: ctx.deploymentId, action: 'deploy-success', commit: currentCommit });
    sendDiscord({
      embeds: [{
        color: 0x57f287,
        title: `Deploy erfolgreich: ${target.name}`,
        fields: [{ name: 'Commit', value: `\`${currentCommit.slice(0, 12)}\``, inline: true }],
        timestamp: new Date().toISOString(),
      }],
    });

    return { success: true, commit: currentCommit, git: gitOut, docker: dockerOut };
  } catch (err) {
    appendAudit(target.name, { deploymentId: ctx.deploymentId, action: 'deploy-failed', error: err.message });

    if (target.build) {
      try {
        run('git', ['-C', target.repoDir, 'reset', '--hard', previousCommit]);
        run('docker', ['compose', '-f', COMPOSE_FILE, 'up', '-d', '--build', '--no-deps', target.composeService]);
        appendAudit(target.name, { deploymentId: ctx.deploymentId, action: 'rollback-success', rollbackTo: previousCommit });
      } catch (rollbackErr) {
        appendAudit(target.name, { deploymentId: ctx.deploymentId, action: 'rollback-failed', error: rollbackErr.message });
      }
    }

    sendDiscord({
      embeds: [{
        color: 0xed4245,
        title: `Deploy fehlgeschlagen: ${target.name}`,
        fields: [{ name: 'Fehler', value: '```' + err.message.slice(0, 900) + '```', inline: false }],
        timestamp: new Date().toISOString(),
      }],
    });

    return { success: false, error: err.message };
  }
}

const server = http.createServer((req, res) => {
  if (req.method === 'GET' && req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    return res.end(JSON.stringify({ status: 'ok', targets: Object.keys(TARGETS) }));
  }

  const match = req.url.match(/^\/deploy\/([a-z0-9_-]+)$/i);
  if (req.method !== 'POST' || !match || !TARGETS[match[1]]) {
    res.writeHead(404);
    return res.end('Not found');
  }
  const target = TARGETS[match[1]];

  const chunks = [];
  let total = 0;
  req.on('data', (chunk) => {
    total += chunk.length;
    if (total > MAX_BODY_BYTES) {
      res.writeHead(413);
      res.end('Payload too large');
      req.destroy();
      return;
    }
    chunks.push(chunk);
  });

  req.on('end', async () => {
    const raw = Buffer.concat(chunks);
    const sig = req.headers['x-hub-signature-256'];
    const event = String(req.headers['x-github-event'] || '');
    const deliveryId = String(req.headers['x-github-delivery'] || '');

    if (!verifySignature(target.secret, raw, sig)) {
      appendAudit(target.name, { action: 'request-rejected', reason: 'invalid-signature', deliveryId });
      res.writeHead(403);
      return res.end('Forbidden');
    }
    if (event !== 'push') {
      res.writeHead(200);
      return res.end('Ignored event');
    }
    if (isDuplicateDelivery(target.name, deliveryId)) {
      res.writeHead(200);
      return res.end('Duplicate ignored');
    }

    let body;
    try {
      body = JSON.parse(raw.toString('utf8'));
    } catch {
      res.writeHead(400);
      return res.end('Bad JSON');
    }

    const ref = body.ref || '';
    if (ref !== `refs/heads/${target.branch}`) {
      res.writeHead(200);
      return res.end('Ignored: wrong branch');
    }

    const deploymentId = crypto.randomUUID();
    let lock;
    try {
      lock = acquireLock(target.name);
    } catch {
      appendAudit(target.name, { deploymentId, action: 'request-rejected', reason: 'deploy-locked' });
      res.writeHead(423);
      return res.end('Another deploy is in progress');
    }

    try {
      const result = await deployTarget(target, { deploymentId, deliveryId, pusher: body.pusher?.name });
      res.writeHead(result.success ? 200 : 500, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify(result));
    } finally {
      releaseLock(lock);
    }
  });
});

server.listen(PORT, () => {
  console.log(`[deploy-extra] listening on :${PORT}`);
  for (const t of Object.values(TARGETS)) {
    console.log(`[deploy-extra]  - /deploy/${t.name} -> ${t.repoDir} (${t.branch})${t.build ? ' + rebuild ' + t.composeService : ''}`);
  }
});

process.on('SIGINT', () => process.exit(0));
process.on('SIGTERM', () => process.exit(0));

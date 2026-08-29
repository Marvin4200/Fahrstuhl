// Interner Ops-Log — frueher Discord-Kanaele unter "fahrstuhl-logs"
// (#commands, #trolls, #guilds, #errors, #system), jetzt eine HTTP-Aufnahme
// bei admin.eselbande.com. Gleiche logToMaster(data, type)-Signatur wie
// zuvor, damit die ~30 bestehenden Aufrufstellen im Bot unveraendert bleiben.
const ADMIN_LOG_URL = (process.env.ADMIN_LOG_URL || '').replace(/\/+$/, '');
const LOG_INGEST_TOKEN = process.env.LOG_INGEST_TOKEN || '';

function trimText(value, limit, fallback = null) {
    const text = String(value ?? "").trim();
    if (!text) return fallback;
    return text.length > limit ? `${text.slice(0, Math.max(0, limit - 3))}...` : text;
}

function normalizeField(field) {
    const name = trimText(field?.name, 256);
    const value = trimText(field?.value, 1024);
    if (!name || !value) return null;
    return { name, value, inline: Boolean(field?.inline) };
}

// data kommt in drei Formen an: ein String, ein Embed-Objekt
// ({title,description,color,fields,...}), oder {embeds:[...]} - alle drei
// werden hier auf dieselbe flache Form gebracht, die die Aufnahme erwartet.
function normalizeLogPayload(data, type) {
    if (data && typeof data === "object" && Array.isArray(data.embeds)) {
        const e = data.embeds[0] || {};
        return {
            type,
            title: trimText(e.title, 256),
            description: trimText(e.description, 4096),
            color: typeof e.color === "number" ? e.color : null,
            fields: Array.isArray(e.fields) ? e.fields.map(normalizeField).filter(Boolean).slice(0, 25) : null,
        };
    }
    if (typeof data === "string") {
        return { type, title: null, description: trimText(data, 4096, "Log"), color: null, fields: null };
    }
    const { title, description, color, fields } = data || {};
    return {
        type,
        title: trimText(title, 256),
        description: trimText(description, 4096),
        color: typeof color === "number" ? color : null,
        fields: Array.isArray(fields) ? fields.map(normalizeField).filter(Boolean).slice(0, 25) : null,
    };
}

// Ratenbegrenzung: waehrend eines Fehlersturms (z.B. eine haengende
// Verbindung, die minuetlich denselben Fehler wirft) soll admin-dashboard
// nicht mit hunderten Anfragen pro Minute geflutet werden. Token-Bucket:
// 30 Log-Sendungen sofort verfuegbar, danach eine neue alle 2 Sekunden.
let _logTokens = 30;
setInterval(() => { _logTokens = Math.min(30, _logTokens + 1); }, 2000);
let _logSuppressedSince = 0;

let _consoleWrapped = false;

function createLogger() {
    async function logToMaster(data, type = "SYSTEM") {
        if (!ADMIN_LOG_URL || !LOG_INGEST_TOKEN) return; // Aufnahme nicht konfiguriert - stiller No-Op
        if (_logTokens <= 0) { _logSuppressedSince++; return; }
        _logTokens--;
        if (_logSuppressedSince > 0) {
            const n = _logSuppressedSince;
            _logSuppressedSince = 0;
            logToMaster(`${n} weitere Meldungen in kurzer Zeit wurden nicht einzeln gesendet (Ratenbegrenzung).`, "SYSTEM");
        }

        const payload = normalizeLogPayload(data, type);
        if (!payload.title && !payload.description) return; // nichts Sinnvolles zum Loggen

        try {
            const controller = new AbortController();
            const timer = setTimeout(() => controller.abort(), 5000);
            await fetch(`${ADMIN_LOG_URL}/api/logs/ingest`, {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-Log-Token": LOG_INGEST_TOKEN },
                body: JSON.stringify(payload),
                signal: controller.signal,
            }).catch(() => {}); // best effort - ein Logging-Ausfall darf den Bot nie stoeren
            clearTimeout(timer);
        } catch {
            // siehe oben
        }
    }

    // console.error/console.warn global umleiten - deckt die vielen
    // bestehenden try/catch-Stellen im Bot ab, die einen Fehler bisher nur
    // lokal geloggt haben, ohne jede einzelne Stelle anzufassen. Nur einmal
    // verdrahten, auch wenn createLogger() mehrfach aufgerufen wird.
    if (!_consoleWrapped) {
        _consoleWrapped = true;
        const origError = console.error.bind(console);
        const origWarn = console.warn.bind(console);
        const fmtArgs = (args) => args.map(a => {
            if (a instanceof Error) return a.stack || a.message;
            if (a && typeof a === "object") { try { return JSON.stringify(a); } catch { return String(a); } }
            return String(a);
        }).join(" ").slice(0, 4096);

        console.error = (...args) => {
            origError(...args);
            logToMaster(fmtArgs(args), "ERRORS");
        };
        console.warn = (...args) => {
            origWarn(...args);
            logToMaster(fmtArgs(args), "WARNINGS");
        };
    }

    // Bleiben als No-Ops bestehen: die alte Discord-Kanal-Verwaltung
    // (Kategorie/Kanaele automatisch anlegen) gibt es nicht mehr.
    async function setupLogChannels() {
        return { devGuild: null, logCategoryId: null };
    }

    function getLogCategoryId() {
        return null;
    }

    return { logToMaster, setupLogChannels, activeLogChannels: new Map(), getLogCategoryId };
}

module.exports = {
    createLogger
};

/**
 * userPrefs.js — lightweight per-user preference store
 * Persists to userPrefs.json alongside userStats.json
 * Fields: customTrollMessage, lastPremiumMonthlyBonus
 */

const fs = require("fs");
const path = require("path");

const FILE = path.join(__dirname, "..", "userPrefs.json");

let cache = {};

function load() {
    if (fs.existsSync(FILE)) {
        try { cache = JSON.parse(fs.readFileSync(FILE, "utf8")); } catch { cache = {}; }
    }
}

function save() {
    try { fs.writeFileSync(FILE, JSON.stringify(cache, null, 2), "utf8"); } catch (e) {
        console.error("userPrefs save error:", e);
    }
}

load();

// Read-only: does NOT create a cache entry for userId if one doesn't exist.
// getCustomTrollMessage() (and therefore getPrefs) runs on every invocation
// of /fahrstuhl, /geist, /stillepost, /spiegel, /toteleitung for the
// invoking user — the old version wrote an entry into the module-level
// `cache` object on every such read, even for users who never set any
// preference, growing it forever for the life of the process.
function getPrefs(userId) {
    return cache[userId] || {};
}

function setPrefs(userId, prefs) {
    cache[userId] = { ...getPrefs(userId), ...prefs };
    save();
}

function getCustomTrollMessage(userId) {
    return getPrefs(userId).customTrollMessage || null;
}

function setCustomTrollMessage(userId, message) {
    setPrefs(userId, { customTrollMessage: message || null });
}

/** Custom embed colour for a user's own troll embeds (paid tiers only). */
function getTrollColor(userId) {
    const raw = getPrefs(userId).trollColor;
    // Must reject null/'' explicitly BEFORE Number(): Number(null) is 0, which
    // is a perfectly valid integer in range, so a reset colour would come back
    // as 0x000000 and render every troll embed pure black with no way to undo.
    if (raw === null || raw === undefined || raw === '') return null;
    const value = Number(raw);
    return Number.isInteger(value) && value >= 0 && value <= 0xFFFFFF ? value : null;
}

function setTrollColor(userId, color) {
    setPrefs(userId, {
        trollColor: (Number.isInteger(color) && color >= 0 && color <= 0xFFFFFF) ? color : null,
    });
}

function getLastPremiumMonthlyBonus(userId) {
    return Number(getPrefs(userId).lastPremiumMonthlyBonus || 0);
}

function setLastPremiumMonthlyBonus(userId, ts) {
    setPrefs(userId, { lastPremiumMonthlyBonus: ts });
}

module.exports = {
    getCustomTrollMessage, setCustomTrollMessage,
    getTrollColor, setTrollColor,
    getLastPremiumMonthlyBonus, setLastPremiumMonthlyBonus,
};

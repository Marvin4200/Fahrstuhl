/**
 * Wochenrückblick — holt die Zahlen der letzten Tage aus den Nachbardiensten
 * und baut daraus ein Embed für Discord.
 *
 * Bewusst tolerant: fällt ein Dienst aus, fehlt sein Abschnitt, statt dass der
 * ganze Rückblick scheitert. Lieber ein unvollständiger Post als gar keiner.
 */

const MUSIC_API = process.env.ESELMUSIC_API_URL || "http://musikbot-docker:3020";
const QUOTES_API = process.env.ZITAT_API_URL || "http://zitatboard:3013";

async function getJson(url, timeoutMs = 6000) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    try {
        const res = await fetch(url, { signal: controller.signal });
        if (!res.ok) return null;
        return await res.json();
    } catch {
        return null;
    } finally {
        clearTimeout(timer);
    }
}

function medal(i) {
    return ["🥇", "🥈", "🥉"][i] || `${i + 1}.`;
}

/**
 * @param {number} days Zeitfenster in Tagen.
 * @returns {Promise<{embed: object, empty: boolean}>}
 */
async function buildRecapEmbed(days = 7) {
    const [music, quotes] = await Promise.all([
        getJson(`${MUSIC_API}/api/public-recap?days=${days}`),
        getJson(`${QUOTES_API}/api/quotes`),
    ]);

    const fields = [];
    let anyData = false;

    // ── Musik ────────────────────────────────────────────────────────────
    if (music && music.totals && music.totals.plays > 0) {
        anyData = true;
        const t = music.totals;
        const byUser = Math.max(0, (t.plays || 0) - (t.automix || 0));

        fields.push({
            name: "🎵 Musik",
            value:
                `**${t.plays}** Songs gespielt · **${t.tracks}** verschiedene\n` +
                `davon **${byUser}** selbst angefragt, **${t.automix || 0}** per AutoMix`,
            inline: false,
        });

        if (music.tracks?.length) {
            fields.push({
                name: "Meistgespielt",
                value: music.tracks
                    .map((x, i) => `${medal(i)} **${x.title}** — ${x.author} *(${x.plays}×)*`)
                    .join("\n")
                    .slice(0, 1024),
                inline: false,
            });
        }

        if (music.requesters?.length) {
            fields.push({
                name: "Fleißigste DJs",
                value: music.requesters
                    .map((x, i) => `${medal(i)} <@${x.user_id}> — ${x.plays} Song${x.plays === 1 ? "" : "s"}`)
                    .join("\n")
                    .slice(0, 1024),
                inline: false,
            });
        }
    }

    // ── Zitate ───────────────────────────────────────────────────────────
    if (Array.isArray(quotes)) {
        const since = Math.floor(Date.now() / 1000) - days * 86400;
        const fresh = quotes.filter((q) => Number(q.created_at) >= since);
        if (fresh.length) {
            anyData = true;
            const top = [...fresh].sort((a, b) => (b.score || 0) - (a.score || 0))[0];
            let value = `**${fresh.length}** neue${fresh.length === 1 ? "s" : ""} Zitat${fresh.length === 1 ? "" : "e"}`;
            if (top && top.text) {
                const text = String(top.text).slice(0, 180);
                value += `\n\n> ${text}${String(top.text).length > 180 ? "…" : ""}`;
                if (top.attributed_to) value += `\n> — *${String(top.attributed_to).slice(0, 60)}*`;
            }
            fields.push({ name: "💬 Zitat-Board", value: value.slice(0, 1024), inline: false });
        }
    }

    const embed = {
        color: 0x5865f2,
        title: days === 7 ? "📅 Wochenrückblick" : `📅 Rückblick — letzte ${days} Tage`,
        description: anyData
            ? undefined
            : "In diesem Zeitraum ist nichts zusammengekommen. Kommt Zeit, kommen Zahlen.",
        fields,
        footer: { text: "eselbande.com" },
        timestamp: new Date().toISOString(),
    };

    return { embed, empty: !anyData };
}

module.exports = { buildRecapEmbed };

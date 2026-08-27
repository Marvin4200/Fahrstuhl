/**
 * premiumReminders.js — expiry reminders for premium plans.
 *
 * Why this exists: /premium/reminders/send already existed, but nothing ever
 * called it — an admin had to click a button in the dashboard. With manual
 * renewal and no reminder, a plan simply lapses and almost nobody comes back;
 * the reminder is the difference between a customer and a one-off purchase.
 *
 * Two things the old endpoint could not do safely on a schedule:
 *
 *   1. Dedup. It sent to everyone inside a single `daysBefore` window, so a
 *      daily job would DM the same person every day for a week.
 *   2. Milestones. One flat threshold can't say "a week out" differently from
 *      "tomorrow", and can't follow up after expiry at all.
 *
 * Reminders fire once per milestone per subscription period. The dedup key
 * includes the expiry timestamp, so renewing (which moves the expiry) re-arms
 * every milestone automatically with no explicit reset.
 */

const DAY_MS = 24 * 60 * 60 * 1000;

/**
 * Milestones, checked in order; the first match wins.
 * `days` is the remaining-days threshold, negative meaning "after expiry".
 */
const MILESTONES = [
    { key: 'd7',      days: 7,  label: 'eine Woche' },
    { key: 'd3',      days: 3,  label: 'drei Tage' },
    { key: 'd1',      days: 1,  label: 'ein Tag' },
    { key: 'expired', days: 0,  label: 'abgelaufen', afterExpiry: true },
];

/**
 * Which milestone (if any) applies to a plan right now.
 *
 * Picks the TIGHTEST threshold that still covers the remaining days — 5 days
 * left resolves to `d7`, 2 days to `d3`. Combined with the once-per-milestone
 * dedup this walks a plan through d7 → d3 → d1 → expired exactly once each,
 * and a plan first seen mid-window still gets an immediate heads-up instead of
 * waiting for the next threshold.
 *
 * Anything beyond the widest threshold, or long expired, gets nothing.
 */
function milestoneFor(daysRemaining) {
    if (!Number.isFinite(daysRemaining)) return null;

    if (daysRemaining <= 0) {
        // Only nudge shortly after lapsing — a plan that ended months ago is
        // not a renewal prospect, it's spam.
        return daysRemaining >= -3 ? MILESTONES.find(m => m.key === 'expired') : null;
    }

    const applicable = MILESTONES
        .filter(m => !m.afterExpiry && daysRemaining <= m.days)
        .sort((a, b) => a.days - b.days);

    return applicable[0] || null;
}

/** Whole days until expiry; negative once expired. */
function daysUntil(expiresAt, now = Date.now()) {
    const ms = new Date(expiresAt).getTime();
    if (!Number.isFinite(ms)) return null;
    return Math.ceil((ms - now) / DAY_MS);
}

/**
 * Build the DM payload for one reminder.
 * Pure: no Discord objects, so it can be asserted on directly in tests.
 */
function buildReminderMessage({ tier, expiresAt, milestone, pricing }) {
    const tierDef = tier === 'pro' ? pricing.TIERS.pro : pricing.TIERS.basic;
    const expiresTs = Math.floor(new Date(expiresAt).getTime() / 1000);
    const expired = milestone.key === 'expired';

    const embed = {
        color: expired ? 0xED4245 : 0xFFD43B,
        title: expired
            ? `${tierDef.emoji} Dein ${tierDef.label} ist abgelaufen`
            : `⏳ Dein ${tierDef.label} läuft bald ab`,
        description: expired
            ? `Dein **${tierDef.label}**-Zugang ist am <t:${expiresTs}:D> ausgelaufen. `
              + `Du bist zurück auf dem Free-Plan — deine Shields und Daten sind alle noch da.`
            : `Dein **${tierDef.label}**-Zugang läuft <t:${expiresTs}:R> ab (<t:${expiresTs}:D>).`,
        fields: [
            {
                name: 'Was du dann verlierst',
                value: [
                    `⏱️ Cooldown zurück auf ${pricing.formatDuration(pricing.TIERS.free.cooldownMs)} `
                    + `(statt ${pricing.formatDuration(tierDef.cooldownMs)})`,
                    `🛡️ Shield alle ${pricing.formatDuration(pricing.TIERS.free.shieldClaimCooldownMs)} `
                    + `statt alle ${pricing.formatDuration(tierDef.shieldClaimCooldownMs)}`,
                    `🛡️ Shield schützt ${pricing.formatDuration(pricing.TIERS.free.shieldDurationMs)} `
                    + `statt ${pricing.formatDuration(tierDef.shieldDurationMs)}`,
                    `📈 XP-Boost ${tierDef.xpMultiplier}× fällt weg`,
                ].join('\n'),
            },
            {
                name: 'Verlängern',
                value: `**${pricing.formatPrice(tierDef.priceMonthly)}**/Monat — `
                     + `oder **${pricing.formatPrice(tierDef.priceLifetime)}** einmalig, dann nie wieder.\n`
                     + (expired
                        ? 'Der Plan startet ab dem Kauf neu.'
                        : 'Verlängerst du vor Ablauf, werden die neuen Tage **auf deine Restlaufzeit draufgerechnet** — du verlierst nichts.'),
            },
        ],
        timestamp: new Date().toISOString(),
    };

    const components = [{
        type: 1,
        components: [
            { type: 2, label: `${tierDef.emoji} Verlängern`, style: 5, url: pricing.PRICING_PAGE_URL },
            { type: 2, label: '💬 Support', style: 5, url: pricing.SUPPORT_INVITE },
        ],
    }];

    return { embeds: [embed], components };
}

/**
 * Decide who should be reminded right now.
 *
 * @param {Array} plans  [{ userId, tier, expiresAt }]
 * @param {(key, expiresAt, milestone) => Promise<boolean>} wasSent dedup check
 * @param {number} now
 * @returns {Promise<Array>} [{ userId, tier, expiresAt, milestone, daysRemaining }]
 */
async function selectDueReminders(plans, wasSent, now = Date.now()) {
    const due = [];

    for (const plan of plans) {
        if (!plan?.userId || !plan?.expiresAt) continue;

        const daysRemaining = daysUntil(plan.expiresAt, now);
        const milestone = milestoneFor(daysRemaining);
        if (!milestone) continue;

        const already = await wasSent(plan.userId, plan.expiresAt, milestone.key);
        if (already) continue;

        due.push({
            userId: plan.userId,
            tier: plan.tier === 'pro' ? 'pro' : 'basic',
            expiresAt: plan.expiresAt,
            milestone,
            daysRemaining,
        });
    }

    return due;
}

module.exports = {
    DAY_MS,
    MILESTONES,
    milestoneFor,
    daysUntil,
    buildReminderMessage,
    selectDueReminders,
};

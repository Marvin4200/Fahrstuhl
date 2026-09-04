/**
 * pricing.js — single source of truth for tiers, prices and tier-dependent limits.
 *
 * Everything the bot or the dashboard says about what a tier costs and what it
 * unlocks should come from here, so a price change is a one-line edit instead of
 * a hunt through embeds and PHP pages.
 *
 * The PHP dashboard mirrors these values in
 * dashboard/public/includes/pricing.php — keep the two in sync when editing.
 */

const num = (envKey, fallback) => {
    const raw = Number(process.env[envKey]);
    return Number.isFinite(raw) && raw > 0 ? raw : fallback;
};

// Cooldown on the core troll commands, per tier.
//
// Free used to sit at 10 minutes, which paywalled the core loop rather than
// upselling it: a new user hit the wall before the bot ever became a habit, and
// "the bot is annoying" is a much more common reaction than "let me pay to fix
// this". 90s keeps free genuinely usable while leaving a real ladder above it.
const COOLDOWNS_MS = {
    free:    num('COOLDOWN_FREE_MS',    90 * 1000),
    basic:   num('COOLDOWN_BASIC_MS',   45 * 1000),
    pro:     num('COOLDOWN_PRO_MS',     20 * 1000),
};

// How long ghost / mute / mirror / deafen keep running, per tier.
const TROLL_DURATION_MS = {
    free:    num('TROLL_DURATION_FREE_MS',   60 * 1000),
    basic:   num('TROLL_DURATION_BASIC_MS',  5 * 60 * 1000),
    pro:     num('TROLL_DURATION_PRO_MS',   10 * 60 * 1000),
};

const MIN = 60 * 1000;
const HOUR = 60 * MIN;

// Perks are strictly ADDITIVE: every free-tier number below is exactly what free
// users already had. Paid tiers only ever get MORE. Taking something away from
// free to create a reason to upgrade is what the old 10-minute cooldown did, and
// it cost more users than it converted.
const TIERS = {
    free: {
        key: 'free',
        label: 'Free',
        emoji: '🆓',
        priceMonthly: 0,
        priceLifetime: 0,
        cooldownMs: COOLDOWNS_MS.free,
        trollDurationMs: TROLL_DURATION_MS.free,

        // Shield economy — shields are the bot's scarce defensive resource, so
        // this is where paid tiers are actually felt day to day.
        shieldClaimCooldownMs: num('SHIELD_CLAIM_FREE_MS', 2.5 * HOUR),
        shieldDurationMs: num('SHIELD_DURATION_FREE_MS', 2 * HOUR),
        monthlyBonusShields: 0,
        claimRequiresDevServer: true,

        xpMultiplier: 1,
        maxElevatorTargets: 1,
        customTrollMessage: false,
        customEmbedColor: false,
        notifySettings: false,
    },
    basic: {
        key: 'basic',
        label: 'Premium',
        emoji: '💎',
        priceMonthly: num('PRICE_BASIC_MONTHLY', 2.49),
        priceLifetime: num('PRICE_BASIC_LIFETIME', 14.99),
        cooldownMs: COOLDOWNS_MS.basic,
        trollDurationMs: TROLL_DURATION_MS.basic,

        shieldClaimCooldownMs: num('SHIELD_CLAIM_BASIC_MS', 1.5 * HOUR),
        shieldDurationMs: num('SHIELD_DURATION_BASIC_MS', 4 * HOUR),
        monthlyBonusShields: Math.round(num('MONTHLY_SHIELDS_BASIC', 5)),
        claimRequiresDevServer: false,

        xpMultiplier: num('XP_MULTIPLIER_BASIC', 1.5),
        maxElevatorTargets: 1,
        customTrollMessage: false,
        customEmbedColor: true,
        notifySettings: true,
    },
    pro: {
        key: 'pro',
        label: 'Pro',
        emoji: '👑',
        priceMonthly: num('PRICE_PRO_MONTHLY', 4.99),
        priceLifetime: num('PRICE_PRO_LIFETIME', 29.99),
        cooldownMs: COOLDOWNS_MS.pro,
        trollDurationMs: TROLL_DURATION_MS.pro,

        shieldClaimCooldownMs: num('SHIELD_CLAIM_PRO_MS', 45 * MIN),
        shieldDurationMs: num('SHIELD_DURATION_PRO_MS', 8 * HOUR),
        monthlyBonusShields: Math.round(num('MONTHLY_SHIELDS_PRO', 15)),
        claimRequiresDevServer: false,

        xpMultiplier: num('XP_MULTIPLIER_PRO', 2),
        maxElevatorTargets: 3,
        customTrollMessage: true,
        customEmbedColor: true,
        notifySettings: true,
    },
};

const CURRENCY = process.env.PRICING_CURRENCY || 'EUR';

const SUPPORT_INVITE = process.env.SUPPORT_INVITE_URL || 'https://discord.gg/zfzDHKcWDx';
const PRICING_PAGE_URL = process.env.PRICING_PAGE_URL || 'https://eselbande.com/fahrstuhl/pages/premium-info.php';

/** Resolve a tier object from the two booleans the bot passes around. */
function tierFor(isPremium, isPro) {
    if (isPro) return TIERS.pro;
    if (isPremium) return TIERS.basic;
    return TIERS.free;
}

/**
 * Cheapest tier satisfying a predicate, in price order.
 * Used so an upsell always names a tier that actually unlocks the thing being
 * blocked — nextTierFor() would happily pitch Premium for a Pro-only perk.
 */
function cheapestTierFor(predicate) {
    for (const key of ['free', 'basic', 'pro']) {
        if (predicate(TIERS[key])) return TIERS[key];
    }
    return null;
}

/** Resolve a tier object from a tier key ('free' | 'basic' | 'pro'). */
function tierByKey(key) {
    return TIERS[key] || TIERS.free;
}

/**
 * The next tier a user could buy into, or null if they are already at the top.
 * Used to tell someone concretely what they're missing rather than generically
 * advertising at them.
 */
function nextTierFor(isPremium, isPro) {
    if (isPro) return null;
    return isPremium ? TIERS.pro : TIERS.basic;
}

/** "2,49 €" */
function formatPrice(amount) {
    if (!amount) return '0 €';
    const symbol = CURRENCY === 'EUR' ? '€' : CURRENCY;
    return `${amount.toFixed(2).replace('.', ',')} ${symbol}`;
}

/** Human-readable duration, e.g. "90 Sekunden" / "5 Minuten". */
function formatDuration(ms) {
    const seconds = Math.round(ms / 1000);
    // Stay in seconds below two minutes — "90 Sekunden" reads better than
    // "1,5 Minuten" and matches how the cooldown actually feels.
    if (seconds < 120) return `${seconds} Sekunden`;

    const minutes = seconds / 60;
    if (minutes < 60) {
        return Number.isInteger(minutes)
            ? `${minutes} Minuten`
            : `${minutes.toFixed(1).replace('.', ',')} Minuten`;
    }

    const hours = minutes / 60;
    if (Number.isInteger(hours)) return `${hours} ${hours === 1 ? 'Stunde' : 'Stunden'}`;
    // 150 min reads as "2,5 Stunden", not "150 Minuten".
    return `${hours.toFixed(1).replace('.', ',')} Stunden`;
}

/**
 * How much shorter a paid cooldown is versus free, as a rounded percentage.
 * The sales copy used to hardcode "50%" while the code actually delivered far
 * more than that — deriving it means the claim can't drift from reality again.
 */
function cooldownSavingPercent(tierKey) {
    const tier = TIERS[tierKey];
    if (!tier || tier.key === 'free' || !TIERS.free.cooldownMs) return 0;
    return Math.round((1 - tier.cooldownMs / TIERS.free.cooldownMs) * 100);
}

module.exports = {
    TIERS,
    tierByKey,
    nextTierFor,
    cheapestTierFor,
    CURRENCY,
    SUPPORT_INVITE,
    PRICING_PAGE_URL,
    tierFor,
    formatPrice,
    formatDuration,
    cooldownSavingPercent,
};

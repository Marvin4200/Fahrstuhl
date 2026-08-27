<?php
/**
 * pricing.php — dashboard-side mirror of utils/pricing.js.
 *
 * Keep the numbers here in sync with utils/pricing.js. Both read the same
 * environment variables, so setting those in the environment keeps the bot and
 * the dashboard aligned without editing either file.
 */

if (!function_exists('pricingNum')) {
    function pricingNum($envKey, $fallback) {
        $raw = getenv($envKey);
        if ($raw === false || $raw === '') return $fallback;
        $val = (float)$raw;
        return $val > 0 ? $val : $fallback;
    }
}

if (!defined('PRICING_LOADED')) {
    define('PRICING_LOADED', true);

    $GLOBALS['PRICING_CURRENCY'] = getenv('PRICING_CURRENCY') ?: 'EUR';

    $GLOBALS['PRICING_TIERS'] = [
        'free' => [
            'key' => 'free',
            'label' => 'Free',
            'emoji' => '🆓',
            'priceMonthly' => 0.0,
            'priceLifetime' => 0.0,
            'cooldownMs' => pricingNum('COOLDOWN_FREE_MS', 90 * 1000),
            'trollDurationMs' => pricingNum('TROLL_DURATION_FREE_MS', 60 * 1000),
            'shieldClaimCooldownMs' => pricingNum('SHIELD_CLAIM_FREE_MS', 2.5 * 3600 * 1000),
            'shieldDurationMs' => pricingNum('SHIELD_DURATION_FREE_MS', 2 * 3600 * 1000),
            'monthlyBonusShields' => 0,
            'claimRequiresDevServer' => true,
            'xpMultiplier' => 1,
            'maxElevatorTargets' => 1,
            'customTrollMessage' => false,
            'customEmbedColor' => false,
            'notifySettings' => false,
        ],
        'basic' => [
            'key' => 'basic',
            'label' => 'Premium',
            'emoji' => '💎',
            'priceMonthly' => pricingNum('PRICE_BASIC_MONTHLY', 2.49),
            'priceLifetime' => pricingNum('PRICE_BASIC_LIFETIME', 14.99),
            'cooldownMs' => pricingNum('COOLDOWN_BASIC_MS', 45 * 1000),
            'trollDurationMs' => pricingNum('TROLL_DURATION_BASIC_MS', 5 * 60 * 1000),
            'shieldClaimCooldownMs' => pricingNum('SHIELD_CLAIM_BASIC_MS', 1.5 * 3600 * 1000),
            'shieldDurationMs' => pricingNum('SHIELD_DURATION_BASIC_MS', 4 * 3600 * 1000),
            'monthlyBonusShields' => (int)pricingNum('MONTHLY_SHIELDS_BASIC', 5),
            'claimRequiresDevServer' => false,
            'xpMultiplier' => pricingNum('XP_MULTIPLIER_BASIC', 1.5),
            'maxElevatorTargets' => 1,
            'customTrollMessage' => false,
            'customEmbedColor' => true,
            'notifySettings' => true,
        ],
        'pro' => [
            'key' => 'pro',
            'label' => 'Pro',
            'emoji' => '👑',
            'priceMonthly' => pricingNum('PRICE_PRO_MONTHLY', 4.99),
            'priceLifetime' => pricingNum('PRICE_PRO_LIFETIME', 29.99),
            'cooldownMs' => pricingNum('COOLDOWN_PRO_MS', 20 * 1000),
            'trollDurationMs' => pricingNum('TROLL_DURATION_PRO_MS', 10 * 60 * 1000),
            'shieldClaimCooldownMs' => pricingNum('SHIELD_CLAIM_PRO_MS', 45 * 60 * 1000),
            'shieldDurationMs' => pricingNum('SHIELD_DURATION_PRO_MS', 8 * 3600 * 1000),
            'monthlyBonusShields' => (int)pricingNum('MONTHLY_SHIELDS_PRO', 15),
            'claimRequiresDevServer' => false,
            'xpMultiplier' => pricingNum('XP_MULTIPLIER_PRO', 2),
            'maxElevatorTargets' => 3,
            'customTrollMessage' => true,
            'customEmbedColor' => true,
            'notifySettings' => true,
        ],
    ];

    // Stripe Payment Links. Empty => the buy buttons fall back to the support
    // server, which is exactly how the page behaved before Stripe existed.
    $GLOBALS['PRICING_CHECKOUT'] = [
        'basicMonthly'  => getenv('STRIPE_LINK_BASIC_MONTHLY')  ?: null,
        'basicLifetime' => getenv('STRIPE_LINK_BASIC_LIFETIME') ?: null,
        'proMonthly'    => getenv('STRIPE_LINK_PRO_MONTHLY')    ?: null,
        'proLifetime'   => getenv('STRIPE_LINK_PRO_LIFETIME')   ?: null,
    ];

    $GLOBALS['PRICING_SUPPORT_INVITE'] = getenv('SUPPORT_INVITE_URL') ?: 'https://discord.gg/zfzDHKcWDx';
}

if (!function_exists('pricingTier')) {
    function pricingTier($key) {
        return $GLOBALS['PRICING_TIERS'][$key] ?? $GLOBALS['PRICING_TIERS']['free'];
    }
}

if (!function_exists('pricingFormat')) {
    function pricingFormat($amount) {
        if (!$amount) return '0 €';
        $symbol = ($GLOBALS['PRICING_CURRENCY'] === 'EUR') ? '€' : $GLOBALS['PRICING_CURRENCY'];
        return number_format((float)$amount, 2, ',', '.') . ' ' . $symbol;
    }
}

if (!function_exists('pricingDuration')) {
    function pricingDuration($ms) {
        $seconds = (int)round($ms / 1000);
        if ($seconds < 120) return $seconds . ' Sekunden';

        $minutes = $seconds / 60;
        if ($minutes < 60) {
            return (floor($minutes) == $minutes)
                ? (int)$minutes . ' Minuten'
                : number_format($minutes, 1, ',', '.') . ' Minuten';
        }

        $hours = $minutes / 60;
        if (floor($hours) == $hours) {
            return (int)$hours . ' ' . ((int)$hours === 1 ? 'Stunde' : 'Stunden');
        }
        return number_format($hours, 1, ',', '.') . ' Stunden';
    }
}

if (!function_exists('pricingCooldownSaving')) {
    /** Derived, never hardcoded — the old page claimed 50% while shipping more. */
    function pricingCooldownSaving($tierKey) {
        $free = pricingTier('free')['cooldownMs'];
        $tier = pricingTier($tierKey);
        if ($tierKey === 'free' || !$free) return 0;
        return (int)round((1 - $tier['cooldownMs'] / $free) * 100);
    }
}

if (!function_exists('pricingCheckoutUrl')) {
    /**
     * Buy link for a tier/interval, with the Discord user id attached as
     * client_reference_id so the Stripe webhook knows who to activate.
     * Falls back to the support server when no Payment Link is configured.
     */
    function pricingCheckoutUrl($tierKey, $interval = 'monthly', $discordUserId = null) {
        $map = [
            'basic:monthly'  => 'basicMonthly',
            'basic:lifetime' => 'basicLifetime',
            'pro:monthly'    => 'proMonthly',
            'pro:lifetime'   => 'proLifetime',
        ];
        $slot = $map["$tierKey:$interval"] ?? null;
        $url = $slot ? ($GLOBALS['PRICING_CHECKOUT'][$slot] ?? null) : null;
        if (!$url) return $GLOBALS['PRICING_SUPPORT_INVITE'];
        if ($discordUserId) {
            $url .= (strpos($url, '?') === false ? '?' : '&')
                 . 'client_reference_id=' . urlencode($discordUserId);
        }
        return $url;
    }
}

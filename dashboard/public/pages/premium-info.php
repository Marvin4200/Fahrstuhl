<?php
$page_title = 'Premium';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/pricing.php';

// NO requireLogin() here on purpose.
//
// The bot's upgrade embed links straight to this page. Gating it behind Discord
// OAuth meant a curious user hit a login wall BEFORE ever seeing a price — the
// single worst place in the funnel to ask for a commitment. The page now renders
// for everyone; only the "your current plan" badge needs a session.
$isLoggedIn = isLoggedIn();
$user       = $isLoggedIn ? getUser() : null;
$userId     = $user['id'] ?? null;

$isPremium = false;
$isPro     = false;
$daysLeft  = 0;

if ($userId) {
    $premRes   = getAPI('/premium/user/' . urlencode($userId), 5);
    $isPremium = $premRes['data']['isPremium'] ?? false;
    $isPro     = $premRes['data']['isPro'] ?? false;
    $premUser  = $premRes['data']['user'] ?? null;
    $expiresAt = $premUser ? strtotime($premUser['expires_at']) : 0;
    $daysLeft  = $isPremium && $expiresAt > time() ? (int)ceil(($expiresAt - time()) / 86400) : 0;
}

$freeTier  = pricingTier('free');
$basicTier = pricingTier('basic');
$proTier   = pricingTier('pro');
$supportUrl = $GLOBALS['PRICING_SUPPORT_INVITE'];
$hasCheckout = (bool)($GLOBALS['PRICING_CHECKOUT']['basicMonthly'] ?? null);
$loginUrl   = BASE_URL . '/index.php';

/** Buy button: real checkout when configured, login first when logged out. */
function premiumBuyUrl($tierKey, $interval, $isLoggedIn, $userId, $loginUrl) {
    if (!$isLoggedIn) return $loginUrl;
    return pricingCheckoutUrl($tierKey, $interval, $userId);
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<style>
.prem-hero { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); border: 1px solid #ffd700; border-radius: 16px; padding: 2.5rem 2rem; text-align: center; margin-bottom: 2rem; position: relative; overflow: hidden; }
.prem-hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse at 50% 0%, rgba(255,215,0,.08) 0%, transparent 70%); pointer-events: none; }
.prem-hero h1 { font-size: 2rem; font-weight: 800; color: #ffd700; margin-bottom: .5rem; }
.prem-hero p { color: var(--text-secondary); font-size: 1rem; }
.status-badge { display: inline-flex; align-items: center; gap: .5rem; padding: .5rem 1.25rem; border-radius: 999px; font-weight: 700; font-size: .95rem; margin-top: 1rem; }
.status-active { background: rgba(81,207,102,.15); border: 1px solid #51cf66; color: #51cf66; }
.status-inactive { background: rgba(255,107,107,.12); border: 1px solid #ff6b6b; color: #ff6b6b; }

.plans-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
.plan-card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 14px; padding: 1.75rem; display: flex; flex-direction: column; gap: 1rem; transition: border-color .2s; }
.plan-card:hover { border-color: var(--primary); }
.plan-card.featured { border-color: #ffd700; box-shadow: 0 0 0 1px #ffd700; }
.plan-card .plan-name { font-size: 1.15rem; font-weight: 700; }
.plan-card .plan-price { font-size: 2.2rem; font-weight: 800; color: var(--primary); }
.plan-card .plan-price span { font-size: .9rem; color: var(--text-secondary); font-weight: 400; }
.plan-card .plan-lifetime { font-size: .82rem; color: var(--text-secondary); margin-top: .2rem; }
.plan-card .plan-lifetime strong { color: var(--text-primary); }
.btn-buy + .btn-buy { margin-top: .5rem; }
.plan-card .badge-featured { background: #ffd700; color: #000; font-size: .7rem; font-weight: 700; padding: .2rem .6rem; border-radius: 999px; margin-left: .5rem; text-transform: uppercase; }
.feature-list { list-style: none; display: flex; flex-direction: column; gap: .55rem; }
.feature-list li { display: flex; align-items: center; gap: .6rem; font-size: .9rem; color: var(--text-secondary); }
.feature-list li .icon { font-size: 1rem; flex-shrink: 0; }
.feature-list li.included { color: var(--text-primary); }
.btn-buy { display: block; text-align: center; padding: .75rem 1rem; border-radius: 8px; font-weight: 700; font-size: .95rem; text-decoration: none; margin-top: auto; transition: opacity .15s; }
.btn-buy:hover { opacity: .85; }
.btn-gold { background: linear-gradient(135deg, #f6d365, #fda085); color: #000; }
.btn-outline { background: transparent; border: 2px solid var(--primary); color: var(--primary); }

.features-section h2 { font-size: 1.15rem; font-weight: 700; margin-bottom: 1.25rem; }
.features-table { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 2rem; }
.features-table table { width: 100%; border-collapse: collapse; }
.features-table th { background: var(--bg-tertiary); padding: .8rem 1.25rem; text-align: left; font-size: .82rem; text-transform: uppercase; letter-spacing: .06em; color: var(--text-secondary); }
.features-table td { padding: .8rem 1.25rem; border-top: 1px solid var(--border); font-size: .9rem; }
.features-table tr:hover td { background: var(--bg-tertiary); }
.check { color: #51cf66; font-weight: 700; }
.cross { color: #ff6b6b; }
.highlight-row td { color: var(--text-primary); font-weight: 500; }

.faq-section { margin-bottom: 2rem; }
.faq-section h2 { font-size: 1.15rem; font-weight: 700; margin-bottom: 1.25rem; }
.faq-item { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 1.1rem 1.25rem; margin-bottom: .75rem; }
.faq-item .q { font-weight: 600; margin-bottom: .4rem; }
.faq-item .a { color: var(--text-secondary); font-size: .9rem; }
</style>

<div class="prem-hero">
    <div>💎</div>
    <h1>Fahrstuhl Premium</h1>
    <p>Mehr Power, mehr Trolls, mehr Spaß</p>
    <?php if ($isPro): ?>
        <div class="status-badge status-active">👑 Pro aktiv — noch <?= (int)$daysLeft ?> Tage</div>
    <?php elseif ($isPremium): ?>
        <div class="status-badge status-active">✅ Premium aktiv — noch <?= (int)$daysLeft ?> Tage</div>
    <?php elseif ($isLoggedIn): ?>
        <div class="status-badge status-inactive">🔒 Kein aktives Premium</div>
    <?php else: ?>
        <div class="status-badge status-inactive">Melde dich mit Discord an, um zu kaufen</div>
    <?php endif; ?>
</div>

<!-- Plans -->
<div class="plans-grid">
    <div class="plan-card">
        <div>
            <div class="plan-name">🆓 Free</div>
            <div class="plan-price">0€ <span>/ immer</span></div>
        </div>
        <ul class="feature-list">
            <li class="included"><span class="icon">✅</span> Alle Basis-Troll-Commands</li>
            <li class="included"><span class="icon">✅</span> Ghost, Mute, Mirror, Deafen laufen <?= esc(pricingDuration($freeTier['trollDurationMs'])) ?></li>
            <li class="included"><span class="icon">✅</span> <?= esc(pricingDuration($freeTier['cooldownMs'])) ?> Cooldown</li>
            <li class="included"><span class="icon">✅</span> Shield alle <?= esc(pricingDuration($freeTier['shieldClaimCooldownMs'])) ?>, schützt <?= esc(pricingDuration($freeTier['shieldDurationMs'])) ?></li>
            <li class="included"><span class="icon">✅</span> Shield-System</li>
            <li class="included"><span class="icon">✅</span> Daily Claim</li>
            <li class="included"><span class="icon">✅</span> Vote Rewards (top.gg)</li>
            <li><span class="icon">❌</span> Keine Bonus-Shields</li>
            <li><span class="icon">❌</span> Kein XP-Boost</li>
            <li><span class="icon">❌</span> /claim nur mit Support-Server-Mitgliedschaft</li>
        </ul>
        <?php if (!$isPremium): ?>
            <span class="btn-buy btn-outline" style="cursor:default; opacity:.5;">Aktueller Plan</span>
        <?php else: ?>
            <a href="<?= esc($supportUrl) ?>" target="_blank" rel="noopener" class="btn-buy btn-outline">💬 Support</a>
        <?php endif; ?>
    </div>

    <div class="plan-card featured">
        <div>
            <div class="plan-name">💎 Premium <span class="badge-featured">Popular</span></div>
            <div class="plan-price"><?= esc(pricingFormat($basicTier['priceMonthly'])) ?> <span>/ Monat</span></div>
            <div class="plan-lifetime">oder <strong><?= esc(pricingFormat($basicTier['priceLifetime'])) ?></strong> einmalig — läuft nie ab</div>
        </div>
        <ul class="feature-list">
            <li class="included"><span class="icon">✅</span> Alles aus Free</li>
            <li class="included"><span class="icon">✅</span> Ghost, Mute, Mirror, Deafen laufen <?= esc(pricingDuration($basicTier['trollDurationMs'])) ?></li>
            <li class="included"><span class="icon">✅</span> /notifysettings (DM-Alerts)</li>
            <li class="included"><span class="icon">✅</span> Nur <?= esc(pricingDuration($basicTier['cooldownMs'])) ?> Cooldown (<?= (int)pricingCooldownSaving('basic') ?>% kürzer)</li>
            <li class="included"><span class="icon">🛡️</span> Shield alle <?= esc(pricingDuration($basicTier['shieldClaimCooldownMs'])) ?> statt <?= esc(pricingDuration($freeTier['shieldClaimCooldownMs'])) ?></li>
            <li class="included"><span class="icon">🛡️</span> Ein Shield schützt <?= esc(pricingDuration($basicTier['shieldDurationMs'])) ?> statt <?= esc(pricingDuration($freeTier['shieldDurationMs'])) ?></li>
            <li class="included"><span class="icon">🎁</span> +<?= (int)$basicTier['monthlyBonusShields'] ?> Bonus-Shields jeden Monat</li>
            <li class="included"><span class="icon">📈</span> <?= esc(rtrim(rtrim(number_format($basicTier['xpMultiplier'], 1, ',', ''), '0'), ',')) ?>× XP im Leveling</li>
            <li class="included"><span class="icon">✅</span> /claim ohne Pflicht-Mitgliedschaft</li>
            <li class="included"><span class="icon">🎨</span> Eigene Farbe für deine Troll-Embeds</li>
            <li class="included"><span class="icon">✅</span> Prioritäts-Support</li>
            <li class="included"><span class="icon">✅</span> 💎 Premium-Badge im Bot</li>
            <li><span class="icon">❌</span> Eigene Troll-Nachricht</li>
            <li><span class="icon">❌</span> Multi-Target Elevator</li>
        </ul>
        <?php if ($isPro): ?>
            <a href="<?= esc($supportUrl) ?>" target="_blank" rel="noopener" class="btn-buy btn-outline">💬 Support</a>
        <?php elseif ($isPremium): ?>
            <span class="btn-buy btn-gold" style="cursor:default;">✅ Aktiv (noch <?= (int)$daysLeft ?> Tage)</span>
            <a href="<?= esc(premiumBuyUrl('basic', 'monthly', $isLoggedIn, $userId, $loginUrl)) ?>" class="btn-buy btn-outline">🔄 Verlängern</a>
        <?php else: ?>
            <a href="<?= esc(premiumBuyUrl('basic', 'monthly', $isLoggedIn, $userId, $loginUrl)) ?>" class="btn-buy btn-gold">
                <?= $isLoggedIn ? '💎 Monatlich holen' : '💎 Mit Discord anmelden & kaufen' ?>
            </a>
            <?php if ($isLoggedIn): ?>
                <a href="<?= esc(premiumBuyUrl('basic', 'lifetime', $isLoggedIn, $userId, $loginUrl)) ?>" class="btn-buy btn-outline">♾️ Lifetime <?= esc(pricingFormat($basicTier['priceLifetime'])) ?></a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="plan-card">
        <div>
            <div class="plan-name">👑 Pro</div>
            <div class="plan-price"><?= esc(pricingFormat($proTier['priceMonthly'])) ?> <span>/ Monat</span></div>
            <div class="plan-lifetime">oder <strong><?= esc(pricingFormat($proTier['priceLifetime'])) ?></strong> einmalig — läuft nie ab</div>
        </div>
        <ul class="feature-list">
            <li class="included"><span class="icon">✅</span> Alles aus Premium</li>
            <li class="included"><span class="icon">✅</span> Ghost, Mute, Mirror, Deafen laufen <?= esc(pricingDuration($proTier['trollDurationMs'])) ?></li>
            <li class="included"><span class="icon">✅</span> Nur <?= esc(pricingDuration($proTier['cooldownMs'])) ?> Cooldown (<?= (int)pricingCooldownSaving('pro') ?>% kürzer)</li>
            <li class="included"><span class="icon">🛡️</span> Shield alle <?= esc(pricingDuration($proTier['shieldClaimCooldownMs'])) ?> — schützt <?= esc(pricingDuration($proTier['shieldDurationMs'])) ?></li>
            <li class="included"><span class="icon">🎁</span> +<?= (int)$proTier['monthlyBonusShields'] ?> Bonus-Shields jeden Monat</li>
            <li class="included"><span class="icon">📈</span> <?= esc(rtrim(rtrim(number_format($proTier['xpMultiplier'], 1, ',', ''), '0'), ',')) ?>× XP im Leveling</li>
            <li class="included"><span class="icon">✅</span> 👑 Pro-Badge im Bot</li>
            <li class="included"><span class="icon">✅</span> /settrollmessage (Custom Text)</li>
            <li class="included"><span class="icon">✅</span> Custom Nachricht auf allen Trolls</li>
            <li class="included"><span class="icon">✅</span> Multi-Target Elevator für bis zu <?= (int)$proTier['maxElevatorTargets'] ?> User</li>
            <li class="included"><span class="icon">✅</span> Multi-Server Admin-Zugang</li>
            <li class="included"><span class="icon">✅</span> Direkter Admin-Kontakt</li>
        </ul>
        <?php if ($isPro): ?>
            <span class="btn-buy btn-gold" style="cursor:default;">👑 Aktiv (noch <?= (int)$daysLeft ?> Tage)</span>
            <a href="<?= esc(premiumBuyUrl('pro', 'monthly', $isLoggedIn, $userId, $loginUrl)) ?>" class="btn-buy btn-outline">🔄 Verlängern</a>
        <?php else: ?>
            <a href="<?= esc(premiumBuyUrl('pro', 'monthly', $isLoggedIn, $userId, $loginUrl)) ?>" class="btn-buy btn-gold">
                <?= $isLoggedIn ? '👑 Monatlich holen' : '👑 Mit Discord anmelden & kaufen' ?>
            </a>
            <?php if ($isLoggedIn): ?>
                <a href="<?= esc(premiumBuyUrl('pro', 'lifetime', $isLoggedIn, $userId, $loginUrl)) ?>" class="btn-buy btn-outline">♾️ Lifetime <?= esc(pricingFormat($proTier['priceLifetime'])) ?></a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Feature Comparison -->
<div class="features-section">
    <h2>📋 Feature-Vergleich</h2>
    <div class="features-table">
        <table>
            <thead>
                <tr>
                    <th>Feature</th>
                    <th>Free</th>
                    <th>Premium</th>
                    <th>Pro</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $xpLabel = fn($t) => rtrim(rtrim(number_format($t['xpMultiplier'], 1, ',', ''), '0'), ',') . '×';
                $yn = fn($v) => $v ? '<td class="check">✅</td>' : '<td class="cross">❌</td>';
                ?>
                <tr class="highlight-row"><td>Troll-Commands (/elevator, /ghost, etc.)</td><td class="check">✅</td><td class="check">✅</td><td class="check">✅</td></tr>
                <tr><td>Ghost / Mute / Mirror / Deafen Dauer</td><td><?= esc(pricingDuration($freeTier['trollDurationMs'])) ?></td><td><?= esc(pricingDuration($basicTier['trollDurationMs'])) ?></td><td><?= esc(pricingDuration($proTier['trollDurationMs'])) ?></td></tr>
                <tr><td>Command-Cooldown</td><td><?= esc(pricingDuration($freeTier['cooldownMs'])) ?></td><td><?= esc(pricingDuration($basicTier['cooldownMs'])) ?></td><td><?= esc(pricingDuration($proTier['cooldownMs'])) ?></td></tr>
                <tr class="highlight-row"><td>🛡️ Neues Shield alle</td><td><?= esc(pricingDuration($freeTier['shieldClaimCooldownMs'])) ?></td><td><?= esc(pricingDuration($basicTier['shieldClaimCooldownMs'])) ?></td><td><?= esc(pricingDuration($proTier['shieldClaimCooldownMs'])) ?></td></tr>
                <tr class="highlight-row"><td>🛡️ Ein Shield schützt</td><td><?= esc(pricingDuration($freeTier['shieldDurationMs'])) ?></td><td><?= esc(pricingDuration($basicTier['shieldDurationMs'])) ?></td><td><?= esc(pricingDuration($proTier['shieldDurationMs'])) ?></td></tr>
                <tr><td>🎁 Bonus-Shields pro Monat</td><td>—</td><td>+<?= (int)$basicTier['monthlyBonusShields'] ?></td><td>+<?= (int)$proTier['monthlyBonusShields'] ?></td></tr>
                <tr><td>📈 XP-Boost im Leveling</td><td><?= esc($xpLabel($freeTier)) ?></td><td><?= esc($xpLabel($basicTier)) ?></td><td><?= esc($xpLabel($proTier)) ?></td></tr>
                <tr><td>/claim ohne Pflicht-Mitgliedschaft im Support-Server</td><?= $yn(!$freeTier['claimRequiresDevServer']) ?><?= $yn(!$basicTier['claimRequiresDevServer']) ?><?= $yn(!$proTier['claimRequiresDevServer']) ?></tr>
                <tr><td>Shield-System & Daily Claim</td><td class="check">✅</td><td class="check">✅</td><td class="check">✅</td></tr>
                <tr><td>top.gg Vote Rewards</td><td class="check">✅</td><td class="check">✅</td><td class="check">✅</td></tr>
                <tr class="highlight-row"><td>/notifysettings (DM wenn du getrollt wirst)</td><?= $yn($freeTier['notifySettings']) ?><?= $yn($basicTier['notifySettings']) ?><?= $yn($proTier['notifySettings']) ?></tr>
                <tr><td>🎨 Eigene Farbe für Troll-Embeds (/trollcolor)</td><?= $yn($freeTier['customEmbedColor']) ?><?= $yn($basicTier['customEmbedColor']) ?><?= $yn($proTier['customEmbedColor']) ?></tr>
                <tr><td>Prioritäts-Support im Discord</td><td class="cross">❌</td><td class="check">✅</td><td class="check">✅</td></tr>
                <tr><td>Premium-Badge (💎 / 👑) in /status</td><td class="cross">❌</td><td class="check">✅</td><td class="check">✅</td></tr>
                <tr><td>Elevator Multi-Target</td><td><?= (int)$freeTier['maxElevatorTargets'] ?> User</td><td><?= (int)$basicTier['maxElevatorTargets'] ?> User</td><td><?= (int)$proTier['maxElevatorTargets'] ?> User</td></tr>
                <tr class="highlight-row"><td>/settrollmessage (Custom Troll-Nachricht)</td><?= $yn($freeTier['customTrollMessage']) ?><?= $yn($basicTier['customTrollMessage']) ?><?= $yn($proTier['customTrollMessage']) ?></tr>
                <tr><td>Multi-Server Admin-Zugang</td><td class="cross">❌</td><td class="cross">❌</td><td class="check">✅</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- FAQ -->
<div class="faq-section">
    <h2>❓ Häufige Fragen</h2>
    <?php if ($hasCheckout): ?>
    <div class="faq-item">
        <div class="q">Wie kaufe ich Premium?</div>
        <div class="a">Über <a href="https://shop.eselbande.com" target="_blank" rel="noopener">shop.eselbande.com</a> — dort mit Discord anmelden und bezahlen. Die Freischaltung passiert automatisch, direkt nach der Zahlung — du bekommst eine DM vom Bot.</div>
    </div>
    <div class="faq-item">
        <div class="q">Welche Zahlungsmethoden gibt es?</div>
        <div class="a">Kreditkarte, PayPal, Apple&nbsp;Pay und weitere — je nachdem, was in deinem Land verfügbar ist. Die Zahlung läuft über Paddle als unseren Merchant of Record, wir sehen deine Zahlungsdaten nie.</div>
    </div>
    <?php else: ?>
    <div class="faq-item">
        <div class="q">Wie kaufe ich Premium?</div>
        <div class="a">Der Online-Checkout ist gerade nicht aktiv. Tritt so lange unserem Support-Server bei und schreib uns — wir schalten dich manuell frei.</div>
    </div>
    <?php endif; ?>
    <div class="faq-item">
        <div class="q">Monatlich oder Lifetime — was ist der Unterschied?</div>
        <div class="a">Monatlich läuft nach 30 Tagen aus und wird nicht automatisch abgebucht — es ist kein Abo, du entscheidest jedes Mal neu. Lifetime zahlst du einmal und behältst den Plan dauerhaft.</div>
    </div>
    <div class="faq-item">
        <div class="q">Was passiert, wenn ich verlängere, bevor mein Plan abläuft?</div>
        <div class="a">Die neuen Tage werden auf deine Restlaufzeit <strong>draufgerechnet</strong>. Du verlierst nichts, wenn du früh verlängerst.</div>
    </div>
    <div class="faq-item">
        <div class="q">Kann ich kündigen?</div>
        <div class="a">Es gibt nichts zu kündigen — es wird nie automatisch abgebucht. Der Plan läuft einfach aus, wenn du ihn nicht verlängerst.</div>
    </div>
    <div class="faq-item">
        <div class="q">Was passiert, wenn Premium abläuft?</div>
        <div class="a">Dein Account wechselt zurück zum Free-Plan. Alle gespeicherten Daten (Shields etc.) bleiben erhalten.</div>
    </div>
    <div class="faq-item">
        <div class="q">Gilt Premium für mich oder für meinen Server?</div>
        <div class="a">Die Pläne hier gelten für <strong>dich persönlich</strong> — auf jedem Server, auf dem der Bot ist. Server-Pläne, die für alle Mitglieder eines Servers gelten, vergeben wir separat; frag dafür im Support-Server nach.</div>
    </div>
</div>

<?php if (!$isLoggedIn): ?>
<div class="faq-section">
    <a href="<?= esc($loginUrl) ?>" class="btn-buy btn-gold" style="max-width:340px;margin:0 auto;">Mit Discord anmelden</a>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

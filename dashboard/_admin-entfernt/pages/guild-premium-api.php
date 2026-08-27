<?php
/**
 * Guild Premium API Handler (AJAX endpoint)
 * Grants / revokes / looks up server-level premium by guild ID.
 *
 * A server carries its OWN plan (premium_guilds). The guild owner's personal
 * premium is only consulted as a fallback, so servers entitled under the old
 * owner-coupled model keep working until their plan is re-issued properly.
 */
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

header('Content-Type: application/json');

/** Effective plan for a guild: own plan first, owner's personal premium as fallback. */
function gpEffectivePlan(array $guildPlansById, $guildId, $ownerId) {
    if (isset($guildPlansById[$guildId])) {
        $plan = $guildPlansById[$guildId];
        return [
            'isPremium' => true,
            'isPro'     => ($plan['tier'] ?? '') === 'pro',
            'tier'      => ($plan['tier'] ?? '') === 'pro' ? 'pro' : 'basic',
            'expiresAt' => $plan['expires_at'] ?? null,
            'source'    => 'guild',
        ];
    }
    if ($ownerId) {
        $premRaw   = getAPI('/premium/user/' . urlencode($ownerId), 6);
        $isPremium = $premRaw['data']['isPremium'] ?? false;
        if ($isPremium) {
            $isPro    = $premRaw['data']['isPro'] ?? false;
            $premUser = $premRaw['data']['user'] ?? null;
            return [
                'isPremium' => true,
                'isPro'     => $isPro,
                'tier'      => $isPro ? 'pro' : 'basic',
                'expiresAt' => $premUser['expires_at'] ?? null,
                'source'    => 'owner',
            ];
        }
    }
    return ['isPremium' => false, 'isPro' => false, 'tier' => 'free', 'expiresAt' => null, 'source' => 'none'];
}

/** guild_id => plan row, from a single API call. */
function gpGuildPlansById() {
    $raw = getAPI('/premium/guild-plans', 10);
    $out = [];
    foreach (($raw['data']['plans'] ?? []) as $plan) {
        if (!empty($plan['guild_id'])) $out[(string)$plan['guild_id']] = $plan;
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'lookup';
    $guildId = trim((string)($_GET['guildId'] ?? ''));

    if ($action === 'lookup') {
        if (!$guildId || !preg_match('/^\d{17,20}$/', $guildId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Ungültige Guild-ID']);
            exit;
        }

        $guildsRaw = getAPI('/guilds', 8);
        $guilds    = $guildsRaw['data']['guilds'] ?? [];
        $guild     = null;
        foreach ($guilds as $g) {
            if (($g['id'] ?? '') === $guildId) { $guild = $g; break; }
        }

        if (!$guild) {
            http_response_code(404);
            echo json_encode(['error' => 'Server nicht gefunden (Bot ist möglicherweise nicht auf diesem Server)']);
            exit;
        }

        $ownerId = $guild['ownerId'] ?? '';
        $plan    = gpEffectivePlan(gpGuildPlansById(), $guildId, $ownerId);

        echo json_encode([
            'success' => true,
            'guild'   => [
                'id'          => $guild['id'],
                'name'        => $guild['name'],
                'memberCount' => $guild['memberCount'] ?? 0,
                'icon'        => $guild['icon'] ?? null,
                'ownerId'     => $ownerId,
            ],
            // Key name kept for the existing frontend; it now carries the
            // EFFECTIVE plan, with `source` saying where it came from.
            'ownerPremium' => $plan,
            'planSource'   => $plan['source'],
        ]);
        exit;
    }

    if ($action === 'list') {
        $guildsRaw  = getAPI('/guilds', 10);
        $guilds     = $guildsRaw['data']['guilds'] ?? [];
        $guildPlans = gpGuildPlansById();

        $result = [];
        foreach ($guilds as $g) {
            $gid     = (string)($g['id'] ?? '');
            $ownerId = $g['ownerId'] ?? '';
            $plan    = gpEffectivePlan($guildPlans, $gid, $ownerId);
            if (!$plan['isPremium']) continue;

            $result[] = [
                'guildId'   => $gid,
                'guildName' => $g['name'] ?? $gid,
                'guildIcon' => $g['icon'] ?? null,
                'ownerId'   => $ownerId,
                'tier'      => $plan['tier'],
                'expiresAt' => $plan['expiresAt'],
                'source'    => $plan['source'],
            ];
        }

        echo json_encode(['success' => true, 'grants' => $result]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unbekannte Aktion']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_GET['action'] ?? 'activate';
$data   = json_decode(file_get_contents('php://input'), true) ?? [];

$guildId = trim((string)($data['guildId'] ?? ''));
$days    = max(1, min(3650, (int)($data['days'] ?? 30)));
$rawTier = trim((string)($data['tier'] ?? 'pro'));
$tier    = in_array($rawTier, ['basic', 'pro'], true) ? $rawTier : 'pro';

if (!$guildId || !preg_match('/^\d{17,20}$/', $guildId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige Guild-ID']);
    exit;
}

// Resolve guild → owner ID
$guildsRaw = getAPI('/guilds', 8);
$guilds    = $guildsRaw['data']['guilds'] ?? [];
$guild     = null;
foreach ($guilds as $g) {
    if (($g['id'] ?? '') === $guildId) { $guild = $g; break; }
}

if (!$guild) {
    http_response_code(404);
    echo json_encode(['error' => 'Server nicht gefunden (Bot muss auf dem Server sein)']);
    exit;
}

$ownerId = trim((string)($guild['ownerId'] ?? ''));
if (!$ownerId || !preg_match('/^\d{17,20}$/', $ownerId)) {
    http_response_code(500);
    echo json_encode(['error' => 'Owner-ID konnte nicht ermittelt werden']);
    exit;
}

// Server plans now write to the guild's OWN entitlement (/premium/guild/*)
// instead of the owner's personal premium account. The old behaviour handed the
// owner Pro on every server they owned and made the plan disappear if ownership
// changed. $ownerId is still resolved above so it can be recorded as the buyer.
switch ($action) {
    case 'activate':
        // 'set' = absolute term. Without it this was byte-identical to 'extend'
        // and there was no way to pin a plan to exactly N days.
        $result = api('/premium/guild/activate', 'POST', [
            'guildId'     => $guildId,
            'daysValid'   => $days,
            'tier'        => $tier,
            'purchasedBy' => $ownerId,
            'mode'        => 'set',
        ]);
        $ok        = $result['data']['success'] ?? false;
        $newExpiry = $result['data']['data']['expiresAt'] ?? null;
        $newExpiry = $newExpiry ? date('Y-m-d', strtotime($newExpiry)) : (new DateTime())->modify("+{$days} days")->format('Y-m-d');
        echo json_encode([
            'success'   => $ok,
            'guildId'   => $guildId,
            'guildName' => $guild['name'] ?? $guildId,
            'ownerId'   => $ownerId,
            'tier'      => $tier,
            'days'      => $days,
            'expiresAt' => $newExpiry,
            'message'   => $ok
                ? "✅ Server-Plan ({$tier}, {$days} Tage) für «{$guild['name']}» aktiviert — gilt nur für diesen Server."
                : "❌ Aktivierung fehlgeschlagen.",
        ]);
        break;

    case 'extend':
        // The API adds days on top of any remaining time by itself now, so the
        // dashboard no longer has to read the current expiry and pre-sum it.
        $result = api('/premium/guild/activate', 'POST', [
            'guildId'     => $guildId,
            'daysValid'   => $days,
            'tier'        => $tier,
            'purchasedBy' => $ownerId,
            'mode'        => 'extend',
        ]);
        $ok        = $result['data']['success'] ?? false;
        $newExpiry = $result['data']['data']['expiresAt'] ?? null;
        $newExpiry = $newExpiry ? date('Y-m-d', strtotime($newExpiry)) : null;
        echo json_encode([
            'success'   => $ok,
            'guildId'   => $guildId,
            'guildName' => $guild['name'] ?? $guildId,
            'ownerId'   => $ownerId,
            'tier'      => $tier,
            'days'      => $days,
            'expiresAt' => $newExpiry,
            'message'   => $ok
                ? "✅ Server-Plan um {$days} Tage verlängert" . ($newExpiry ? " (neu bis {$newExpiry})." : ".")
                : "❌ Verlängerung fehlgeschlagen.",
        ]);
        break;

    case 'deactivate':
        // Removing the guild's own plan is not enough on its own: if the server
        // was entitled through the OWNER-fallback, that UPDATE touches zero rows,
        // still reports success, and the server stays premium. Detect that case
        // and say so instead of claiming a revoke that didn't happen — we
        // deliberately do NOT strip the owner's personal premium here, because
        // that would also revoke every other server they own.
        $plansById = gpGuildPlansById();
        $hadOwnPlan = isset($plansById[$guildId]);
        $effective  = gpEffectivePlan($plansById, $guildId, $ownerId);

        $result = api('/premium/guild/deactivate', 'POST', ['guildId' => $guildId]);
        $ok     = $result['data']['success'] ?? false;

        if ($ok && !$hadOwnPlan && $effective['source'] === 'owner') {
            echo json_encode([
                'success'   => false,
                'guildId'   => $guildId,
                'guildName' => $guild['name'] ?? $guildId,
                'ownerId'   => $ownerId,
                'message'   => "⚠️ «{$guild['name']}» hat keinen eigenen Server-Plan — "
                             . "der Premium-Status kommt vom Privat-Account des Owners ({$ownerId}). "
                             . "Den kannst du nur unter «Premium Management» entziehen, das betrifft aber "
                             . "alle Server dieser Person.",
            ]);
            break;
        }

        echo json_encode([
            'success'   => $ok,
            'guildId'   => $guildId,
            'guildName' => $guild['name'] ?? $guildId,
            'ownerId'   => $ownerId,
            'message'   => $ok
                ? "✅ Server-Plan für «{$guild['name']}» deaktiviert."
                : "❌ Deaktivierung fehlgeschlagen.",
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unbekannte Aktion']);
}

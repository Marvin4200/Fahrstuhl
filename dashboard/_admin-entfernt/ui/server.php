<?php
/**
 * Server-Detail — alles zu einem Server unter Tabs statt auf sechs Seiten.
 *
 * Zugriff: gleiche Prüfung wie im alten Dashboard (isAdmin oder echte
 * Admin-Rechte auf genau diesem Server). Die Prüfung stammt aus config.php und
 * ist die, die heute gegen IDOR abgesichert wurde — hier nicht neu erfinden.
 */
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/_layout.php';

$guildId = trim((string)($_GET['id'] ?? ''));
if ($guildId === '' || !preg_match('/^\d{17,20}$/', $guildId)) {
    header('Location: ' . ui_url('servers.php'));
    exit;
}
if (!isAdmin() && !isServerAdmin($guildId)) {
    http_response_code(403);
    ui_head('servers', 'Kein Zugriff');
    echo ui_status_strip('crit', 'Kein Zugriff auf diesen Server',
        'Du brauchst Administrator-Rechte auf diesem Discord-Server.',
        'Zurück zur Übersicht', ui_url('servers.php'));
    ui_foot();
    exit;
}

$gRaw  = getAPI('/guilds/' . urlencode($guildId), 10);
$guild = $gRaw['data']['data'] ?? $gRaw['data'] ?? [];
if (!$guild || empty($guild['id'])) {
    ui_head('servers', 'Server nicht gefunden');
    echo ui_status_strip('crit', 'Server nicht gefunden', 'Ist der Bot noch auf diesem Server?',
        'Zurück', ui_url('servers.php'));
    ui_foot();
    exit;
}

$modRaw  = getAPI('/guilds/' . urlencode($guildId) . '/modules', 10);
$modules = $modRaw['data']['data']['modules'] ?? $modRaw['data']['modules'] ?? [];

$premRaw = getAPI('/guilds/' . urlencode($guildId) . '/premium', 8);
$prem    = $premRaw['data']['data'] ?? $premRaw['data'] ?? [];

$healthRaw = getAPI('/guilds/' . urlencode($guildId) . '/setup-health', 8);
$setup     = $healthRaw['data']['data'] ?? $healthRaw['data'] ?? [];
$issues    = $setup['issues'] ?? $setup['warnings'] ?? [];

$name = (string)($guild['name'] ?? $guildId);
$tier = strtolower((string)($prem['tier'] ?? 'free'));

ui_head('servers', $name, $guildId);

if (!empty($issues)) {
    $first = $issues[0];
    echo ui_status_strip('warn',
        count($issues) === 1 ? 'Ein Punkt in der Einrichtung fehlt' : count($issues) . ' Punkte in der Einrichtung fehlen',
        is_array($first) ? (string)($first['message'] ?? $first['label'] ?? '') : (string)$first);
} else {
    echo ui_status_strip('ok', 'Server sauber eingerichtet', 'Keine offenen Punkte.');
}
?>

<div class="grid grid-4">
    <?= ui_stat('Mitglieder', ui_num($guild['memberCount'] ?? 0)) ?>
    <?= ui_stat('Plan', $tier === 'pro' ? 'Pro' : ($tier === 'basic' ? 'Premium' : 'Free'),
        !empty($prem['planSource']) && $prem['planSource'] === 'owner' ? 'geerbt vom Owner' : '') ?>
    <?= ui_stat('Kanäle', ui_num($guild['channelCount'] ?? count($guild['channels'] ?? []))) ?>
    <?= ui_stat('Rollen', ui_num($guild['roleCount'] ?? count($guild['roles'] ?? []))) ?>
</div>

<div class="card">
    <div class="tabs" role="tablist">
        <button class="tab" role="tab" data-tab="module" aria-selected="true">Module</button>
        <button class="tab" role="tab" data-tab="setup" aria-selected="false">Einrichtung</button>
        <button class="tab" role="tab" data-tab="plan" aria-selected="false">Plan</button>
    </div>

    <div class="card-body" data-panel="module">
        <?php if (!$modules): ?>
            <?= ui_empty('Keine Modul-Daten', 'Der Bot hat für diesen Server nichts geliefert.') ?>
        <?php else: ?>
            <div class="grid grid-2">
            <?php foreach ($modules as $key => $m):
                $enabled = is_array($m) ? !empty($m['enabled']) : (bool)$m;
                $label   = is_array($m) ? (string)($m['label'] ?? $key) : (string)$key;
                $desc    = is_array($m) ? (string)($m['description'] ?? '') : '';
            ?>
                <div style="display:flex;align-items:center;gap:var(--s3)">
                    <?= $enabled ? ui_badge('An', 'ok') : ui_badge('Aus', 'mute') ?>
                    <div>
                        <div class="cell-title"><?= esc($label) ?></div>
                        <?php if ($desc !== ''): ?><div class="cell-sub"><?= esc($desc) ?></div><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-body" data-panel="setup" hidden>
        <?php if (!$issues): ?>
            <?= ui_empty('Alles eingerichtet', 'Für diesen Server ist nichts offen.') ?>
        <?php else: ?>
            <div class="feed">
            <?php foreach ($issues as $i):
                $msg = is_array($i) ? (string)($i['message'] ?? $i['label'] ?? '') : (string)$i;
                $sev = is_array($i) && ($i['severity'] ?? '') === 'critical' ? 'crit' : 'warn';
                echo ui_feed_item($sev, $msg, is_array($i) ? (string)($i['module'] ?? '') : '');
            endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-body" data-panel="plan" hidden>
        <div class="grid grid-2">
            <?= ui_stat('Aktueller Plan', $tier === 'pro' ? 'Pro' : ($tier === 'basic' ? 'Premium' : 'Free')) ?>
            <?= ui_stat('Läuft bis', !empty($prem['premiumUntil']) ? date('d.m.Y', strtotime($prem['premiumUntil'])) : '—') ?>
        </div>
        <?php if (($prem['planSource'] ?? '') === 'owner'): ?>
            <div style="margin-top:var(--s4)">
                <?= ui_badge('Achtung', 'warn') ?>
                <span class="cell-sub">Dieser Server hat keinen eigenen Plan — der Premium-Status kommt vom
                Privat-Account des Owners und gilt damit für alle seine Server.</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php ui_foot(); ?>

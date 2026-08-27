<?php
/**
 * Cockpit — die Startseite.
 *
 * Beantwortet in dieser Reihenfolge: Brennt was? Wie stehen die Zahlen? Was
 * will eine Handlung von mir? Erst danach Details. Das alte Dashboard hatte
 * dafür vier separate Seiten (cockpit, status, analytics, activity), die sich
 * überlappt haben.
 *
 * Alles kommt aus EINEM API-Aufruf (/dashboard/cockpit).
 */

require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/_layout.php';

$snapRaw = getAPI('/dashboard/cockpit', 12);
$snap    = $snapRaw['data']['data'] ?? $snapRaw['data'] ?? [];
$apiDown = empty($snap);

$bot       = $snap['bot'] ?? [];
$analytics = $snap['analytics'] ?? [];
$premium   = $snap['premium'] ?? [];
$expiring  = $snap['premiumExpiring'] ?? [];
$revenue   = $snap['revenue'] ?? [];
$health    = $snap['health'] ?? [];
$warnings  = $health['warnings'] ?? [];
$deploy    = $snap['deploy']['fahrstuhl'] ?? null;

// ── Status: die eine Zeile, die zählt ───────────────────────────────────────
if ($apiDown) {
    $tone = 'crit';
    $headline = 'Keine Verbindung zum Bot';
    $detail = 'Die API antwortet nicht. Läuft der Container?';
} elseif (!empty($warnings)) {
    $tone = count($warnings) > 2 ? 'crit' : 'warn';
    $headline = count($warnings) === 1 ? 'Ein Punkt braucht Aufmerksamkeit' : count($warnings) . ' Punkte brauchen Aufmerksamkeit';
    $first = $warnings[0];
    $detail = is_array($first) ? (string)($first['message'] ?? $first['label'] ?? '') : (string)$first;
} else {
    $tone = 'ok';
    $headline = 'Alles läuft';
    $detail = ($bot['guilds'] ?? 0) . ' Server verbunden · seit ' . ui_duration($bot['uptime'] ?? 0) . ' ohne Neustart';
}

ui_head('index', 'Cockpit', date('d.m. H:i'));
echo ui_status_strip($tone, $headline, $detail,
    $apiDown ? '' : 'Details', $apiDown ? '' : ui_url('operations.php'));
?>

<div class="grid grid-4">
    <?= ui_stat('Server', ui_num($bot['guilds'] ?? 0), ui_num($bot['totalMembers'] ?? 0) . ' Mitglieder') ?>
    <?= ui_stat('Commands gesamt', ui_num($analytics['totalCommands'] ?? 0), ui_num($analytics['activeUsers'] ?? 0) . ' aktive Nutzer') ?>
    <?= ui_stat('Premium aktiv', ui_num($premium['active'] ?? 0),
        ($premium['expiringSoon'] ?? 0) > 0 ? $premium['expiringSoon'] . ' laufen bald ab' : 'keine Abläufe', 
        ($premium['expiringSoon'] ?? 0) > 0 ? 'down' : '') ?>
    <?= ui_stat('Umsatz / Monat', ui_money($revenue['monthly'] ?? 0), ui_money($revenue['total'] ?? 0) . ' gesamt') ?>
</div>

<div class="grid grid-2">
    <?php
    // ── Braucht dich ────────────────────────────────────────────────────────
    echo ui_card_head_only('Braucht dich');
    echo '<div class="feed">';
    $any = false;

    foreach (array_slice($warnings, 0, 4) as $w) {
        $any = true;
        $msg = is_array($w) ? (string)($w['message'] ?? $w['label'] ?? 'Warnung') : (string)$w;
        $sev = is_array($w) && ($w['severity'] ?? '') === 'critical' ? 'crit' : 'warn';
        echo ui_feed_item($sev, $msg, 'Systemzustand', 'Ansehen', ui_url('operations.php'));
    }

    foreach (array_slice($expiring, 0, 3) as $u) {
        $any = true;
        $days = (int)($u['daysRemaining'] ?? 0);
        echo ui_feed_item($days <= 3 ? 'crit' : 'warn',
            ($u['username'] ?? $u['userId'] ?? 'Nutzer') . ' — ' . ($u['tier'] === 'pro' ? 'Pro' : 'Premium') . ' läuft in ' . $days . ' Tagen ab',
            'Erinnerung wurde verschickt', 'Verlängern', ui_url('premium.php'));
    }

    if (!$any) echo ui_empty('Nichts offen', 'Keine Warnungen, keine ablaufenden Pläne.');
    echo '</div></div>';

    // ── Top-Commands ────────────────────────────────────────────────────────
    echo ui_card_head_only('Meistgenutzte Commands');
    $top = $analytics['topCommands'] ?? [];
    if (!$top) {
        echo ui_empty('Noch keine Daten', 'Sobald Commands genutzt werden, erscheinen sie hier.');
    } else {
        echo '<div class="table-wrap"><table class="data"><thead><tr><th>Command</th><th class="num">Aufrufe</th></tr></thead><tbody>';
        foreach (array_slice($top, 0, 8) as $c) {
            echo '<tr><td class="mono">/' . esc($c['command'] ?? '?') . '</td>'
               . '<td class="num">' . esc(ui_num($c['count'] ?? 0)) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';
    ?>
</div>

<?php
// ── Letzte Aktivität ────────────────────────────────────────────────────────
echo ui_card_head_only('Zuletzt ausgeführt',
    '<a class="btn btn-sm" href="' . esc(ui_url('servers.php')) . '">Alle Server</a>');
$recent = $analytics['recent'] ?? [];
if (!$recent) {
    echo ui_empty('Noch nichts passiert');
} else {
    echo '<div class="table-wrap"><table class="data"><thead><tr><th>Command</th><th>Nutzer</th><th>Wann</th><th>Ergebnis</th></tr></thead><tbody>';
    foreach (array_slice($recent, 0, 8) as $r) {
        $ok = !isset($r['success']) || $r['success'];
        $ts = !empty($r['timestamp']) ? date('d.m. H:i', is_numeric($r['timestamp']) ? (int)$r['timestamp'] : strtotime($r['timestamp'])) : '—';
        echo '<tr>'
           . '<td class="mono">/' . esc($r['command'] ?? '?') . '</td>'
           . '<td class="mono cell-sub">' . esc($r['user_id'] ?? '—') . '</td>'
           . '<td>' . esc($ts) . '</td>'
           . '<td>' . ($ok ? ui_badge('OK', 'ok') : ui_badge('Fehler', 'crit')) . '</td>'
           . '</tr>';
    }
    echo '</tbody></table></div>';
}
echo '</div>';

if ($deploy) {
    $state = $deploy['state'] ?? 'unknown';
    $tone  = $state === 'success' ? 'ok' : ($state === 'failed' ? 'crit' : 'mute');
    echo ui_card_open('Letztes Deployment', ui_badge($state, $tone));
    echo ui_meta_row([
        '#Commit'   => substr((string)($deploy['commit'] ?? '—'), 0, 12),
        'Branch'    => (string)($deploy['branch'] ?? '—'),
        'Zeitpunkt' => !empty($deploy['updatedAt']) ? date('d.m. H:i', strtotime($deploy['updatedAt'])) : '—',
    ]);
    echo ui_card_close();
}

ui_foot();

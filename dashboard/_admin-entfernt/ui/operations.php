<?php
/**
 * Operations — Systemzustand, Dienste, Deployment.
 * Fasst zusammen, was vorher auf operations, status, backups, security und
 * ops-health verteilt war.
 */
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/_layout.php';
if (!isAdmin()) { header('Location: ' . ui_url()); exit; }

$healthRaw = getAPI('/health/summary', 10);
$health    = $healthRaw['data']['data'] ?? $healthRaw['data'] ?? [];
$warnings  = $health['warnings'] ?? [];

$opsRaw = getAPI('/ops-health', 10);
$ops    = $opsRaw['data']['data'] ?? $opsRaw['data'] ?? [];
$targets = $ops['targets'] ?? $ops['services'] ?? [];

$deployRaw = getAPI('/deploy/status', 8);
$deploy    = $deployRaw['data']['data']['fahrstuhl'] ?? $deployRaw['data']['fahrstuhl'] ?? null;

if (!empty($warnings)) {
    $tone = count($warnings) > 2 ? 'crit' : 'warn';
    $first = $warnings[0];
    $detail = is_array($first) ? (string)($first['message'] ?? $first['label'] ?? '') : (string)$first;
    $headline = count($warnings) === 1 ? 'Ein Punkt braucht Aufmerksamkeit' : count($warnings) . ' Punkte brauchen Aufmerksamkeit';
} else {
    $tone = 'ok'; $headline = 'Alle Systeme normal'; $detail = 'Keine offenen Warnungen.';
}

ui_head('operations', 'Operations', date('d.m. H:i'));
echo ui_status_strip($tone, $headline, $detail);

if (!empty($warnings)) {
    echo ui_card_head_only('Offene Warnungen');
    echo '<div class="feed">';
    foreach ($warnings as $w) {
        $msg = is_array($w) ? (string)($w['message'] ?? $w['label'] ?? 'Warnung') : (string)$w;
        $sev = is_array($w) && ($w['severity'] ?? '') === 'critical' ? 'crit' : 'warn';
        $src = is_array($w) ? (string)($w['source'] ?? $w['check'] ?? '') : '';
        echo ui_feed_item($sev, $msg, $src);
    }
    echo '</div></div>';
}

echo ui_card_head_only('Dienste');
if (!$targets) {
    echo ui_empty('Keine Daten', 'Der Ops-Health-Endpunkt hat nichts geliefert.');
} else {
    echo '<div class="table-wrap"><table class="data"><thead><tr><th>Dienst</th><th>Status</th><th>Detail</th></tr></thead><tbody>';
    foreach ($targets as $t) {
        $name = (string)($t['name'] ?? $t['id'] ?? '?');
        $up   = !empty($t['ok']) || ($t['status'] ?? '') === 'ok' || ($t['status'] ?? '') === 'up';
        $note = (string)($t['detail'] ?? $t['message'] ?? $t['url'] ?? '');
        echo '<tr><td class="mono">' . esc($name) . '</td>'
           . '<td>' . ($up ? ui_badge('Läuft', 'ok') : ui_badge('Problem', 'crit')) . '</td>'
           . '<td class="cell-sub">' . esc($note) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
echo '</div>';

if ($deploy) {
    $state = (string)($deploy['state'] ?? 'unbekannt');
    $tone  = $state === 'success' ? 'ok' : ($state === 'failed' ? 'crit' : 'mute');
    echo ui_card_open('Letztes Deployment', ui_badge($state, $tone));
    echo ui_meta_row([
        '#Commit'   => substr((string)($deploy['commit'] ?? '—'), 0, 12),
        'Branch'    => (string)($deploy['branch'] ?? '—'),
        'Zeitpunkt' => !empty($deploy['updatedAt']) ? date('d.m. H:i', strtotime($deploy['updatedAt'])) : '—',
    ]);
    if (!empty($deploy['error'])) {
        echo '<div style="margin-top:var(--s4)">' . ui_badge('Fehler', 'crit') . ' <span class="cell-sub">' . esc($deploy['error']) . '</span></div>';
    }
    echo ui_card_close();
}

ui_foot();

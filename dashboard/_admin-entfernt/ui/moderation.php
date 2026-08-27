<?php
/**
 * Moderation — Cases, Blacklist und AutoMod-Zustand an einem Ort.
 */
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/_layout.php';

$casesRaw = getAPI('/moderation/cases?limit=100', 12);
$cases    = $casesRaw['data']['data']['cases'] ?? $casesRaw['data']['cases'] ?? [];

$blRaw = isAdmin() ? getAPI('/blacklist/list', 10) : ['data' => []];
$blacklist = $blRaw['data']['data']['entries'] ?? $blRaw['data']['entries'] ?? [];

$open = array_values(array_filter($cases, fn($c) => ($c['status'] ?? '') !== 'resolved'));

if (count($open) > 0) {
    echo ''; // Status folgt nach ui_head
}

ui_head('moderation', 'Moderation', count($cases) . ' Fälle');

if (count($open) > 0) {
    echo ui_status_strip('warn',
        count($open) === 1 ? 'Ein offener Fall' : count($open) . ' offene Fälle',
        'Fälle ohne Abschluss stehen oben in der Liste.');
} else {
    echo ui_status_strip('ok', 'Keine offenen Fälle', 'Alles abgearbeitet.');
}
?>

<div class="grid grid-3">
    <?= ui_stat('Fälle gesamt', ui_num(count($cases))) ?>
    <?= ui_stat('Offen', ui_num(count($open))) ?>
    <?= ui_stat('Blacklist', ui_num(count($blacklist))) ?>
</div>

<?php
echo ui_card_head_only('Moderations-Fälle');
if (!$cases) {
    echo ui_empty('Keine Fälle', 'Es wurde noch nichts moderiert.');
} else {
    usort($cases, fn($a, $b) => (($b['createdAt'] ?? 0) <=> ($a['createdAt'] ?? 0)));
    echo '<div class="table-wrap"><table class="data"><thead><tr>'
       . '<th>Nutzer</th><th>Art</th><th>Grund</th><th>Wann</th><th>Status</th>'
       . '</tr></thead><tbody>';
    foreach (array_slice($cases, 0, 60) as $c) {
        $type   = strtolower((string)($c['type'] ?? '—'));
        $tone   = in_array($type, ['ban', 'kick'], true) ? 'crit' : ($type === 'warn' ? 'warn' : 'mute');
        $status = (string)($c['status'] ?? 'open');
        $when   = !empty($c['createdAt'])
                ? date('d.m. H:i', is_numeric($c['createdAt']) ? (int)$c['createdAt'] / 1000 : strtotime($c['createdAt']))
                : '—';
        echo '<tr>'
           . '<td>' . ui_cell_identity((string)($c['username'] ?? $c['userId'] ?? '?'), (string)($c['userId'] ?? '')) . '</td>'
           . '<td>' . ui_badge($type, $tone) . '</td>'
           . '<td class="cell-sub">' . esc(mb_strimwidth((string)($c['reason'] ?? '—'), 0, 60, '…')) . '</td>'
           . '<td>' . esc($when) . '</td>'
           . '<td>' . ($status === 'resolved' ? ui_badge('erledigt', 'ok') : ui_badge('offen', 'warn')) . '</td>'
           . '</tr>';
    }
    echo '</tbody></table></div>';
}
echo '</div>';

if (isAdmin()) {
    echo ui_card_head_only('Blacklist');
    if (!$blacklist) {
        echo ui_empty('Blacklist ist leer');
    } else {
        echo '<div class="table-wrap"><table class="data"><thead><tr><th>Nutzer</th><th>Grund</th></tr></thead><tbody>';
        foreach (array_slice($blacklist, 0, 40) as $b) {
            echo '<tr>'
               . '<td>' . ui_cell_identity((string)($b['username'] ?? $b['userId'] ?? '?'), (string)($b['userId'] ?? '')) . '</td>'
               . '<td class="cell-sub">' . esc((string)($b['reason'] ?? '—')) . '</td>'
               . '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';
}
ui_foot();

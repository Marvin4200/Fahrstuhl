<?php
/**
 * Mitglieder — Nutzerliste mit Nutzung und Premium-Status.
 */
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/_layout.php';
if (!isAdmin()) { header('Location: ' . ui_url()); exit; }

$limit  = 100;
$offset = max(0, (int)($_GET['offset'] ?? 0));
$raw    = getAPI('/analytics/users?limit=' . $limit . '&offset=' . $offset, 12);
$data   = $raw['data']['data'] ?? $raw['data'] ?? [];
$users  = $data['users'] ?? [];
$total  = (int)($data['total'] ?? count($users));

ui_head('members', 'Mitglieder', ui_num($total) . ' Nutzer');
?>
<div class="grid grid-3">
    <?= ui_stat('Nutzer gesamt', ui_num($total)) ?>
    <?= ui_stat('Auf dieser Seite', ui_num(count($users))) ?>
    <?= ui_stat('Ab Position', ui_num($offset + 1)) ?>
</div>

<?php
echo ui_card_head_only('Nutzer nach Aktivität');
if (!$users) {
    echo ui_empty('Keine Nutzerdaten', 'Sobald Commands genutzt werden, erscheinen hier Nutzer.');
} else {
    echo '<div class="table-wrap"><table class="data"><thead><tr>'
       . '<th>Nutzer</th><th class="num">Commands</th><th>Zuletzt aktiv</th>'
       . '</tr></thead><tbody>';
    foreach ($users as $u) {
        $last = !empty($u['last_used'])
              ? date('d.m.Y', is_numeric($u['last_used']) ? (int)$u['last_used'] : strtotime($u['last_used']))
              : '—';
        echo '<tr>'
           . '<td>' . ui_cell_identity((string)($u['username'] ?? $u['user_id'] ?? '?'), (string)($u['user_id'] ?? '')) . '</td>'
           . '<td class="num">' . esc(ui_num($u['count'] ?? $u['command_count'] ?? 0)) . '</td>'
           . '<td>' . esc($last) . '</td>'
           . '</tr>';
    }
    echo '</tbody></table></div>';
}
echo '</div>';

if ($total > $limit) {
    echo '<div class="btn-row">';
    if ($offset > 0) {
        echo '<a class="btn" href="' . esc(ui_url('members.php?offset=' . max(0, $offset - $limit))) . '">← Zurück</a>';
    }
    if ($offset + $limit < $total) {
        echo '<a class="btn" href="' . esc(ui_url('members.php?offset=' . ($offset + $limit))) . '">Weiter →</a>';
    }
    echo '</div>';
}
ui_foot();

<?php
/**
 * Tickets — offene Support-Tickets über alle Server, an denen man Rechte hat.
 */
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/_layout.php';

$guildsRaw = getAPI('/guilds', 10);
$guilds    = $guildsRaw['data']['data']['guilds'] ?? $guildsRaw['data']['guilds'] ?? [];
$guildId   = dashboardSelectedGuildId($guilds);

$tickets = [];
if ($guildId !== '') {
    $tRaw = getAPI('/guilds/' . urlencode($guildId) . '/tickets', 12);
    $tickets = $tRaw['data']['data']['tickets'] ?? $tRaw['data']['tickets'] ?? [];
}
$open = array_values(array_filter($tickets, fn($t) => ($t['status'] ?? 'open') === 'open'));

ui_head('tickets', 'Tickets', $guildId !== '' ? count($tickets) . ' Tickets' : '');

if ($guildId === '') {
    echo ui_status_strip('warn', 'Kein Server ausgewählt', 'Wähle unten einen Server, um dessen Tickets zu sehen.');
} elseif (count($open) > 0) {
    echo ui_status_strip('warn', count($open) === 1 ? 'Ein offenes Ticket' : count($open) . ' offene Tickets',
        'Offene Tickets stehen oben.');
} else {
    echo ui_status_strip('ok', 'Keine offenen Tickets', 'Alles abgearbeitet.');
}
?>

<form method="get" class="card" style="padding:var(--s4)">
    <div class="field">
        <label for="guildId">Server</label>
        <select id="guildId" name="guildId" onchange="this.form.submit()">
            <option value="">— auswählen —</option>
            <?php foreach ($guilds as $g): ?>
                <option value="<?= esc($g['id'] ?? '') ?>"<?= ($g['id'] ?? '') === $guildId ? ' selected' : '' ?>>
                    <?= esc($g['name'] ?? $g['id'] ?? '') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php
if ($guildId !== '') {
    echo ui_card_head_only('Tickets');
    if (!$tickets) {
        echo ui_empty('Keine Tickets', 'Für diesen Server wurde noch kein Ticket geöffnet.');
    } else {
        usort($tickets, fn($a, $b) => (($b['createdAt'] ?? 0) <=> ($a['createdAt'] ?? 0)));
        echo '<div class="table-wrap"><table class="data"><thead><tr>'
           . '<th>Ersteller</th><th>Thema</th><th>Priorität</th><th>Status</th>'
           . '</tr></thead><tbody>';
        foreach (array_slice($tickets, 0, 60) as $t) {
            $prio = strtolower((string)($t['priority'] ?? 'normal'));
            $ptone = $prio === 'high' || $prio === 'urgent' ? 'crit' : ($prio === 'low' ? 'mute' : 'warn');
            $st = (string)($t['status'] ?? 'open');
            echo '<tr>'
               . '<td>' . ui_cell_identity((string)($t['ownerTag'] ?? $t['userId'] ?? '?'), (string)($t['userId'] ?? '')) . '</td>'
               . '<td class="cell-sub">' . esc(mb_strimwidth((string)($t['reason'] ?? $t['type'] ?? '—'), 0, 60, '…')) . '</td>'
               . '<td>' . ui_badge($prio, $ptone) . '</td>'
               . '<td>' . ($st === 'open' ? ui_badge('offen', 'warn') : ui_badge($st, 'ok')) . '</td>'
               . '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';
}
ui_foot();

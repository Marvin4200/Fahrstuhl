<?php
/**
 * Server — Liste aller Guilds mit dem, was man beim Draufschauen wissen will:
 * Größe, Plan, ob der Bot dort gesund ist. Kein Weg über eine Zwischenseite.
 */
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/_layout.php';

$raw    = getAPI('/guilds', 12);
$guilds = $raw['data']['data']['guilds'] ?? $raw['data']['guilds'] ?? [];

$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $needle = mb_strtolower($q);
    $guilds = array_values(array_filter($guilds, function ($g) use ($needle) {
        return str_contains(mb_strtolower((string)($g['name'] ?? '')), $needle)
            || str_contains((string)($g['id'] ?? ''), $needle);
    }));
}

usort($guilds, fn($a, $b) => ((int)($b['memberCount'] ?? 0)) <=> ((int)($a['memberCount'] ?? 0)));
$totalMembers = array_sum(array_map(fn($g) => (int)($g['memberCount'] ?? 0), $guilds));

ui_head('servers', 'Server', count($guilds) . ' Server');
?>

<div class="grid grid-3">
    <?= ui_stat('Server', ui_num(count($guilds))) ?>
    <?= ui_stat('Mitglieder gesamt', ui_num($totalMembers)) ?>
    <?= ui_stat('Ø pro Server', ui_num(count($guilds) ? round($totalMembers / count($guilds)) : 0)) ?>
</div>

<form method="get" class="card" style="padding:var(--s4)">
    <div class="field">
        <label for="q">Suchen</label>
        <input type="search" id="q" name="q" value="<?= esc($q) ?>" placeholder="Servername oder ID">
    </div>
</form>

<?php
echo ui_card_head_only('Alle Server');
if (!$guilds) {
    echo ui_empty($q !== '' ? 'Nichts gefunden' : 'Keine Server', $q !== '' ? 'Andere Suche versuchen.' : 'Der Bot ist auf keinem Server.');
} else {
    echo '<div class="table-wrap"><table class="data"><thead><tr>'
       . '<th>Server</th><th class="num">Mitglieder</th><th>Plan</th><th></th>'
       . '</tr></thead><tbody>';
    foreach ($guilds as $g) {
        $id   = (string)($g['id'] ?? '');
        $tier = strtolower((string)($g['premiumTier'] ?? $g['tier'] ?? 'free'));
        $badge = $tier === 'pro' ? ui_badge('Pro', 'accent')
               : ($tier === 'basic' || $tier === 'premium' ? ui_badge('Premium', 'ok') : ui_badge('Free', 'mute'));
        echo '<tr>'
           . '<td>' . ui_cell_identity((string)($g['name'] ?? $id), $id, $g['iconUrl'] ?? $g['icon'] ?? null) . '</td>'
           . '<td class="num">' . esc(ui_num($g['memberCount'] ?? 0)) . '</td>'
           . '<td>' . $badge . '</td>'
           . '<td><a class="btn btn-sm" href="' . esc(ui_url('server.php?id=' . urlencode($id))) . '">Öffnen</a></td>'
           . '</tr>';
    }
    echo '</tbody></table></div>';
}
echo '</div>';
ui_foot();

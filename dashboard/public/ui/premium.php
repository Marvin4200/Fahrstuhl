<?php
/**
 * Premium — Pläne, Umsatz, Abläufe an einem Ort.
 * Ersetzt premium-hub, premium, monetization, monetization-health und
 * guild-premium, die sich vorher gegenseitig überlappt haben.
 */
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/_layout.php';
if (!isAdmin()) { header('Location: ' . ui_url()); exit; }

$calRaw = getAPI('/premium/calendar?days=30', 12);
$cal    = $calRaw['data']['data'] ?? $calRaw['data'] ?? [];
$users  = $cal['users'] ?? [];
$sum    = $cal['summary'] ?? [];

$revRaw  = getAPI('/monetization/revenue', 10);
$revenue = ($revRaw['data']['data']['summary'] ?? $revRaw['data']['summary'] ?? []);

$plansRaw  = getAPI('/premium/guild-plans', 10);
$guildPlans = $plansRaw['data']['data']['plans'] ?? $plansRaw['data']['plans'] ?? [];

$expiringSoon = array_values(array_filter($users, fn($u) => !empty($u['expiringSoon'])));

if (count($expiringSoon) > 0) {
    $tone = 'warn';
    $headline = count($expiringSoon) === 1 ? 'Ein Plan läuft bald ab' : count($expiringSoon) . ' Pläne laufen bald ab';
    $detail = 'Ablauf-Erinnerungen verschickt der Bot automatisch.';
} else {
    $tone = 'ok';
    $headline = 'Keine Abläufe in Sicht';
    $detail = ($sum['active'] ?? 0) . ' aktive Pläne.';
}

ui_head('premium', 'Premium', date('d.m. H:i'));
echo ui_status_strip($tone, $headline, $detail);
?>

<div class="grid grid-4">
    <?= ui_stat('Aktive Pläne', ui_num($sum['active'] ?? 0)) ?>
    <?= ui_stat('Laufen bald ab', ui_num(count($expiringSoon)), count($expiringSoon) ? 'in den nächsten 30 Tagen' : '') ?>
    <?= ui_stat('Umsatz / Monat', ui_money($revenue['monthly'] ?? 0)) ?>
    <?= ui_stat('Server-Pläne', ui_num(count($guildPlans)), 'eigenes Entitlement') ?>
</div>

<?php
echo ui_card_head_only('Premium-Nutzer');
if (!$users) {
    echo ui_empty('Noch keine Premium-Nutzer', 'Sobald jemand kauft, erscheint er hier.');
} else {
    usort($users, fn($a, $b) => strtotime($a['expiresAt'] ?? '2099-01-01') <=> strtotime($b['expiresAt'] ?? '2099-01-01'));
    echo '<div class="table-wrap"><table class="data"><thead><tr>'
       . '<th>Nutzer</th><th>Plan</th><th>Läuft ab</th><th class="num">Tage</th>'
       . '</tr></thead><tbody>';
    foreach ($users as $u) {
        $days = $u['daysRemaining'];
        $expired = !empty($u['expired']);
        $daysCell = $expired ? ui_badge('abgelaufen', 'crit')
                  : (is_numeric($days) && $days <= 7 ? ui_badge((string)(int)$days, 'warn') : esc((string)(int)$days));
        echo '<tr>'
           . '<td>' . ui_cell_identity((string)($u['displayName'] ?? $u['username'] ?? 'Unbekannt'), (string)($u['userId'] ?? '')) . '</td>'
           . '<td>' . (($u['tier'] ?? '') === 'pro' ? ui_badge('Pro', 'accent') : ui_badge('Premium', 'ok')) . '</td>'
           . '<td>' . esc(!empty($u['expiresAt']) ? date('d.m.Y', strtotime($u['expiresAt'])) : '—') . '</td>'
           . '<td class="num">' . $daysCell . '</td>'
           . '</tr>';
    }
    echo '</tbody></table></div>';
}
echo '</div>';

echo ui_card_head_only('Server-Pläne');
if (!$guildPlans) {
    echo ui_empty('Keine Server-Pläne vergeben', 'Server-Pläne gelten für einen ganzen Server, unabhängig vom Owner-Account.');
} else {
    echo '<div class="table-wrap"><table class="data"><thead><tr><th>Server</th><th>Plan</th><th>Läuft ab</th></tr></thead><tbody>';
    foreach ($guildPlans as $p) {
        echo '<tr>'
           . '<td>' . ui_cell_identity((string)($p['guild_name'] ?? $p['guild_id'] ?? '?'), (string)($p['guild_id'] ?? '')) . '</td>'
           . '<td>' . (($p['tier'] ?? '') === 'pro' ? ui_badge('Pro', 'accent') : ui_badge('Premium', 'ok')) . '</td>'
           . '<td>' . esc(!empty($p['expires_at']) ? date('d.m.Y', strtotime($p['expires_at'])) : '—') . '</td>'
           . '</tr>';
    }
    echo '</tbody></table></div>';
}
echo '</div>';
ui_foot();

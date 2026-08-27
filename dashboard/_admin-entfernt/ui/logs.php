<?php
/**
 * App-Logs — die Laufzeit-Logs des Bots.
 * Hieß vorher "Logs" und stand direkt neben "Logging" (Discord-Events) und
 * "Audit Log" (Admin-Aktionen), was niemand auseinanderhalten konnte.
 */
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/_layout.php';
if (!isAdmin()) { header('Location: ' . ui_url()); exit; }

$raw  = getAPI('/logs/recent?limit=200', 12);
$logs = $raw['data']['data']['logs'] ?? $raw['data']['logs'] ?? [];

$level = strtolower(trim((string)($_GET['level'] ?? '')));
if ($level !== '' && $level !== 'all') {
    $logs = array_values(array_filter($logs, fn($l) => strtolower((string)($l['level'] ?? 'info')) === $level));
}

ui_head('logs', 'App-Logs', count($logs) . ' Zeilen');
?>
<form method="get" class="card" style="padding:var(--s4)">
    <div class="field">
        <label for="level">Nur anzeigen</label>
        <select id="level" name="level" onchange="this.form.submit()">
            <option value="all"<?= $level === '' || $level === 'all' ? ' selected' : '' ?>>Alle Meldungen</option>
            <option value="error"<?= $level === 'error' ? ' selected' : '' ?>>Nur Fehler</option>
            <option value="warn"<?= $level === 'warn' ? ' selected' : '' ?>>Nur Warnungen</option>
            <option value="info"<?= $level === 'info' ? ' selected' : '' ?>>Nur Info</option>
        </select>
    </div>
</form>

<?php
echo ui_card_head_only('Laufzeit-Logs');
if (!$logs) {
    echo ui_empty('Keine Einträge', $level !== '' && $level !== 'all' ? 'Mit diesem Filter gibt es nichts.' : 'Der Bot hat nichts geloggt.');
} else {
    echo '<div class="feed">';
    foreach (array_slice($logs, 0, 200) as $l) {
        $lv  = strtolower((string)($l['level'] ?? 'info'));
        $sev = $lv === 'error' ? 'crit' : ($lv === 'warn' ? 'warn' : 'ok');
        $ts  = !empty($l['timestamp'])
             ? date('d.m. H:i:s', is_numeric($l['timestamp']) ? (int)$l['timestamp'] : strtotime($l['timestamp']))
             : '';
        echo ui_feed_item($sev, (string)($l['message'] ?? ''), trim($ts . ' · ' . strtoupper($lv), ' ·'));
    }
    echo '</div>';
}
echo '</div>';
ui_foot();

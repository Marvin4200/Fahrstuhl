<?php
$page_title = 'Moderation';
require_once __DIR__ . '/../includes/config.php';
requireLogin();
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<?php include '../includes/eselmoderator-notice.php'; ?>

<div class="page-header">
    <div class="page-header-row">
        <div>
            <h1>🤖 Moderation & Community sind umgezogen</h1>
            <p class="subtitle">Diese Funktionen laufen jetzt über unseren neuen Bot EselModerator statt über Fahrstuhl.</p>
        </div>
    </div>
</div>

<div class="section">
    <h2>Was umgezogen ist</h2>
    <p style="color:var(--text-secondary); margin-bottom:1rem;">
        Moderation (Cases, Kicks/Bans/Timeouts), AutoMod, Logging, Tickets, Welcome-Nachrichten,
        Leveling, Reaction Roles, Social Alerts, Free-Games-Benachrichtigungen, Temp-Voice und
        Server-Backups werden von Fahrstuhl nicht mehr ausgewertet — egal was hier im Dashboard
        noch eingestellt ist. Alle Einstellungen dafür findest du jetzt bei EselModerator.
    </p>
    <a class="btn btn-primary" href="https://discord.com/api/oauth2/authorize?client_id=1545456084754628658&permissions=1099798277142&scope=bot%20applications.commands" target="_blank" rel="noopener">
        🤖 EselModerator einladen
    </a>
</div>

<div class="section">
    <h2>Was bei Fahrstuhl bleibt</h2>
    <p style="color:var(--text-secondary);">
        Die Troll-Effekte (Fahrstuhl/Ghost/Mute/Mirror/Deafen), die Shield-Wirtschaft und die
        täglichen Rewards (Daily/Voice/Promo) sind der Kern von Fahrstuhl und bleiben unverändert
        hier — dafür brauchst du EselModerator nicht.
    </p>
</div>

<?php include '../includes/footer.php'; ?>

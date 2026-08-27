<?php
$page_title = 'Bot einladen';
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$guildId = trim($_GET['guildId'] ?? ($_SESSION['selected_guild_id'] ?? ''));

// Namen des Servers aus den Discord-Guilds der Person holen — der Bot kennt
// ihn ja gerade nicht, deshalb nicht über die Bot-API.
$guildName = '';
foreach (getUserGuilds() as $g) {
    if (($g['id'] ?? '') === $guildId) {
        $guildName = (string)($g['name'] ?? '');
        break;
    }
}

// Falls der Bot doch drauf ist (z. B. gerade eingeladen), gehört man hier
// nicht hin — dann zurück ins Portal.
if ($guildId !== '' && guildHasBot($guildId)) {
    header('Location: ' . BASE_URL . '/pages/portal.php?guildId=' . urlencode($guildId));
    exit();
}

$clientId = getenv('BOT_CLIENT_ID') ?: (getenv('DISCORD_CLIENT_ID') ?: '');
$inviteUrl = 'https://discord.com/oauth2/authorize?' . http_build_query(array_filter([
    'client_id'   => $clientId,
    'scope'       => 'bot applications.commands',
    'permissions' => '1099511627775',
    'guild_id'    => $guildId ?: null,
]));
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.inv-wrap { max-width: 680px; margin: 3rem auto; text-align: center; }
.inv-icon { font-size: 3.4rem; line-height: 1; margin-bottom: 1rem; }
.inv-wrap h1 { margin: 0 0 .6rem; font-size: 1.7rem; }
.inv-server {
    display: inline-flex; align-items: center; gap: .5rem;
    background: var(--bg-tertiary); border: 1px solid var(--border-light);
    border-radius: 999px; padding: .4rem .9rem; margin-bottom: 1.2rem;
    font-weight: 700; font-size: .9rem;
}
.inv-text { color: var(--text-secondary); line-height: 1.6; margin: 0 auto 1.8rem; max-width: 520px; }
.inv-actions { display: flex; gap: .7rem; justify-content: center; flex-wrap: wrap; }
.inv-steps {
    margin-top: 2.5rem; text-align: left; display: grid; gap: .7rem;
    background: var(--panel); border: 1px solid var(--border-light);
    border-radius: 12px; padding: 1.2rem 1.4rem;
}
.inv-steps h2 { font-size: .78rem; text-transform: uppercase; letter-spacing: .05em;
    color: var(--text-secondary); margin: 0 0 .3rem; }
.inv-step { display: grid; grid-template-columns: 1.6rem 1fr; gap: .7rem; align-items: start;
    font-size: .9rem; color: var(--text-secondary); }
.inv-step b { color: var(--text-primary); }
.inv-num { width: 1.5rem; height: 1.5rem; border-radius: 50%; background: var(--primary);
    color: #fff; font-size: .75rem; font-weight: 800; display: grid; place-items: center; }
</style>

<div class="module-page">
    <div class="inv-wrap">
        <div class="inv-icon">🫏</div>
        <h1>Fahrstuhl ist hier noch nicht dabei</h1>

        <?php if ($guildName !== ''): ?>
            <div class="inv-server">🏰 <?php echo esc($guildName); ?></div>
        <?php endif; ?>

        <p class="inv-text">
            Auf diesem Server läuft der Bot noch nicht — deshalb gibt es hier auch
            nichts einzustellen. Lade ihn ein, dann stehen dir Welcome, Leveling,
            Tickets, Moderation und der Rest offen.
        </p>

        <div class="inv-actions">
            <?php if ($clientId !== ''): ?>
                <a class="btn-icon cta btn-primary-ui" href="<?php echo esc($inviteUrl); ?>" target="_blank" rel="noopener">
                    <span class="i">🚀</span> Auf diesem Server einladen
                </a>
            <?php endif; ?>
            <a class="btn-icon" href="<?php echo BASE_URL; ?>/pages/portal.php">
                <span class="i">↩</span> Anderen Server wählen
            </a>
        </div>

        <div class="inv-steps">
            <h2>Und dann?</h2>
            <div class="inv-step"><span class="inv-num">1</span>
                <span>Im Discord-Dialog <b>diesen Server</b> auswählen und bestätigen.</span></div>
            <div class="inv-step"><span class="inv-num">2</span>
                <span>Hierher zurückkommen und den Server oben links erneut auswählen.</span></div>
            <div class="inv-step"><span class="inv-num">3</span>
                <span>Der <b>Setup-Assistent</b> führt dich durch die wichtigsten Einstellungen.</span></div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

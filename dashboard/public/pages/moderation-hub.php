<?php
$page_title = 'Moderation';
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$voiceGuildsRaw = getAPI('/voice/guilds', 8);
$manageableGuilds = $voiceGuildsRaw['data']['guilds'] ?? [];
// Ausgewählte Guild für die guild-scoped Links unten (AutoMod, Logging).
$guildId = dashboardSelectedGuildId($manageableGuilds);
$health = [];
$warnings = [];
if (isAdmin()) {
    $healthRaw = getAPI('/health/summary', 8);
    $health = $healthRaw['data'] ?? [];
    $warnings = $health['warnings'] ?? [];
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="page-header">
    <div class="page-header-row">
        <div>
            <h1>🛡️ <?= t('mh.title') ?></h1>
            <p class="subtitle"><?= t('mh.subtitle') ?></p>
        </div>
        <div class="page-meta"><?= t('mh.last_refresh') ?> <?php echo date('d.m.Y H:i'); ?></div>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:1rem;">
    <div class="stat-card"><div class="stat-icon">🏰</div><div class="stat-label"><?= t('mh.your_servers') ?></div><div class="stat-value"><?php echo formatNum(count($manageableGuilds)); ?></div><p style="color:#aaa;"><?= t('mh.your_servers_sub') ?></p></div>
    <div class="stat-card"><div class="stat-icon">⚠️</div><div class="stat-label"><?= t('mh.warnings') ?></div><div class="stat-value"><?php echo isAdmin() ? formatNum($health['overall']['warnings'] ?? count($warnings)) : 'Scoped'; ?></div><p style="color:#aaa;"><?= t('mh.warnings_sub') ?></p></div>
    <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-label"><?= t('mh.timeouts') ?></div><div class="stat-value"><?= t('mh.ready') ?></div><p style="color:#aaa;"><?= t('mh.timeouts_sub') ?></p></div>
    <div class="stat-card"><div class="stat-icon">📝</div><div class="stat-label"><?= t('mh.notes') ?></div><div class="stat-value"><?= t('mh.ready') ?></div><p style="color:#aaa;"><?= t('mh.notes_sub') ?></p></div>
</div>

<div class="hub-grid" style="margin-bottom:1rem;">
    <a class="hub-card" href="<?php echo BASE_URL; ?>/pages/moderation.php">
        <h3>🛡️ <?= t('mh.console') ?></h3>
        <p><?= t('mh.console_sub') ?></p>
    </a>
    <?php if (isAdmin()): ?>
    <a class="hub-card" href="<?php echo BASE_URL; ?>/ui/logs.php">
        <h3>📋 <?= t('mh.logs') ?></h3>
        <p><?= t('mh.logs_sub') ?></p>
    </a>
    <?php endif; ?>
    <a class="hub-card" href="<?php echo BASE_URL; ?>/pages/automod.php<?php echo $guildId ? '?guildId=' . urlencode($guildId) : ''; ?>">
        <h3>🚨 <?= t('mh.automod') ?></h3>
        <p><?= t('mh.automod_sub') ?></p>
    </a>
    <a class="hub-card" href="<?php echo BASE_URL; ?>/pages/logging.php<?php echo $guildId ? '?guildId=' . urlencode($guildId) : ''; ?>">
        <h3>🧾 <?= t('mh.events') ?></h3>
        <p><?= t('mh.events_sub') ?></p>
    </a>
</div>

<?php if (!isAdmin() && empty($manageableGuilds)): ?>
    <div class="section">
        <h2><?= t('mh.no_access_title') ?></h2>
        <p style="color:var(--text-secondary);"><?= t('mh.no_access_text') ?></p>
    </div>
<?php endif; ?>

<div class="section">
    <div class="section-header"><h2>🔮 <?= t('mh.coming') ?></h2></div>
    <div class="hub-grid">
        <div class="hub-card" style="opacity:.7; pointer-events:none;">
            <h3><?= t('mh.esc_rules') ?> <span class="status-badge coming" style="vertical-align:middle;"><?= t('mh.soon') ?></span></h3>
            <p><?= t('mh.esc_rules_sub') ?></p>
        </div>
        <div class="hub-card" style="opacity:.7; pointer-events:none;">
            <h3><?= t('mh.case_page') ?> <span class="status-badge coming" style="vertical-align:middle;"><?= t('mh.soon') ?></span></h3>
            <p><?= t('mh.case_page_sub') ?></p>
        </div>
        <div class="hub-card" style="opacity:.7; pointer-events:none;">
            <h3><?= t('mh.mod_cmds') ?> <span class="status-badge coming" style="vertical-align:middle;"><?= t('mh.soon') ?></span></h3>
            <p><?= t('mh.mod_cmds_sub') ?></p>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

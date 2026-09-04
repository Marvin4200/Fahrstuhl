<?php
$page_title = 'Tickets';
require_once __DIR__ . '/../includes/config.php';
requireLogin();

function ticketsPageAccessCheck($guildId, $moduleKey = 'tickets') {
    $guildId = trim((string)$guildId);
    if ($guildId === '') return ['allowed' => false, 'reason' => 'missing_context'];
    if (isAdmin()) return ['allowed' => true, 'reason' => 'owner_admin_mode'];
    $response = getAPI('/guilds/' . urlencode($guildId) . '/dashboard-access?module=' . urlencode($moduleKey), 8);
    if (($response['success'] ?? false) === true) {
        return ['allowed' => !empty($response['data']['allowed']), 'reason' => $response['data']['reason'] ?? null];
    }
    if (isServerAdmin($guildId)) return ['allowed' => true, 'reason' => 'fallback_server_admin'];
    return ['allowed' => false, 'reason' => $response['error'] ?? 'access_check_failed'];
}

function ticketsPageAccessMessage($reason) {
    if ($reason === 'missing_module_role') return 'Dir fehlt eine freigegebene Dashboard-Rolle für dieses Modul.';
    if ($reason === 'admin_role_not_configured') return 'Es ist noch keine Dashboard-Admin-Rolle gesetzt.';
    if ($reason === 'not_guild_admin') return 'Du bist kein Server-Owner/Admin und hast keine freigegebene Dashboard-Rolle.';
    return 'Du hast aktuell keinen Zugriff auf Tickets.';
}

$guildsRaw = getAPI('/voice/guilds', 8);
$guilds = $guildsRaw['data']['guilds'] ?? [];
$guildId = dashboardSelectedGuildId($guilds);

$moduleAccess = $guildId ? ticketsPageAccessCheck($guildId, 'tickets') : ['allowed' => true];
if ($guildId && empty($moduleAccess['allowed'])) {
    $denyLabel = 'Tickets';
    $denyMessage = ticketsPageAccessMessage($moduleAccess['reason'] ?? '');
    include '../includes/header.php';
    include '../includes/sidebar.php';
    ?>
    <div class="empty-state" style="max-width:780px; margin:1rem auto; text-align:left;">
        <strong>Kein Zugriff auf <?= esc($denyLabel) ?></strong>
        <p><?= esc($denyMessage) ?></p>
        <p style="color:var(--text-secondary); font-size:.82rem;">Owner, Discord-Administratoren und die Dashboard-Admin-Rolle bleiben weiterhin erlaubt.</p>
        <a class="btn-icon cta btn-primary-ui" href="portal.php">Zurueck zum Portal</a>
    </div>
    <?php
    include '../includes/footer.php';
    return;
}

$message = '';
$messageType = 'success';
$operationSuccess = null;
$isAjaxRequest = strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'XMLHttpRequest') === 0
    || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
$sendJson = function ($payload, $statusCode = 200) {
    http_response_code((int)$statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
};

function ticketTypePayloadFromPost() {
    $ticketTypes = [];
    $typeLabels = $_POST['typeLabels'] ?? [];
    $typeDescriptions = $_POST['typeDescriptions'] ?? [];
    $typePriorities = $_POST['typePriorities'] ?? [];
    $typeEmojis = $_POST['typeEmojis'] ?? [];
    $typeCategories = $_POST['typeCategories'] ?? [];
    $typeStaffRoles = $_POST['typeStaffRoles'] ?? [];
    for ($i = 0; $i < count($typeLabels); $i++) {
        $label = trim($typeLabels[$i] ?? '');
        if ($label === '') continue;
        $ticketTypes[] = [
            'label' => $label,
            'description' => trim($typeDescriptions[$i] ?? ''),
            'priority' => $typePriorities[$i] ?? 'normal',
            'emoji' => trim($typeEmojis[$i] ?? ''),
            'categoryId' => trim($typeCategories[$i] ?? ''),
            'staffRoleId' => trim($typeStaffRoles[$i] ?? ''),
        ];
    }
    return $ticketTypes;
}

function ticketPanelDesignPayloadFromPost() {
    return [
        'panelTitle' => $_POST['panelTitle'] ?? '',
        'panelDescription' => $_POST['panelDescription'] ?? '',
        'panelButtonLabel' => $_POST['panelButtonLabel'] ?? '',
        'panelPlaceholder' => $_POST['panelPlaceholder'] ?? '',
        'panelFooterText' => $_POST['panelFooterText'] ?? '',
        'panelBrandName' => $_POST['panelBrandName'] ?? '',
        'panelBannerUrl' => $_POST['panelBannerUrl'] ?? '',
        'panelColor' => $_POST['panelColor'] ?? '',
        'panelShowLiveStatus' => isset($_POST['panelShowLiveStatus']),
        'panelShowStaffOnline' => isset($_POST['panelShowStaffOnline']),
        'panelShowQueue' => isset($_POST['panelShowQueue']),
        'panelShowRating' => isset($_POST['panelShowRating']),
    ];
}

function ticketPanelInfoPayloadFromPost() {
    return [
        'enabled' => isset($_POST['panelInfoEnabled']),
        'showOpenTickets' => isset($_POST['panelInfoShowOpenTickets']),
        'showAverageResolution' => isset($_POST['panelInfoShowAverageResolution']),
        'showOverdueTickets' => isset($_POST['panelInfoShowOverdueTickets']),
        'showLastUpdated' => isset($_POST['panelInfoShowLastUpdated']),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $guildId) {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'send_panel') {
        $result = api('/guilds/' . urlencode($guildId) . '/tickets/panel', 'POST', [
            'channelId' => $_POST['panelChannelId'] ?? '',
            'categoryId' => $_POST['categoryId'] ?? '',
            'staffRoleId' => $_POST['staffRoleId'] ?? '',
            'transcriptChannelId' => $_POST['transcriptChannelId'] ?? '',
            'defaultPriority' => $_POST['defaultPriority'] ?? 'normal',
            'closeDelaySeconds' => intval($_POST['closeDelaySeconds'] ?? 5),
            'slaMinutes' => intval($_POST['slaMinutes'] ?? 240),
            'enableClaiming' => isset($_POST['enableClaiming']),
            'requireCloseReason' => isset($_POST['requireCloseReason']),
            'enableTicketTypes' => isset($_POST['enableTicketTypes']),
            'ticketTypes' => ticketTypePayloadFromPost(),
            'ticketPanelInfo' => ticketPanelInfoPayloadFromPost(),
        ] + ticketPanelDesignPayloadFromPost(), 20);
        $panelSuccess = ($result['data']['success'] ?? false) === true;
        if ($panelSuccess) {
            $panelMessage = 'Panel gesendet!';
            $panelMessageType = 'success';
            $panelUrl = $result['data']['data']['url'] ?? null;
        } else {
            $panelMessageType = 'error';
            if (($result['data']['code'] ?? '') === 'LIMIT_REACHED') {
                $limit = $result['data']['limit'] ?? '?';
                $current = $result['data']['current'] ?? '?';
                $panelMessage = 'Limit erreicht: ' . $current . ' / ' . $limit . '. Upgrade für mehr Ticket-Panels.';
            } else {
                $panelMessage = $result['data']['message'] ?? 'Failed to send ticket panel.';
            }
        }
        if ($isAjaxRequest) {
            $sendJson([
                'success' => $panelSuccess,
                'message' => $panelMessage,
                'messageType' => $panelMessageType,
                'url' => $panelUrl ?? null,
                'data' => $result['data']['data'] ?? null,
            ], $panelSuccess ? 200 : 400);
        }
        // Non-AJAX fallback: set regular $message
        $message = $panelMessage;
        $messageType = $panelMessageType;
    } elseif ($action === 'remove_panel') {
        $result = api('/guilds/' . urlencode($guildId) . '/tickets/panel/remove', 'POST', [
            'channelId' => $_POST['removeChannelId'] ?? '',
        ], 20);
        $removeSuccess = ($result['data']['success'] ?? false) === true;
        $removeMessage = $removeSuccess
            ? 'Panel entfernt.'
            : ($result['data']['message'] ?? 'Panel konnte nicht entfernt werden.');
        if ($isAjaxRequest) {
            $sendJson([
                'success' => $removeSuccess,
                'message' => $removeMessage,
            ], $removeSuccess ? 200 : 400);
        }
        $message = $removeMessage;
        $messageType = $removeSuccess ? 'success' : 'error';
    } elseif ($action === 'test_ticket') {
        $result = api('/guilds/' . urlencode($guildId) . '/tickets/test', 'POST', [
            'reason' => $_POST['testTicketReason'] ?? '',
            'priority' => $_POST['defaultPriority'] ?? 'normal',
        ], 20);
        $operationSuccess = (($result['data']['success'] ?? false) === true);
        if ($operationSuccess) {
            $channelName = $result['data']['data']['channelName'] ?? 'ticket';
            $message = 'Test-Ticket erstellt: #' . $channelName;
        } else {
            $messageType = 'error';
            $message = $result['data']['message'] ?? 'Test-Ticket konnte nicht erstellt werden.';
        }

        if ($isAjaxRequest) {
            $sendJson([
                'success' => $operationSuccess === true,
                'message' => $message,
                'messageType' => $messageType,
                'data' => $result['data']['data'] ?? null,
            ], $operationSuccess === true ? 200 : 400);
        }
    } else {
        $payload = [
            'categoryId' => $_POST['categoryId'] ?? '',
            'staffRoleId' => $_POST['staffRoleId'] ?? '',
            'transcriptChannelId' => $_POST['transcriptChannelId'] ?? '',
        ] + ticketPanelDesignPayloadFromPost();
        $payload['defaultPriority'] = $_POST['defaultPriority'] ?? 'normal';
        $payload['closeDelaySeconds'] = intval($_POST['closeDelaySeconds'] ?? 5);
        $payload['slaMinutes'] = intval($_POST['slaMinutes'] ?? 240);
        $payload['enableClaiming'] = isset($_POST['enableClaiming']);
        $payload['requireCloseReason'] = isset($_POST['requireCloseReason']);
        $payload['enableTicketTypes'] = isset($_POST['enableTicketTypes']);
        $payload['ticketTypes'] = ticketTypePayloadFromPost();
        $payload['ticketPanelInfo'] = ticketPanelInfoPayloadFromPost();
        $result = api('/guilds/' . urlencode($guildId) . '/tickets', 'POST', $payload, 15);
        if (($result['data']['success'] ?? false) === true) {
            $message = 'Ticket settings saved.';
            $operationSuccess = true;
        } else {
            $messageType = 'error';
            $message = $result['data']['message'] ?? 'Failed to save ticket settings.';
            $operationSuccess = false;
        }

        if ($isAjaxRequest) {
            $sendJson([
                'success' => $operationSuccess === true,
                'message' => $message,
                'messageType' => $messageType,
            ], $operationSuccess === true ? 200 : 400);
        }
    }
}

$moduleRaw = $guildId ? getAPI('/guilds/' . urlencode($guildId) . '/modules', 10) : null;
$modules = $moduleRaw['data']['modules'] ?? [];
$ticketsEnabled = false;
foreach ($modules as $module) {
    if (($module['key'] ?? '') === 'tickets') {
        $ticketsEnabled = !empty($module['enabled']);
        break;
    }
}

$ticketsRaw = $guildId ? getAPI('/guilds/' . urlencode($guildId) . '/tickets', 10) : null;
$data = $ticketsRaw['data'] ?? [];
$settings = $data['settings'] ?? [];
$channels = $data['channels'] ?? [];
$roles = $data['roles'] ?? [];
$categories = $data['categories'] ?? [];
$openTickets = $data['openTickets'] ?? [];
$ticketStats = $data['ticketStats'] ?? [];
$recentTickets = $data['recentTickets'] ?? [];
$permissions = $data['permissions'] ?? [];
$guildName = $data['guildName'] ?? 'Selected server';
$priorityLabels = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'];
$feedbackAvg = $ticketStats['feedbackAvg'] ?? null;
$feedbackLabel = $feedbackAvg === null ? '-' : number_format((float)$feedbackAvg, 1) . '/5';
$ticketTypes = $settings['ticketTypes'] ?? [
    ['label' => 'Support', 'emoji' => '🎫', 'description' => 'Allgemeine Hilfe vom Team.', 'priority' => 'normal'],
    ['label' => 'Kauf & Zahlung', 'emoji' => '💳', 'description' => 'Fragen zu Käufen, Zahlungen oder Rechnungen.', 'priority' => 'normal'],
    ['label' => 'Fehler melden', 'emoji' => '🐛', 'description' => 'Melde einen Bug oder ein technisches Problem.', 'priority' => 'high'],
    ['label' => 'Partnerschaft', 'emoji' => '🤝', 'description' => 'Kooperationen und Partneranfragen.', 'priority' => 'low'],
    ['label' => 'Sonstiges', 'emoji' => '📋', 'description' => 'Alles, was in keine andere Kategorie passt.', 'priority' => 'normal'],
];
// Das Dashboard laeuft hinter nginx, dort ist $_SERVER['HTTPS'] oft nicht gesetzt.
$forwardedProto = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0]));
$dashboardScheme = $forwardedProto === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$dashboardHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'eselbande.com';
$defaultBannerUrl = $dashboardScheme . '://' . $dashboardHost . BASE_URL . '/assets/img/eselbande-ticket-banner.png';
$panelPlaceholder = $settings['panelPlaceholder'] ?? 'Choose a category ...';
$panelFooterText = $settings['panelFooterText'] ?? 'eselbande.com';
$panelBrandName = $settings['panelBrandName'] ?? 'Eselbande';
$panelBannerUrl = $settings['panelBannerUrl'] ?? '';
$panelColor = $settings['panelColor'] ?? '#667eea';
$panelShowLiveStatus = $settings['panelShowLiveStatus'] ?? true;
$panelShowStaffOnline = $settings['panelShowStaffOnline'] ?? true;
$panelShowQueue = $settings['panelShowQueue'] ?? true;
$panelShowRating = $settings['panelShowRating'] ?? true;
$liveStatus = $data['liveStatus'] ?? [];
$ticketPanelInfo = is_array($settings['ticketPanelInfo'] ?? null) ? $settings['ticketPanelInfo'] : [];
$panelInfoEnabled = !empty($ticketPanelInfo['enabled']);
$panelInfoShowOpenTickets = !empty($ticketPanelInfo['showOpenTickets']);
$panelInfoShowAverageResolution = !empty($ticketPanelInfo['showAverageResolution']);
$panelInfoShowOverdueTickets = !empty($ticketPanelInfo['showOverdueTickets']);
$panelInfoShowLastUpdated = !empty($ticketPanelInfo['showLastUpdated']);

$premRaw = $guildId ? getAPI('/guilds/' . urlencode($guildId) . '/premium', 5) : null;
$maxTicketPanels = (int)(($premRaw['data']['featureLimits']['ticketPanels'] ?? 1));
$deployedPanels = is_array($settings['panels'] ?? null) ? $settings['panels'] : [];
$ticketPanelCount = count($deployedPanels);
$atTicketPanelLimit = $maxTicketPanels >= 0 && $ticketPanelCount >= $maxTicketPanels;

function formatTicketAge($minutes) {
    $minutes = (int)$minutes;
    if ($minutes < 60) return $minutes . 'm';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
}
$feedbackStars = $feedbackAvg !== null ? max(0, min(5, (int)round((float)$feedbackAvg))) : 0;
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<?php include '../includes/eselmoderator-notice.php'; ?>

<style>
/* === TICKET SYSTEM === */
.tk-compact { display: grid; grid-template-columns: 320px 1fr 320px; gap: 1.25rem; align-items: start; }
.tk-card { background: var(--panel); border: 1px solid var(--border-light); border-radius: 12px; padding: 1rem; display: flex; flex-direction: column; gap: 0.8rem; }
.tk-card h2 { font-size: 1rem; margin: 0; display: flex; align-items: center; gap: 0.5rem; }
.tk-section-title { font-size: 0.8rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin: 0.5rem 0 0.2rem; }
.tk-field { display: grid; gap: 0.3rem; }
.tk-field label { font-size: 0.75rem; font-weight: 700; color: var(--text-secondary); }
.tk-field select, .tk-field textarea, .tk-field input[type="text"] {
    width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid var(--border-light);
    background: var(--bg-tertiary); color: var(--text-primary); font-size: 0.9rem;
}
.discord-preview { background: #2b2d31; border-radius: 8px; border-left: 4px solid #667eea; padding: 1rem; font-family: 'gg sans', sans-serif; font-size: 0.95rem; }
.discord-brand { display:flex; align-items:center; gap:.4rem; font-size:.78rem; font-weight:600; color:#dbdee1; margin-bottom:.45rem; }
.discord-brand::before { content:''; width:16px; height:16px; border-radius:50%; background:#667eea; flex:0 0 auto; }
.discord-title { font-weight: 600; font-size: 1.1rem; margin-bottom: 0.4rem; }
.discord-desc { color: #dbdee1; white-space: pre-line; margin-bottom: 0.7rem; }
.discord-banner { width:100%; border-radius:6px; margin-bottom:.7rem; display:block; }
.discord-divider { height:1px; background:#3f4248; margin:.7rem 0; }
.discord-status { color:#b5bac1; font-size:.78rem; margin-bottom:.7rem; }
.discord-select { background:#1e1f22; border:1px solid #3f4248; border-radius:4px; padding:.55rem .75rem; color:#b5bac1; font-size:.85rem; display:flex; justify-content:space-between; align-items:center; }
.discord-options { display:grid; gap:.2rem; margin-top:.35rem; }
.discord-option { display:flex; gap:.45rem; align-items:baseline; font-size:.78rem; color:#dbdee1; padding:.25rem .4rem; border-radius:4px; background:rgba(255,255,255,.03); }
.discord-option small { color:#949ba4; font-size:.7rem; }
.discord-footer { color:#949ba4; font-size:.72rem; margin-top:.8rem; padding-top:.5rem; border-top:1px solid #3f4248; }
.discord-btn { background: #667eea; color: #fff; padding: 0.5rem 1rem; border-radius: 4px; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; }

/* Stats */
.tk-metric-grid { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:.75rem; }
.tk-metric { background:rgba(32,38,49,.72); border:1px solid var(--border-light); border-radius:10px; padding:.85rem .9rem .7rem; position:relative; overflow:hidden; }
.tk-metric strong { display:block; font-size:1.5rem; font-weight:800; line-height:1.1; margin-bottom:.25rem; }
.tk-metric > span { color:var(--text-secondary); font-size:.72rem; text-transform:uppercase; font-weight:800; letter-spacing:.04em; }
.tk-metric-accent { position:absolute; bottom:0; left:0; right:0; height:3px; }
.tk-metric.accent-blue .tk-metric-accent { background:linear-gradient(90deg,#5865f2,#7289da); }
.tk-metric.accent-red .tk-metric-accent { background:linear-gradient(90deg,#ff6b6b,#ff9f43); }
.tk-metric.accent-orange .tk-metric-accent { background:linear-gradient(90deg,#ff9f43,#ffd43b); }
.tk-metric.accent-green .tk-metric-accent { background:linear-gradient(90deg,#51cf66,#94e07a); }
.tk-metric.accent-yellow .tk-metric-accent { background:linear-gradient(90deg,#ffd43b,#f0b232); }
.tk-metric-stars { display:flex; gap:.05rem; margin-top:.25rem; font-size:.85rem; line-height:1; }
.tk-metric-stars .s-filled { color:#ffd43b; }
.tk-metric-stars .s-empty { color:rgba(255,255,255,.15); }

/* Priority pills */
.tk-priority { font-size:.68rem; font-weight:900; text-transform:uppercase; padding:.14rem .4rem; border-radius:999px; border:1px solid; white-space:nowrap; flex-shrink:0; }
.tk-priority.low { color:#57f287; border-color:rgba(87,242,135,.35); background:rgba(87,242,135,.12); }
.tk-priority.normal { color:#f0b232; border-color:rgba(240,178,50,.35); background:rgba(240,178,50,.12); }
.tk-priority.high { color:#ff6b6b; border-color:rgba(255,107,107,.42); background:rgba(255,107,107,.14); }
.tk-priority.sla { color:#ff9f43; border-color:rgba(255,159,67,.42); background:rgba(255,159,67,.14); }

/* Kanban board */
.tk-board { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; align-items:start; }
.tk-lane { border:1px solid var(--border-light); background:rgba(0,0,0,.12); border-radius:10px; padding:.7rem; display:grid; gap:.45rem; min-height:120px; }
.tk-lane-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:.15rem; }
.tk-lane-header h3 { margin:0; font-size:.78rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:var(--text-secondary); }
.tk-lane-count { font-size:.7rem; font-weight:800; padding:.1rem .42rem; border-radius:999px; background:rgba(255,255,255,.07); color:var(--text-secondary); }
.tk-lane[data-lane="open"] { border-color:rgba(88,101,242,.4); }
.tk-lane[data-lane="open"] .tk-lane-header h3 { color:#7289da; }
.tk-lane[data-lane="waiting_user"] { border-color:rgba(240,178,50,.4); }
.tk-lane[data-lane="waiting_user"] .tk-lane-header h3 { color:#f0b232; }
.tk-lane[data-lane="waiting_staff"] { border-color:rgba(255,159,67,.4); }
.tk-lane[data-lane="waiting_staff"] .tk-lane-header h3 { color:#ff9f43; }
.tk-lane[data-lane="resolved"] { border-color:rgba(87,242,135,.35); }
.tk-lane[data-lane="resolved"] .tk-lane-header h3 { color:#57f287; }

/* Kanban cards */
.tk-board-card { background:rgba(255,255,255,.035); border:1px solid var(--border-light); border-radius:8px; padding:.6rem .7rem; display:flex; flex-direction:column; gap:.3rem; transition:border-color .15s,background .15s; }
.tk-board-card:hover { background:rgba(255,255,255,.055); border-color:rgba(88,101,242,.45); }
.tk-board-card.sla-breach { border-color:rgba(255,107,107,.45); }
.tk-board-card.sla-breach:hover { border-color:rgba(255,107,107,.7); }
.tk-card-top { display:flex; align-items:flex-start; justify-content:space-between; gap:.4rem; }
.tk-card-name { font-size:.82rem; font-weight:700; color:var(--text-primary); line-height:1.3; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.tk-card-owner { font-size:.73rem; color:var(--text-secondary); }
.tk-card-meta { display:flex; align-items:center; gap:.35rem; flex-wrap:wrap; }
.tk-card-type { font-size:.67rem; font-weight:700; color:var(--text-secondary); background:rgba(255,255,255,.07); padding:.1rem .38rem; border-radius:4px; }
.tk-claimed-badge { font-size:.67rem; font-weight:700; color:var(--success); background:rgba(35,165,89,.13); border:1px solid rgba(35,165,89,.27); padding:.1rem .38rem; border-radius:999px; }
.tk-card-footer { display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; margin-top:.05rem; }
.tk-card-age { font-size:.7rem; color:var(--text-secondary); display:flex; align-items:center; gap:.3rem; }
.tk-card-age.is-overdue { color:var(--warning); font-weight:700; }

/* Ticket type cards */
.tk-type-card { background:rgba(255,255,255,.04); border:1px solid var(--border-light); border-radius:8px; padding:.55rem .65rem; display:grid; gap:.38rem; }
.tk-type-card-top { display:grid; grid-template-columns:44px 1fr 88px 26px; gap:.4rem; align-items:center; }
.tk-type-card-top .tk-type-emoji { text-align:center; }
.tk-type-card-routing { display:grid; grid-template-columns:1fr 1fr; gap:.4rem; }
.tk-type-card-routing select { font-size:.72rem; padding:.32rem .4rem; border-radius:5px; border:1px solid var(--border-light); background:var(--bg-tertiary); color:var(--text-secondary); width:100%; min-width:0; }
.tk-type-card-top input[type="text"] { font-size:.85rem; padding:.38rem .55rem; border-radius:5px; border:1px solid var(--border-light); background:var(--bg-tertiary); color:var(--text-primary); width:100%; }
.tk-type-card-top select { font-size:.78rem; padding:.38rem .4rem; border-radius:5px; border:1px solid var(--border-light); background:var(--bg-tertiary); color:var(--text-primary); }
.tk-type-card-desc input[type="text"] { font-size:.78rem; padding:.35rem .55rem; border-radius:5px; border:1px solid var(--border-light); background:var(--bg-tertiary); color:var(--text-secondary); width:100%; }
.tk-type-remove { background:rgba(242,63,67,.13); border:1px solid rgba(242,63,67,.28); color:var(--danger); border-radius:5px; padding:0; width:26px; height:26px; cursor:pointer; font-size:1rem; line-height:1; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:background .12s; }
.tk-type-remove:hover { background:rgba(242,63,67,.24); }

/* Archive */
.tk-archive-search { margin-bottom:.55rem; }
.tk-archive-search input { width:100%; padding:.55rem .8rem; border-radius:8px; border:1px solid var(--border-light); background:var(--bg-tertiary); color:var(--text-primary); font-size:.85rem; transition:border-color .15s; }
.tk-archive-search input:focus { outline:none; border-color:rgba(88,101,242,.5); }
.tk-archive-list { display:grid; gap:.5rem; max-height:320px; overflow:auto; }
.tk-archive-row { display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; border:1px solid var(--border-light); background:rgba(0,0,0,.14); border-radius:8px; padding:.65rem .75rem; transition:border-color .12s; }
.tk-archive-row:hover { border-color:rgba(88,101,242,.35); }
.tk-archive-row[hidden] { display:none; }
.tk-archive-left { flex:1; display:grid; gap:.18rem; min-width:0; }
.tk-archive-title { display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; }
.tk-archive-type { font-size:.82rem; font-weight:700; color:var(--text-primary); }
.tk-archive-status { font-size:.67rem; font-weight:800; text-transform:uppercase; padding:.1rem .38rem; border-radius:4px; background:rgba(255,255,255,.07); color:var(--text-secondary); }
.tk-archive-status.closed { background:rgba(87,242,135,.1); color:#57f287; }
.tk-archive-status.open { background:rgba(88,101,242,.1); color:#7289da; }
.tk-archive-owner { font-size:.75rem; color:var(--text-secondary); }
.tk-archive-close-reason { font-size:.73rem; color:var(--text-secondary); font-style:italic; }
.tk-archive-footer { display:flex; gap:.55rem; font-size:.7rem; color:var(--text-secondary); flex-wrap:wrap; margin-top:.1rem; }

/* Legacy */
.tk-ticket-list { display:grid; gap:.55rem; max-height:260px; overflow:auto; }
.tk-ticket-row { display:grid; grid-template-columns:1fr auto; gap:.5rem; border:1px solid var(--border-light); background:rgba(0,0,0,.16); border-radius:8px; padding:.65rem; }

#ticketsForm > .tk-card:nth-of-type(1) { order: 2; }
#ticketsForm > .tk-card:nth-of-type(2) { order: 1; }
#ticketsForm > .tk-card:nth-of-type(3) { order: 3; }

@media (max-width: 1100px) { .tk-compact { grid-template-columns: 1fr 1fr; } }
@media (max-width: 900px) { .tk-metric-grid { grid-template-columns:repeat(3,minmax(0,1fr)); } }
@media (max-width: 800px) { .tk-compact { grid-template-columns: 1fr; } .tk-board { grid-template-columns: 1fr 1fr; } }
@media (max-width: 520px) { .tk-board { grid-template-columns: 1fr; } .tk-metric-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }

.alert { padding: 10px; border-radius: 6px; font-size: 0.85rem; margin-bottom: 0.8rem; border-left: 4px solid; }
.alert-success { background: rgba(81,207,102,.1); color: #51cf66; border-color: #51cf66; }
.alert-error { background: rgba(255,107,107,.1); color: #ff6b6b; border-color: #ff6b6b; }
.tk-note { background: rgba(88,101,242,0.1); border: 1px solid rgba(88,101,242,0.25); border-radius: 8px; padding: 0.75rem; color: var(--text-secondary); font-size: 0.8rem; line-height: 1.35; }
.tk-note a { color: var(--primary); font-weight: 800; text-decoration: none; }
.tk-test-result { display:none; padding:0.75rem; border-radius:8px; border:1px solid var(--border-light); background:rgba(0,0,0,.16); font-size:0.8rem; color:var(--text-secondary); line-height:1.45; }
.tk-test-result.success { display:block; border-color:rgba(81,207,102,.35); color:#b2f2bb; }
.tk-test-result.error { display:block; border-color:rgba(255,107,107,.45); color:#ffb4b4; }
.tk-test-result.info { display:block; border-color:rgba(88,101,242,.4); color:#c7d2fe; }
</style>

<div class="module-page">

<section class="dashboard-page-header">
    <div class="dashboard-page-copy">
        <span class="dashboard-page-eyebrow"><?= t('tk.eyebrow') ?></span>
        <h1>Tickets</h1>
        <p><?= t('tk.subtitle') ?></p>
        <div class="dashboard-page-meta">
            <span class="status-badge <?php echo $ticketsEnabled ? 'active' : 'inactive'; ?>"><?php echo $ticketsEnabled ? 'Aktiv' : 'Inaktiv'; ?></span>
        </div>
    </div>
    <div class="module-header-actions">
        <form method="GET">
            <select class="module-header-select" name="guildId" onchange="this.form.submit()">
                <?php foreach ($guilds as $g): ?>
                    <option value="<?php echo esc($g['id']); ?>" <?php echo $guildId === ($g['id'] ?? '') ? 'selected' : ''; ?>><?php echo esc($g['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</section>

<?php if ($message): ?><div class="alert alert-<?php echo esc($messageType); ?>"><?php echo esc($message); ?></div><?php endif; ?>

<?php if (!$ticketsEnabled): ?>
    <div class="empty-state">
        <strong><?= t('tk.disabled_title') ?></strong>
        <p>Aktiviere das Modul und starte danach mit Panel, Routing und Staff-Workflow.</p>
        <a class="btn-icon cta btn-primary-ui" href="modules.php?guildId=<?php echo urlencode($guildId); ?>">Modul aktivieren</a>
    </div>
<?php else: ?>
    <form method="POST" class="tk-compact" id="ticketsForm">
        <input type="hidden" name="guildId" value="<?php echo esc($guildId); ?>">
        <input type="hidden" name="action" value="save">
        
        <!-- COLUMN 1: SETUP -->
        <div class="tk-card">
            <h2><span class="i">⚙️</span> <?= t('tk.tech_setup') ?></h2>
            <div class="tk-note">
                <?= t('tk.logs_hint_pre') ?> <a href="logging.php?guildId=<?php echo urlencode($guildId); ?>">Logging</a><?= t('tk.logs_hint_post') ?>
            </div>
            
            <div class="tk-field">
                <label><?= t('tk.category') ?></label>
                <select name="categoryId">
                    <option value=""><?= t('tk.category_new') ?></option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo esc($cat['id']); ?>" <?php echo ($settings['categoryId'] ?? '') === $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo esc($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="tk-field">
                <label><?= t('tk.staff_role') ?></label>
                <select name="staffRoleId">
                    <option value=""><?= t('tk.staff_none') ?></option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo esc($role['id']); ?>" <?php echo ($settings['staffRoleId'] ?? '') === $role['id'] ? 'selected' : ''; ?>>
                            <?php echo esc($role['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small><?= t('tk.staff_hint') ?></small>
            </div>

            <div class="tk-field">
                <label><?= t('tk.high_role') ?> <small style="font-weight:400; text-transform:none; font-size:.7rem;">(<?= t('common.optional') ?>)</small></label>
                <select name="highTeamRoleId">
                    <option value=""><?= t('tk.high_none') ?></option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo esc($role['id']); ?>" <?php echo ($settings['highTeamRoleId'] ?? '') === $role['id'] ? 'selected' : ''; ?>>
                            <?php echo esc($role['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small><?= t('tk.high_hint') ?></small>
            </div>

            <div class="tk-field">
                <label><?= t('tk.transcript') ?></label>
                <select name="transcriptChannelId">
                    <option value=""><?= t('tk.transcript_none') ?></option>
                    <?php foreach ($channels as $channel): ?>
                        <option value="<?php echo esc($channel['id']); ?>" <?php echo ($settings['transcriptChannelId'] ?? '') === $channel['id'] ? 'selected' : ''; ?>>
                            #<?php echo esc($channel['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small><?= t('tk.transcript_hint') ?></small>
            </div>

            <div class="tk-field">
                <label><?= t('tk.priority') ?></label>
                <select name="defaultPriority">
                    <?php foreach ($priorityLabels as $value => $label): ?>
                        <option value="<?php echo esc($value); ?>" <?php echo ($settings['defaultPriority'] ?? 'normal') === $value ? 'selected' : ''; ?>><?php echo esc($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="tk-field">
                <label><?= t('tk.close_delay') ?></label>
                <input type="text" name="closeDelaySeconds" value="<?php echo esc($settings['closeDelaySeconds'] ?? 5); ?>">
            </div>

            <div class="tk-field">
                <label><?= t('tk.sla') ?></label>
                <input type="text" name="slaMinutes" value="<?php echo esc($settings['slaMinutes'] ?? 240); ?>">
                <small><?= t('tk.sla_hint') ?></small>
            </div>

            <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:var(--text-secondary);">
                <input type="checkbox" name="enableClaiming" <?php echo ($settings['enableClaiming'] ?? true) ? 'checked' : ''; ?>>
                Enable staff claim buttons
            </label>
            <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:var(--text-secondary);">
                <input type="checkbox" name="requireCloseReason" <?php echo !empty($settings['requireCloseReason']) ? 'checked' : ''; ?>>
                Require close reason in staff workflow
            </label>

            <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:var(--text-secondary);">
                <input type="checkbox" name="enableTicketTypes" <?php echo !empty($settings['enableTicketTypes']) ? 'checked' : ''; ?>>
                <?= t('tk.menu_toggle') ?>
            </label>
            <small style="color:var(--text-secondary); font-size:.72rem; margin-top:-.3rem;"><?= t('tk.menu_toggle_hint') ?></small>

            <button type="submit" id="ticketsSaveBtn" class="btn-icon" style="margin-top:0.5rem; justify-content:center; background:var(--primary); color:#fff; border:none; padding:0.7rem;"><span class="i">💾</span> Save Settings</button>
        </div>

        <!-- COLUMN 2: PANEL CONFIG -->
        <div class="tk-card">
            <h2><span class="i">🎫</span> <?= t('tk.panel_design') ?></h2>
            
            <div class="tk-field">
                <label><?= t('tk.panel_title') ?></label>
                <input type="text" name="panelTitle" id="tkTitle" value="<?php echo esc($settings['panelTitle'] ?? 'Discord Ticket System'); ?>">
            </div>

            <div class="tk-field">
                <label><?= t('tk.panel_desc') ?></label>
                <textarea name="panelDescription" id="tkDesc" style="min-height:100px;"><?php echo esc($settings['panelDescription'] ?? 'Du brauchst Hilfe? Wähle unten eine Kategorie aus und unser Team kümmert sich um dein Anliegen.'); ?></textarea>
            </div>

            <div class="tk-field">
                <label><?= t('tk.placeholder') ?></label>
                <input type="text" name="panelPlaceholder" id="tkPlaceholder" value="<?php echo esc($panelPlaceholder); ?>">
                <small><?= t('tk.placeholder_hint') ?></small>
            </div>

            <div class="tk-field">
                <label><?= t('tk.button_label') ?></label>
                <input type="text" name="panelButtonLabel" id="tkBtn" value="<?php echo esc($settings['panelButtonLabel'] ?? 'Ticket öffnen'); ?>">
                <small><?= t('tk.button_hint') ?></small>
            </div>

            <div class="tk-section-title"><?= t('tk.branding') ?></div>
            <div class="tk-field">
                <label><?= t('tk.banner') ?></label>
                <input type="text" name="panelBannerUrl" id="tkBanner" value="<?php echo esc($panelBannerUrl); ?>" placeholder="<?php echo esc($defaultBannerUrl); ?>">
                <div style="display:flex; gap:.4rem; margin-top:.35rem;">
                    <button type="button" id="tkUseDefaultBanner" data-url="<?php echo esc($defaultBannerUrl); ?>" class="btn-icon" style="font-size:.72rem; padding:.3rem .6rem; background:rgba(102,126,234,.14); border-color:rgba(102,126,234,.35); color:#c7d2fe;"><?= t('tk.banner_default') ?></button>
                    <button type="button" id="tkClearBanner" class="btn-icon" style="font-size:.72rem; padding:.3rem .6rem;"><?= t('tk.banner_clear') ?></button>
                </div>
                <small><?= t('tk.banner_hint') ?></small>
            </div>

            <div class="tk-field">
                <label><?= t('tk.accent') ?></label>
                <div style="display:flex; gap:.5rem; align-items:center;">
                    <input type="color" name="panelColor" id="tkColor" value="<?php echo esc($panelColor); ?>" style="width:46px; height:34px; padding:2px; background:var(--bg-tertiary); border:1px solid var(--border-light); border-radius:5px;">
                    <input type="text" id="tkColorText" value="<?php echo esc($panelColor); ?>" style="flex:1;" readonly>
                </div>
            </div>

            <div class="tk-field">
                <label><?= t('tk.brand_name') ?></label>
                <input type="text" name="panelBrandName" id="tkBrand" value="<?php echo esc($panelBrandName); ?>">
                <small><?= t('tk.brand_hint') ?></small>
            </div>

            <div class="tk-field">
                <label><?= t('tk.footer') ?></label>
                <input type="text" name="panelFooterText" id="tkFooter" value="<?php echo esc($panelFooterText); ?>">
                <small><?= t('tk.footer_hint') ?></small>
            </div>

            <div class="tk-section-title"><?= t('tk.live_status') ?></div>
            <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:var(--text-secondary);">
                <input type="checkbox" name="panelShowLiveStatus" <?php echo $panelShowLiveStatus ? 'checked' : ''; ?>>
                <?= t('tk.live_show') ?>
            </label>
            <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:var(--text-secondary);">
                <input type="checkbox" name="panelShowStaffOnline" <?php echo $panelShowStaffOnline ? 'checked' : ''; ?>>
                <?= t('tk.live_staff') ?><?php if (!empty($liveStatus['staffTracked'])): ?> <span style="font-size:.72rem;">(<?php echo (int)($liveStatus['staffOnline'] ?? 0); ?>/<?php echo (int)($liveStatus['staffTotal'] ?? 0); ?>)</span><?php endif; ?>
            </label>
            <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:var(--text-secondary);">
                <input type="checkbox" name="panelShowQueue" <?php echo $panelShowQueue ? 'checked' : ''; ?>>
                <?= t('tk.live_queue') ?>
            </label>
            <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:var(--text-secondary);">
                <input type="checkbox" name="panelShowRating" <?php echo $panelShowRating ? 'checked' : ''; ?>>
                <?= t('tk.live_rating') ?>
            </label>
            <small style="color:var(--text-secondary); font-size:.72rem;"><?= t('tk.live_hint') ?></small>

            <div class="tk-section-title"><?= t('tk.panel_info') ?></div>
            <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:var(--text-secondary);">
                <input type="checkbox" name="panelInfoEnabled" <?php echo $panelInfoEnabled ? 'checked' : ''; ?>>
                <?= t('tk.info_extra') ?>
            </label>
            <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:var(--text-secondary);">
                <input type="checkbox" name="panelInfoShowOpenTickets" <?php echo $panelInfoShowOpenTickets ? 'checked' : ''; ?>>
                <?= t('tk.info_open') ?>
            </label>
            <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:var(--text-secondary);">
                <input type="checkbox" name="panelInfoShowAverageResolution" <?php echo $panelInfoShowAverageResolution ? 'checked' : ''; ?>>
                <?= t('tk.info_avg') ?>
            </label>
            <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:var(--text-secondary);">
                <input type="checkbox" name="panelInfoShowOverdueTickets" <?php echo $panelInfoShowOverdueTickets ? 'checked' : ''; ?>>
                <?= t('tk.info_overdue') ?>
            </label>
            <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:var(--text-secondary);">
                <input type="checkbox" name="panelInfoShowLastUpdated" <?php echo $panelInfoShowLastUpdated ? 'checked' : ''; ?>>
                <?= t('tk.info_updated') ?>
            </label>
            <small style="color:var(--text-secondary); font-size:.72rem;"><?= t('tk.info_hint') ?></small>

            <div class="tk-section-title"><?= t('tk.types') ?></div>
            <div id="tkTypeContainer" style="display:grid; gap:.42rem;">
                <?php foreach ($ticketTypes as $type): ?>
                    <div class="tk-type-card">
                        <div class="tk-type-card-top">
                            <input type="text" name="typeEmojis[]" class="tk-type-emoji" value="<?php echo esc($type['emoji'] ?? ''); ?>" placeholder="🎫">
                            <input type="text" name="typeLabels[]" value="<?php echo esc($type['label'] ?? ''); ?>" placeholder="<?= t('tk.cat_label_ph') ?>">
                            <select name="typePriorities[]">
                                <?php foreach ($priorityLabels as $value => $label): ?>
                                    <option value="<?php echo esc($value); ?>" <?php echo ($type['priority'] ?? 'normal') === $value ? 'selected' : ''; ?>><?php echo esc($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="tk-type-remove" title="<?= t('tk.banner_clear') ?>">&times;</button>
                        </div>
                        <div class="tk-type-card-desc">
                            <input type="text" name="typeDescriptions[]" value="<?php echo esc($type['description'] ?? ''); ?>" placeholder="<?= t('tk.cat_desc_ph') ?>">
                        </div>
                        <div class="tk-type-card-routing">
                            <select name="typeCategories[]">
                                <option value=""><?= t('tk.cat_default_cat') ?></option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo esc($category['id']); ?>" <?php echo ($type['categoryId'] ?? '') === $category['id'] ? 'selected' : ''; ?>><?php echo esc($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="typeStaffRoles[]">
                                <option value=""><?= t('tk.cat_default_team') ?></option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo esc($role['id']); ?>" <?php echo ($type['staffRoleId'] ?? '') === $role['id'] ? 'selected' : ''; ?>>@<?php echo esc($role['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="tkAddType" class="btn-icon" style="font-size:.8rem; padding:.4rem .75rem; background:rgba(102,126,234,.14); border-color:rgba(102,126,234,.35); color:#c7d2fe; margin-top:.2rem;"><span class="i">+</span> <?= t('tk.cat_add') ?></button>
            <small style="color:var(--text-secondary); font-size:.72rem;"><?= t('tk.types_hint') ?></small>

            <div class="tk-section-title"><?= t('tk.deployment') ?></div>
            <div style="font-size:0.75rem; color:var(--text-secondary); display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; margin-bottom:0.3rem;">
                <span><?php echo $ticketPanelCount; ?> / <?php echo $maxTicketPanels < 0 ? '∞' : $maxTicketPanels; ?> Ticket-Panels genutzt</span>
                <?php if ($atTicketPanelLimit): ?><span class="status-badge warning" style="font-size:0.68rem;"><?= t('common.limit_reached') ?></span><?php endif; ?>
            </div>
            <div class="tk-field">
                <label><?= t('tk.target_channel') ?></label>
                <select name="panelChannelId">
                    <option value=""><?= t('tk.select_channel') ?></option>
                    <?php $deployedChannelIds = array_column($deployedPanels, 'channelId'); ?>
                    <?php foreach ($channels as $channel): ?>
                        <option value="<?php echo esc($channel['id']); ?>">#<?php echo esc($channel['name']); ?><?php echo in_array($channel['id'], $deployedChannelIds, true) ? ' (Panel aktiv)' : ''; ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color:var(--text-secondary); font-size:.72rem;">Ein Kanal mit bereits aktivem Panel wird beim Senden aktualisiert statt doppelt gepostet.</small>
            </div>
            <?php if (count($deployedPanels) > 0): ?>
            <div class="tk-note" style="background:rgba(87,242,135,.08); border-color:rgba(87,242,135,.25); color:#51cf66; display:flex; flex-direction:column; gap:.5rem;">
                <?php foreach ($deployedPanels as $panel): ?>
                <div style="display:flex; align-items:center; justify-content:space-between; gap:.5rem; flex-wrap:wrap;">
                    <span>✅ <strong>#<?php echo esc($panel['channelName'] ?? $panel['channelId']); ?></strong> &mdash;
                        <a href="<?php echo esc($panel['url']); ?>" target="_blank" style="color:#51cf66;">Zur Nachricht →</a>
                    </span>
                    <button type="button" class="tk-panel-remove-btn" data-channel-id="<?php echo esc($panel['channelId']); ?>" style="background:rgba(237,66,69,.14); border:1px solid rgba(237,66,69,.35); color:#ff8787; border-radius:6px; padding:.25rem .6rem; font-size:.72rem; cursor:pointer;">Entfernen</button>
                </div>
                <?php endforeach; ?>
                <small><?= t('tk.resend_hint') ?></small>
            </div>
            <?php else: ?>
            <div class="tk-note">
                ⚠️ Noch kein Panel gesendet. Wähle einen Kanal und klicke auf "Send Panel to Discord".
            </div>
            <?php endif; ?>
            <div id="tkPanelRemoveResult" class="tk-test-result" style="display:none;"></div>
            <?php if ($atTicketPanelLimit): ?>
            <div class="upgrade-limit-card">
                <div class="ulc-icon">🚫</div>
                <div class="ulc-body">
                    <div class="ulc-title"><?= t('tk.panel_limit') ?></div>
                    <div class="ulc-hint">💎 Premium ermöglicht bis zu 3 Panels, Pro unbegrenzt viele Ticket-Panels. Ein bereits aktiver Kanal laesst sich weiterhin aktualisieren.</div>
                </div>
                <a href="server-plans.php<?php echo $guildId ? '?guildId=' . urlencode($guildId) : ''; ?>" class="ulc-cta">Jetzt upgraden</a>
            </div>
            <?php endif; ?>
            <button type="button" id="tkSendPanelBtn" class="btn-icon" style="justify-content:center; background:#5865f2; color:#fff; border:none; padding:0.7rem;"><span class="i">🚀</span> Send Panel to Discord</button>
            <div id="tkPanelResult" class="tk-test-result" style="margin-top:0.5rem;"></div>
        </div>

        <!-- COLUMN 3: PREVIEW -->
        <div class="tk-card">
            <h2><span class="i">👁️</span> <?= t('tk.live_preview') ?></h2>
            <div class="discord-preview" id="pPreview">
                <div id="pBrand" class="discord-brand"></div>
                <div id="pTitle" class="discord-title"></div>
                <div id="pDesc" class="discord-desc"></div>
                <img id="pBanner" class="discord-banner" alt="" hidden>
                <div id="pDivider" class="discord-divider"></div>
                <div id="pStatus" class="discord-status"></div>
                <div id="pSelect" class="discord-select"><span id="pPlaceholder"></span><span>⌄</span></div>
                <div id="pOptions" class="discord-options"></div>
                <div id="pButtonWrap" class="discord-btn" hidden>🎫 <span id="pBtn"></span></div>
                <div id="pFooter" class="discord-footer"></div>
            </div>

            <div class="tk-section-title"><?= t('tk.instructions') ?></div>
            <div style="font-size:0.75rem; color:var(--text-secondary); display:grid; gap:0.4rem;">
                <p>• <strong>Transcripts:</strong> Automatisch als .txt + .html gespeichert wenn ein Ticket geschlossen wird.</p>
                <p>• <strong>Permissions:</strong> Bot verwaltet Channel-Berechtigungen automatisch für den User und Staff.</p>
                <p>• <strong>Claim:</strong> Staff kann Tickets via Button <em>oder</em> <code>/ticket claim</code> beanspruchen.</p>
                <p>• <strong>Controls:</strong> Staff kann claimen, unclaimen, Priorität setzen, Status setzen und schließen.</p>
                <p>• <strong>Staff Ops:</strong> <code>/ticket note</code>, <code>/ticket adduser</code>, <code>/ticket removeuser</code> innerhalb des Ticket-Channels.</p>
            </div>

            <div class="tk-section-title"><?= t('tk.live_test') ?></div>
            <div class="tk-field">
                <label><?= t('tk.test_reason') ?></label>
                <input type="text" id="tkTestReason" placeholder="z.B. Dashboard Test: Staff antwortet nicht.">
            </div>
            <button type="button" id="tkTestBtn" class="btn-icon" style="justify-content:center; background:rgba(88,101,242,.14); border-color:rgba(88,101,242,.4); color:#c7d2fe;"><span class="i">🧪</span> Test-Ticket erstellen</button>
            <div id="tkTestResult" class="tk-test-result"></div>
        </div>

        <div class="ux-savebar" id="ticketsSaveBar">
            <div class="ux-save-info">
                <strong><?= t('common.unsaved') ?></strong>
                <span><?= t('tk.save_hint') ?></span>
            </div>
            <div class="ux-save-actions">
                <span class="ux-save-status" id="ticketsSaveStatus"><?= t('common.ready') ?></span>
                <button type="submit" name="action" value="save" id="ticketsStickySaveBtn" class="btn-icon btn-primary-ui"><span class="i">💾</span> Speichern</button>
            </div>
        </div>
    </form>

    <div class="tk-card" style="margin-top:1.25rem;">
        <h2><span class="i">📌</span> Live Ticket Desk</h2>
        <div class="tk-metric-grid">
            <div class="tk-metric accent-blue">
                <strong><?php echo (int)($ticketStats['open'] ?? count($openTickets)); ?></strong>
                <span>Open</span>
                <div class="tk-metric-accent"></div>
            </div>
            <div class="tk-metric accent-red">
                <strong><?php echo (int)($ticketStats['highOpen'] ?? count(array_filter($openTickets, fn($t) => ($t['priority'] ?? '') === 'high'))); ?></strong>
                <span>High Priority</span>
                <div class="tk-metric-accent"></div>
            </div>
            <div class="tk-metric accent-orange">
                <strong><?php echo (int)($ticketStats['overdueOpen'] ?? count(array_filter($openTickets, fn($t) => !empty($t['slaBreached'])))); ?></strong>
                <span><?= t('tk.a_overdue') ?></span>
                <div class="tk-metric-accent"></div>
            </div>
            <div class="tk-metric accent-green">
                <strong><?php echo (int)($ticketStats['closed'] ?? 0); ?></strong>
                <span><?= t('tk.archive') ?></span>
                <div class="tk-metric-accent"></div>
            </div>
            <div class="tk-metric accent-yellow">
                <strong><?php echo esc($feedbackLabel); ?></strong>
                <span>Feedback</span>
                <?php if ($feedbackStars > 0): ?>
                <div class="tk-metric-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="<?php echo $i <= $feedbackStars ? 's-filled' : 's-empty'; ?>">&#9733;</span>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
                <div class="tk-metric-accent"></div>
            </div>
        </div>
        <?php $lanes = ['open' => 'Open', 'waiting_user' => 'Waiting User', 'waiting_staff' => 'Waiting Staff', 'resolved' => 'Resolved']; ?>
        <div class="tk-board">
            <?php foreach ($lanes as $laneKey => $laneLabel):
                $laneTickets = array_values(array_filter($openTickets, fn($t) => ($t['status'] ?? 'open') === $laneKey)); ?>
                <div class="tk-lane" data-lane="<?php echo esc($laneKey); ?>">
                    <div class="tk-lane-header">
                        <h3><?php echo esc($laneLabel); ?></h3>
                        <span class="tk-lane-count"><?php echo count($laneTickets); ?></span>
                    </div>
                    <?php foreach ($laneTickets as $ticket): ?>
                        <div class="tk-board-card<?php echo !empty($ticket['slaBreached']) ? ' sla-breach' : ''; ?>">
                            <div class="tk-card-top">
                                <span class="tk-card-name">#<?php echo esc($ticket['name']); ?></span>
                                <span class="tk-priority <?php echo esc($ticket['priority'] ?? 'normal'); ?>"><?php echo esc($priorityLabels[$ticket['priority'] ?? 'normal'] ?? 'Normal'); ?></span>
                            </div>
                            <div class="tk-card-owner"><?php echo esc($ticket['ownerTag'] ?? $ticket['ownerId'] ?? 'Unknown'); ?></div>
                            <div class="tk-card-meta">
                                <span class="tk-card-type"><?php echo esc($ticket['type'] ?? 'Support'); ?></span>
                                <?php if (!empty($ticket['claimedBy'])): ?>
                                    <span class="tk-claimed-badge">claimed: <?php echo esc($ticket['claimedBy']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="tk-card-footer">
                                <span class="tk-card-age<?php echo !empty($ticket['slaBreached']) ? ' is-overdue' : ''; ?>">
                                    <?php echo formatTicketAge((int)($ticket['ageMinutes'] ?? 0)); ?>
                                    <?php if (!empty($ticket['slaBreached'])): ?><span class="tk-priority sla">SLA!</span><?php endif; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($laneTickets)): ?>
                        <div style="color:var(--text-secondary); font-size:.75rem; text-align:center; padding:.4rem 0;"><?= t('tk.empty') ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="tk-card" style="margin-top:1.25rem;">
        <h2><span class="i">🗂️</span> Recent Ticket Archive</h2>
        <div class="tk-archive-search">
            <input type="text" id="tkArchiveSearch" placeholder="Suche nach Owner, Typ, Status, Schließgrund...">
        </div>
        <div class="tk-archive-list">
            <?php foreach ($recentTickets as $ticket):
                $searchData = strtolower(
                    ($ticket['ownerTag'] ?? $ticket['ownerId'] ?? '') . ' ' .
                    ($ticket['type'] ?? '') . ' ' .
                    ($ticket['status'] ?? '') . ' ' .
                    ($ticket['closeReason'] ?? '')
                );
            ?>
                <div class="tk-archive-row" data-search="<?php echo esc($searchData); ?>">
                    <div class="tk-archive-left">
                        <div class="tk-archive-title">
                            <span class="tk-archive-type"><?php echo esc($ticket['type'] ?? 'Support'); ?></span>
                            <span class="tk-archive-status <?php echo esc($ticket['status'] ?? 'open'); ?>"><?php echo esc($ticket['status'] ?? 'open'); ?></span>
                        </div>
                        <div class="tk-archive-owner"><?php echo esc($ticket['ownerTag'] ?? $ticket['ownerId'] ?? 'Unknown'); ?></div>
                        <?php if (!empty($ticket['closeReason'])): ?>
                            <div class="tk-archive-close-reason">&ldquo;<?php echo esc($ticket['closeReason']); ?>&rdquo;</div>
                        <?php endif; ?>
                        <div class="tk-archive-footer">
                            <?php if (!empty($ticket['noteCount'])): ?>
                                <span>&#128221; <?php echo (int)$ticket['noteCount']; ?> Notes</span>
                            <?php endif; ?>
                            <?php if (!empty($ticket['feedbackRating'])): ?>
                                <?php $r = (int)$ticket['feedbackRating']; ?>
                                <span><?php echo str_repeat('&#9733;', $r) . str_repeat('&#9734;', 5 - $r); ?> <?php echo $r; ?>/5</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="tk-priority <?php echo esc($ticket['priority'] ?? 'normal'); ?>"><?php echo esc($priorityLabels[$ticket['priority'] ?? 'normal'] ?? 'Normal'); ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (empty($recentTickets)): ?>
                <div style="color:var(--text-secondary); font-size:.9rem;"><?= t('tk.archive_hint') ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="tk-card">
        <h2><?= t('tk.analytics') ?></h2>
        <div class="tk-metric-grid">
            <div class="tk-metric">
                <strong><?= $ticketStats['avgResolutionMinutes'] ?? 'N/A' ?></strong>
                <span><?= t('tk.a_avg') ?></span>
            </div>
            <div class="tk-metric">
                <strong><?= $ticketStats['resolvedCount'] ?? 0 ?></strong>
                <span><?= t('tk.a_resolved') ?></span>
            </div>
            <div class="tk-metric">
                <strong><?= $ticketStats['feedback']['average'] ?? 'N/A' ?></strong>
                <span><?= t('tk.a_fb_avg') ?></span>
            </div>
            <div class="tk-metric">
                <strong><?= $ticketStats['feedback']['count'] ?? 0 ?></strong>
                <span><?= t('tk.a_fb_count') ?></span>
            </div>
            <div class="tk-metric">
                <strong><?= $ticketStats['claimed']['openClaimed'] ?? 0 ?></strong>
                <span>Open Claimed</span>
            </div>
        </div>
        <div class="tk-section-title"><?= t('tk.top_claimers') ?></div>
        <ul>
            <?php foreach ($ticketStats['claimed']['topClaimers'] ?? [] as $claimer): ?>
                <li><?= esc($claimer['claimedBy']) ?>: <?= $claimer['count'] ?></li>
            <?php endforeach; ?>
            <?php if (empty($ticketStats['claimed']['topClaimers'])): ?>
                <li>No claim data available</li>
            <?php endif; ?>
        </ul>
    </div>
<?php endif; ?>

</div>

<script>
function escapeHtml(str) {
    return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

const PREVIEW_LIVE_STATUS = <?php echo json_encode([
    'staffOnline' => (int)($liveStatus['staffOnline'] ?? 0),
    'staffTracked' => (bool)($liveStatus['staffTracked'] ?? false),
    'open' => (int)($ticketStats['open'] ?? 0),
    'overdue' => (int)($ticketStats['overdueOpen'] ?? 0),
    'ratingAvg' => (float)($ticketStats['feedback']['average'] ?? 0),
    'ratingCount' => (int)($ticketStats['feedback']['count'] ?? 0),
    'staffLabel' => t('tk.live_staff'),
]); ?>;

function previewQueueLabel() {
    if (PREVIEW_LIVE_STATUS.overdue > 0) return PREVIEW_LIVE_STATUS.overdue + ' überfällig';
    if (PREVIEW_LIVE_STATUS.open === 0) return 'Queue frei';
    return PREVIEW_LIVE_STATUS.open === 1 ? '1 offenes Ticket' : PREVIEW_LIVE_STATUS.open + ' offene Tickets';
}

function previewStatusLine() {
    if (!document.querySelector('[name="panelShowLiveStatus"]')?.checked) return '';
    const parts = [];
    if (document.querySelector('[name="panelShowStaffOnline"]')?.checked && PREVIEW_LIVE_STATUS.staffTracked) {
        parts.push(PREVIEW_LIVE_STATUS.staffOnline + ' ' + PREVIEW_LIVE_STATUS.staffLabel);
    }
    if (document.querySelector('[name="panelShowQueue"]')?.checked) parts.push(previewQueueLabel());
    if (document.querySelector('[name="panelShowRating"]')?.checked && PREVIEW_LIVE_STATUS.ratingCount > 0) {
        parts.push(PREVIEW_LIVE_STATUS.ratingAvg.toFixed(1) + '★ (' + PREVIEW_LIVE_STATUS.ratingCount + ')');
    }
    if (!parts.length) return '';
    const indicator = PREVIEW_LIVE_STATUS.staffTracked && PREVIEW_LIVE_STATUS.staffOnline === 0 ? '🟠' : '🟢';
    return indicator + ' ' + parts.join(' • ');
}

function updatePreview() {
    // Ohne gewaehlten Server wird das Formular nicht gerendert.
    if (!document.getElementById('pPreview') || !document.getElementById('tkTitle')) return;

    const color = document.getElementById('tkColor')?.value || '#667eea';
    const useCategories = document.querySelector('[name="enableTicketTypes"]')?.checked;
    const banner = (document.getElementById('tkBanner')?.value || '').trim();

    document.getElementById('pPreview').style.borderLeftColor = color;
    document.getElementById('pBrand').textContent = (document.getElementById('tkBrand')?.value || 'Eselbande') + ' • online';
    document.getElementById('pTitle').textContent = document.getElementById('tkTitle').value;
    document.getElementById('pDesc').textContent = document.getElementById('tkDesc').value;
    document.getElementById('pPlaceholder').textContent = document.getElementById('tkPlaceholder')?.value || 'Choose a category ...';
    document.getElementById('pBtn').textContent = document.getElementById('tkBtn').value;
    document.getElementById('pFooter').textContent = (document.getElementById('tkFooter')?.value || 'eselbande.com')
        + ' • ' + new Date().toLocaleString('de-DE', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    const bannerEl = document.getElementById('pBanner');
    if (/^https?:\/\//i.test(banner)) {
        bannerEl.src = banner;
        bannerEl.hidden = false;
    } else {
        bannerEl.hidden = true;
    }

    const status = previewStatusLine();
    document.getElementById('pStatus').textContent = status;
    document.getElementById('pStatus').hidden = !status;
    document.getElementById('pDivider').hidden = !status;

    document.getElementById('pSelect').hidden = !useCategories;
    document.getElementById('pOptions').hidden = !useCategories;
    document.getElementById('pButtonWrap').hidden = !!useCategories;

    const options = document.getElementById('pOptions');
    options.innerHTML = '';
    if (useCategories) {
        document.querySelectorAll('#tkTypeContainer .tk-type-card').forEach(card => {
            const label = card.querySelector('[name="typeLabels[]"]')?.value.trim();
            if (!label) return;
            const emoji = card.querySelector('[name="typeEmojis[]"]')?.value.trim() || '';
            const desc = card.querySelector('[name="typeDescriptions[]"]')?.value.trim() || '';
            const row = document.createElement('div');
            row.className = 'discord-option';
            const name = document.createElement('span');
            name.textContent = (emoji ? emoji + ' ' : '') + label;
            row.appendChild(name);
            if (desc) {
                const small = document.createElement('small');
                small.textContent = desc;
                row.appendChild(small);
            }
            options.appendChild(row);
        });
    }
}

document.addEventListener('input', updatePreview);
document.addEventListener('change', updatePreview);
document.addEventListener('DOMContentLoaded', updatePreview);

document.getElementById('tkColor')?.addEventListener('input', event => {
    const text = document.getElementById('tkColorText');
    if (text) text.value = event.target.value;
});

document.getElementById('tkUseDefaultBanner')?.addEventListener('click', event => {
    const input = document.getElementById('tkBanner');
    input.value = event.currentTarget.dataset.url || '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
});

document.getElementById('tkClearBanner')?.addEventListener('click', () => {
    const input = document.getElementById('tkBanner');
    input.value = '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
});

(function() {
    const form = document.getElementById('ticketsForm');
    const saveBtn = document.getElementById('ticketsSaveBtn');
    const stickySaveBtn = document.getElementById('ticketsStickySaveBtn');
    const saveBar = document.getElementById('ticketsSaveBar');
    const saveStatus = document.getElementById('ticketsSaveStatus');
    const testBtn = document.getElementById('tkTestBtn');
    const testReason = document.getElementById('tkTestReason');
    const testResult = document.getElementById('tkTestResult');
    const sendPanelBtn = document.getElementById('tkSendPanelBtn');
    const panelResult = document.getElementById('tkPanelResult');
    if (!form) return;

    let initialState = new URLSearchParams(new FormData(form)).toString();
    let allowUnload = false;

    function currentState() {
        const data = new FormData(form);
        data.set('action', 'save');
        return new URLSearchParams(data).toString();
    }

    function isDirty() {
        return currentState() !== initialState;
    }

    function syncSaveBar() {
        saveBar?.classList.toggle('is-visible', isDirty());
    }

    function setStatus(text, type = '') {
        if (!saveStatus) return;
        saveStatus.textContent = text;
        saveStatus.classList.remove('success', 'error');
        if (type) saveStatus.classList.add(type);
    }

    function setSaveButtonsLoading(loading) {
        [saveBtn, stickySaveBtn].forEach((btn) => {
            if (!btn) return;
            btn.disabled = loading;
            btn.innerHTML = loading ? '<span class="i">⏳</span> Speichert...' : '<span class="i">💾</span> Speichern';
        });
    }

    function setTestResult(message, type = 'info', data = null) {
        if (!testResult) return;
        // message/channelName come from the API response and channel names
        // are attacker-controllable (any member with rename permission), so
        // both must be escaped before going into innerHTML.
        const link = data?.channelId
            ? `<br>Erstellt in <strong>#${escapeHtml(data.channelName || data.channelId)}</strong>.`
            : '';
        testResult.className = `tk-test-result ${type}`;
        testResult.innerHTML = `${escapeHtml(message)}${link}`;
    }

    form.addEventListener('input', syncSaveBar);
    form.addEventListener('change', syncSaveBar);

    window.addEventListener('beforeunload', (event) => {
        if (allowUnload || !isDirty()) return;
        event.preventDefault();
        event.returnValue = '';
    });

    form.addEventListener('submit', async (event) => {
        const submitter = event.submitter;
        const action = submitter?.value || form.querySelector('input[name="action"]')?.value || 'save';

        if (action !== 'save') {
            // Non-save submit actions (e.g. guild selector change) — allow normal navigation
            allowUnload = true;
            return;
        }

        event.preventDefault();
        setSaveButtonsLoading(true);
        setStatus('Speichert...');

        try {
            const data = new FormData(form);
            data.set('action', 'save');
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: data,
                credentials: 'same-origin'
            });
            const json = await response.json().catch(() => ({ success: false, message: 'Ungueltige Serverantwort.' }));
            if (!response.ok || !json.success) {
                throw new Error(json.message || 'Speichern fehlgeschlagen.');
            }

            initialState = currentState();
            allowUnload = false;
            syncSaveBar();
            setStatus('Gespeichert', 'success');
        } catch (error) {
            setStatus('Fehler', 'error');
            alert(error.message || 'Speichern fehlgeschlagen.');
        } finally {
            setSaveButtonsLoading(false);
        }
    });

    testBtn?.addEventListener('click', async () => {
        testBtn.disabled = true;
        testBtn.innerHTML = '<span class="i">⏳</span> Erstellt...';
        setTestResult('Erstelle Test-Ticket mit der aktuell gespeicherten Konfiguration...', 'info');

        try {
            const data = new FormData();
            data.set('guildId', '<?php echo esc($guildId); ?>');
            data.set('action', 'test_ticket');
            data.set('testTicketReason', testReason?.value?.trim() || 'Dashboard test ticket');
            data.set('defaultPriority', form.querySelector('[name="defaultPriority"]')?.value || 'normal');
            data.set('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: data,
                credentials: 'same-origin'
            });
            const json = await response.json().catch(() => {
                console.error('[Dashboard] Non-JSON response (tickets test):', response.status, response.url);
                return { success: false, message: 'Ungueltige Serverantwort.' };
            });
            if (!response.ok || !json.success) {
                throw new Error(json.message || 'Test-Ticket fehlgeschlagen.');
            }
            setTestResult(json.message || 'Test-Ticket erstellt.', 'success', json.data);
        } catch (error) {
            setTestResult(error.message || 'Test-Ticket fehlgeschlagen.', 'error');
        } finally {
            testBtn.disabled = false;
            testBtn.innerHTML = '<span class="i">🧪</span> Test-Ticket erstellen';
        }
    });

    sendPanelBtn?.addEventListener('click', async () => {
        if (!panelResult) return;
        const channelId = form.querySelector('[name="panelChannelId"]')?.value || '';
        if (!channelId) {
            panelResult.className = 'tk-test-result error';
            panelResult.textContent = '⚠️ Bitte erst einen Ziel-Kanal auswählen.';
            return;
        }
        sendPanelBtn.disabled = true;
        sendPanelBtn.innerHTML = '<span class="i">⏳</span> Sendet...';
        panelResult.className = 'tk-test-result info';
        panelResult.textContent = 'Panel wird gesendet...';

        try {
            const data = new FormData(form);
            data.set('action', 'send_panel');
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: data,
                credentials: 'same-origin'
            });
            const json = await response.json().catch(() => {
                console.error('[Dashboard] Non-JSON response (send_panel):', response.status, response.url);
                return { success: false, message: 'Ungültige Serverantwort.' };
            });
            if (!json.success) {
                throw new Error(json.message || 'Panel konnte nicht gesendet werden.');
            }
            panelResult.className = 'tk-test-result success';
            // Only build the link for an actual http(s) URL, and escape it
            // for the href attribute — json.url is API-returned and should
            // always be a discord.com message link, but this closes off a
            // javascript:-scheme or attribute-breakout XSS either way.
            const isSafeUrl = typeof json.url === 'string' && /^https:\/\//i.test(json.url);
            const link = isSafeUrl ? ` <a href="${escapeHtml(json.url)}" target="_blank" rel="noopener" style="color:inherit;">→ Zur Nachricht</a>` : '';
            panelResult.innerHTML = `✅ Panel erfolgreich gesendet!${link}`;
            // Reload so the deployed-panels list (rendered server-side) picks up the new/updated entry.
            setTimeout(() => window.location.reload(), 900);
        } catch (error) {
            panelResult.className = 'tk-test-result error';
            panelResult.textContent = '❌ ' + (error.message || 'Panel konnte nicht gesendet werden.');
        } finally {
            sendPanelBtn.disabled = false;
            sendPanelBtn.innerHTML = '<span class="i">🚀</span> Send Panel to Discord';
        }
    });

    const panelRemoveResult = document.getElementById('tkPanelRemoveResult');
    document.querySelectorAll('.tk-panel-remove-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const channelId = btn.dataset.channelId || '';
            if (!channelId || !panelRemoveResult) return;
            btn.disabled = true;
            panelRemoveResult.style.display = 'block';
            panelRemoveResult.className = 'tk-test-result info';
            panelRemoveResult.textContent = 'Panel wird entfernt...';

            try {
                const data = new FormData();
                data.set('action', 'remove_panel');
                data.set('removeChannelId', channelId);
                data.set('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: data,
                    credentials: 'same-origin'
                });
                const json = await response.json().catch(() => ({ success: false, message: 'Ungueltige Serverantwort.' }));
                if (!json.success) {
                    throw new Error(json.message || 'Panel konnte nicht entfernt werden.');
                }
                panelRemoveResult.className = 'tk-test-result success';
                panelRemoveResult.textContent = '✅ ' + (json.message || 'Panel entfernt.');
                setTimeout(() => window.location.reload(), 700);
            } catch (error) {
                btn.disabled = false;
                panelRemoveResult.className = 'tk-test-result error';
                panelRemoveResult.textContent = '❌ ' + (error.message || 'Panel konnte nicht entfernt werden.');
            }
        });
    });
})();

// Dynamic ticket categories
(function() {
    const container = document.getElementById('tkTypeContainer');
    const addBtn = document.getElementById('tkAddType');
    if (!container || !addBtn) return;

    const MAX_CATEGORIES = 25;
    const CAT_MAX_LABEL = <?php echo json_encode(t('tk.cat_max'), JSON_UNESCAPED_UNICODE); ?>;
    const discordCategories = <?php echo json_encode($categories, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const staffRoles = <?php echo json_encode($roles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function makeSelect(name, placeholder, items, prefix) {
        const sel = document.createElement('select');
        sel.name = name;
        const empty = document.createElement('option');
        empty.value = ''; empty.textContent = placeholder;
        sel.appendChild(empty);
        items.forEach(item => {
            const o = document.createElement('option');
            o.value = item.id; o.textContent = (prefix || '') + item.name;
            sel.appendChild(o);
        });
        return sel;
    }

    function makeRow(label, description, priority) {
        const card = document.createElement('div');
        card.className = 'tk-type-card';

        const top = document.createElement('div');
        top.className = 'tk-type-card-top';

        const emoji = document.createElement('input');
        emoji.type = 'text'; emoji.name = 'typeEmojis[]'; emoji.className = 'tk-type-emoji'; emoji.placeholder = '🎫';

        const i1 = document.createElement('input');
        i1.type = 'text'; i1.name = 'typeLabels[]'; i1.value = label || ''; i1.placeholder = <?php echo json_encode(t('tk.cat_label_ph'), JSON_UNESCAPED_UNICODE); ?>;

        const sel = document.createElement('select');
        sel.name = 'typePriorities[]';
        [['low', 'Low'], ['normal', 'Normal'], ['high', 'High']].forEach(([v, l]) => {
            const o = document.createElement('option');
            o.value = v; o.textContent = l;
            if (v === (priority || 'normal')) o.selected = true;
            sel.appendChild(o);
        });

        const btn = document.createElement('button');
        btn.type = 'button'; btn.className = 'tk-type-remove'; btn.title = 'Entfernen'; btn.textContent = '\u00d7';
        btn.addEventListener('click', () => {
            card.remove();
            document.getElementById('ticketsForm')?.dispatchEvent(new Event('input'));
        });

        top.append(emoji, i1, sel, btn);

        const desc = document.createElement('div');
        desc.className = 'tk-type-card-desc';
        const i2 = document.createElement('input');
        i2.type = 'text'; i2.name = 'typeDescriptions[]'; i2.value = description || ''; i2.placeholder = <?php echo json_encode(t('tk.cat_desc_ph'), JSON_UNESCAPED_UNICODE); ?>;
        desc.appendChild(i2);

        const routing = document.createElement('div');
        routing.className = 'tk-type-card-routing';
        routing.append(
            makeSelect('typeCategories[]', <?php echo json_encode(t('tk.cat_default_cat'), JSON_UNESCAPED_UNICODE); ?>, discordCategories, ''),
            makeSelect('typeStaffRoles[]', <?php echo json_encode(t('tk.cat_default_team'), JSON_UNESCAPED_UNICODE); ?>, staffRoles, '@')
        );

        card.append(top, desc, routing);
        return card;
    }

    container.querySelectorAll('.tk-type-remove').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.closest('.tk-type-card').remove();
            document.getElementById('ticketsForm')?.dispatchEvent(new Event('input'));
        });
    });

    addBtn.addEventListener('click', () => {
        if (container.querySelectorAll('.tk-type-card').length >= MAX_CATEGORIES) {
            const orig = addBtn.innerHTML;
            addBtn.textContent = CAT_MAX_LABEL;
            setTimeout(() => { addBtn.innerHTML = orig; }, 1500);
            return;
        }
        container.appendChild(makeRow());
        document.getElementById('ticketsForm')?.dispatchEvent(new Event('input'));
    });
})();

// Archive search filter
(function() {
    const input = document.getElementById('tkArchiveSearch');
    if (!input) return;
    input.addEventListener('input', () => {
        const q = input.value.toLowerCase().trim();
        document.querySelectorAll('.tk-archive-row').forEach(row => {
            row.hidden = q !== '' && !(row.dataset.search || '').includes(q);
        });
    });
})();
</script>

<?php include '../includes/footer.php'; ?>

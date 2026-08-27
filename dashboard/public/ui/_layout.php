<?php
/**
 * _layout.php — die Hülle: <head>, linke Leiste, Topbar, Footer.
 *
 * Die Navigation steht genau EINMAL hier. Im alten Dashboard war sie über
 * mehrere Dateien und zwei Rollen-Varianten verteilt, was dazu geführt hat,
 * dass Einträge doppelt existierten und auseinanderliefen.
 */

/** Navigationsstruktur. 'admin' => nur für Admins sichtbar. */
function ui_nav() {
    return [
        'Überblick' => [
            ['page' => 'index',      'icon' => '◈', 'label' => 'Cockpit'],
            ['page' => 'servers',    'icon' => '▤', 'label' => 'Server'],
        ],
        'Verwalten' => [
            ['page' => 'members',    'icon' => '◎', 'label' => 'Mitglieder', 'admin' => true],
            ['page' => 'moderation', 'icon' => '◆', 'label' => 'Moderation'],
            ['page' => 'tickets',    'icon' => '▣', 'label' => 'Tickets'],
        ],
        'Premium' => [
            ['page' => 'premium',    'icon' => '◇', 'label' => 'Premium',    'admin' => true],
        ],
        'Betrieb' => [
            ['page' => 'operations', 'icon' => '⬡', 'label' => 'Operations', 'admin' => true],
            ['page' => 'logs',       'icon' => '≡', 'label' => 'App-Logs',   'admin' => true],
        ],
    ];
}

/**
 * @param string $current  Dateiname ohne .php, markiert den aktiven Eintrag
 * @param string $title    Überschrift in der Topbar
 * @param string $meta     rechts in der Topbar (z. B. Zeitstempel)
 */
function ui_head($current, $title, $meta = '') {
    $user = getUser();
    $v = @filemtime(__DIR__ . '/../assets/css/ui.css') ?: time();
    ?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title><?= esc($title) ?> · Fahrstuhl</title>
<link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/ui.css?v=<?= $v ?>">
</head>
<body>
<div class="scrim" id="uiScrim"></div>
<div class="app">

<aside class="rail" id="uiRail">
    <div class="rail-brand">
        <div class="rail-mark">F</div>
        <div>
            <div class="rail-name">Fahrstuhl</div>
            <div class="rail-sub"><?= esc($user['username'] ?? 'Dashboard') ?></div>
        </div>
    </div>
    <nav class="rail-nav">
        <?php foreach (ui_nav() as $group => $items):
            $visible = array_filter($items, fn($i) => empty($i['admin']) || isAdmin());
            if (!$visible) continue; ?>
            <div class="rail-group"><?= esc($group) ?></div>
            <?php foreach ($visible as $item): ?>
                <a class="rail-item" href="<?= esc(ui_url($item['page'] . '.php')) ?>"
                   <?= $item['page'] === $current ? 'aria-current="page"' : '' ?>>
                    <span class="rail-icon" aria-hidden="true"><?= $item['icon'] ?></span>
                    <?= esc($item['label']) ?>
                </a>
            <?php endforeach;
        endforeach; ?>
        <div class="rail-group">Konto</div>
        <a class="rail-item" href="<?= BASE_URL ?>/pages/portal.php?view_mode=user"><span class="rail-icon" aria-hidden="true">↩</span> Normal View</a>
        <a class="rail-item" href="<?= BASE_URL ?>/logout.php"><span class="rail-icon" aria-hidden="true">⏻</span> Abmelden</a>
    </nav>
</aside>

<div class="main">
    <header class="topbar">
        <button class="drawer-btn" id="uiDrawerBtn" aria-label="Navigation öffnen" aria-expanded="false" aria-controls="uiRail">☰</button>
        <h1><?= esc($title) ?></h1>
        <?php if ($meta !== ''): ?><span class="topbar-meta"><?= esc($meta) ?></span><?php endif; ?>
    </header>
    <div class="content">
<?php
}

function ui_foot() {
    ?>
    </div>
</div>
</div>
<script>
(function () {
    var rail = document.getElementById('uiRail');
    var scrim = document.getElementById('uiScrim');
    var btn = document.getElementById('uiDrawerBtn');
    function close() {
        rail.classList.remove('open');
        scrim.classList.remove('show');
        btn.setAttribute('aria-expanded', 'false');
    }
    btn.addEventListener('click', function () {
        var open = rail.classList.toggle('open');
        scrim.classList.toggle('show', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    scrim.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

    // Tabs: rein clientseitig, Panels heißen wie das data-tab-Ziel.
    document.querySelectorAll('[role="tablist"]').forEach(function (list) {
        list.addEventListener('click', function (e) {
            var tab = e.target.closest('[role="tab"]');
            if (!tab) return;
            list.querySelectorAll('[role="tab"]').forEach(function (t) { t.setAttribute('aria-selected', 'false'); });
            tab.setAttribute('aria-selected', 'true');
            var target = tab.dataset.tab;
            document.querySelectorAll('[data-panel]').forEach(function (p) {
                p.hidden = (p.dataset.panel !== target);
            });
        });
    });
})();
</script>
</body>
</html>
<?php
}

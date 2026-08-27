<?php
$p = currentPage();

// ── Server Switcher ──────────────────────────────────────────────────────────
$_sw_id      = trim((string)($_SESSION['selected_guild_id'] ?? ''));
$_sw_current = null;
$_sw_guilds  = [];
$_sw_scopedPages = [
    'portal', 'guild-detail', 'serverconfig', 'modules', 'command-center', 'setup',
    'welcome', 'reaction-roles', 'tickets', 'logging', 'temp-voice', 'social', 'leveling',
    'moderation', 'moderation-hub', 'automod', 'voice-time', 'server-backup', 'freegames',
];
$_sw_isScoped = in_array($p, $_sw_scopedPages, true);

if (!isAdmin()) {
    // User: nutze gecachte Discord-Guilds, filter für Admin-Recht
    foreach (getUserGuilds() as $ug) {
        $uid   = $ug['id'] ?? '';
        if (!$uid) continue;
        $perms = (int)($ug['permissions'] ?? 0);
        $ok    = ($perms & 0x8) === 0x8 || ($perms & 0x20) === 0x20 || !empty($ug['owner']);
        if (!$ok) continue;
        $hash  = $ug['icon'] ?? null;
        $ico   = ($uid && $hash) ? 'https://cdn.discordapp.com/icons/' . $uid . '/' . $hash . '.png?size=64' : '';
        $entry = ['id' => $uid, 'name' => $ug['name'] ?? $uid, 'icon' => $ico];
        $_sw_guilds[] = $entry;
        if ($uid === $_sw_id) $_sw_current = $entry;
    }
} elseif ($_sw_id !== '') {
    // Admin: Guildname per leichtgewichtigem Session-Cache
    $_swc = $_SESSION['sidebar_guild_cache'] ?? [];
    if (($_swc['id'] ?? '') !== $_sw_id) {
        $swgr = getAPI('/guilds/' . urlencode($_sw_id), 5);
        if (!empty($swgr['data'])) {
            $_swc = [
                'id'   => $_sw_id,
                'name' => $swgr['data']['name'] ?? $_sw_id,
                'icon' => $swgr['data']['iconUrl'] ?? null,
            ];
            $_SESSION['sidebar_guild_cache'] = $_swc;
        }
    }
    if (($_swc['id'] ?? '') === $_sw_id && !empty($_swc['name'])) {
        $_sw_current = ['id' => $_sw_id, 'name' => $_swc['name'], 'icon' => $_swc['icon'] ?? null];
    }
}
// ── End Server Switcher data ─────────────────────────────────────────────────

if (!function_exists('sidebar_item_active')) {
    function sidebar_item_active($item, $current) {
        $pages = array_merge([$item['page']], $item['aliases'] ?? []);
        return in_array($current, $pages, true);
    }

    function sidebar_nav_row($item, $current) {
        $page = $item['page'];
        $icon = $item['icon'];
        $label = $item['label'];
        $description = $item['description'] ?? '';
        $serverScopedPages = [
          'portal', 'guilds', 'guild-detail', 'serverconfig', 'modules', 'command-center', 'setup',
            'welcome', 'reaction-roles', 'tickets', 'logging', 'temp-voice', 'social', 'leveling',
            'moderation', 'moderation-hub', 'automod', 'voice-time', 'server-backup', 'freegames',
        ];
        $href = $item['href'] ?? (in_array($page, $serverScopedPages, true)
            ? dashboardPageUrl($page)
            : BASE_URL . '/pages/' . esc($page) . '.php');
        $isActive = sidebar_item_active($item, $current);
        $active = $isActive ? ' active' : '';
        $activeRow = $isActive ? ' is-active' : '';
        $ariaCurrent = $isActive ? ' aria-current="page"' : '';

        $meta = strtolower(trim($label . ' ' . $description . ' ' . $page . ' ' . implode(' ', $item['aliases'] ?? [])));

        echo '<li class="nav-row' . $activeRow . '" data-page="' . esc($page) . '" data-label="' . esc($label) . '" data-href="' . esc($href) . '" data-meta="' . esc($meta) . '">';
        echo '<a href="' . $href . '" class="link' . $active . '"' . $ariaCurrent . '>';
        echo '<span class="nav-icon">' . esc($icon) . '</span>';
        echo '<span class="nav-copy">';
        echo '<span class="nav-label">' . esc($label) . '</span>';
        echo '</span>';
        echo '</a>';
        echo '</li>';
    }

    function sidebar_nav_list($items, $current) {
        echo '<ul class="menu">';
        foreach ($items as $item) sidebar_nav_row($item, $current);
        echo '</ul>';
    }

    function sidebar_group_has_active($items, $current) {
        foreach ($items as $item) {
            if (sidebar_item_active($item, $current)) {
                return true;
            }
        }

        return false;
    }

    function sidebar_render_groups($groups, $current) {
        foreach ($groups as $group) {
            $title = $group['title'];
            $description = $group['description'] ?? '';
            $items = $group['items'];
            $isActive = sidebar_group_has_active($items, $current);
            $open = !array_key_exists('open', $group) || !empty($group['open']) || $isActive;

            echo '<details class="nav-group nav-workspace' . ($isActive ? ' is-active' : '') . '"' . ($open ? ' open' : '') . '>';
            echo '<summary>';
            echo '<span class="nav-group-copy">';
            echo '<span class="nav-group-title">' . esc($title) . '</span>';
            if ($description !== '') {
                echo '<span class="nav-group-description sr-only">' . esc($description) . '</span>';
            }
            echo '</span>';
            echo '</summary>';
            sidebar_nav_list($items, $current);
            echo '</details>';
        }
    }
}

// ── Navigation ───────────────────────────────────────────────────────────────
//
// Struktur bewusst flach gehalten: vorher 28 Admin-Einträge in 9 Gruppen, was
// niemand mehr überblickt hat. Jetzt 16 in 5. Es ist KEINE Seite entfernt
// worden — die selteneren liegen hinter ihrem Hub und sind über 'aliases'
// weiterhin korrekt in der Navigation markiert, wenn man sie geöffnet hat.
//
// Beim Verschieben einer Seite hinter einen Hub gilt: der Hub MUSS sie
// verlinken, sonst ist sie nur noch per URL erreichbar.

$adminGroups = [
    [
      'title' => 'Überblick',
      'description' => 'Admin entry points',
      'items' => [
        ['page' => 'cockpit', 'icon' => '🎛️', 'label' => 'Cockpit', 'description' => 'Live-Status, Alerts und Aktivität', 'aliases' => ['status', 'activity']],
        ['page' => 'analytics', 'icon' => '📊', 'label' => 'Analytics', 'description' => 'Plattform-Metriken'],
      ],
    ],
    [
      'title' => 'Server',
      'description' => 'Guilds, members and moderation',
      'items' => [
        ['page' => 'guilds', 'icon' => '🏰', 'label' => 'Server', 'description' => 'Alle Guilds', 'aliases' => ['guild-detail']],
        ['page' => 'members-hub', 'icon' => '👥', 'label' => 'Mitglieder', 'description' => 'Profile und Stats', 'aliases' => ['users', 'user-detail', 'voice-time']],
        ['page' => 'moderation-hub', 'icon' => '🛡️', 'label' => 'Moderation', 'description' => 'Cases, AutoMod, Blacklist und Logs', 'aliases' => ['moderation', 'automod', 'logging', 'blacklist', 'audit']],
        ['page' => 'tickets', 'icon' => '🎫', 'label' => 'Tickets', 'description' => 'Panels und Workflows'],
        ['page' => 'server-backup', 'icon' => '💾', 'label' => 'Discord-Server sichern', 'description' => 'Guild-Backup und Restore'],
      ],
    ],
    [
      'title' => 'Premium',
      'description' => 'Plans, revenue and rewards',
      'items' => [
        ['page' => 'premium-hub', 'icon' => '💎', 'label' => 'Premium', 'description' => 'Pläne, Revenue, Promos und Health', 'aliases' => ['premium', 'monetization', 'monetization-health', 'guild-premium', 'redeem', 'premium-info']],
        ['page' => 'rewards-hub', 'icon' => '🎁', 'label' => 'Rewards', 'description' => 'Votes, Shields und Rewards'],
      ],
    ],
    [
      'title' => 'Betrieb',
      'description' => 'Infrastructure and operations',
      'items' => [
        ['page' => 'operations', 'icon' => '🛠️', 'label' => 'Operations', 'description' => 'Deployments, Backups, Security und Jobs', 'aliases' => ['deploys', 'webhooks', 'flags', 'ueberwachung', 'ops-health', 'backups', 'security']],
        ['page' => 'logs', 'icon' => '📋', 'label' => 'App-Logs', 'description' => 'Laufzeit-Logs des Bots'],
      ],
    ],
    [
      'title' => 'System',
      'description' => 'Utilities and reference',
      'items' => [
        ['page' => 'console', 'icon' => '💻', 'label' => 'Console', 'description' => 'Admin-Konsole'],
        ['page' => 'tools', 'icon' => '🧰', 'label' => 'Tools', 'description' => 'Utilities'],
        ['page' => 'fun-hub', 'icon' => '🎭', 'label' => 'Fun', 'description' => 'Fun-Tools und Troll-Befehle', 'aliases' => ['voicetroll']],
        ['page' => 'botinfo', 'icon' => '🤖', 'label' => 'Bot Info', 'description' => 'Commands und Fähigkeiten', 'aliases' => ['commands']],
        ['page' => 'eselmusic', 'icon' => '🎵', 'label' => 'EselMusic', 'description' => 'Musikbot Status & Guilds', 'href' => BASE_URL . '/eselmusic'],
      ],
    ],
  ];

$userGroups = [
    [
      'title' => 'Start',
      'description' => 'Server entry points',
      'items' => [
        ['page' => 'portal', 'icon' => '🏠', 'label' => 'Portal', 'description' => 'Startseite deines Servers', 'aliases' => ['guild-detail', 'command-center']],
        ['page' => 'setup', 'icon' => '🚀', 'label' => 'Setup', 'description' => 'Geführte Einrichtung'],
        ['page' => 'serverconfig', 'icon' => '⚙️', 'label' => 'Einstellungen', 'description' => 'Rollen, Zugriff und Health'],
        ['page' => 'modules', 'icon' => '🧩', 'label' => 'Module', 'description' => 'Features an- und ausschalten'],
      ],
    ],
    [
      'title' => 'Features',
      'description' => 'Community features',
      'items' => [
        ['page' => 'welcome', 'icon' => '👋', 'label' => 'Welcome', 'description' => 'Begrüßung und Verifizierung'],
        ['page' => 'leveling', 'icon' => '📈', 'label' => 'Leveling', 'description' => 'XP und Rewards'],
        ['page' => 'reaction-roles', 'icon' => '🎭', 'label' => 'Reaction Roles', 'description' => 'Rollen zum Selbstvergeben'],
        ['page' => 'social', 'icon' => '📣', 'label' => 'Social Alerts', 'description' => 'YouTube, Twitch und RSS'],
        ['page' => 'freegames', 'icon' => '🎮', 'label' => 'Free Games', 'description' => 'Benachrichtigungen zu Gratis-Spielen'],
        ['page' => 'temp-voice', 'icon' => '🔊', 'label' => 'Temp Voice', 'description' => 'Dynamische Sprachkanäle'],
      ],
    ],
    [
      'title' => 'Moderation & Support',
      'description' => 'Moderation and tickets',
      'items' => [
        ['page' => 'moderation-hub', 'icon' => '🛡️', 'label' => 'Moderation', 'description' => 'Cases, AutoMod und Logs', 'aliases' => ['moderation', 'automod', 'logging']],
        ['page' => 'tickets', 'icon' => '🎫', 'label' => 'Tickets', 'description' => 'Panels und Workflows'],
      ],
    ],
    [
      'title' => 'Server & Premium',
      'description' => 'Stats, plans and profile tools',
      'items' => [
        ['page' => 'stats', 'icon' => '📊', 'label' => 'Server Stats', 'description' => 'Analytics und Aktivität', 'aliases' => ['activity']],
        ['page' => 'server-plans', 'icon' => '🗂️', 'label' => 'Server Plans', 'description' => 'Limits und Stufen'],
        ['page' => 'premium-info', 'icon' => '💎', 'label' => 'Premium', 'description' => 'Deine Vorteile', 'aliases' => ['redeem']],
        ['page' => 'botinfo', 'icon' => '🤖', 'label' => 'Bot Info', 'description' => 'Commands und Support'],
      ],
    ],
  ];

$legalItems = [
    ['page' => 'privacy', 'icon' => '🔒', 'label' => 'Privacy Policy'],
    ['page' => 'terms', 'icon' => '📜', 'label' => 'Terms of Service'],
];
?>
<aside class="sidebar" id="dashboardSidebar" aria-label="Dashboard navigation">
    <nav class="sidebar-nav">

        <!-- ── Server Switcher ────────────────────────────────────────── -->
        <div class="sw-wrap">
            <?php if ($_sw_current): ?>
            <button class="sw-btn" id="swBtn" type="button" aria-haspopup="listbox" aria-expanded="false">
                <?php if (!empty($_sw_current['icon'])): ?>
                    <img class="sw-icon" src="<?= esc($_sw_current['icon']) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <span class="sw-icon sw-icon-fallback"><?= esc(mb_substr($_sw_current['name'], 0, 1)) ?></span>
                <?php endif; ?>
                <span class="sw-name"><?= esc($_sw_current['name']) ?></span>
                <span class="sw-caret" aria-hidden="true">▾</span>
            </button>
            <?php else: ?>
            <a class="sw-btn sw-btn-empty" href="<?= BASE_URL ?>/pages/<?= isAdmin() ? 'guilds' : 'portal' ?>.php">
                <span class="sw-icon sw-icon-fallback">🏰</span>
                <span class="sw-name sw-name-empty">Server wählen</span>
            </a>
            <?php endif; ?>

            <div class="sw-dropdown" id="swDropdown" role="listbox" hidden>
                <?php foreach ($_sw_guilds as $_swg): ?>
                    <?php
                        $_swActive = $_swg['id'] === $_sw_id;
                        if ($_sw_isScoped) {
                            // Gleiche Seite, aber mit neuer guildId
                            $uri = $_SERVER['REQUEST_URI'] ?? '';
                            $parts = parse_url($uri);
                            $path  = $parts['path'] ?? '';
                            parse_str($parts['query'] ?? '', $swParams);
                            $swParams['guildId'] = $_swg['id'];
                            unset($swParams['id']);
                            if ($p === 'guild-detail') { $swParams['id'] = $_swg['id']; unset($swParams['guildId']); }
                            // nginx leitet mit `proxy_pass .../` weiter und schneidet dabei
                            // das /fahrstuhl-Praefix ab - REQUEST_URI enthaelt es hier also
                            // nicht. Ohne BASE_URL davor zeigt der Link auf /pages/... und
                            // laeuft in die 404-Seite. Erst abschneiden, dann setzen, damit
                            // es auch beim direkten Zugriff ohne Proxy stimmt.
                            $swBase = rtrim(BASE_URL, '/');
                            if ($swBase !== '' && strpos($path, $swBase . '/') === 0) {
                                $path = substr($path, strlen($swBase));
                            }
                            $_swUrl = $swBase . $path . '?' . http_build_query($swParams);
                        } else {
                            $_swUrl = BASE_URL . '/pages/' . (isAdmin() ? 'guild-detail' : 'portal') . '.php?'
                                . (isAdmin() ? 'id=' : 'guildId=') . urlencode($_swg['id']);
                        }
                    ?>
                    <a class="sw-item<?= $_swActive ? ' sw-item-active' : '' ?>"
                       href="<?= esc($_swUrl) ?>" role="option" aria-selected="<?= $_swActive ? 'true' : 'false' ?>">
                        <?php if (!empty($_swg['icon'])): ?>
                            <img class="sw-item-icon" src="<?= esc($_swg['icon']) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="sw-item-icon sw-icon-fallback"><?= esc(mb_substr($_swg['name'], 0, 1)) ?></span>
                        <?php endif; ?>
                        <span class="sw-item-name"><?= esc($_swg['name']) ?></span>
                        <?php if ($_swActive): ?><span class="sw-check" aria-hidden="true">✓</span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <div class="sw-divider"></div>
                <a class="sw-item sw-item-all" href="<?= BASE_URL ?>/pages/<?= isAdmin() ? 'guilds' : 'portal' ?>.php">
                    <span class="sw-item-icon">🏰</span>
                    <span class="sw-item-name"><?= isAdmin() ? 'Alle Server' : 'Server-Übersicht' ?></span>
                </a>
            </div>
        </div>
        <!-- ── End Server Switcher ────────────────────────────────────── -->

        <div class="sidebar-search">
          <input id="sidebarSearch" type="search" placeholder="<?= t('sidebar.search') ?>" autocomplete="off" />
        </div>

        <section id="sidebarPinned" class="sidebar-section sidebar-pinned" hidden>
            <p class="sidebar-section-title"><?= t('sidebar.pinned') ?></p>
            <ul class="menu" id="pinnedList"></ul>
        </section>

        <section class="sidebar-section">
            <p class="sidebar-section-title"><?php echo isAdmin() ? t('sidebar.nav_admin') : t('sidebar.nav_user'); ?></p>
            <?php
                // Ohne Bot auf dem gewaehlten Server gibt es nichts zu
                // konfigurieren - dann nur der Weg zum Einladen, statt einer
                // Leiste voller Seiten, die alle ins Leere laufen.
                $_navGroups = isAdmin() ? $adminGroups : $userGroups;
                if (!isAdmin() && $_sw_id !== '' && function_exists('guildHasBot') && !guildHasBot($_sw_id)) {
                    $_navGroups = [[
                        'title' => 'Erste Schritte',
                        'description' => 'Bot fehlt noch',
                        'items' => [
                            ['page' => 'invite', 'icon' => '🚀', 'label' => 'Bot einladen', 'description' => 'Fahrstuhl auf diesen Server holen'],
                        ],
                    ]];
                }
                sidebar_render_groups($_navGroups, $p);
            ?>
        </section>

        <details class="nav-group nav-advanced">
            <summary><?= t('sidebar.legal') ?></summary>
            <?php sidebar_nav_list($legalItems, $p); ?>
        </details>
    </nav>
</aside>
<main class="content" id="main-content">

<script>
(function() {
  const storageKey = 'fh_dashboard_pins_v1';
  const maxPins = 4;
  const search = document.getElementById('sidebarSearch');
  const pinnedSection = document.getElementById('sidebarPinned');
  const pinnedList = document.getElementById('pinnedList');
  const rows = Array.from(document.querySelectorAll('.sidebar .nav-row'));

  function loadPins() {
    try {
      const raw = localStorage.getItem(storageKey);
      const pins = raw ? JSON.parse(raw) : [];
      return Array.isArray(pins) ? pins.slice(0, maxPins) : [];
    } catch {
      return [];
    }
  }

  function savePins(pins) {
    try { localStorage.setItem(storageKey, JSON.stringify(pins.slice(0, maxPins))); } catch {}
  }

  function setRowPinnedState(page, pinned) {
    for (const r of rows) {
      if (r.dataset.page === page) {
        const btn = r.querySelector('.pin-btn');
        if (btn) btn.textContent = pinned ? '★' : '☆';
        r.dataset.pinned = pinned ? '1' : '0';
      }
    }
  }

  function renderPinned() {
    const pins = loadPins();
    pinnedList.innerHTML = '';

    if (!pins.length) {
      pinnedSection.hidden = true;
    } else {
      pinnedSection.hidden = false;
    }

    const byPage = new Map();
    for (const r of rows) byPage.set(r.dataset.page, r);

    for (const page of pins) {
      const r = byPage.get(page);
      if (!r) continue;
      const li = document.createElement('li');
      li.className = 'nav-row pinned-row';
      li.dataset.page = page;
      li.dataset.label = r.dataset.label || '';
      li.dataset.href = r.dataset.href || '';
      li.dataset.meta = r.dataset.meta || '';

      const a = document.createElement('a');
      a.className = 'link';
      a.href = r.dataset.href || '#';
      a.innerHTML = r.querySelector('a.link') ? r.querySelector('a.link').innerHTML : (r.dataset.label || page);

      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'pin-btn';
      b.title = 'Unpin';
      b.setAttribute('aria-label', 'Unpin');
      b.textContent = '★';
      b.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        togglePin(page);
      });

      li.appendChild(a);
      li.appendChild(b);
      pinnedList.appendChild(li);
    }

    for (const r of rows) setRowPinnedState(r.dataset.page, pins.includes(r.dataset.page));
    applyFilter();
  }

  function togglePin(page) {
    const pins = loadPins();
    const idx = pins.indexOf(page);
    if (idx >= 0) pins.splice(idx, 1);
    else {
      if (pins.length >= maxPins) pins.pop();
      pins.unshift(page);
    }
    savePins(pins);
    renderPinned();
  }

  function applyFilter() {
    const q = (search?.value || '').trim().toLowerCase();
    const allRows = Array.from(document.querySelectorAll('.sidebar .nav-row'));
    for (const r of allRows) {
      const hay = (r.dataset.meta || (r.dataset.label || '')).toLowerCase();
      const show = !q || hay.includes(q);
      r.style.display = show ? '' : 'none';
    }
    if (pinnedSection) {
      const anyPinnedShown = Array.from(pinnedList?.children || []).some(el => el.style.display !== 'none');
      pinnedSection.hidden = !anyPinnedShown;
    }
  }

  document.addEventListener('click', (e) => {
    const btn = e.target && e.target.closest ? e.target.closest('.pin-btn') : null;
    if (!btn) return;
    const page = btn.getAttribute('data-pin');
    if (!page) return;
    e.preventDefault();
    e.stopPropagation();
    togglePin(page);
  });

  if (search) search.addEventListener('input', applyFilter);
  renderPinned();
})();

// ── Server Switcher ──────────────────────────────────────────────────────────
(function () {
  const btn = document.getElementById('swBtn');
  const dd  = document.getElementById('swDropdown');
  if (!btn || !dd) return;

  function openDrop() {
    dd.hidden = false;
    btn.setAttribute('aria-expanded', 'true');
  }
  function closeDrop() {
    dd.hidden = true;
    btn.setAttribute('aria-expanded', 'false');
  }

  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    dd.hidden ? openDrop() : closeDrop();
  });
  btn.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); dd.hidden ? openDrop() : closeDrop(); }
    if (e.key === 'Escape') closeDrop();
  });
  document.addEventListener('click', closeDrop);
  dd.addEventListener('click', function (e) { e.stopPropagation(); });
})();
</script>

<?php
/**
 * _boot.php — Fundament des neuen Dashboards.
 *
 * Baut NICHT auf einer eigenen Auth/Session-Schicht auf, sondern nutzt
 * config.php weiter: Session, Discord-OAuth, CSRF-Prüfung, api()/getAPI() und
 * die Zugriffsprüfungen sind dort erprobt und abgesichert. Neu ist nur alles
 * oberhalb davon — Layout, Navigation, Komponenten.
 *
 * Die Render-Helfer unten sind der Grund, warum Seiten kein eigenes CSS mehr
 * brauchen: wer eine Kachel will, ruft ui_stat() auf, statt sich eine zu bauen.
 */

require_once __DIR__ . '/../includes/config.php';
requireLogin();

/** Basis-URL des neuen Dashboards. */
function ui_url($path = '') {
    return BASE_URL . '/ui' . ($path !== '' ? '/' . ltrim($path, '/') : '/');
}

/** Zahl im deutschen Format, ohne Nachkommastellen. */
function ui_num($n) { return number_format((float)$n, 0, ',', '.'); }

/** Geldbetrag. */
function ui_money($n) { return number_format((float)$n, 2, ',', '.') . ' €'; }

/** Millisekunden als "3 Tage" / "4 Std" / "12 Min". */
function ui_duration($ms) {
    $s = (int)round($ms / 1000);
    if ($s < 60)    return $s . ' Sek';
    if ($s < 3600)  return floor($s / 60) . ' Min';
    if ($s < 86400) return floor($s / 3600) . ' Std';
    $d = floor($s / 86400);
    return $d . ($d === 1 ? ' Tag' : ' Tage');
}

/** Bytes menschenlesbar. */
function ui_bytes($b) {
    $u = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0; $b = (float)$b;
    while ($b >= 1024 && $i < count($u) - 1) { $b /= 1024; $i++; }
    return number_format($b, $b < 10 && $i > 0 ? 1 : 0, ',', '.') . ' ' . $u[$i];
}

/** Erste Buchstaben für Avatar-Platzhalter. */
function ui_initial($name) {
    $n = trim((string)$name);
    return $n === '' ? '?' : mb_strtoupper(mb_substr($n, 0, 1));
}

// ── Komponenten ──────────────────────────────────────────────────────────────
// Alle geben HTML aus. Keine Seite schreibt eigenes CSS; fehlt eine Komponente,
// kommt sie HIER dazu und steht damit sofort überall zur Verfügung.

/** Zustands-Pille. $tone: ok | warn | crit | mute | accent */
function ui_badge($label, $tone = 'mute') {
    $tones = ['ok', 'warn', 'crit', 'mute', 'accent'];
    $t = in_array($tone, $tones, true) ? $tone : 'mute';
    return '<span class="badge badge-' . $t . '">' . esc($label) . '</span>';
}

/** Kennzahl-Kachel. */
function ui_stat($label, $value, $foot = '', $footTone = '') {
    $html  = '<div class="stat">';
    $html .= '<span class="stat-label">' . esc($label) . '</span>';
    $html .= '<span class="stat-value">' . esc($value) . '</span>';
    if ($foot !== '') {
        $cls = $footTone !== '' ? ' class="stat-delta ' . esc($footTone) . '"' : '';
        $html .= '<span class="stat-foot"><span' . $cls . '>' . esc($foot) . '</span></span>';
    }
    return $html . '</div>';
}

/** Status-Streifen ganz oben: beantwortet "brennt was?" vor allen Details. */
function ui_status_strip($tone, $headline, $detail = '', $actionLabel = '', $actionHref = '') {
    $tones = ['ok', 'warn', 'crit'];
    $t = in_array($tone, $tones, true) ? $tone : 'ok';
    $html  = '<div class="status-strip is-' . $t . '"><span class="status-dot"></span><div>';
    $html .= '<div class="status-headline">' . esc($headline) . '</div>';
    if ($detail !== '') $html .= '<div class="status-detail">' . esc($detail) . '</div>';
    $html .= '</div>';
    if ($actionLabel !== '' && $actionHref !== '') {
        $html .= '<a class="btn btn-sm" style="margin-left:auto" href="' . esc($actionHref) . '">' . esc($actionLabel) . '</a>';
    }
    return $html . '</div>';
}

/** Karten-Kopf öffnen. $action ist fertiges HTML (z. B. ein Button). */
function ui_card_open($title = '', $action = '', $flush = false) {
    $html = '<div class="card">';
    if ($title !== '') {
        $html .= '<div class="card-head"><h2>' . esc($title) . '</h2>' . $action . '</div>';
    }
    $html .= '<div class="card-body' . ($flush ? ' flush' : '') . '">';
    return $html;
}
function ui_card_close() { return '</div></div>'; }

/** Karte, deren Inhalt eine Tabelle oder ein Feed ist (kein Body-Padding). */
function ui_card_head_only($title, $action = '') {
    return '<div class="card"><div class="card-head"><h2>' . esc($title) . '</h2>' . $action . '</div>';
}

/** Kompakte Beschriftung/Wert-Zeile für Metadaten (Commit, Branch, Zeitpunkt …). */
function ui_meta_row(array $pairs) {
    $html = '<div class="meta-row">';
    foreach ($pairs as $key => $val) {
        $mono = str_starts_with((string)$key, '#');
        $label = $mono ? substr((string)$key, 1) : (string)$key;
        $html .= '<div class="meta-item"><span class="meta-key">' . esc($label) . '</span>'
               . '<span class="meta-val' . ($mono ? ' mono' : '') . '">' . esc((string)$val) . '</span></div>';
    }
    return $html . '</div>';
}

/** Leerzustand — sagt, was fehlt, statt eine leere Fläche zu zeigen. */
function ui_empty($title, $detail = '') {
    return '<div class="empty"><div class="empty-title">' . esc($title) . '</div>'
         . ($detail !== '' ? '<div>' . esc($detail) . '</div>' : '') . '</div>';
}

/** Zeile im "Braucht dich"-Feed. */
function ui_feed_item($severity, $title, $meta = '', $actionLabel = '', $actionHref = '') {
    $sev = in_array($severity, ['ok', 'warn', 'crit'], true) ? $severity : 'ok';
    $html  = '<div class="feed-item sev-' . $sev . '"><span class="feed-rail"></span><div class="feed-body">';
    $html .= '<div class="feed-title">' . esc($title) . '</div>';
    if ($meta !== '') $html .= '<div class="feed-meta">' . esc($meta) . '</div>';
    $html .= '</div>';
    if ($actionLabel !== '' && $actionHref !== '') {
        $html .= '<a class="btn btn-sm" href="' . esc($actionHref) . '">' . esc($actionLabel) . '</a>';
    }
    return $html . '</div>';
}

/** Name + ID in einer Tabellenzelle, mit Avatar. */
function ui_cell_identity($title, $sub = '', $avatarUrl = null) {
    $av = $avatarUrl
        ? '<span class="avatar"><img src="' . esc($avatarUrl) . '" alt="" width="28" height="28" loading="lazy"></span>'
        : '<span class="avatar">' . esc(ui_initial($title)) . '</span>';
    $html = '<div class="cell-main">' . $av . '<div><div class="cell-title">' . esc($title) . '</div>';
    if ($sub !== '') $html .= '<div class="cell-sub">' . esc($sub) . '</div>';
    return $html . '</div></div>';
}

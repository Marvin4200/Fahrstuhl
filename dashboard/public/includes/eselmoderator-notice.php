<?php
// Reversibler Hinweis-Banner fuer Seiten, deren Funktion zu EselModerator umgezogen ist --
// analog zum MIGRATED_TO_ESELMODERATOR-Schalter auf Bot-Seite (utils/eselModeratorMigration.js).
// Einfach diese Datei nicht mehr einbinden, um eine Seite wieder ohne Hinweis zu zeigen.
$eselModeratorInviteUrl = 'https://discord.com/api/oauth2/authorize?client_id=1545456084754628658&permissions=1099798277142&scope=bot%20applications.commands';
?>
<div style="background:rgba(102,126,234,.1); border:1px solid rgba(102,126,234,.35); border-left:3px solid #667eea; border-radius:8px; padding:14px 18px; margin-bottom:20px; font-size:.9rem;">
    🤖 <strong>Diese Funktion ist zu unserem neuen Bot EselModerator umgezogen.</strong>
    Der Fahrstuhl-Bot reagiert auf diesem Server nicht mehr darauf, egal was hier eingestellt ist.
    <a href="<?= htmlspecialchars($eselModeratorInviteUrl) ?>" target="_blank" rel="noopener" style="color:#a0a8ff; text-decoration:underline;">EselModerator einladen →</a>
</div>

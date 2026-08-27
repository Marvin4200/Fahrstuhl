<?php
$page_title = 'Nutzungsbedingungen';
require_once __DIR__ . '/../includes/config.php';
// Öffentliche Seite — kein Login nötig
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nutzungsbedingungen – Fahrstuhl Bot</title>
<style>
  body { font-family: 'Segoe UI', sans-serif; background:#0d0d1a; color:#e0e0e0; margin:0; padding:0; }
  .container { max-width: 800px; margin: 0 auto; padding: 40px 20px 80px; }
  h1 { color: #fff; border-bottom: 2px solid #5865F2; padding-bottom: 12px; }
  h2 { color: #a0a8ff; margin-top: 32px; }
  p, li { line-height: 1.7; color: #bbb; }
  ul { padding-left: 20px; }
  a { color: #5865F2; text-decoration: none; }
  a:hover { text-decoration: underline; }
  .meta { color: #555; font-size: 0.85em; margin-top: -8px; margin-bottom: 32px; }
  .back { display: inline-block; margin-bottom: 24px; background: #1a1a2e; border: 1px solid #333;
          border-radius: 6px; padding: 6px 14px; color: #aaa; font-size: 0.85em; }
  .warning { background:#2a1a1a; border:1px solid #5a2a2a; border-left:3px solid #ED4245;
             border-radius:6px; padding:14px 16px; margin:18px 0; color:#e8c4c4; }
</style>
</head>
<body>
<div class="container">
  <a href="<?= BASE_URL ?>/" class="back">← Zurück</a>
  <h1>Nutzungsbedingungen</h1>
  <p class="meta">Stand: 27. August 2026</p>

  <p>
    Wer den Fahrstuhl Bot auf einen Discord-Server holt oder seine Befehle nutzt, stimmt diesen
    Bedingungen zu.
  </p>

  <h2>1. Wofür der Bot gedacht ist</h2>
  <p>
    Fahrstuhl ist ein Allround-Bot für Discord-Server: Begrüßung, Level, Tickets, Moderation,
    AutoMod, Reaction Roles, Temp-Voice und einige Spaß-Funktionen für Sprachkanäle. Die
    Spaß-Funktionen sind für Runden gedacht, in denen alle Beteiligten damit einverstanden sind.
  </p>
  <div class="warning">
    ⚠️ Der Bot darf <strong>nicht</strong> zum Belästigen, Bloßstellen oder Schikanieren
    eingesetzt werden. Missbrauch kann zu einer dauerhaften Sperre führen.
  </div>

  <h2>2. Was von dir erwartet wird</h2>
  <ul>
    <li>Die <a href="https://discord.com/terms" target="_blank" rel="noopener">Discord-Nutzungsbedingungen</a>
        und <a href="https://discord.com/guidelines" target="_blank" rel="noopener">Community-Richtlinien</a> einhalten</li>
    <li>Als Server-Admin dafür sorgen, dass der Bot auf dem eigenen Server angemessen genutzt wird</li>
    <li>Spaß-Befehle nur dort einsetzen, wo alle Beteiligten einverstanden sind</li>
    <li>Befehle nicht missbrauchen, ausnutzen oder zuspammen</li>
    <li>Sperrlisten und andere Schutzmechanismen nicht umgehen</li>
  </ul>

  <h2>3. Funktionen und Verfügbarkeit</h2>
  <ul>
    <li>Der Bot wird <strong>ohne Gewähr</strong> bereitgestellt — es gibt keine Zusage zu
        Verfügbarkeit oder Funktionsumfang</li>
    <li>Funktionen können jederzeit hinzukommen, sich ändern oder wegfallen</li>
    <li>Premium-Funktionen setzen aktiven Premium-Status voraus, der bei Regelverstößen
        entzogen werden kann</li>
    <li>Das Dashboard zeigt Server-Admins Betriebs- und Statistikdaten, darunter Sprachkanal-
        Nutzung und Befehlsverläufe</li>
  </ul>

  <h2>4. Was nicht erlaubt ist</h2>
  <p>Folgendes führt zur dauerhaften Sperre:</p>
  <ul>
    <li>Befehle nutzen, um andere ohne deren Einverständnis zu belästigen oder gezielt zu treffen</li>
    <li>Funktionen missbrauchen oder Lücken ausnutzen</li>
    <li>Den Bot verwenden, um gegen die Discord-Nutzungsbedingungen zu verstoßen</li>
    <li>Den Betrieb des Bots stören</li>
  </ul>

  <h2>5. Premium</h2>
  <p>
    Premium-Funktionen sind ein Extra für Unterstützende. Sie können jederzeit geändert,
    ausgesetzt oder eingestellt werden. Für Premium-Zugang, der wegen eines Regelverstoßes
    entzogen wird, gibt es keine Erstattung.
  </p>

  <h2>6. Shield-System</h2>
  <p>
    Shields bekommt man über Partner-Communities oder Server-Boosts. Sie sind nicht
    übertragbar und können weder verkauft noch getauscht werden.
  </p>

  <h2>7. Haftung</h2>
  <p>
    Der Fahrstuhl Bot und die Personen dahinter haften nicht für Schäden, Verluste oder
    Streitigkeiten, die aus der Nutzung entstehen. Die Nutzung erfolgt auf eigene Verantwortung.
  </p>

  <h2>8. Level-System</h2>
  <p>
    Das Level-System zählt XP, die durch Nachrichten und Sprachaktivität gesammelt werden.
    XP und Level gelten jeweils nur für einen Server und werden nicht zwischen Servern geteilt.
    Server-Admins können die Daten einzelner Mitglieder oder des ganzen Servers jederzeit im
    Dashboard unter <em>Leveling → XP zurücksetzen</em> löschen.
  </p>

  <h2>9. Kontakt und Einsprüche</h2>
  <p>Bei Fragen oder wenn du eine Sperre für ungerechtfertigt hältst:</p>
  <ul>
    <li>Support-Discord: <a href="https://discord.gg/zfzDHKcWDx" target="_blank" rel="noopener">discord.gg/zfzDHKcWDx</a></li>
    <li>GitHub: <a href="https://github.com/Marvin4200/Fahrstuhl" target="_blank" rel="noopener">github.com/Marvin4200/Fahrstuhl</a></li>
  </ul>

  <p style="margin-top:40px; color:#555; font-size:0.85em;">
    Siehe auch: <a href="<?= BASE_URL ?>/pages/privacy.php">Datenschutz</a>
  </p>
</div>
</body>
</html>

<?php
$page_title = 'Datenschutz';
require_once __DIR__ . '/../includes/config.php';
// Öffentliche Seite — kein Login nötig
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Datenschutz – Fahrstuhl Bot</title>
<style>
  body { font-family: 'Segoe UI', sans-serif; background:#0d0d1a; color:#e0e0e0; margin:0; padding:0; }
  .container { max-width: 800px; margin: 0 auto; padding: 40px 20px 80px; }
  h1 { color: #fff; border-bottom: 2px solid #5865F2; padding-bottom: 12px; }
  h2 { color: #a0a8ff; margin-top: 32px; }
  h3 { color: #cfd4ff; margin-top: 22px; font-size: 1rem; }
  p, li { line-height: 1.7; color: #bbb; }
  ul { padding-left: 20px; }
  a { color: #5865F2; text-decoration: none; }
  a:hover { text-decoration: underline; }
  .meta { color: #555; font-size: 0.85em; margin-top: -8px; margin-bottom: 32px; }
  .back { display: inline-block; margin-bottom: 24px; background: #1a1a2e; border: 1px solid #333;
          border-radius: 6px; padding: 6px 14px; color: #aaa; font-size: 0.85em; }
  .note { background:#1a1a2e; border:1px solid #333; border-left:3px solid #5865F2;
          border-radius:6px; padding:14px 16px; margin:18px 0; }
  table { width:100%; border-collapse: collapse; margin: 14px 0; font-size: .92em; }
  th, td { text-align:left; padding:8px 10px; border-bottom:1px solid #262640; vertical-align: top; }
  th { color:#cfd4ff; font-weight:700; }
  td { color:#bbb; }
</style>
</head>
<body>
<div class="container">
  <a href="<?= BASE_URL ?>/" class="back">← Zurück</a>
  <h1>Datenschutz</h1>
  <p class="meta">Stand: 27. August 2026</p>

  <p>
    Diese Seite beschreibt, welche Daten der <strong>Fahrstuhl Bot</strong> und das dazugehörige
    Dashboard verarbeiten. Sie gilt auch für die übrigen Dienste unter eselbande.com, soweit
    unten ausdrücklich erwähnt.
  </p>

  <h2>1. Welche Daten verarbeitet werden</h2>

  <h3>Kennungen</h3>
  <ul>
    <li><strong>Discord-Nutzer-ID</strong> – für Einstellungen, Statistiken, Level und Premium-Status</li>
    <li><strong>Discord-Server- und Kanal-IDs</strong> – für die Konfiguration pro Server</li>
    <li><strong>Discord-Nutzername</strong> – in Ticket-Datensätzen und auf dem Zitat-Board</li>
  </ul>

  <h3>Nachrichteninhalte</h3>
  <p>
    Ja — anders als in einer früheren Fassung dieser Seite behauptet, werden an mehreren
    Stellen Nachrichteninhalte verarbeitet. Konkret:
  </p>
  <table>
    <tr><th>Funktion</th><th>Was gespeichert wird</th></tr>
    <tr>
      <td><strong>Logging</strong></td>
      <td>Bei gelöschten und bearbeiteten Nachrichten wird der Text in den eingestellten
          Log-Kanal geschrieben und in der Ereignis-Tabelle abgelegt. Nur aktiv, wenn ein
          Server das Logging-Modul einschaltet.</td>
    </tr>
    <tr>
      <td><strong>Tickets</strong></td>
      <td>Beim Schließen eines Tickets entsteht ein vollständiges Transkript des Gesprächs
          mit Autor und Zeitstempel, das als Datei in den eingestellten Kanal gepostet wird.</td>
    </tr>
    <tr>
      <td><strong>AutoMod</strong></td>
      <td>Nachrichten werden auf gesperrte Begriffe und Muster geprüft. Der Text wird dabei
          gelesen, aber nicht dauerhaft gespeichert — nur ein Treffer wird vermerkt.</td>
    </tr>
    <tr>
      <td><strong>Zitat-Board</strong></td>
      <td>Wer eine Nachricht per Rechtsklick als Zitat einreicht, überträgt deren Text
          zusammen mit dem Namen der zitierten Person an das Zitat-Board.</td>
    </tr>
  </table>

  <h3>Freitexte</h3>
  <ul>
    <li>Gründe für Verwarnungen, Timeouts und andere Moderationsfälle</li>
    <li>Ticket-Anliegen, interne Team-Notizen und Feedback-Kommentare</li>
    <li>Selbst gesetzte Texte wie Willkommensnachrichten oder Ticket-Beschriftungen</li>
  </ul>

  <h3>Aktivitätsdaten</h3>
  <ul>
    <li><strong>Sprachkanal-Nutzung</strong> – Beitritts-, Verlassens- und Wechselzeitpunkte
        sowie die Dauer. <strong>Kein Audio</strong> wird mitgeschnitten oder gespeichert.</li>
    <li><strong>Level und XP</strong> – gesammelt durch Nachrichten <em>und</em> Sprachaktivität,
        getrennt pro Server</li>
    <li><strong>Befehlsnutzung</strong> – welcher Befehl wann verwendet wurde und ob er
        funktioniert hat</li>
    <li><strong>Premium- und Shield-Status</strong> samt Ablaufzeitpunkt</li>
    <li><strong>Benachrichtigungs-Einstellungen</strong> – nur wenn per <code>/notifysettings</code>
        aktiviert</li>
  </ul>

  <h3>Server-Sicherungen</h3>
  <p>
    Wer die Backup-Funktion nutzt, speichert damit die <em>Struktur</em> seines Servers:
    Kanäle, Kategorien, Rollen und Berechtigungen — keine Nachrichten.
  </p>

  <h3>EselMusic</h3>
  <p>
    Der Musikbot speichert, welcher Titel wann auf welchem Server lief, und wer ihn angefragt
    hat. Automatisch nachgelegte Titel (AutoMix, 24/7) werden ohne Personenbezug gespeichert.
  </p>

  <h2>2. Wozu die Daten verwendet werden</h2>
  <ul>
    <li>Damit die Funktionen überhaupt arbeiten können — Begrüßung, Level, Tickets, Moderation, Musik</li>
    <li>Damit Regeln durchsetzbar sind — Abklingzeiten, Sperrlisten, Moderationsverlauf</li>
    <li>Für Statistiken im Dashboard</li>
  </ul>
  <p>Die Daten werden <strong>nicht verkauft, nicht weitergegeben und nicht für Werbung genutzt</strong>.</p>

  <h2>3. Wo die Daten liegen</h2>
  <p>
    Auf einem privat betriebenen Server in Deutschland, in einer MySQL-Datenbank und in
    lokalen SQLite-Dateien. Zugriff hat nur die Betreiberseite. Zugangsdaten liegen nicht in
    öffentlichen Code-Verzeichnissen.
  </p>

  <h2>4. Wie lange die Daten bleiben</h2>
  <ul>
    <li><strong>Konfiguration</strong> bleibt, solange der Bot auf dem Server ist</li>
    <li><strong>Level, Statistiken, Moderationsfälle</strong> bleiben, bis sie gelöscht werden —
        Server-Admins können Level-Daten jederzeit zurücksetzen</li>
    <li><strong>Ticket-Transkripte</strong> liegen als Datei in dem Kanal, den der Server dafür
        eingerichtet hat, und unterliegen damit dessen Kontrolle</li>
    <li><strong>Log-Einträge</strong> bleiben in der Ereignis-Tabelle, bis sie aufgeräumt werden</li>
  </ul>
  <p>Du kannst jederzeit die Löschung deiner Daten verlangen — siehe Kontakt.</p>

  <h2>5. Deine Rechte</h2>
  <ul>
    <li><strong>Auskunft</strong> – was über dich gespeichert ist</li>
    <li><strong>Löschung</strong> – deine Daten entfernen lassen</li>
    <li><strong>Widerspruch</strong> – Benachrichtigungen abschalten, Zitate zurückziehen lassen</li>
  </ul>

  <h2>6. Kontakt</h2>
  <p>
    Support-Discord: <a href="https://discord.gg/zfzDHKcWDx" target="_blank" rel="noopener">discord.gg/zfzDHKcWDx</a><br>
    GitHub: <a href="https://github.com/Marvin4200/Fahrstuhl" target="_blank" rel="noopener">github.com/Marvin4200/Fahrstuhl</a>
  </p>

  <h2>7. Änderungen</h2>
  <p>
    Diese Seite wird angepasst, wenn sich der Funktionsumfang ändert. Wesentliche Änderungen
    werden im Support-Discord angekündigt.
  </p>

  <p style="margin-top:40px; color:#555; font-size:0.85em;">
    Siehe auch: <a href="<?= BASE_URL ?>/pages/terms.php">Nutzungsbedingungen</a>
  </p>
</div>
</body>
</html>

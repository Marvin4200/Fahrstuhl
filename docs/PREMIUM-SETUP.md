# Premium-System — Aufbau

## Zwei getrennte Ebenen

| Ebene | Tabelle | Gilt für |
|---|---|---|
| **User-Premium** | `premium_users` | eine Person, auf jedem Server |
| **Server-Plan** | `premium_guilds` | einen Server, für alle dort |

Beide liegen in `data/premium.db` (SQLite, `utils/premiumDatabase.js`).

Früher gab es nur die erste Ebene: ein "Server-Plan" war in Wahrheit das
Privat-Premium des Server-Owners. Das hatte drei Probleme — eine Zahlung
schaltete *alle* Server dieser Person frei, ein Admin ohne Owner-Rechte konnte
für seinen Server gar nichts kaufen, und bei Ownership-Übertragung war der Plan
weg. Server haben jetzt ein eigenes Entitlement.

**Rückwärtskompatibilität:** `premiumManager.getGuildTier(guildId, ownerId)`
prüft zuerst den Server-Plan und fällt dann auf das Owner-Premium zurück. Alle
bestehenden Kunden bleiben also freigeschaltet. Das Feld `source` in der Antwort
(`guild` | `owner` | `none`) sagt, welcher Weg gegriffen hat — die Seite
*Server-Plan vergeben* zeigt Server, die noch am Owner-Account hängen, mit
⚠️ *Owner-Account* an.

## Tiers und Preise

Einzige Quelle der Wahrheit: **`utils/pricing.js`**, gespiegelt in
`dashboard/public/includes/pricing.php`. Bot-Embeds, Cooldowns, Troll-Dauern und
die Verkaufsseite lesen alle von dort — kein Preis steht mehr fest im Code.

| | Free | 💎 Premium | 👑 Pro |
|---|---|---|---|
| Preis / Monat | – | 2,49 € | 4,99 € |
| Lifetime | – | 14,99 € | 29,99 € |
| Command-Cooldown | 90 s | 45 s | 20 s |
| Troll-Dauer | 60 s | 5 min | 10 min |
| 🛡️ Neues Shield alle | 2,5 h | 1,5 h | 45 min |
| 🛡️ Ein Shield schützt | 2 h | 4 h | 8 h |
| 🎁 Bonus-Shields / Monat | – | +5 | +15 |
| 📈 XP-Boost | 1× | 1,5× | 2× |
| `/claim` ohne Support-Server | ❌ | ✅ | ✅ |
| `/notifysettings` | ❌ | ✅ | ✅ |
| `/trollcolor` (eigene Embed-Farbe) | ❌ | ✅ | ✅ |
| `/settrollmessage` | ❌ | ❌ | ✅ |
| Elevator-Ziele | 1 | 1 | 3 |

**Warum diese Perks?** Der Bot hat zwei Währungen: Shields (Verteidigung) und
Troll-Fähigkeit. Vorher hing fast der gesamte Premium-Wert an *weniger Wartezeit*
— also am Wegnehmen einer künstlichen Bremse. Das verkauft schlecht, weil es sich
für den Free-Nutzer wie ein Defekt anfühlt statt wie ein Upgrade. Die Perks oben
sind bewusst **additiv**: jede Free-Zahl ist exakt die, die Free-Nutzer vorher
schon hatten, bezahlte Stufen bekommen ausschließlich *mehr*.

Der Shield-Block ist dabei der eigentliche Hebel. Shields sind die knappe
Ressource im Spiel — schneller nachfüllen **und** pro Shield länger geschützt
sein heißt, dass derselbe Vorrat bei Pro rund 8× so weit reicht wie bei Free.
Das merkt man täglich, im Gegensatz zu einem Badge.

Alle Werte per Environment überschreibbar, ohne Code-Änderung:
`PRICE_*`, `COOLDOWN_*`, `TROLL_DURATION_*`, `SHIELD_CLAIM_*`,
`SHIELD_DURATION_*`, `MONTHLY_SHIELDS_*`, `XP_MULTIPLIER_*`.

> Der Free-Cooldown lag früher bei **10 Minuten**. Das hat den Kern-Loop
> zugesperrt statt ihn zu verkaufen: neue Nutzer sind gegen die Wand gelaufen,
> bevor der Bot überhaupt zur Gewohnheit werden konnte. 90 Sekunden halten Free
> benutzbar und lassen trotzdem eine echte Leiter nach oben.

## Laufzeit-Logik

`activate()` **addiert** standardmäßig auf eine bestehende Restlaufzeit, statt
sie zu überschreiben — wer mit 20 Tagen Rest 30 Tage nachkauft, hat danach 50.
(Vorher wurde immer ab *heute* gerechnet und die Restlaufzeit ging verloren.)

Wer stattdessen eine **absolute** Laufzeit setzen will, schickt `mode: 'set'`
mit — dann läuft der Plan exakt `daysValid` Tage ab jetzt. Die Dashboard-Aktion
*Aktivieren* nutzt `set`, *Verlängern* nutzt `extend`.

Ausnahme: bei **Tier-Wechsel** startet die Uhr neu ab jetzt, weil die
verbleibenden Tage zu einem anderen Preis gekauft wurden.

Der Default ist **30 Tage**, passend zum Monatspreis. Früher stand hier 365 —
jeder Aufrufpfad ohne explizite Tagesangabe hat ein volles Jahr verschenkt.

## Kaufwege

1. **Stripe** (automatisch) — siehe [STRIPE-SETUP.md](./STRIPE-SETUP.md)
2. **Promo-Codes** — im Dashboard unter *Monetization* erstellen, Nutzer lösen
   sie auf `pages/redeem.php` ein
3. **Manuell** — *Premium Management* (`premium.php`) für User,
   *Server-Plan vergeben* (`guild-premium.php`) für Server

## Wichtige Endpunkte

| Route | Zweck |
|---|---|
| `GET /premium/user/:userId` | Premium-Status einer Person |
| `POST /premium/activate` | User-Premium setzen (Default 30 Tage) |
| `POST /premium/deactivate` | User-Premium entziehen |
| `GET /guilds/:guildId/premium` | **effektiver** Plan eines Servers (inkl. `planSource`) |
| `POST /premium/guild/activate` | Server-Plan setzen |
| `POST /premium/guild/deactivate` | Server-Plan entziehen |
| `GET /premium/guild-plans` | alle aktiven Server-Pläne |
| `POST /stripe/webhook` | Stripe-Checkout (signaturgeprüft, kein Bearer-Token) |

Alle brauchen den `BOT_API_TOKEN` als Bearer-Token, außer den vier Ausnahmen in
der Auth-Middleware (`services/botAPI.js`): `/health`, `/topgg/webhook`,
`/guild/team` und `/stripe/webhook` (letzterer authentifiziert per Signatur).

## Bekannte offene Punkte

- ~~Ablauf-Erinnerungen laufen nicht automatisch.~~ **Erledigt** — siehe unten.
- **Stripe-Kündigungen und Rückerstattungen werden nicht verarbeitet** — Premium
  läuft dann normal aus, statt sofort entzogen zu werden (siehe STRIPE-SETUP.md).
- **`utils/migratePremiumToUser.js` ist kaputt** — ruft `db.addFeature()` und
  `db.close()` auf, die es nicht gibt. Einmal-Skript, nicht im Betriebspfad.

## Ablauf-Erinnerungen

Laufen automatisch, ohne Konfiguration. Der Bot prüft 2 Minuten nach dem Start
und danach alle 6 Stunden, wessen Plan bald ausläuft, und schickt eine DM.

**Meilensteine** — jeder feuert **genau einmal pro Laufzeit**:

| Meilenstein | wann |
|---|---|
| `d7` | ab 7 Tagen Restlaufzeit |
| `d3` | ab 3 Tagen |
| `d1` | ab 1 Tag |
| `expired` | am Ablauftag, bis 3 Tage danach |

Ein Plan, der erst mit 5 Tagen Rest auftaucht, bekommt sofort die `d7`-DM statt
bis zur 3-Tage-Marke zu warten — danach normal `d3`, `d1`, `expired`.

**Dedup** liegt in der Tabelle `reminder_log`, Schlüssel ist
`(user_id, expires_at, milestone)`. Weil das Ablaufdatum Teil des Schlüssels
ist, **setzt eine Verlängerung alle Meilensteine automatisch zurück** — ohne
Sonderbehandlung. Deshalb ist es auch egal, wie oft der Job läuft: mehrfaches
Ausführen verschickt nichts doppelt, und ein Bot, der oft neu startet, verpasst
trotzdem kein Fenster.

**Blockierte DMs** (Discord-Fehler 50007) werden als zugestellt vermarkt. Sonst
würde der Job es jeden Tag erneut versuchen, obwohl es nie klappen kann.
Andere Fehler (API-Aussetzer) bleiben offen und werden beim nächsten Lauf
wiederholt.

Die DM nennt konkret, was verloren geht (Cooldown, Shield-Takt, Shield-Dauer,
XP-Boost — mit echten Zahlen aus `pricing.js`), den Preis, und hat einen
Verlängern-Button. Vor Ablauf steht dabei, dass frühes Verlängern nichts kostet,
weil Tage draufgerechnet werden.

**Manuell auslösen** geht weiterhin über das Dashboard bzw.
`POST /premium/reminders/send`. Der Endpunkt nutzt jetzt exakt dieselbe Logik
wie der Job; `{"force": true}` umgeht den Dedup für einen bewussten Re-Send,
`{"userIds": ["..."]}` schränkt auf einzelne Leute ein.

Alte `reminder_log`-Zeilen (Ablauf älter als 120 Tage) räumt ein täglicher Job weg.

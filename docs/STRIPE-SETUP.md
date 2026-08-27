# Stripe-Checkout einrichten

Fahrstuhl nutzt **Stripe Payment Links** — keine eigene Checkout-Seite, keine
Kartendaten auf unserem Server, kein `stripe`-npm-Paket. Stripe hostet den
Bezahlvorgang, wir bekommen danach einen Webhook und schalten frei.

Einrichtung dauert ~15 Minuten.

---

## 1. Produkte und Preise anlegen

Im [Stripe Dashboard](https://dashboard.stripe.com/products) vier Preise anlegen:

| Produkt | Preis | Typ |
|---|---|---|
| Fahrstuhl Premium | 2,49 € | wiederkehrend, monatlich |
| Fahrstuhl Premium Lifetime | 14,99 € | einmalig |
| Fahrstuhl Pro | 4,99 € | wiederkehrend, monatlich |
| Fahrstuhl Pro Lifetime | 29,99 € | einmalig |

Die Beträge müssen zu `utils/pricing.js` passen. Änderst du sie hier, setze
auch `PRICE_BASIC_MONTHLY` usw. in der `.env` (siehe Abschnitt 5).

Jeder Preis hat eine ID der Form `price_1AbC...` — **die brauchst du gleich.**

## 2. Payment Links erstellen

Für jeden der vier Preise einen [Payment Link](https://dashboard.stripe.com/payment-links)
anlegen. Wichtige Einstellungen:

- **"Nach der Zahlung"** → auf deine Dashboard-Seite weiterleiten, z.B.
  `https://eselbande.com/fahrstuhl/pages/premium-info.php`
- **Kundendaten**: E-Mail reicht, mehr wird nicht gebraucht

Die Links sehen aus wie `https://buy.stripe.com/xxxxx`.

> Die Pricing-Seite hängt automatisch `?client_reference_id=<Discord-User-ID>`
> an den Link an. **Genau daran** erkennt der Webhook später, wer bezahlt hat —
> darum darf der Link nirgends ohne diesen Parameter beworben werden. Wer den
> nackten Link teilt, zahlt zwar, wird aber nicht automatisch freigeschaltet
> (dann manuell über `premium.php` freischalten).

## 3. Webhook einrichten

Unter [Webhooks](https://dashboard.stripe.com/webhooks) einen Endpunkt anlegen:

- **URL**: `https://<deine-domain>/stripe/webhook`

> ⚠️ **Wichtig:** Diese URL muss auf die **Bot-API** zeigen, nicht auf das
> PHP-Dashboard. Das sind zwei getrennte Container: das Dashboard läuft auf
> `dashboard-php:8081`, die Bot-API auf `fahrstuhl-docker:3002`, nach außen nur
> als `127.0.0.1:3102` (siehe `docker-compose.yml`). Ohne Extra-Regel landet
> `https://<domain>/stripe/webhook` beim Dashboard, das diese Route nicht kennt
> — es wird dann nie jemand freigeschaltet.
>
> Im Reverse Proxy (nginx) also eine eigene Location anlegen:
>
> ```nginx
> location = /stripe/webhook {
>     proxy_pass http://127.0.0.1:3102/stripe/webhook;
>     proxy_set_header Host $host;
>     proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
> }
> ```
>
> Danach prüfen: `curl -i https://<domain>/stripe/webhook -X POST -d '{}'`
> muss **503** (`Stripe is not configured`) oder **400** (`Invalid signature`)
> liefern — beides heißt "richtig angekommen". Eine 404 oder eine HTML-Seite
> heißt, der Proxy zeigt noch aufs Dashboard.
- **Events**:
  - `checkout.session.completed` — der eigentliche Kauf
  - `checkout.session.async_payment_succeeded` — für Klarna/Lastschrift, die
    verzögert bestätigen
  - `invoice.paid` — **nur nötig bei monatlichen Abo-Preisen**, damit
    Folgezahlungen verlängern (die erste Rechnung wird automatisch übersprungen,
    weil der Checkout sie schon abgedeckt hat)

Stripe zeigt danach ein **Signing secret** (`whsec_...`) — das ist
`STRIPE_WEBHOOK_SECRET`.

### Den Tier erkennbar machen — Metadata setzen (Pflicht)

`checkout.session.completed` enthält **keine** `line_items`. Der Bot kann den
gekauften Tier also nicht aus dem Preis ableiten — er braucht Metadata.

Beim Anlegen jedes Payment Links unter *Metadata* eintragen:

| Link | `tier` | `days` |
|---|---|---|
| Premium monatlich | `basic` | `30` |
| Premium Lifetime | `basic` | `36500` |
| Pro monatlich | `pro` | `30` |
| Pro Lifetime | `pro` | `36500` |

**Das ist nicht optional** — ohne Metadata loggt der Bot
`Event not actioned: unknown_price` und schaltet niemanden frei.

Die `STRIPE_PRICE_*`-Variablen weiter unten sind ein zusätzlicher Fallback für
`invoice.paid`-Events (Abo-Verlängerungen), die ihre Positionen in `lines.data`
mitschicken. Bei reinen Lifetime-/Einmalkäufen brauchst du sie nicht.

## 4. Testen

Im Stripe-Testmodus mit der Testkarte `4242 4242 4242 4242` (beliebiges
zukünftiges Datum, beliebige CVC) einen Kauf durchführen.

Erwartet:
1. Stripe zeigt den Webhook unter *Events* als `200` an
2. Bot-Log: `[Stripe] ✅ pro (30d) activated for <userId>`
3. Der Käufer bekommt eine DM vom Bot
4. Der Kauf taucht im Dashboard unter *Monetization* als Revenue-Eintrag auf

Kommt kein `200` zurück:

| Meldung im Log | Ursache |
|---|---|
| `Rejected webhook: signature_mismatch` | falsches `STRIPE_WEBHOOK_SECRET` |
| `Rejected webhook: timestamp_out_of_tolerance` | Serveruhr läuft falsch (NTP prüfen) |
| `Event not actioned: no_discord_user_id` | Link ohne `client_reference_id` aufgerufen |
| `Event not actioned: unknown_price` | Metadata (`tier`/`days`) am Payment Link fehlt |
| `Event not actioned: duplicate_event` | harmlos — Stripe hat den Event erneut zugestellt, er war schon verarbeitet |
| `Stripe is not configured` | `STRIPE_WEBHOOK_SECRET` nicht gesetzt |

## 5. Environment-Variablen

```bash
# Pflicht — ohne das ist der Webhook deaktiviert
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxx

# Payment Links (die Buttons auf premium-info.php)
STRIPE_LINK_BASIC_MONTHLY=https://buy.stripe.com/xxxx
STRIPE_LINK_BASIC_LIFETIME=https://buy.stripe.com/xxxx
STRIPE_LINK_PRO_MONTHLY=https://buy.stripe.com/xxxx
STRIPE_LINK_PRO_LIFETIME=https://buy.stripe.com/xxxx

# Nur nötig für Variante B aus Schritt 3
STRIPE_PRICE_BASIC_MONTHLY=price_xxxx
STRIPE_PRICE_BASIC_LIFETIME=price_xxxx
STRIPE_PRICE_PRO_MONTHLY=price_xxxx
STRIPE_PRICE_PRO_LIFETIME=price_xxxx

# Optional: Preise überschreiben (Default steht in utils/pricing.js)
PRICE_BASIC_MONTHLY=2.49
PRICE_BASIC_LIFETIME=14.99
PRICE_PRO_MONTHLY=4.99
PRICE_PRO_LIFETIME=29.99
```

Solange `STRIPE_LINK_*` leer ist, zeigen die Kauf-Buttons weiterhin auf den
Support-Server — die Seite funktioniert also auch ohne Stripe, genau wie vorher.

---

## Sicherheitshinweise

- `/stripe/webhook` ist **von der Bearer-Token-Auth ausgenommen** (Stripe kann
  den Token nicht mitschicken) und stattdessen über die HMAC-Signatur
  abgesichert. Ohne gesetztes `STRIPE_WEBHOOK_SECRET` antwortet der Endpunkt
  mit `503` und tut nichts — er ist also nie ungeschützt offen.
- Die Signaturprüfung nutzt `crypto.timingSafeEqual` und lehnt Requests älter
  als 5 Minuten ab (Replay-Schutz).
- Webhook-Bodies landen bewusst **nicht** im Dashboard-Audit-Log, weil sie
  Kundennamen und Adressdaten enthalten können.
- Verifizierte Events werden immer mit `200` beantwortet, auch wenn sie
  bewusst ignoriert wurden — sonst versucht Stripe sie tagelang erneut.

## Was der Webhook NICHT tut

**Kündigungen und fehlgeschlagene Zahlungen** (`customer.subscription.deleted`,
`invoice.payment_failed`) werden nicht verarbeitet. Bei einem gekündigten Abo
wird Premium also nicht sofort entzogen — es läuft am Ende der bezahlten Periode
einfach aus. Das ist bewusst so (und für den Kunden die freundlichere Variante),
sollte dir aber bekannt sein.

**Rückerstattungen** (`charge.refunded`) entziehen Premium ebenfalls nicht
automatisch — das musst du bei Bedarf manuell über *Premium Management* machen.

## Doppelte Zustellung

Stripe stellt Events erneut zu, wenn dein Server nicht mit 2xx antwortet oder
zu lange braucht. Weil eine Aktivierung die Restlaufzeit **verlängert** statt
sie zu überschreiben, würde ein doppelt verarbeiteter Event zwei Monate für eine
Zahlung gutschreiben. Der Bot merkt sich deshalb jede `event.id` in der Tabelle
`processed_events` und ignoriert Wiederholungen (`duplicate_event` im Log).

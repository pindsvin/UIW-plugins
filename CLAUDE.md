# UIW Plugins

WordPress plugins voor Uit in Wageningen.

## Repository

GitHub: https://github.com/pindsvin/UIW-plugins  
Branch: `master`

## Plugins

### 1. Stad Wageningen Doorplaatser (`stadwageningen-doorplaatsen/`)

Zet een WordPress-bericht klaar en vult er via een **bookmarklet** het tip-de-redactie-formulier van stadwageningen.nl mee in.

**Werking (v2.0.0 — browser-side via bookmarklet):**
- Blok "Stad Wageningen" in de zijbalk van de bericht-editor: categorie, onderschrift, fotocredit + knop **Klaarzetten voor Stad Wageningen**
- Klaarzetten slaat het bericht op als "queued" (post_id + categorie + onderschrift + credit) in de optie `stadwag_queued`
- Op stadwageningen.nl/tip-de-redactie klikt de gebruiker een **bookmarklet** die de klaargezette data ophaalt via een REST-endpoint en de formuliervelden invult (titel, tekst, categorie, onderschrift, fotocredit)
- **De foto wordt automatisch geüpload** (v2.1.0): de bookmarklet haalt de afbeelding op via een tweede REST-endpoint en plaatst die met `DataTransfer` in het file-input van het formulier
- De velden `title`/`text` zijn tekst-editors (`contenteditable` DIVs vóór de verborgen textareas); de bookmarklet "typt" erin via `document.execCommand('insertText')` — direct de textarea zetten werkt niet (editor overschrijft die)
- De daadwerkelijke verzending gebeurt in de browser van de gebruiker, op de pagina van Stad Wageningen, met diens eigen sessie + antiforgery-token → geen WAF/firewall-blokkade
- Titel/tekst worden opgeschoond (HTML + losse URLs gestript, lege regels tussen alinea's genormaliseerd)

**Waarom v2.0.0:** de oude server-side aanpak (v1.x) logde via cURL in op stadwageningen.nl. Dat werd door bot-/firewallbescherming geblokkeerd (HTTP 403 op login- en formulierpagina). De bookmarklet-aanpak omzeilt dit door alles vanuit de echte browser te doen.

**REST-endpoints:** (beide token-beveiligd, WP's eigen REST-CORS echoot de Origin)
- `GET /wp-json/stadwag/v1/queued?token=XXX` — klaargezette berichtdata als JSON
- `GET /wp-json/stadwag/v1/queued-image?token=XXX` — de uitgelichte afbeelding als ruwe bytes (omzeilt JSON-serialisatie: zet headers + `readfile` + `exit`)

**Instellingen:** WordPress → Instellingen → Stad Wageningen (bookmarklet installeren + token)  
**Versie:** 2.1.1

**Bestanden:**
- `stadwageningen-doorplaatsen.php` — plugin header, constanten, laadt rest + admin
- `includes/class-stadwag-rest.php` — REST-endpoint dat de queued data teruggeeft
- `includes/class-stadwag-admin.php` — metabox (klaarzetten) + instellingenpagina (bookmarklet/token) + AJAX
- `assets/js/metabox.js` — jQuery (klaarzet-knop → AJAX)
- `includes/class-stadwag-api.php` — **legacy, niet meer geladen** (oude server-side login/submit)

**Categorieën stadwageningen.nl:**
- 4651 = Lokaal
- 4562 = Sport
- 4608 = Zakelijk

**Formuliervelden op tip-de-redactie:** `title` (textarea), `text` (textarea), `CategoryId` (select), `caption[0]`, `credit[0]`, file-upload (handmatig)

---

### 2. Cultuur in Wageningen (`cultuur-in-wageningen/`)

Plaatst een WordPress-bericht door naar cultuurinwageningen.nl via hun Contact Form 7-formulier (CF7 REST API).

**Werking (v2.0.0 — formulier in nieuw tabblad, server-side proxy):**
- Knop "Doorplaatsen →" in de zijbalk opent een **nieuw tabblad** (admin-pagina) met een pre-ingevuld formulier
- Gebruiker vult aan, upload zelf de afbeelding (max 1 MB), vinkt de voorwaarden aan en drukt op Versturen
- De browser stuurt het formulier via **WP AJAX** (`cultuur_wageningen_submit`) naar de WordPress-server
- De server haalt **net vóór verzending** een verse quiz-hash + CF7-metadata + **honeypot-veld** (`stoppert`) op van de live formulierpagina (`fetch_form_data()`)
- De server POST't via cURL (CURLFile voor de afbeelding) naar de CF7 REST API — server-side, dus geen CORS-beperking
- **Cruciaal voor spam-omzeiling:** de honeypot (`stoppert-random-hash` + de willekeurige veldnaam in `stoppert-wrap`) wordt correct leeg meegestuurd, net als bij een echte bezoeker. Dit loste de eerdere spam-blokkade op
- Quiz-antwoord: `gelderland` (lowercase); acceptance: `1`
- Bij succes (`status: mail_sent`): tijdstip opgeslagen als post meta (`_cultuur_wageningen_submitted`)

**Instellingen:** geen aparte instellingenpagina — werkt op basis van de ingelogde WordPress-gebruiker (naam + e-mail)  
**Versie:** 2.0.0

**Bestanden:**
- `cultuur-wageningen.php` — plugin header, klasse, formulierrenderer (nieuw tabblad), `ajax_submit()` (cURL → CF7), `fetch_form_data()` (quiz-hash + honeypot)
- `doorplaats.js` — verstuurt het formulier via WP AJAX (FormData), resultaatverwerking
- `admin.js` — niet meer in gebruik (metabox is nu een plain link)

**CF7-formulier op cultuurinwageningen.nl:**
- Form ID: 5315
- Container post: 447
- Quiz-veld: `quiz-467` (antwoord: `gelderland` — lowercase, hash wordt dynamisch opgehaald)
- Honeypot: `stoppert` (CF7 Apps Honeypot — willekeurige veldnaam in `stoppert-wrap` + `stoppert-random-hash`)

---

## Versienummering

- `x.1.0` — nieuwe functionaliteit
- `x.0.x` — bugfixes
- Versie staat in de plugin header (`* Version:`) én als `define()` constante in hetzelfde bestand
- ZIP altijd opnieuw genereren na een update

## Checklist na elke wijziging

Voer altijd in deze volgorde uit — sla niets over:

1. Versienummer ophogen in de plugin header (`* Version:`)
2. Versienummer ophogen in de `define('..._VERSION', ...)` constante
3. ZIP opnieuw aanmaken
4. Bevestig aan de gebruiker welk versienummer de nieuwe ZIP heeft

## Pushen naar GitHub

```bash
git remote set-url origin https://pindsvin:TOKEN@github.com/pindsvin/UIW-plugins.git
git pull origin master --no-rebase
git add .
git commit -m "Beschrijving van de wijziging"
git push origin master
```

> Token aanmaken: GitHub → Settings → Developer settings → Personal access tokens → Classic → vink `repo` aan

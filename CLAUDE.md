# UIW Plugins

WordPress plugins voor Uit in Wageningen.

## Repository

GitHub: https://github.com/pindsvin/UIW-plugins  
Branch: `master`

## Plugins

### 1. Stad Wageningen Doorplaatser (`stadwageningen-doorplaatsen/`)

Zet een WordPress-bericht klaar en vult er via een **Tampermonkey-userscript** het tip-de-redactie-formulier van stadwageningen.nl mee in, inclusief automatisch verzenden.

**Werking (v3.0.0 — userscript met auto-submit):**
- Blok "Stad Wageningen" in de zijbalk van de bericht-editor: categorie, onderschrift, fotocredit + knop **Doorplaatsen naar Stad Wageningen**
- Klikken op de knop slaat het bericht op als "queued" (post_id + categorie + onderschrift + credit) in de optie `stadwag_queued` en opent automatisch stadwageningen.nl/tip-de-redactie in een nieuw tabblad
- Het **userscript** (Tampermonkey) draait automatisch op page-load van de tip-de-redactie-pagina: het haalt de klaargezette data op via een REST-endpoint en vult de formuliervelden in (titel, tekst, categorie, onderschrift, fotocredit)
- **De foto wordt automatisch geüpload**: het userscript haalt de afbeelding op via een tweede REST-endpoint en plaatst die met `DataTransfer` in het file-input van het formulier
- **Automatisch verzenden**: na het invullen + foto-upload vinkt het userscript de voorwaarden-checkbox (`okGeneralConditions`) aan en roept `pubbleWebsiteForms.submit()` aan. Er verschijnt een `confirm()` dialoog ("Verzenden naar Stad Wageningen?") — bij OK wordt direct verzonden, bij Annuleren kan de gebruiker handmatig controleren en verzenden
- Als er géén bericht klaargezet is (REST geeft 404), doet het userscript niets — de gebruiker kan het formulier normaal handmatig gebruiken
- De velden `title`/`text` zijn tekst-editors (`contenteditable` DIVs vóór de verborgen textareas); het userscript "typt" erin via `document.execCommand('insertText')` — direct de textarea zetten werkt niet (editor overschrijft die)
- De daadwerkelijke verzending gebeurt in de browser van de gebruiker, op de pagina van Stad Wageningen, met diens eigen sessie + antiforgery-token → geen WAF/firewall-blokkade
- Titel/tekst worden opgeschoond (HTML + losse URLs gestript, lege regels tussen alinea's genormaliseerd)

**Waarom v3.0.0:** de bookmarklet-aanpak (v2.x) werkte, maar vereiste handmatig navigeren naar de tip-de-redactie-pagina en klikken op de bookmarklet. Het userscript draait automatisch op page-load, waardoor de flow wordt: knop klikken in WP → pagina opent → alles wordt ingevuld → bevestigen → verzonden. Eén klik minder.

**REST-endpoints:** (alle token-beveiligd, WP's eigen REST-CORS echoot de Origin)
- `GET /wp-json/stadwag/v1/queued?token=XXX` — klaargezette berichtdata als JSON
- `GET /wp-json/stadwag/v1/queued-image?token=XXX` — de uitgelichte afbeelding als ruwe bytes (omzeilt JSON-serialisatie: zet headers + `readfile` + `exit`)
- `GET /wp-json/stadwag/v1/userscript` — serveert het Tampermonkey-userscript met token ingebed (alleen voor ingelogde WP-admins)

**Instellingen:** WordPress → Instellingen → Stad Wageningen (userscript installeren + token)  
**Versie:** 3.0.0

**Bestanden:**
- `stadwageningen-doorplaatsen.php` — plugin header, constanten, laadt rest + admin
- `includes/class-stadwag-rest.php` — REST-endpoints: queued data, queued image, userscript
- `includes/class-stadwag-admin.php` — metabox (doorplaats-knop) + instellingenpagina (userscript/token) + AJAX
- `assets/js/metabox.js` — jQuery (doorplaats-knop → AJAX → opent stadwageningen-pagina in nieuw tabblad)
- `includes/class-stadwag-api.php` — **legacy, niet meer geladen** (oude server-side login/submit)

**Categorieën stadwageningen.nl:**
- 4651 = Lokaal
- 4562 = Sport
- 4608 = Zakelijk

**Formuliervelden op tip-de-redactie:** `title` (textarea + contenteditable), `text` (textarea + contenteditable), `CategoryId` (select), `okGeneralConditions` (checkbox, verplicht), `hp_website` (honeypot, moet leeg blijven), `send` (button, roept `pubbleWebsiteForms.submit()` aan), `caption[0]`, `credit[0]`, file-upload (automatisch via DataTransfer)

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

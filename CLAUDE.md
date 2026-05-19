# UIW Plugins

WordPress plugins voor Uit in Wageningen.

## Repository

GitHub: https://github.com/pindsvin/UIW-plugins  
Branch: `master`

## Plugins

### 1. Stad Wageningen Doorplaatser (`stadwageningen-doorplaatsen/`)

Plaatst een WordPress-bericht door naar stadwageningen.nl/tip-de-redactie via een knop in de bericht-editor.

**Werking:**
- Knop in de zijbalk van de bericht-editor
- Twee-stap flow: eerst voorvertoning, dan bevestigen
- Logt server-side in op stadwageningen.nl (ASP.NET / Pubble CMS)
- Sessie wordt 20 minuten gecached als WP transient
- Velden: koptekst (post_title), tekst (post_content gestript), categorie, uitgelichte afbeelding
- HTML en URLs worden gestript uit de tekst voor verzending
- Server retourneert HTTP 200 met bedankpagina bij succes (géén 302-redirect) — succes wordt gedetecteerd via keywords in de response body

**Instellingen:** WordPress → Instellingen → Stad Wageningen  
**Versie:** 1.2.0

**Bestanden:**
- `stadwageningen-doorplaatsen.php` — plugin header, constanten
- `includes/class-stadwag-api.php` — alle HTTP/cURL logica (login, tokens, submit)
- `includes/class-stadwag-admin.php` — settings, metabox, AJAX handlers
- `assets/js/metabox.js` — jQuery UI (twee-stap flow)

**Categorieën stadwageningen.nl:**
- 4651 = Lokaal
- 4562 = Sport
- 4608 = Zakelijk

---

### 2. Cultuur in Wageningen (`cultuur-in-wageningen/`)

Plaatst een WordPress-bericht door naar cultuurinwageningen.nl via hun Contact Form 7-formulier (CF7 REST API).

**Werking (v1.4.0 — browser-side submission):**
- Knop "Doorplaatsen →" in de zijbalk opent een **nieuw tabblad** met een pre-ingevuld formulier
- PHP haalt de CF7-pagina op om quiz-hash + metadata op te halen (spam-bescherming)
- Gebruiker upload zelf de afbeelding, vinkt de voorwaarden aan en drukt op Verzenden
- **De browser stuurt de POST rechtstreeks naar cultuurinwageningen.nl** (geen WP-server tussenin)
- Ziet er voor de ontvanger identiek uit aan een gewone bezoeker die het formulier invult
- Bij succes: tijdstip wordt opgeslagen als post meta via apart WP AJAX-verzoek (`_cultuur_wageningen_submitted`)

**Instellingen:** geen aparte instellingenpagina — werkt op basis van de ingelogde WordPress-gebruiker (naam + e-mail)  
**Versie:** 1.4.0

**Bestanden:**
- `cultuur-wageningen.php` — plugin header, klasse, PHP-formulierrenderer, AJAX save-handler
- `doorplaats.js` — browser-side fetch() naar CF7 REST API, resultaatverwerking
- `admin.js` — niet meer in gebruik (metabox is nu een plain link)

**CF7-formulier op cultuurinwageningen.nl:**
- Form ID: 5315
- Container post: 447
- Quiz-veld: `quiz-467` (antwoord: `gelderland` — lowercase, hash wordt dynamisch opgehaald)

---

## Versienummering

- `x.1.0` — nieuwe functionaliteit
- `x.0.x` — bugfixes
- Versie staat in de plugin header (`Plugin Name`) én als `define()` constante
- ZIP altijd opnieuw genereren na een update

## Pushen naar GitHub

```bash
git remote set-url origin https://pindsvin:TOKEN@github.com/pindsvin/UIW-plugins.git
git pull origin master --no-rebase
git add .
git commit -m "Beschrijving van de wijziging"
git push origin master
```

> Token aanmaken: GitHub → Settings → Developer settings → Personal access tokens → Classic → vink `repo` aan

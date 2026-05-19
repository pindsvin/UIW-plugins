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

**Instellingen:** WordPress → Instellingen → Stad Wageningen  
**Versie:** 1.1.0

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

**Werking:**
- Knop in de zijbalk van de bericht-editor
- Twee-stap flow: eerst voorvertoning (naam, e-mail, titel, tekst, afbeelding), dan bevestigen
- Haalt eerst de CF7-pagina op om een quiz-hash op te halen (spam-bescherming)
- Verstuurt via multipart POST naar de CF7 REST API (`/wp-json/contact-form-7/v1/contact-forms/5315/feedback`)
- Uitgelichte afbeelding wordt meegestuurd; indien >1 MB wordt die automatisch verkleind (max 5 pogingen, steeds 75% van de breedte)
- HTML wordt omgezet naar platte tekst; minimaal 50 tekens vereist
- Na succesvolle verzending wordt tijdstip opgeslagen als post meta (`_cultuur_wageningen_submitted`)

**Instellingen:** geen aparte instellingenpagina — werkt op basis van de ingelogde WordPress-gebruiker (naam + e-mail)  
**Versie:** 1.2.0

**Bestanden:**
- `cultuur-wageningen.php` — volledige plugin (één bestand: plugin header, klasse, AJAX handlers, hulpfuncties)
- `admin.js` — jQuery UI (twee-stap flow: preview → bevestig en verstuur)

**CF7-formulier op cultuurinwageningen.nl:**
- Form ID: 5315
- Container post: 447
- Quiz-veld: `quiz-467` (antwoord: `Gelderland`, hash wordt dynamisch opgehaald)

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

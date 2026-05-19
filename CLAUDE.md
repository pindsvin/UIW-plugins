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

[Beschrijving volgt — zie de andere chat voor context]

**Versie:** zie `cultuur-wageningen.php`

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

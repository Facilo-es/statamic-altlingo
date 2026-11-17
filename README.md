# AltLingo

**Alt text in every language of your multi-site.**

AltLingo is a free Statamic 6 addon for multi-site websites in more than one language. Statamic keeps one alt text per image, so on a multi-site your Spanish pages read the English description, or the reverse. AltLingo gives every image a description in each language, and `{{ alt }}` shows the right one on each site. No template changes.

**How you use it**

1. Install it with `composer require facilo/statamic-altlingo`.
2. Run `php please altlingo:install`. Your existing alt texts stay and become the main language's text.
3. Open an image in the control panel and write its description in each language's tab. Your templates show the right one by themselves.

**What it doesn't do:** it doesn't translate or write the descriptions for you. You (or your translators) write the text for each language.

## What you get

- One alt text per language, right in the image panel, with tabs
- The right text on every site, in Antlers, Blade, Bard, Glide, REST and GraphQL
- Mark decorative images once for all languages
- One command to set it up, and imports from `alt_en`-style fields, Aida and Multisite Any Field

Images found in Google Images in every language, and screen readers that read the description in the visitor's language.

No lock-in: your main text stays in Statamic's own `alt` field, so removing AltLingo leaves a working site. A command to move your texts into Statamic's own per-language asset fields, once Statamic has them, is planned.

## Requirements

- Statamic 6, PHP 8.3 or newer
- Two or more sites. With one site, the field works as a plain text field.

## Install

```bash
composer require facilo/statamic-altlingo
php please altlingo:install --dry-run   # see what would change
php please altlingo:install
```

`altlingo:install` turns the `alt` field of your image containers into an AltLingo field. Your existing texts stay where they are and become the main language's text. Run it again at any time: it reports "Nothing to do" when everything is set up.

Open any image in the control panel: the alt text field now has one tab per site. Your templates keep using `{{ alt }}`, `$asset->alt` or the API as before.

## Commands

| Command | What it does |
|---|---|
| `altlingo:install` | Converts the alt field. Options: `--container=` (repeatable, only these containers), `--field=` (default `alt`), `--dry-run`, `--force` |
| `altlingo:import --from=fields` | Moves texts from one field per site, like `alt_en` and `alt_ca`, including fields written by Aida or Multisite for Assets. Use `--map=en:alt_english` for other names |
| `altlingo:import --from=daun` | Moves texts from Multisite Any Field |
| `altlingo:import` options | `--overwrite` replaces texts that already exist (off by default), `--dry-run` only counts |
| `altlingo:uninstall` | Turns AltLingo fields back into plain text fields. The main text stays in `alt`. Add `--purge` to also delete the other languages' texts |

## How the right text is chosen

On each site, `{{ alt }}` shows, in this order:

1. The image's own text for that site
2. Otherwise, the main language's text

Decorative images always give `alt=""` on every site.

This works in Antlers, Blade, Bard image nodes, Glide tags, the REST API (`?site=en`) and GraphQL (`alt(site: "ca")`).

## Settings (Configure → Addons → AltLingo)

- **Containers:** which containers are covered. Empty means every container with images.
- **Field handle:** the field that holds the alt text, usually `alt`.

Who can change these settings is Statamic's own permission **AltLingo settings** (`edit facilo/statamic-altlingo settings`).

Site access uses Statamic's own site permissions: a user who can't access a site sees its tab read-only and can't save it.

## Data format

AltLingo keeps everything in the asset's own meta file:

```yaml
alt: 'Terraza con vistas al mar'      # main language, a plain string as always
alt_altlingo:                          # other sites, by site handle
  en: 'Terrace with a sea view'
  ca: 'Terrassa amb vistes al mar'
altlingo:                              # only when set
  decorative: true
```

`$asset->get('alt')` stays a plain string. Removing AltLingo leaves a working site.

## Privacy

AltLingo sends nothing anywhere: no telemetry, no calls home. Your texts stay in your own files.

## Uninstall

```bash
php please altlingo:uninstall           # fields back to plain text, other languages kept in the files
php please altlingo:uninstall --purge   # also delete the other languages' texts
composer remove facilo/statamic-altlingo
```

## Support

Email support@facilo.es. Security issues: see [SECURITY.md](SECURITY.md).

AltLingo helps you write alt text in every language. It doesn't on its own make a site accessible or compliant with any law.

---

## En español

**El texto alternativo en cada idioma de tu web multisitio.**

AltLingo es un addon gratuito para webs Statamic 6 con varios idiomas. Statamic guarda un solo texto alternativo por imagen, así que en una web con varios idiomas tus páginas en inglés leen la descripción en español, o al revés. AltLingo da a cada imagen una descripción en cada idioma, y `{{ alt }}` muestra la correcta en cada sitio, sin tocar las plantillas.

1. Instálalo: `composer require facilo/statamic-altlingo`.
2. Ejecuta `php please altlingo:install`. Tus textos actuales se quedan como texto del idioma principal.
3. Abre una imagen en el panel y escribe su descripción en la pestaña de cada idioma.

Incluye un texto por idioma en el panel de la imagen, con pestañas; el texto correcto en Antlers, Blade, Bard, Glide, REST y GraphQL; imágenes decorativas; e importación desde campos `alt_en`, Aida y Multisite Any Field. No traduce ni escribe las descripciones por ti: las escribes tú o tus traductores. No envía nada a ningún sitio. Soporte: support@facilo.es.

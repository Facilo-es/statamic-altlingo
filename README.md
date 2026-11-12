# AltLingo

**Alt text in every language of your multi-site.**

Statamic keeps one alt text per image, so on a multi-site your Spanish pages read the English description, or the reverse. AltLingo gives every image a description in each language, and `{{ alt }}` shows the right one on each site. No template changes.

### Free

- One alt text per language, right in the image panel, with tabs
- The right text on every site, in Antlers, Blade, Bard, Glide, REST and GraphQL
- Mark decorative images once for all languages
- One command to set it up, and imports from `alt_en`-style fields, Aida and Multisite Any Field

### Pro

- See what's missing in each language, at a glance and on your dashboard
- Fill hundreds of descriptions fast in a keyboard-friendly grid
- Translate missing texts from your main language with your own DeepL or AI key, marked for review
- Send a CSV to your translators and import it back with a preview
- Fallbacks per language, such as Mexican Spanish to Spanish
- The same for captions, titles and credits

Images found in Google Images in every language, and screen readers that read the description in the visitor's language.

No lock-in: your main text stays in Statamic's own `alt` field, and when Statamic adds per-language asset fields, one command moves your texts there.

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

To use Pro, choose the Pro edition in `config/statamic/editions.php` and buy a licence on the Statamic Marketplace:

```php
'addons' => [
    'facilo/statamic-altlingo' => 'pro',
],
```

## Commands

| Command | What it does |
|---|---|
| `altlingo:install` | Converts the alt field. Options: `--container=` (repeatable, only these containers), `--field=` (default `alt`), `--dry-run`, `--force` |
| `altlingo:import --from=fields` | Moves texts from one field per site, like `alt_en` and `alt_ca`, including fields written by Aida or Multisite for Assets. Use `--map=en:alt_english` for other names |
| `altlingo:import --from=daun` | Moves texts from Multisite Any Field |
| `altlingo:import` options | `--overwrite` replaces texts that already exist (off by default), `--dry-run` only counts |
| `altlingo:refresh` | Rebuilds the coverage numbers (Pro) |
| `altlingo:uninstall` | Turns AltLingo fields back into plain text fields. The main text stays in `alt`. Add `--purge` to also delete the other languages' texts |

## How the right text is chosen

On each site, `{{ alt }}` shows, in this order:

1. The image's own text for that site
2. Pro: the languages in that site's fallback chain, in order (such as Mexican Spanish → Spanish)
3. The main language's text, or nothing, if that site is set to "Leave empty"

Decorative images always give `alt=""` on every site.

This works in Antlers, Blade, Bard image nodes, Glide tags, the REST API (`?site=en`) and GraphQL (`alt(site: "ca")`).

## Settings (Configure → Addons → AltLingo)

- **Containers:** which containers are covered. Empty means every container with images.
- **Field handle:** the field that holds the alt text, usually `alt`.
- **Pro:** fallbacks per language, translation provider, and more fields with one text per language (such as caption or credit).

## Translation keys (Pro)

Translation uses your own account. Put the key in `.env`, never in the control panel:

```dotenv
ALTLINGO_DEEPL_KEY=your-deepl-key        # keys ending in :fx use DeepL Free
ALTLINGO_OPENAI_KEY=your-key             # for the AI option (OpenAI-compatible)
```

The key is read from `.env` only. AltLingo never logs it, never sends it to the browser and never writes it to a YAML file. Without a key, the Translate buttons don't show.

## Permissions

- `view altlingo`: the Alt text page and the dashboard widget
- `edit altlingo`: the grid, bulk actions and CSV import
- `translate altlingo`: run translations, which can cost money on your provider account
- `configure altlingo`: the settings

Site access uses Statamic's own site permissions, on every action: a user who can't access a site sees its tab read-only and can't save it through any route.

## Data format

AltLingo keeps everything in the asset's own meta file:

```yaml
alt: 'Terraza con vistas al mar'      # main language, a plain string as always
alt_altlingo:                          # other sites, by site handle
  en: 'Terrace with a sea view'
  ca: 'Terrassa amb vistes al mar'
altlingo:                              # only when set
  decorative: true
  review: [en]                         # translated automatically, not yet reviewed
  machine: [en]
```

`$asset->get('alt')` stays a plain string. Removing AltLingo leaves a working site.

## Privacy

AltLingo sends nothing anywhere by itself: no telemetry, no calls home. Only when you add your own key and click Translate, the image's main-language text (not the image) is sent to the provider you chose: DeepL, or the OpenAI-compatible address in the settings. Mention that provider in your own privacy notice if you use it.

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

Statamic guarda un solo texto alternativo por imagen, así que en una web con varios idiomas tus páginas en inglés leen la descripción en español, o al revés. AltLingo da a cada imagen una descripción en cada idioma, y `{{ alt }}` muestra la correcta en cada sitio, sin tocar las plantillas.

- **Gratis:** un texto por idioma en el panel de la imagen, con pestañas; el texto correcto en Antlers, Blade, Bard, Glide, REST y GraphQL; imágenes decorativas; un comando de instalación e importación desde campos `alt_en`, Aida y Multisite Any Field.
- **Pro:** ves qué falta en cada idioma, rellenas cientos de descripciones en una tabla rápida, traduces con tu propia clave de DeepL o IA (marcado para revisar), CSV para tus traductores, idiomas de respaldo y lo mismo para pies de foto, títulos y créditos.

Instalación: `composer require facilo/statamic-altlingo` y después `php please altlingo:install`. Las claves de traducción van solo en `.env`. Solo cuando pulsas Traducir se envía el texto del idioma principal (nunca la imagen) al proveedor que elijas. Soporte: support@facilo.es.

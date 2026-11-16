# Changelog

## 1.0.2

- Pro: the Translate button on extra fields (such as caption or credit) now translates that field, not the alt text. It only fills the form: nothing is written until you save, so leaving without saving keeps your texts
- Translation keys keep working after `php artisan config:cache` or `optimize`: they are read from `.env` through the new `config/altlingo.php`
- Grid cells can be opened with the keyboard (Tab, then Enter or Space)
- Removed the `configure altlingo` permission, which had no effect: Statamic's own AltLingo settings permission controls the settings
- README: how to add extra fields to the container blueprint, which permission controls the settings, that AltLingo's permissions cover every covered container, and that the migration command is planned

## 1.0.1

- Clearer wording in the CSV import preview, in every language
- Smaller download: tests and source assets are left out of the package

## 1.0.0

First release.

- One alt text per language in the image panel, with tabs, and the right text on every site in Antlers, Blade, Bard, Glide, REST and GraphQL
- Decorative images
- `altlingo:install`, `altlingo:import` (fields, Aida, Multisite Any Field), `altlingo:uninstall`
- Pro: coverage page and dashboard widget, grid with bulk actions, translation with your own DeepL or OpenAI-compatible key, CSV export and import with preview, fallbacks per language, more fields per language
- Control panel in Spanish, English, German, French, Italian, Dutch and Portuguese

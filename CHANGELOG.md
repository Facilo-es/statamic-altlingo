# Changelog

## 1.0.4

- Leaner package: only the features described in the README. Texts you've already saved are kept
- Shorter settings page: the images covered and the field that holds the alt text

## 1.0.3

- README: describes only what you can use today, with a 3-step start and what AltLingo doesn't do

## 1.0.2

- Fixes from the Statamic Marketplace review, including settings that keep working after `php artisan config:cache` or `optimize`
- Removed the `configure altlingo` permission, which had no effect: Statamic's own AltLingo settings permission controls the settings

## 1.0.1

- Clearer wording, in every language
- Smaller download: tests and source assets are left out of the package

## 1.0.0

First release.

- One alt text per language in the image panel, with tabs, and the right text on every site in Antlers, Blade, Bard, Glide, REST and GraphQL
- Decorative images
- `altlingo:install`, `altlingo:import` (fields, Aida, Multisite Any Field), `altlingo:uninstall`
- Control panel in Spanish, English, German, French, Italian, Dutch and Portuguese

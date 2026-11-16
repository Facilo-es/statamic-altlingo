<?php

/*
 * AltLingo's translation keys. They stay in .env: this file only reads them, so they still work
 * after `php artisan config:cache` or `optimize`, when Laravel no longer loads .env.
 */
return [
    'deepl_key' => env('ALTLINGO_DEEPL_KEY'),
    'openai_key' => env('ALTLINGO_OPENAI_KEY'),
];

<?php

namespace Facilo\AltLingo\Translate;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DeepL implements Translator
{
    public function __construct(private string $key)
    {
    }

    public function translate(string $text, string $sourceLocale, string $targetLocale): string
    {
        $target = Languages::deeplTarget($targetLocale, $this->languages());
        if ($target === null) {
            throw TranslationFailed::unsupported($targetLocale);
        }

        $response = $this->http()->post('/v2/translate', [
            'text' => [$text],
            'source_lang' => Languages::deeplSource($sourceLocale),
            'target_lang' => $target,
        ]);

        if ($response->failed()) {
            throw new TranslationFailed($this->message($response));
        }

        $out = trim((string) $response->json('translations.0.text'));
        if ($out === '') {
            throw new TranslationFailed('DeepL returned an empty text.');
        }

        return $out;
    }

    /** Target codes DeepL offers, fetched once and kept for a day. */
    public function languages(): array
    {
        return Cache::remember('altlingo.deepl.languages', 86400, function () {
            $response = $this->http()->get('/v2/languages', ['type' => 'target']);
            if ($response->failed()) {
                throw new TranslationFailed($this->message($response));
            }

            return collect($response->json())->pluck('language')->filter()->values()->all();
        });
    }

    public function host(): string
    {
        return str_ends_with($this->key, ':fx') ? 'https://api-free.deepl.com' : 'https://api.deepl.com';
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->host())
            ->withHeaders(['Authorization' => 'DeepL-Auth-Key '.$this->key])
            ->acceptJson()
            ->timeout(30)
            ->retry(4, fn (int $attempt) => $attempt * 1000, fn ($e) => $e instanceof RequestException && $e->response->status() === 429, throw: false);
    }

    private function message($response): string
    {
        return 'DeepL '.$response->status().': '.(string) ($response->json('message') ?? $response->reason());
    }
}

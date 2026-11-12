<?php

namespace Facilo\AltLingo\Translate;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class OpenAiCompatible implements Translator
{
    public const SYSTEM = 'You translate image descriptions (alt text) for websites. Reply with the translation only, no quotes, at most 150 characters.';

    public function __construct(private string $key, private string $baseUrl, private string $model)
    {
    }

    public function translate(string $text, string $sourceLocale, string $targetLocale): string
    {
        $response = Http::withToken($this->key)
            ->acceptJson()
            ->timeout(60)
            ->retry(4, fn (int $attempt) => $attempt * 1000, fn ($e) => $e instanceof RequestException && $e->response->status() === 429, throw: false)
            ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                'model' => $this->model,
                'temperature' => 0.2,
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM],
                    ['role' => 'user', 'content' => 'From '.Languages::name($sourceLocale).' to '.Languages::name($targetLocale).': '.$text],
                ],
            ]);

        if ($response->failed()) {
            throw new TranslationFailed('AI '.$response->status().': '.(string) ($response->json('error.message') ?? $response->reason()));
        }

        $out = self::clean((string) $response->json('choices.0.message.content'));
        if ($out === '') {
            throw new TranslationFailed('The AI returned an empty text.');
        }

        return $out;
    }

    public static function clean(string $answer): string
    {
        return trim(trim($answer), " \t\n\"'“”‘’«»");
    }
}

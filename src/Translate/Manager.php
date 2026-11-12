<?php

namespace Facilo\AltLingo\Translate;

use Facilo\AltLingo\AltLingo;
use Facilo\AltLingo\Store\AltRecord;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\Site;

/**
 * Fills one image's text for one site from the main site's text, and marks it To review.
 */
class Manager
{
    public function __construct(private AltLingo $altlingo)
    {
    }

    public function translator(): ?Translator
    {
        if (app()->bound('altlingo.translator.fake')) {
            return app('altlingo.translator.fake');
        }

        $config = $this->altlingo->config;
        if (! $key = $config->apiKey()) {
            return null;
        }

        return $config->provider() === 'openai'
            ? new OpenAiCompatible($key, (string) $config->setting('base_url', 'https://api.openai.com/v1'), (string) $config->setting('model', 'gpt-4o-mini'))
            : new DeepL($key);
    }

    /**
     * @return array{status: 'ok'|'skipped'|'failed', text?: string, message?: string}
     */
    public function translate(Asset $asset, string $site, bool $overwrite = false, bool $save = true): array
    {
        $field = $this->altlingo->config->field();
        $main = $this->altlingo->config->mainSite();
        $record = $this->altlingo->record($asset);

        if ($site === $main || $record->decorative) {
            return ['status' => 'skipped'];
        }
        if (! $overwrite && $record->own($site, $field) !== null) {
            return ['status' => 'skipped'];
        }
        if (($source = $record->mainText($field)) === null) {
            return ['status' => 'failed', 'message' => __('altlingo::messages.no_source')];
        }
        if (! $translator = $this->translator()) {
            return ['status' => 'failed', 'message' => __('altlingo::messages.no_key')];
        }

        try {
            $text = $translator->translate($source, $this->locale($main), $this->locale($site));
        } catch (TranslationFailed $e) {
            return ['status' => $e->unsupported ? 'skipped' : 'failed', 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }

        if ($save) {
            $record->setOwn($site, $text, $field)->markReview($site)->markMachine($site);
            $this->altlingo->save($asset, $record);
        }

        return ['status' => 'ok', 'text' => AltRecord::clean($text) ?? ''];
    }

    private function locale(string $site): string
    {
        return (string) Site::get($site)?->locale();
    }
}

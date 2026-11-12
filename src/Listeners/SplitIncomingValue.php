<?php

namespace Facilo\AltLingo\Listeners;

use Facilo\AltLingo\AltLingo;
use Facilo\AltLingo\Store\AltRecord;
use Statamic\Events\AssetSaving;
use Statamic\Facades\User;

/**
 * The field sends every language at once. Before the asset is written, this keeps the
 * main text as a plain string in the original field and stores the rest beside it.
 */
class SplitIncomingValue
{
    public function __construct(private AltLingo $altlingo)
    {
    }

    public function handle(AssetSaving $event): void
    {
        $asset = $event->asset;
        $fields = $this->altlingo->config->fields();
        $incoming = collect($fields)
            ->mapWithKeys(fn ($f) => [$f => $asset->get($f)])
            ->filter(fn ($v) => is_array($v) && ($v['__altlingo'] ?? false));

        if ($incoming->isEmpty()) {
            return;
        }

        $stored = $this->storedRecord($asset, $fields);
        $record = clone $stored;
        $mainSite = $this->altlingo->config->mainSite();
        $edited = [];

        foreach ($incoming as $field => $value) {
            $record->setMain($value['main'] ?? null, $field);
            if ($record->mainText($field) !== $stored->mainText($field)) {
                $edited[$mainSite][] = $field;
            }

            foreach ((array) ($value['sites'] ?? []) as $site => $text) {
                $site = (string) $site;
                if ($site === $mainSite || ! in_array($site, $this->altlingo->config->sites(), true)) {
                    continue;
                }
                $record->setOwn($site, is_string($text) ? $text : null, $field);
                if ($record->own($site, $field) !== $stored->own($site, $field)) {
                    $edited[$site][] = $field;
                }
            }

            if ($field === $this->altlingo->config->field()) {
                $decorative = (bool) ($value['decorative'] ?? false);
                if ($decorative !== $stored->decorative) {
                    $edited[$mainSite][] = 'decorative';
                }
                $record->decorative = $decorative;

                // The form can only clear review flags (Mark reviewed), never add them.
                $keep = array_map('strval', (array) ($value['review'] ?? []));
                foreach ($stored->review as $site) {
                    $record->markReview($site, in_array($site, $keep, true));
                }
            }
        }

        $this->authorize($edited);

        $altField = $this->altlingo->config->field();
        foreach ($edited as $site => $editedFields) {
            if ($site !== $mainSite && in_array($altField, $editedFields, true)) {
                // A person wrote this text, so it is theirs now.
                $record->markReview($site, false)->markMachine($site, false);
            }
        }

        if (in_array($altField, $edited[$mainSite] ?? [], true)) {
            // The source changed, so machine translations need another look.
            foreach ($record->machine as $site) {
                if ($record->own($site, $altField) !== null) {
                    $record->markReview($site);
                }
            }
        }

        $this->altlingo->store->apply($asset, $record, $fields);
    }

    private function storedRecord($asset, array $fields): AltRecord
    {
        $record = $this->altlingo->store->read($asset, $fields);
        $original = (array) $asset->getOriginal('data');

        foreach ($fields as $field) {
            $value = $original[$field] ?? null;
            $record->setMain(is_array($value) ? ($value['main'] ?? null) : $value, $field);
        }

        return $record;
    }

    private function authorize(array $edited): void
    {
        if (! $this->altlingo->config->isMultisite() || ! ($user = User::current())) {
            return;
        }

        foreach (array_keys($edited) as $site) {
            if (! $user->can("access {$site} site")) {
                abort(403, __('altlingo::messages.no_site_access'));
            }
        }
    }
}

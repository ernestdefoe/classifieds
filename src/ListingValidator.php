<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds;

use Flarum\Foundation\AbstractValidator;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Validation\Factory;
use Illuminate\Validation\Validator;

class ListingValidator extends AbstractValidator
{
    public function __construct(
        Factory $validator,
        TranslatorInterface $translator,
        protected SettingsRepositoryInterface $settings,
    ) {
        parent::__construct($validator, $translator);
    }

    protected array $rules = [
        'label' => ['nullable', 'string', 'max:16'],
        'status' => ['nullable', 'string', 'in:active,sold,completed'],
        'price' => ['nullable', 'numeric', 'min:0'],
        'price_max' => ['nullable', 'numeric', 'min:0', 'gte:price'],
        'currency' => ['nullable', 'string', 'max:8'],
        'location' => ['nullable', 'string', 'max:255'],
    ];

    protected function getRules(): array
    {
        $rules = $this->rules;

        $allowedLabels = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $this->settings->get('flarum-classifieds.allowed_labels', 'iso,wtb,wts,trade'))
        )));

        if (! empty($allowedLabels)) {
            $rules['label'][] = 'in:'.implode(',', $allowedLabels);
        }

        if ($this->settings->get('flarum-classifieds.require_label')) {
            $rules['label'] = $this->makeRequired($rules['label']);
        }

        if ($this->settings->get('flarum-classifieds.require_price')) {
            $rules['price'] = $this->makeRequired($rules['price']);
        }

        if ($this->settings->get('flarum-classifieds.require_location')) {
            $rules['location'] = $this->makeRequired($rules['location']);
        }

        if (! $this->settings->get('flarum-classifieds.allow_price_range')) {
            $rules['price_max'] = ['nullable'];
        }

        return $rules;
    }

    protected function makeRequired(array $rules): array
    {
        $rules = array_values(array_filter($rules, fn ($r) => $r !== 'nullable'));

        return array_merge(['required'], $rules);
    }
}

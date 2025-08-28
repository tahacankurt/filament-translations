<?php

namespace App\Traits;

use App\Models\SiteSetting;
use Filament\Actions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

trait HasAiTranslationAction
{
    /**
     * Get the AI translation action
     */
    protected function getAiTranslationAction(): Actions\Action
    {
        return Actions\Action::make('aiTranslate')
            ->label($this->getTranslationActionLabel())
            ->disabled(fn() => $this->isAiTranslationDisabled())
            ->tooltip(fn() => $this->getAiTranslationTooltip())
            ->form($this->getTranslationFormSchema())
            ->action(fn(array $data) => $this->handleAiTranslation($data))
            ->requiresConfirmation()
            ->modalDescription($this->getTranslationModalDescription());
    }

    /**
     * Check if AI translation is disabled
     */
    protected function isAiTranslationDisabled(): bool
    {
        $siteSettings = SiteSetting::select(
            'ai_api_provider',
            'ai_api_key',
            'ai_default_model',
            'ai_default_prompt_for_translations'
        )->first();

        return empty($siteSettings->ai_api_provider) ||
            empty($siteSettings->ai_api_key) ||
            empty($siteSettings->ai_default_model) ||
            empty($siteSettings->ai_default_prompt_for_translations);
    }

    /**
     * Get translation action tooltip
     */
    protected function getAiTranslationTooltip(): ?string
    {
        if ($this->isAiTranslationDisabled()) {
            return 'Lütfen Yapay Zeka ayarlarını Site Ayarları sayfasından tamamlayınız.';
        }

        return null;
    }

    /**
     * Get translation form schema
     */
    protected function getTranslationFormSchema(): array
    {
        return [
            Select::make('source_locale')
                ->label('Kaynak Dil')
                ->options($this->getLocaleOptions())
                ->default(config('app.default_locale'))
                ->required(),

            Checkbox::make('target_all')
                ->label('Tüm Dilleri Hedefle')
                ->reactive()
                ->afterStateUpdated(function (callable $set, $state) {
                    if ($state) {
                        $set('target_locale', null);
                    }
                })
                ->live(),

            Select::make('target_locale')
                ->label('Hedef Dil')
                ->hidden(fn($get) => $get('target_all'))
                ->options($this->getLocaleOptions())
                ->default(config('app.fallback_locale'))
                ->required(),
        ];
    }

    /**
     * Get locale options for select components
     */
    protected function getLocaleOptions(): array
    {
        return array_combine(
            config('app.supported_locales'),
            array_map(fn($locale) => strtoupper($locale), config('app.supported_locales'))
        );
    }

    /**
     * Handle AI translation process
     */
    protected function handleAiTranslation(array $data)
    {
        $sourceLocale = $data['source_locale'];

        // Determine target locales
        if ($data['target_all']) {
            $targetLocales = array_filter(
                config('app.supported_locales'),
                fn($locale) => $locale != $sourceLocale
            );
        } else {
            $targetLocales = [$data['target_locale']];
        }

        // Validate locales
        if (in_array($sourceLocale, $targetLocales)) {
            return false;
        }

        // Get translatable attributes
        $translatableAttributes = $this->getTranslatableAttributes();


        // Remove slug field if exists
        $translatableAttributes = array_filter(
            $translatableAttributes,
            fn($attribute) => $attribute !== 'slug'
        );

        // Filter empty fields in source language
        $translatableAttributes = array_filter(
            $translatableAttributes,
            fn($attribute) => !empty($this->record->getTranslation($attribute, $sourceLocale, false))
        );

        if (empty($translatableAttributes)) {
            Notification::make()
                ->title('Çevrilecek içerik bulunamadı.')
                ->warning()
                ->send();
            return false;
        }

        // Get source data
        $record = $this->getRecord();
        $sourceData = Arr::only($record->toArray(), $translatableAttributes);

        // Perform translation

        try {
            $translations = $this->performAiTranslation(
                $sourceData,
                $translatableAttributes,
                $targetLocales,
                $sourceLocale
            );

            // Save translations
            $this->saveTranslations($translations, $translatableAttributes, $targetLocales);

            // Send success notification
            Notification::make()
                ->title('Çeviri işlemi başarıyla tamamlandı.')
                ->success()
                ->send();

            return redirect(request()->header('Referer'));

        } catch (\Exception $e) {
            Notification::make()
                ->title('Çeviri işlemi sırasında hata oluştu.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return false;
        }
    }

    /**
     * Perform AI translation using Prism
     */
    protected function performAiTranslation(array $sourceData, array $translatableAttributes, array $targetLocales,$sourceLocale): array
    {
        // Build schema
        $properties = [];
        foreach ($targetLocales as $targetLocale) {
            $subProperties = [];
            foreach ($translatableAttributes as $translatableAttribute) {
                $subProperties[] = new StringSchema(
                    name: $translatableAttribute,
                    description: 'Translation for the ' . $translatableAttribute
                );
            }

            $properties[] = new ObjectSchema(
                name: $targetLocale,
                description: 'Translation for the ' . $targetLocale . ' locale',
                properties: $subProperties,
            );
        }

        $schema = new ObjectSchema(
            name: 'translations',
            description: 'Structured translation data',
            properties: $properties,
            requiredFields: []
        );

        // Get AI settings
        $siteSettings = SiteSetting::select(
            'ai_api_provider',
            'ai_api_key',
            'ai_default_model',
            'ai_default_prompt_for_translations'
        )->first();

        $aiApiProviderEnum = $siteSettings->ai_api_provider
            ? Provider::tryFrom($siteSettings->ai_api_provider)
            : null;


        // Perform translation
        $response = Prism::structured()
            ->using($aiApiProviderEnum, $siteSettings->ai_default_model, [
                'api_key' => $siteSettings->ai_api_key,
            ])
            ->withSystemPrompt($siteSettings->ai_default_prompt_for_translations)
            ->withSchema($schema)
            ->withProviderOptions(['use_tool_calling' => true])
            ->withPrompt(
                'Source Language: ' . $sourceLocale .
                ' Target Languages: ' . implode(',', $targetLocales) .
                ' | Translate the following text: ' . json_encode($sourceData)
            )
            ->asStructured();

        return $response->structured;
    }

    /**
     * Save translations to the model
     */
    protected function saveTranslations(array $translations, array $translatableAttributes, array $targetLocales): void
    {
        foreach ($targetLocales as $targetLocale) {
            foreach ($translatableAttributes as $translatableAttribute) {
                if (isset($translations[$targetLocale][$translatableAttribute]) &&
                    !empty($translations[$targetLocale][$translatableAttribute])) {

                    $this->record->setTranslation(
                        $translatableAttribute,
                        $targetLocale,
                        $translations[$targetLocale][$translatableAttribute]
                    );
                }
            }
        }

        $this->record->save();
    }

    /**
     * Get translatable attributes for the current resource
     * Override this method in your resource if needed
     */
    protected function getTranslatableAttributes(): array
    {
        if (method_exists(static::getResource(), 'getTranslatableAttributes')) {
            return static::getResource()::getTranslatableAttributes();
        }

        // Fallback: try to get from model
        $model = $this->getRecord();
        if (property_exists($model, 'translatable')) {
            return $model->translatable;
        }

        throw new \Exception('Translatable attributes could not be determined. Please override getTranslatableAttributes() method.');
    }

    /**
     * Get translation action label
     * Override this method to customize the label
     */
    protected function getTranslationActionLabel(): string
    {
        return 'Yapay Zeka ile Çevir (BETA)';
    }

    /**
     * Get translation modal description
     * Override this method to customize the description
     */
    protected function getTranslationModalDescription(): string
    {
        return 'Seçilen kaynak dilden hedef dile çeviri yapılacaktır. Mevcut içerik üzerine yazılacaktır.';
    }
}

<?php

namespace TomatoPHP\FilamentTranslations\Services;

use App\Models\SiteSetting;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

class AiTranlsationService
{


    public function handleAiTranslation($records, $supportedLocales, $sourceLocale, $targetLocale, $targetAll)
    {

        // Determine target locales
        if ($targetAll) {
            $targetLocales = array_filter($supportedLocales, fn($locale) => $locale !== $sourceLocale);
        } else {
            $targetLocales = [$targetLocale];
        }

        // Validate locales
        if (in_array($sourceLocale, $targetLocales)) {
            return false;
        }


        // Perform translation
        try {
            $translations = $this->performAiTranslation(
                $records,
                $sourceLocale,
                $targetLocales,
                $sourceLocale
            );

        } catch (\Exception $e) {
            Notification::make()
                ->title('Çeviri işlemi sırasında hata oluştu.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return false;
        }
    }


    protected function performAiTranslation($records, $sourceLocale, $targetLocales)
    {
        $translations = $records;

        foreach ($translations as $index => $translation) {
            $translationArr[$index] = $translation->text;
            $langProperties = [];

            foreach ($targetLocales as $targetLocale) {
                $langProperties[$targetLocale] = new StringSchema(
                    name: $targetLocale,
                    description: 'Translation for the ' . $targetLocale . ' language'
                );
            }


            $properties[(string)$index] = new ObjectSchema(
                name: (string)$index,
                description: 'Translation for the translation key:' . $index,
                properties: $langProperties,
            );
        }

        $schema = new ObjectSchema(
            name: 'translations',
            description: 'Structured translation data of the texts',
            properties: $properties,
            requiredFields: []
        );


        $aiApiProvider = config('filament-translations.ai_api_provider', 'openai');
        $aiApiKey = config('filament-translations.ai_api_key');
        $aiApiDefaultModel = config('filament-translations.ai_default_model', 'gpt-3.5-turbo');
        $aiApiDefaultSystemPromptForTranslations = config('filament-translations.ai_default_prompt_for_translations', 'You are a helpful assistant that translates texts from one language to another. You always respond in a JSON format that strictly adheres to the provided schema. Do not include any additional text or explanations outside of the JSON structure.');

        $aiApiProviderEnum = $aiApiProvider
            ? Provider::tryFrom($aiApiProvider)
            : null;


        //Change array to string for ai
        $productsJson = json_encode($translationArr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $response = Prism::structured()
            ->using($aiApiProviderEnum, $aiApiDefaultModel, [
                'api_key' => $aiApiKey,
            ])
            ->withSystemPrompt($aiApiDefaultSystemPromptForTranslations)
            ->withSchema($schema)
            ->withPrompt(
                "Source Language: {$sourceLocale}\n" .
                "Target Languages: " . implode(',', $targetLocales) . "\n" .
                "Translate the following records into the schema format strictly:\n\n{$productsJson}"
            )
            ->asStructured();

        $this->saveTranslations($records, $response->structured,$targetLocales);
    }

    protected function saveTranslations($records,$aiTranslations, $targetLocales)
    {
        // Save translations to records
        foreach ($records as $index=>$record) {
            // Pair ai translations with records
            foreach ($targetLocales as $targetLocale) {
                $record->setTranslation($targetLocale, $aiTranslations[$index][$targetLocale] ?? '');
                $record->save();
            }

        }
    }
}

<?php

namespace TomatoPHP\FilamentTranslations\Filament\Resources\TranslationResource\Actions\Components;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Tables;
use Filament\Actions;
use TomatoPHP\FilamentTranslations\Services\AiTranlsationService;

class AiTranslateAction extends Action
{
    public static function make(): Actions\Action
    {
        //Make custom action
        return Actions\Action::make('aiTranslate')
            ->label(self::getTranslationActionLabel())
            ->form(self::getTranslationFormSchema())
            ->icon('heroicon-o-cube-transparent')
            ->action(function ($record, array $data) {
                $supportedLocales = array_keys(config('filament-translations.locals', []));
                $sourceLocale = $data['source_locale'];
                $targetLocale = $data['target_locale'] ?? null;
                $targetAll = $data['target_all'] ?? false;
                $records = $record instanceof Tables\Actions\Action ? $record->getTable()->getSelectedRecords() : [$record];
                $aiTranslationService = new AiTranlsationService();
                $aiTranslationService->handleAiTranslation($records, $supportedLocales, $sourceLocale, $targetLocale, $targetAll);
                return redirect(request()->header('Referer'));

            })
            ->requiresConfirmation()
            ->modalDescription(self::getTranslationModalDescription());
    }

    protected static function getTranslationFormSchema(): array
    {
        //Set options for locales
        $langOptions = [];
        foreach (config('filament-translations.locals', []) as $key => $value) {
            $langOptions[$key] = $value['label'] ?? $key;
        }

        return [
            Select::make('source_locale')
                ->label(__('filament-translations::translation.source_locale'))
                ->options($langOptions)
                ->default(config('filament-translations.default_locale'))
                ->required(),

            Checkbox::make('target_all')
                ->label(__('filament-translations::translation.target_all_locales'))
                ->reactive()
                ->afterStateUpdated(function (callable $set, $state) {
                    if ($state) {
                        $set('target_locale', null);
                    }
                })
                ->live(),

            Select::make('target_locale')
                ->label(__('filament-translations::translation.target_locale'))
                ->hidden(fn($get) => $get('target_all'))
                ->options($langOptions)
                ->default(config('app.fallback_locale'))
                ->required(),
        ];
    }

    protected static function getTranslationActionLabel(): string
    {
        return __('filament-translations::translation.translate_with_ai');
    }

    protected static function getTranslationModalDescription(): string
    {
        return __('filament-translations::translation.translate_with_ai_description');
    }
}

<?php

namespace TomatoPHP\FilamentTranslations\Filament\Resources\TranslationResource\Table\Columns;

use Filament\Tables;

class Key extends Column
{
    public static function make(): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make('key')
            ->label(trans('filament-translations::translation.key'))
            ->searchable()
            ->sortable();
    }
}

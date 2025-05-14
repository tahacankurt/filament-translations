<?php

namespace TomatoPHP\FilamentTranslations\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use TomatoPHP\FilamentTranslations\Models\Translation;

class SaveScan
{
    private $paths;

    public function __construct()
    {
        $this->paths = config('filament-translations.paths');
    }

    public function save()
    {
        $scanner = app(Scan::class);
        collect($this->paths)->filter(function ($path) {
            return File::exists($path);
        })->each(function ($path) use ($scanner) {
            $scanner->addScannedPath($path);
        });

        [$trans, $__] = $scanner->getAllViewFilesWithTranslations();

        /** @var Collection $trans */
        /** @var Collection $__ */
        DB::transaction(function () use ($trans, $__) {
            Translation::query()
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => Carbon::now(),
                ]);

            $trans->each(function ($trans) {
                [$group, $key] = explode('.', $trans, 2);
                $namespaceAndGroup = explode('::', $group, 2);
                if (count($namespaceAndGroup) === 1) {
                    $namespace = '*';
                    $group = $namespaceAndGroup[0];
                } else {
                    [$namespace, $group] = $namespaceAndGroup;
                }
                $this->createOrUpdate($namespace, $group, $key, $trans);
            });

            $__->each(function ($default) {
                $this->createOrUpdate('*', '*', $default, $default);
            });
        });
    }

    protected function createOrUpdate($namespace, $group, $key, $mainKey = null): void
    {
        /** @var Translation $translation */
        $translation = Translation::withTrashed()
            ->where('namespace', $namespace)
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        $defaultLocale = config('app.locale');

        if ($translation) {
            if (! $this->isCurrentTransForTranslationArray($translation, $defaultLocale)) {
                $translation->restore();
            }
        } else {
            $locals = config('filament-translations.locals');
            $text = [];
            foreach ($locals as $locale => $lang) {
                $translation = Lang::get(key: $key, locale: $locale, fallback: str($key)->replace('.', ' ')->replace('_', ' ')->title()->toString());
                $text[$locale] = ! is_array($translation) ? $translation : '';
            }
            $translation = Translation::query()->create([
                'namespace' => $namespace,
                'group' => $group,
                'key' => $key,
                'text' => $text,
            ]);

            if (! $this->isCurrentTransForTranslationArray($translation, $defaultLocale)) {
                $translation->save();
            }
        }
    }

    private function isCurrentTransForTranslationArray(Translation $translation, $locale): bool
    {
        if ($translation->group === '*') {
            return is_array(__($translation->key, [], $locale));
        }

        if ($translation->namespace === '*') {
            return is_array(trans($translation->group . '.' . $translation->key, [], $locale));
        }

        return is_array(trans($translation->namespace . '::' . $translation->group . '.' . $translation->key, [], $locale));
    }
}

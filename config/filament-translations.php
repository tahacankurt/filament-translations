<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | add path that will be show to the scaner to catch lanuages tags
    |
    */
    'paths' => [
        app_path(),
        resource_path('views'),
        base_path('vendor'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded paths
    |--------------------------------------------------------------------------
    |
    | Put here any folder that you want to exclude that is inside of paths
    |
    */

    'excludedPaths' => [],

    /*
     * |--------------------------------------------------------------------------
     * | Default Locale
     * |--------------------------------------------------------------------------
     */
    'default_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Locals
    |--------------------------------------------------------------------------
    |
    | add the locals that will be show on the languages selector
    |
    */
    'locals' => [
        'en' => [
            'label' => 'English',
            'flag' => 'us',
        ],
        'ar' => [
            'label' => 'Arabic',
            'flag' => 'eg',
        ],
        'fr' => [
            'label' => 'French',
            'flag' => 'fr',
        ],
        'pt_BR' => [
            'label' => 'Português (Brasil)',
            'flag' => 'br',
        ],
        'my' => [
            'label' => 'Burmese',
            'flag' => 'mm',
        ],
        'id' => [
            'label' => 'Indonesia',
            'flag' => 'id',
        ],
        'tr' => [
            'label' => 'Turkish',
            'flag' => 'tr',
        ],
        'jp' => [
            'label' => 'Japanese',
            'flag' => 'jp',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Modal
    |--------------------------------------------------------------------------
    |
    | use simple modal resource for the translation resource
    |
    */
    'modal' => true,

    /*
    |--------------------------------------------------------------------------
    |
    | Add groups that should be excluded in translation import from files to database
    |
    */
    'exclude_groups' => [],

    /*
     |--------------------------------------------------------------------------
     |
     | Register the navigation for the translations.
     |
     */
    'register_navigation' => true,

    /*
     |--------------------------------------------------------------------------
     |
     | Use Queue to scan the translations.
     |
     */
    'use_queue_on_scan' => true,

    /*
     |--------------------------------------------------------------------------
     |
     | Custom import command.
     |
     */
    'path_to_custom_import_command' => null,

    /*
     |--------------------------------------------------------------------------
     |
     | Show buttons in Translation resource.
     |
     */
    'scan_enabled' => true,
    'export_enabled' => true,
    'import_enabled' => true,

    /*
     |--------------------------------------------------------------------------
     |
     | Translation resource.
     |
     */
    'translation_resource' => \TomatoPHP\FilamentTranslations\Filament\Resources\TranslationResource::class,

    /*
     |--------------------------------------------------------------------------
     |
     | Custom Excel export.
     |
     */
    'path_to_custom_excel_export' => null,

    /*
     |--------------------------------------------------------------------------
     |
     | Custom Excel import.
     |
     */



    'path_to_custom_excel_import' => null,

    /*
     * |--------------------------------------------------------------------------
     * | AI Translation Settings
     * |--------------------------------------------------------------------------
     */
    'ai_api_key'=> env('AI_API_KEY'),
    'ai_api_provider'=> env('AI_API_PROVIDER','openai'),
    'ai_default_model'=> env('AI_DEFAULT_MODEL','gpt-3.5-turbo'),
    'ai_default_prompt_for_translations'=> env('AI_DEFAULT_PROMPT_FOR_TRANSLATIONS','You are a professional translator. You will be provided with text in a source language, and your task is to translate it accurately into one or more target languages while preserving the original meaning and context. Your translations should be clear, natural, and culturally appropriate for native speakers of the target languages. Avoid literal translations that may not convey the intended message effectively. If you encounter any text that is ambiguous or lacks sufficient context, please indicate this in your response rather than making assumptions.'),
];

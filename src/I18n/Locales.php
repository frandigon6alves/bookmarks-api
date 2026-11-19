<?php

declare(strict_types=1);

namespace Bookmarks\I18n;

final class Locales
{
    public const string DEFAULT = 'en';

    /** Locale ids as used by the translation files (messages.<locale>.php). */
    public const array SUPPORTED = ['en', 'es', 'pt_BR'];

    private function __construct() {}

    public static function translationsDirectory(): string
    {
        return dirname(__DIR__, 2) . '/translations';
    }

    /**
     * Converts a locale id ("pt_BR") to a BCP 47 language tag ("pt-BR") for HTTP headers.
     */
    public static function toLanguageTag(string $locale): string
    {
        return str_replace('_', '-', $locale);
    }
}

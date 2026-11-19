<?php

declare(strict_types=1);

namespace Bookmarks\I18n;

use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

final class TranslatorFactory
{
    private function __construct() {}

    /**
     * @param list<string> $locales
     */
    public static function create(
        string $directory = '',
        array $locales = Locales::SUPPORTED,
        string $default = Locales::DEFAULT,
    ): Translator {
        $directory = $directory === '' ? Locales::translationsDirectory() : $directory;

        $translator = new Translator($default);
        $translator->setFallbackLocales([$default]);
        $translator->addLoader('php', new PhpFileLoader());
        foreach ($locales as $locale) {
            $translator->addResource('php', sprintf('%s/messages.%s.php', $directory, $locale), $locale);
        }

        return $translator;
    }
}

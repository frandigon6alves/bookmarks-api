<?php

declare(strict_types=1);

namespace Bookmarks\Tests\Unit\I18n;

use Bookmarks\I18n\Locales;
use Bookmarks\I18n\TranslatorFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the translation files so a new message cannot ship in one language only.
 */
final class TranslationCatalogueTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function nonDefaultLocales(): iterable
    {
        foreach (Locales::SUPPORTED as $locale) {
            if ($locale !== Locales::DEFAULT) {
                yield $locale => [$locale];
            }
        }
    }

    public function testEverySupportedLocaleHasACatalogue(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            self::assertFileExists(self::path($locale));
        }
    }

    #[DataProvider('nonDefaultLocales')]
    public function testAllLocalesDefineTheSameKeys(string $locale): void
    {
        $reference = array_keys(self::messages(Locales::DEFAULT));
        $keys = array_keys(self::messages($locale));
        sort($reference);
        sort($keys);

        self::assertSame($reference, $keys, sprintf(
            'Locale "%s" is out of sync. Missing: [%s]. Extra: [%s].',
            $locale,
            implode(', ', array_diff($reference, $keys)),
            implode(', ', array_diff($keys, $reference)),
        ));
    }

    #[DataProvider('nonDefaultLocales')]
    public function testTranslationsKeepTheSamePlaceholders(string $locale): void
    {
        $reference = self::messages(Locales::DEFAULT);

        foreach (self::messages($locale) as $key => $message) {
            self::assertSame(
                self::placeholders($reference[$key] ?? ''),
                self::placeholders($message),
                sprintf('Placeholders differ for "%s" in locale "%s".', $key, $locale),
            );
        }
    }

    public function testNoMessageIsEmptyOrUntranslated(): void
    {
        $english = self::messages(Locales::DEFAULT);

        foreach (Locales::SUPPORTED as $locale) {
            foreach (self::messages($locale) as $key => $message) {
                self::assertNotSame('', trim($message), sprintf('"%s" is empty in "%s".', $key, $locale));
                if ($locale !== Locales::DEFAULT) {
                    self::assertNotSame($english[$key], $message, sprintf('"%s" in "%s" is still in English.', $key, $locale));
                }
            }
        }
    }

    public function testTheTranslatorResolvesEachLocale(): void
    {
        $translator = TranslatorFactory::create();

        self::assertSame('This field is required.', $translator->trans('validation.required', [], null, 'en'));
        self::assertSame('Este campo es obligatorio.', $translator->trans('validation.required', [], null, 'es'));
        self::assertSame('Este campo é obrigatório.', $translator->trans('validation.required', [], null, 'pt_BR'));
        self::assertSame('There is no bookmark with id 7.', $translator->trans('problem.bookmark_not_found.detail', ['%id%' => 7], null, 'en'));
    }

    /**
     * @return array<string, string>
     */
    private static function messages(string $locale): array
    {
        $messages = require self::path($locale);
        self::assertIsArray($messages);

        $typed = [];
        foreach ($messages as $key => $message) {
            self::assertIsString($key);
            self::assertIsString($message, sprintf('"%s" in "%s" must be a string.', $key, $locale));
            $typed[$key] = $message;
        }

        return $typed;
    }

    /**
     * @return list<string>
     */
    private static function placeholders(string $message): array
    {
        preg_match_all('/%[a-z_]+%/', $message, $matches);
        $placeholders = array_values(array_unique($matches[0]));
        sort($placeholders);

        return $placeholders;
    }

    private static function path(string $locale): string
    {
        return sprintf('%s/messages.%s.php', Locales::translationsDirectory(), $locale);
    }
}

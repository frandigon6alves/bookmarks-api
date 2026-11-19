<?php

declare(strict_types=1);

namespace Bookmarks\Tests\Unit\I18n;

use Bookmarks\I18n\LocaleNegotiator;
use Bookmarks\I18n\Locales;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocaleNegotiatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function headers(): iterable
    {
        yield 'missing header' => ['', 'en'];
        yield 'english' => ['en-US,en;q=0.9', 'en'];
        yield 'spanish' => ['es', 'es'];
        yield 'regional spanish falls back to es' => ['es-MX', 'es'];
        yield 'brazilian portuguese' => ['pt-BR', 'pt_BR'];
        yield 'case and underscore insensitive' => ['PT_br', 'pt_BR'];
        yield 'plain portuguese maps to pt_BR' => ['pt', 'pt_BR'];
        yield 'european portuguese maps to pt_BR' => ['pt-PT', 'pt_BR'];
        yield 'quality wins over order' => ['en;q=0.5, es;q=0.9', 'es'];
        yield 'header order breaks ties' => ['pt-BR, es', 'pt_BR'];
        yield 'unsupported languages are skipped' => ['de-DE, fr;q=0.9, es;q=0.1', 'es'];
        yield 'q=0 means not acceptable' => ['es;q=0, pt-BR;q=0.2', 'pt_BR'];
        yield 'wildcard gives the default' => ['*', 'en'];
        yield 'only unsupported gives the default' => ['de, fr', 'en'];
        yield 'garbage gives the default' => [';;;,,=q', 'en'];
    }

    #[DataProvider('headers')]
    public function testItNegotiates(string $header, string $expected): void
    {
        self::assertSame($expected, (new LocaleNegotiator())->negotiate($header));
    }

    public function testLanguageTagsUseHyphens(): void
    {
        self::assertSame('pt-BR', Locales::toLanguageTag('pt_BR'));
        self::assertSame('es', Locales::toLanguageTag('es'));
    }
}

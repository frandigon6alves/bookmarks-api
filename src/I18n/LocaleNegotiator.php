<?php

declare(strict_types=1);

namespace Bookmarks\I18n;

/**
 * Picks the best supported locale for an Accept-Language header.
 *
 * Ranges are tried by descending quality (ties keep header order). Each range
 * first matches a supported locale exactly ("pt-BR"), then by primary language
 * ("pt", "pt-PT" or "es-MX" fall back to "pt_BR" / "es"). "*" and anything
 * unknown fall back to the default locale.
 */
final readonly class LocaleNegotiator
{
    /**
     * @param non-empty-list<string> $supported
     */
    public function __construct(
        private array $supported = Locales::SUPPORTED,
        private string $default = Locales::DEFAULT,
    ) {}

    public function negotiate(string $acceptLanguage): string
    {
        foreach ($this->ranges($acceptLanguage) as $range) {
            if ($range === '*') {
                return $this->default;
            }
            foreach ($this->supported as $locale) {
                if (self::normalize($locale) === $range) {
                    return $locale;
                }
            }
            $primary = explode('-', $range)[0];
            foreach ($this->supported as $locale) {
                if (explode('-', self::normalize($locale))[0] === $primary) {
                    return $locale;
                }
            }
        }

        return $this->default;
    }

    /**
     * @return list<string> normalized language ranges, best first, q=0 removed
     */
    private function ranges(string $header): array
    {
        $ranges = [];
        foreach (explode(',', $header) as $position => $part) {
            $pieces = array_map(trim(...), explode(';', $part));
            $range = self::normalize($pieces[0]);
            if ($range === '' || preg_match('/^(\*|[a-z]{1,8}(-[a-z0-9]{1,8})*)$/', $range) !== 1) {
                continue;
            }

            $quality = 1.0;
            foreach (array_slice($pieces, 1) as $parameter) {
                if (preg_match('/^q\s*=\s*([01](?:\.[0-9]{0,3})?)$/i', $parameter, $matches) === 1) {
                    $quality = (float) $matches[1];
                }
            }
            if ($quality <= 0.0) {
                continue;
            }

            $ranges[] = ['range' => $range, 'quality' => $quality, 'position' => $position];
        }

        usort(
            $ranges,
            static fn(array $a, array $b): int => [$b['quality'], $a['position']] <=> [$a['quality'], $b['position']],
        );

        return array_column($ranges, 'range');
    }

    private static function normalize(string $tag): string
    {
        return strtolower(str_replace('_', '-', trim($tag)));
    }
}

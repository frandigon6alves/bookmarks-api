<?php

declare(strict_types=1);

namespace Bookmarks\Domain;

/**
 * Rules for tag names: lowercase letters, digits and single hyphens ("php", "web-dev").
 */
final class TagName
{
    public const int MAX_LENGTH = 30;

    private const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private function __construct() {}

    public static function normalize(string $tag): string
    {
        return mb_strtolower(trim($tag));
    }

    public static function isValid(string $tag): bool
    {
        return strlen($tag) <= self::MAX_LENGTH && preg_match(self::PATTERN, $tag) === 1;
    }
}

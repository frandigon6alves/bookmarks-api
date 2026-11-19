<?php

declare(strict_types=1);

namespace Bookmarks\Application;

/**
 * Opaque keyset cursor. Clients must treat it as a black box; internally it
 * carries the id of the last bookmark on the previous page.
 */
final class Cursor
{
    private const string PREFIX = 'before:';

    private function __construct() {}

    public static function encode(int $beforeId): string
    {
        return rtrim(strtr(base64_encode(self::PREFIX . $beforeId), '+/', '-_'), '=');
    }

    /**
     * @return positive-int|null null when the cursor was not produced by this API
     */
    public static function decode(string $cursor): ?int
    {
        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($raw === false || preg_match('/^' . self::PREFIX . '([1-9][0-9]{0,17})$/', $raw, $matches) !== 1) {
            return null;
        }

        $id = (int) $matches[1];

        return $id > 0 ? $id : null;
    }
}

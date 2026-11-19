<?php

declare(strict_types=1);

namespace Bookmarks\Tests\Unit\Application;

use Bookmarks\Application\Cursor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CursorTest extends TestCase
{
    public function testItRoundTrips(): void
    {
        foreach ([1, 7, 1234, 999_999_999_999] as $id) {
            $cursor = Cursor::encode($id);

            self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $cursor, 'Cursors are URL safe.');
            self::assertSame($id, Cursor::decode($cursor));
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCursors(): iterable
    {
        yield 'empty' => [''];
        yield 'not base64' => ['%%%'];
        yield 'foreign payload' => [rtrim(base64_encode('{"id":1}'), '=')];
        yield 'zero id' => [rtrim(base64_encode('before:0'), '=')];
        yield 'negative id' => [rtrim(base64_encode('before:-3'), '=')];
    }

    #[DataProvider('invalidCursors')]
    public function testItRejectsCursorsItDidNotIssue(string $cursor): void
    {
        self::assertNull(Cursor::decode($cursor));
    }
}

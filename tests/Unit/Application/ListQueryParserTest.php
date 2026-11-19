<?php

declare(strict_types=1);

namespace Bookmarks\Tests\Unit\Application;

use Bookmarks\Application\Cursor;
use Bookmarks\Application\Validation\InputSource;
use Bookmarks\Application\Validation\ListQueryParser;
use Bookmarks\Application\Validation\ValidationFailed;
use Bookmarks\Application\Validation\Violation;
use Bookmarks\Domain\BookmarkCriteria;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListQueryParserTest extends TestCase
{
    public function testDefaults(): void
    {
        self::assertEquals(new BookmarkCriteria(ListQueryParser::DEFAULT_LIMIT), (new ListQueryParser())->parse([]));
    }

    public function testItParsesAllParameters(): void
    {
        $criteria = (new ListQueryParser())->parse([
            'limit' => '5',
            'tag' => ' PHP ',
            'q' => '  slim framework ',
            'cursor' => Cursor::encode(42),
            'ignored' => 'yes',
        ]);

        self::assertEquals(new BookmarkCriteria(5, 'php', 'slim framework', 42), $criteria);
    }

    public function testBlankSearchIsIgnored(): void
    {
        self::assertNull((new ListQueryParser())->parse(['q' => '   '])->search);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidQueries(): iterable
    {
        yield 'limit zero' => [['limit' => '0'], 'limit', 'validation.limit.range'];
        yield 'limit too high' => [['limit' => '101'], 'limit', 'validation.limit.range'];
        yield 'limit not a number' => [['limit' => 'ten'], 'limit', 'validation.limit.range'];
        yield 'limit negative' => [['limit' => '-1'], 'limit', 'validation.limit.range'];
        yield 'limit as array' => [['limit' => ['1']], 'limit', 'validation.limit.range'];
        yield 'tag with spaces' => [['tag' => 'two words'], 'tag', 'validation.tag.invalid'];
        yield 'empty tag' => [['tag' => ''], 'tag', 'validation.tag.invalid'];
        yield 'search as array' => [['q' => ['a']], 'q', 'validation.type.string'];
        yield 'search too long' => [['q' => str_repeat('a', ListQueryParser::SEARCH_MAX_LENGTH + 1)], 'q', 'validation.too_long'];
        yield 'garbage cursor' => [['cursor' => 'not-a-cursor'], 'cursor', 'validation.cursor.invalid'];
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('invalidQueries')]
    public function testItRejectsInvalidParameters(array $query, string $field, string $key): void
    {
        try {
            (new ListQueryParser())->parse($query);
            self::fail('Expected a validation failure.');
        } catch (ValidationFailed $exception) {
            self::assertSame(InputSource::Query, $exception->source);
            self::assertCount(1, $exception->violations);
            self::assertSame($field, $exception->violations[0]->field);
            self::assertSame($key, $exception->violations[0]->messageKey);
        }
    }

    public function testItCollectsEveryInvalidParameter(): void
    {
        try {
            (new ListQueryParser())->parse(['limit' => '1000', 'cursor' => '???']);
            self::fail('Expected a validation failure.');
        } catch (ValidationFailed $exception) {
            self::assertSame(['limit', 'cursor'], array_map(static fn(Violation $v): ?string => $v->field, $exception->violations));
        }
    }
}

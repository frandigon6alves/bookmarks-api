<?php

declare(strict_types=1);

namespace Bookmarks\Tests\Unit\Application;

use Bookmarks\Application\Validation\BookmarkValidator;
use Bookmarks\Application\Validation\InputSource;
use Bookmarks\Application\Validation\ValidationFailed;
use Bookmarks\Application\Validation\Violation;
use Bookmarks\Domain\BookmarkData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BookmarkValidatorTest extends TestCase
{
    private BookmarkValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new BookmarkValidator();
    }

    public function testItNormalizesAValidBookmark(): void
    {
        $data = $this->validator->validateNew(self::decode(
            '{"url": "  https://www.php.net/releases/8.4/  ", "title": " PHP 8.4 ", "notes": "  ", "tags": ["PHP", "release-notes", "php"]}',
        ));

        self::assertSame('https://www.php.net/releases/8.4/', $data->url);
        self::assertSame('PHP 8.4', $data->title);
        self::assertNull($data->notes, 'Blank notes are stored as null.');
        self::assertSame(['php', 'release-notes'], $data->tags, 'Tags are lowercased, deduplicated and sorted.');
    }

    public function testTagsAndNotesAreOptional(): void
    {
        $data = $this->validator->validateNew(self::decode('{"url": "http://example.com", "title": "Example"}'));

        self::assertSame([], $data->tags);
        self::assertNull($data->notes);
    }

    /**
     * @return iterable<string, array{string, list<array{string|null, string}>}>
     */
    public static function invalidNewBookmarks(): iterable
    {
        yield 'not an object' => ['[]', [[null, 'validation.body.not_object']]];
        yield 'scalar body' => ['"hello"', [[null, 'validation.body.not_object']]];
        yield 'missing required fields' => ['{}', [['url', 'validation.required'], ['title', 'validation.required']]];
        yield 'wrong types' => ['{"url": 42, "title": false, "notes": 1, "tags": "php"}', [
            ['url', 'validation.type.string'],
            ['title', 'validation.type.string'],
            ['notes', 'validation.type.string_or_null'],
            ['tags', 'validation.type.list'],
        ]];
        yield 'blank strings' => ['{"url": " ", "title": ""}', [['url', 'validation.blank'], ['title', 'validation.blank']]];
        yield 'relative url' => ['{"url": "/just/a/path", "title": "x"}', [['url', 'validation.url.invalid']]];
        yield 'non-http scheme' => ['{"url": "ftp://example.com/file", "title": "x"}', [['url', 'validation.url.invalid']]];
        yield 'javascript url' => ['{"url": "javascript:alert(1)", "title": "x"}', [['url', 'validation.url.invalid']]];
        yield 'tags as object' => ['{"url": "https://a.dev", "title": "x", "tags": {"a": "b"}}', [['tags', 'validation.type.list']]];
        yield 'invalid tag entries' => ['{"url": "https://a.dev", "title": "x", "tags": ["ok", "no spaces", 3, "-edge"]}', [
            ['tags[1]', 'validation.tag.invalid'],
            ['tags[2]', 'validation.type.string'],
            ['tags[3]', 'validation.tag.invalid'],
        ]];
        yield 'unknown field' => ['{"url": "https://a.dev", "title": "x", "rating": 5}', [['rating', 'validation.unknown_field']]];
    }

    /**
     * @param list<array{string|null, string}> $expected
     */
    #[DataProvider('invalidNewBookmarks')]
    public function testItRejectsInvalidInput(string $json, array $expected): void
    {
        $exception = $this->catchFailure(fn() => $this->validator->validateNew(self::decode($json)));

        self::assertSame(InputSource::Body, $exception->source);
        self::assertSame($expected, array_map(
            static fn(Violation $violation): array => [$violation->field, $violation->messageKey],
            $exception->violations,
        ));
    }

    public function testItReportsLengthLimitsWithTheMaximum(): void
    {
        $exception = $this->catchFailure(fn() => $this->validator->validateNew((object) [
            'url' => 'https://example.com/' . str_repeat('a', BookmarkValidator::URL_MAX_LENGTH),
            'title' => str_repeat('é', BookmarkValidator::TITLE_MAX_LENGTH + 1),
            'notes' => str_repeat('n', BookmarkValidator::NOTES_MAX_LENGTH + 1),
        ]));

        self::assertEquals([
            new Violation('url', 'validation.too_long', ['max' => BookmarkValidator::URL_MAX_LENGTH]),
            new Violation('title', 'validation.too_long', ['max' => BookmarkValidator::TITLE_MAX_LENGTH]),
            new Violation('notes', 'validation.too_long', ['max' => BookmarkValidator::NOTES_MAX_LENGTH]),
        ], $exception->violations);
    }

    public function testTitleLengthCountsCharactersNotBytes(): void
    {
        $data = $this->validator->validateNew((object) [
            'url' => 'https://example.com',
            'title' => str_repeat('é', BookmarkValidator::TITLE_MAX_LENGTH),
        ]);

        self::assertSame(BookmarkValidator::TITLE_MAX_LENGTH, mb_strlen($data->title));
    }

    public function testItLimitsTheNumberOfDistinctTags(): void
    {
        $tags = array_map(static fn(int $i): string => 'tag-' . $i, range(1, BookmarkValidator::MAX_TAGS + 1));

        $exception = $this->catchFailure(fn() => $this->validator->validateNew((object) [
            'url' => 'https://example.com',
            'title' => 'Too many tags',
            'tags' => $tags,
        ]));

        self::assertEquals([new Violation('tags', 'validation.tags.too_many', ['max' => BookmarkValidator::MAX_TAGS])], $exception->violations);
    }

    public function testChangesOnlyTouchTheGivenFields(): void
    {
        $current = new BookmarkData('https://example.com', 'Example', 'Some notes', ['web']);

        $changed = $this->validator->validateChanges(self::decode('{"title": "Renamed", "tags": []}'), $current);

        self::assertEquals(new BookmarkData('https://example.com', 'Renamed', 'Some notes', []), $changed);
    }

    public function testChangesCanClearNotesWithNull(): void
    {
        $current = new BookmarkData('https://example.com', 'Example', 'Some notes', []);

        self::assertNull($this->validator->validateChanges(self::decode('{"notes": null}'), $current)->notes);
    }

    public function testChangesCannotBlankRequiredFields(): void
    {
        $current = new BookmarkData('https://example.com', 'Example', null, []);

        $exception = $this->catchFailure(fn() => $this->validator->validateChanges(self::decode('{"title": "  ", "url": null}'), $current));

        self::assertEquals([
            new Violation('title', 'validation.blank'),
            new Violation('url', 'validation.type.string'),
        ], $exception->violations);
    }

    private static function decode(string $json): mixed
    {
        return json_decode($json, false, 32, JSON_THROW_ON_ERROR);
    }

    /**
     * @param callable(): mixed $operation
     */
    private function catchFailure(callable $operation): ValidationFailed
    {
        try {
            $operation();
        } catch (ValidationFailed $exception) {
            return $exception;
        }

        self::fail('Expected a validation failure.');
    }
}

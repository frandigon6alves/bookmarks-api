<?php

declare(strict_types=1);

namespace Bookmarks\Tests\Unit\Application;

use Bookmarks\Application\BookmarkService;
use Bookmarks\Application\Exception\BookmarkNotFound;
use Bookmarks\Application\Exception\DuplicateUrl;
use Bookmarks\Application\Validation\BookmarkValidator;
use Bookmarks\Application\Validation\ListQueryParser;
use Bookmarks\Application\Validation\ValidationFailed;
use Bookmarks\Tests\Support\FixedClock;
use Bookmarks\Tests\Support\InMemoryBookmarkRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BookmarkServiceTest extends TestCase
{
    private InMemoryBookmarkRepository $repository;

    private FixedClock $clock;

    private BookmarkService $service;

    protected function setUp(): void
    {
        $this->repository = new InMemoryBookmarkRepository();
        $this->clock = new FixedClock(new DateTimeImmutable('2026-03-01T12:00:00+00:00'));
        $this->service = new BookmarkService($this->repository, new BookmarkValidator(), new ListQueryParser(), $this->clock);
    }

    public function testCreateStoresTheValidatedBookmark(): void
    {
        $bookmark = $this->service->create((object) ['url' => 'https://slimframework.com', 'title' => 'Slim', 'tags' => ['PHP']]);

        self::assertSame(1, $bookmark->id);
        self::assertSame(['php'], $bookmark->tags);
        self::assertEquals($this->clock->now(), $bookmark->createdAt);
        self::assertSame($bookmark, $this->repository->find(1));
    }

    public function testCreateRejectsAUrlThatIsAlreadySaved(): void
    {
        $this->service->create((object) ['url' => 'https://slimframework.com', 'title' => 'Slim']);

        $this->expectException(DuplicateUrl::class);
        $this->service->create((object) ['url' => 'https://slimframework.com', 'title' => 'Slim again']);
    }

    public function testCreateDoesNotWriteInvalidInput(): void
    {
        try {
            $this->service->create((object) ['title' => 'No URL']);
            self::fail('Expected a validation failure.');
        } catch (ValidationFailed) {
            self::assertSame(0, $this->repository->writes);
        }
    }

    public function testUpdateMergesChangesAndBumpsUpdatedAt(): void
    {
        $created = $this->service->create((object) ['url' => 'https://example.com', 'title' => 'Example', 'notes' => 'Keep me']);
        $this->clock->advance('+1 hour');

        $updated = $this->service->update($created->id, (object) ['title' => 'Renamed']);

        self::assertSame('Renamed', $updated->title);
        self::assertSame('Keep me', $updated->notes);
        self::assertEquals($created->createdAt, $updated->createdAt);
        self::assertEquals(new DateTimeImmutable('2026-03-01T13:00:00+00:00'), $updated->updatedAt);
    }

    public function testUpdateWithoutChangesDoesNotWrite(): void
    {
        $created = $this->service->create((object) ['url' => 'https://example.com', 'title' => 'Example']);

        $same = $this->service->update($created->id, (object) ['title' => 'Example']);

        self::assertSame($created, $same);
        self::assertSame(1, $this->repository->writes);
    }

    public function testUpdateRejectsTakingAnotherBookmarksUrl(): void
    {
        $this->service->create((object) ['url' => 'https://a.example', 'title' => 'A']);
        $b = $this->service->create((object) ['url' => 'https://b.example', 'title' => 'B']);

        try {
            $this->service->update($b->id, (object) ['url' => 'https://a.example']);
            self::fail('Expected a duplicate URL failure.');
        } catch (DuplicateUrl $exception) {
            self::assertSame(1, $exception->existingId);
        }
    }

    public function testUnknownIdsAreReportedAsNotFound(): void
    {
        foreach ([
            fn() => $this->service->get(99),
            fn() => $this->service->update(99, (object) ['title' => 'x']),
            fn() => $this->service->delete(99),
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected BookmarkNotFound.');
            } catch (BookmarkNotFound $exception) {
                self::assertSame(99, $exception->id);
            }
        }
    }

    public function testDeleteRemovesTheBookmark(): void
    {
        $created = $this->service->create((object) ['url' => 'https://example.com', 'title' => 'Example']);

        $this->service->delete($created->id);

        self::assertNull($this->repository->find($created->id));
    }
}

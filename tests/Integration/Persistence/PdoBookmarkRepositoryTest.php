<?php

declare(strict_types=1);

namespace Bookmarks\Tests\Integration\Persistence;

use Bookmarks\Domain\Bookmark;
use Bookmarks\Domain\BookmarkCriteria;
use Bookmarks\Domain\BookmarkData;
use Bookmarks\Domain\TagCount;
use Bookmarks\Infrastructure\Persistence\PdoBookmarkRepository;
use Bookmarks\Tests\Support\Database;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoBookmarkRepositoryTest extends TestCase
{
    private PDO $pdo;

    private PdoBookmarkRepository $repository;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->pdo = Database::migratedInMemory();
        $this->repository = new PdoBookmarkRepository($this->pdo);
        $this->now = new DateTimeImmutable('2026-02-10T08:30:00+00:00');
    }

    public function testItAddsAndFindsBookmarks(): void
    {
        $added = $this->add('https://www.sqlite.org/lang_upsert.html', 'SQLite UPSERT', ['sqlite', 'database'], 'ON CONFLICT clause');

        self::assertEquals(
            new Bookmark($added->id, 'https://www.sqlite.org/lang_upsert.html', 'SQLite UPSERT', 'ON CONFLICT clause', ['database', 'sqlite'], $this->now, $this->now),
            $this->repository->find($added->id),
        );
        self::assertSame($added->id, $this->repository->findByUrl('https://www.sqlite.org/lang_upsert.html')?->id);
        self::assertNull($this->repository->find(999));
        self::assertNull($this->repository->findByUrl('https://nowhere.example'));
    }

    public function testUpdateReplacesFieldsAndTags(): void
    {
        $bookmark = $this->add('https://a.example', 'A', ['one', 'two']);
        $later = $this->now->modify('+1 day');

        $updated = $this->repository->update($bookmark->id, new BookmarkData('https://b.example', 'B', 'notes', ['two', 'three']), $later);

        self::assertSame(['three', 'two'], $updated->tags);
        self::assertSame('https://b.example', $updated->url);
        self::assertEquals($this->now, $updated->createdAt);
        self::assertEquals($later, $updated->updatedAt);
        self::assertEquals([new TagCount('three', 1), new TagCount('two', 1)], $this->repository->tagCounts());
        self::assertSame(2, $this->countRows('tags'), 'Tags no longer used are deleted.');
    }

    public function testRemoveCascadesToTagLinksAndUnusedTags(): void
    {
        $first = $this->add('https://a.example', 'A', ['shared', 'only-a']);
        $this->add('https://b.example', 'B', ['shared']);

        self::assertTrue($this->repository->remove($first->id));
        self::assertFalse($this->repository->remove($first->id));

        self::assertNull($this->repository->find($first->id));
        self::assertSame(1, $this->countRows('bookmark_tags'));
        self::assertEquals([new TagCount('shared', 1)], $this->repository->tagCounts());
    }

    public function testSearchPaginatesNewestFirstWithAKeysetCursor(): void
    {
        $ids = [];
        foreach (range(1, 5) as $i) {
            $ids[] = $this->add("https://example.com/{$i}", "Bookmark {$i}")->id;
        }

        $first = $this->repository->search(new BookmarkCriteria(2));
        self::assertSame([$ids[4], $ids[3]], $this->ids($first->items));
        self::assertSame($ids[3], $first->nextBeforeId);

        $second = $this->repository->search(new BookmarkCriteria(2, beforeId: $first->nextBeforeId));
        self::assertSame([$ids[2], $ids[1]], $this->ids($second->items));

        $last = $this->repository->search(new BookmarkCriteria(2, beforeId: $second->nextBeforeId));
        self::assertSame([$ids[0]], $this->ids($last->items));
        self::assertNull($last->nextBeforeId);
    }

    public function testAnExactlyFullLastPageHasNoNextCursor(): void
    {
        $this->add('https://example.com/1', 'One');
        $this->add('https://example.com/2', 'Two');

        self::assertNull($this->repository->search(new BookmarkCriteria(2))->nextBeforeId);
    }

    public function testSearchFiltersByTagAndText(): void
    {
        $php = $this->add('https://www.php.net', 'PHP manual', ['php', 'docs']);
        $slim = $this->add('https://www.slimframework.com', 'Slim', ['php'], 'Micro framework for PHP');
        $mdn = $this->add('https://developer.mozilla.org', 'MDN', ['docs', 'web']);

        self::assertSame([$slim->id, $php->id], $this->ids($this->repository->search(new BookmarkCriteria(10, tag: 'php'))->items));
        self::assertSame([$slim->id], $this->ids($this->repository->search(new BookmarkCriteria(10, search: 'micro'))->items), 'Matches notes, case-insensitively.');
        self::assertSame([$mdn->id], $this->ids($this->repository->search(new BookmarkCriteria(10, search: 'mozilla'))->items), 'Matches the URL.');
        self::assertSame([$php->id], $this->ids($this->repository->search(new BookmarkCriteria(10, tag: 'docs', search: 'php'))->items), 'Filters combine.');
        self::assertSame([], $this->repository->search(new BookmarkCriteria(10, tag: 'missing'))->items);
    }

    public function testSearchTreatsLikeWildcardsLiterally(): void
    {
        $this->add('https://a.example', 'Discount 100%');
        $this->add('https://b.example', 'snake_case names');
        $this->add('https://c.example', 'Plain title');

        self::assertCount(1, $this->repository->search(new BookmarkCriteria(10, search: '%'))->items);
        self::assertCount(1, $this->repository->search(new BookmarkCriteria(10, search: '_'))->items);
    }

    public function testTagCountsAreOrderedByUsageThenName(): void
    {
        $this->add('https://a.example', 'A', ['php', 'web']);
        $this->add('https://b.example', 'B', ['php', 'api']);
        $this->add('https://c.example', 'C', ['php', 'web']);

        self::assertEquals(
            [new TagCount('php', 3), new TagCount('web', 2), new TagCount('api', 1)],
            $this->repository->tagCounts(),
        );
    }

    /**
     * @param list<string> $tags
     */
    private function add(string $url, string $title, array $tags = [], ?string $notes = null): Bookmark
    {
        sort($tags);

        return $this->repository->add(new BookmarkData($url, $title, $notes, $tags), $this->now);
    }

    /**
     * @param list<Bookmark> $bookmarks
     *
     * @return list<int>
     */
    private function ids(array $bookmarks): array
    {
        return array_map(static fn(Bookmark $bookmark): int => $bookmark->id, $bookmarks);
    }

    private function countRows(string $table): int
    {
        $statement = $this->pdo->query('SELECT COUNT(*) FROM ' . $table);
        self::assertNotFalse($statement);

        $count = $statement->fetchColumn();
        self::assertIsInt($count);

        return $count;
    }
}

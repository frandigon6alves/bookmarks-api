<?php

declare(strict_types=1);

namespace Bookmarks\Tests\Support;

use Bookmarks\Domain\Bookmark;
use Bookmarks\Domain\BookmarkCriteria;
use Bookmarks\Domain\BookmarkData;
use Bookmarks\Domain\BookmarkPage;
use Bookmarks\Domain\BookmarkRepository;
use Bookmarks\Domain\TagCount;
use DateTimeImmutable;
use LogicException;

/**
 * Minimal test double for service unit tests. Search semantics are covered
 * against the real PDO implementation in the integration suite.
 */
final class InMemoryBookmarkRepository implements BookmarkRepository
{
    /** @var array<int, Bookmark> */
    private array $bookmarks = [];

    private int $nextId = 1;

    public int $writes = 0;

    public function find(int $id): ?Bookmark
    {
        return $this->bookmarks[$id] ?? null;
    }

    public function findByUrl(string $url): ?Bookmark
    {
        foreach ($this->bookmarks as $bookmark) {
            if ($bookmark->url === $url) {
                return $bookmark;
            }
        }

        return null;
    }

    public function add(BookmarkData $data, DateTimeImmutable $now): Bookmark
    {
        ++$this->writes;
        $id = $this->nextId++;

        return $this->bookmarks[$id] = new Bookmark($id, $data->url, $data->title, $data->notes, $data->tags, $now, $now);
    }

    public function update(int $id, BookmarkData $data, DateTimeImmutable $now): Bookmark
    {
        ++$this->writes;
        $current = $this->find($id) ?? throw new LogicException('Unknown bookmark.');

        return $this->bookmarks[$id] = new Bookmark($id, $data->url, $data->title, $data->notes, $data->tags, $current->createdAt, $now);
    }

    public function remove(int $id): bool
    {
        $exists = isset($this->bookmarks[$id]);
        unset($this->bookmarks[$id]);

        return $exists;
    }

    public function search(BookmarkCriteria $criteria): BookmarkPage
    {
        $items = array_values(array_reverse($this->bookmarks));

        return new BookmarkPage(array_slice($items, 0, $criteria->limit), $criteria->limit, null);
    }

    public function tagCounts(): array
    {
        $counts = [];
        foreach ($this->bookmarks as $bookmark) {
            foreach ($bookmark->tags as $tag) {
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }

        return array_map(static fn(string $tag, int $count): TagCount => new TagCount($tag, $count), array_keys($counts), $counts);
    }
}

<?php

declare(strict_types=1);

namespace Bookmarks\Domain;

use DateTimeImmutable;

interface BookmarkRepository
{
    public function find(int $id): ?Bookmark;

    public function findByUrl(string $url): ?Bookmark;

    public function add(BookmarkData $data, DateTimeImmutable $now): Bookmark;

    public function update(int $id, BookmarkData $data, DateTimeImmutable $now): Bookmark;

    /**
     * @return bool false when no bookmark had that id
     */
    public function remove(int $id): bool;

    public function search(BookmarkCriteria $criteria): BookmarkPage;

    /**
     * Tags in use, most used first, then alphabetically.
     *
     * @return list<TagCount>
     */
    public function tagCounts(): array;
}

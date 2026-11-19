<?php

declare(strict_types=1);

namespace Bookmarks\Domain;

use DateTimeImmutable;

/**
 * A saved bookmark as it exists in storage.
 */
final readonly class Bookmark
{
    /**
     * @param list<string> $tags normalized tag names, sorted alphabetically
     */
    public function __construct(
        public int $id,
        public string $url,
        public string $title,
        public ?string $notes,
        public array $tags,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}

    public function data(): BookmarkData
    {
        return new BookmarkData($this->url, $this->title, $this->notes, $this->tags);
    }
}

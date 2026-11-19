<?php

declare(strict_types=1);

namespace Bookmarks\Domain;

final readonly class BookmarkPage
{
    /**
     * @param list<Bookmark> $items
     * @param int|null $nextBeforeId id to continue from, or null when this is the last page
     */
    public function __construct(
        public array $items,
        public int $limit,
        public ?int $nextBeforeId,
    ) {}
}

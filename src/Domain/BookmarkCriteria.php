<?php

declare(strict_types=1);

namespace Bookmarks\Domain;

/**
 * Filters and pagination for listing bookmarks, newest first.
 */
final readonly class BookmarkCriteria
{
    /**
     * @param positive-int $limit
     * @param int|null $beforeId only return bookmarks with an id lower than this (keyset cursor)
     */
    public function __construct(
        public int $limit,
        public ?string $tag = null,
        public ?string $search = null,
        public ?int $beforeId = null,
    ) {}
}

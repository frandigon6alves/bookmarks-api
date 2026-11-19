<?php

declare(strict_types=1);

namespace Bookmarks\Domain;

/**
 * The user-editable fields of a bookmark, already validated and normalized.
 */
final readonly class BookmarkData
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $url,
        public string $title,
        public ?string $notes,
        public array $tags,
    ) {}

    public function equals(self $other): bool
    {
        return $this->url === $other->url
            && $this->title === $other->title
            && $this->notes === $other->notes
            && $this->tags === $other->tags;
    }
}

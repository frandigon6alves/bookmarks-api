<?php

declare(strict_types=1);

namespace Bookmarks\Http;

use Bookmarks\Domain\Bookmark;
use DateTimeInterface;

final class BookmarkSerializer
{
    private function __construct() {}

    /**
     * @return array{id: int, url: string, title: string, notes: string|null, tags: list<string>, created_at: string, updated_at: string}
     */
    public static function toArray(Bookmark $bookmark): array
    {
        return [
            'id' => $bookmark->id,
            'url' => $bookmark->url,
            'title' => $bookmark->title,
            'notes' => $bookmark->notes,
            'tags' => $bookmark->tags,
            'created_at' => $bookmark->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $bookmark->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}

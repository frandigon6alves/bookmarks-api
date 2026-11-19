<?php

declare(strict_types=1);

namespace Bookmarks\Application\Exception;

use RuntimeException;

final class BookmarkNotFound extends RuntimeException
{
    public function __construct(public readonly int $id)
    {
        parent::__construct(sprintf('Bookmark %d does not exist.', $id));
    }
}

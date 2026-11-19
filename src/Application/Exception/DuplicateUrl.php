<?php

declare(strict_types=1);

namespace Bookmarks\Application\Exception;

use RuntimeException;

final class DuplicateUrl extends RuntimeException
{
    public function __construct(
        public readonly string $url,
        public readonly int $existingId,
    ) {
        parent::__construct(sprintf('URL "%s" is already saved as bookmark %d.', $url, $existingId));
    }
}

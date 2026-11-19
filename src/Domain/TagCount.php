<?php

declare(strict_types=1);

namespace Bookmarks\Domain;

final readonly class TagCount
{
    public function __construct(
        public string $name,
        public int $count,
    ) {}
}

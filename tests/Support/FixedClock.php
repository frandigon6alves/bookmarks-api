<?php

declare(strict_types=1);

namespace Bookmarks\Tests\Support;

use Bookmarks\Application\Clock;
use DateTimeImmutable;

final class FixedClock implements Clock
{
    public function __construct(private DateTimeImmutable $now = new DateTimeImmutable('2026-01-15T10:00:00+00:00')) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}

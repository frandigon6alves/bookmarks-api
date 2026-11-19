<?php

declare(strict_types=1);

namespace Bookmarks\Application;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}

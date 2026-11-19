<?php

declare(strict_types=1);

namespace Bookmarks\Application\Validation;

enum InputSource
{
    case Body;
    case Query;
}

<?php

declare(strict_types=1);

namespace Bookmarks\Http;

use JsonException;
use RuntimeException;

final class MalformedJson extends RuntimeException
{
    public function __construct(JsonException $previous)
    {
        parent::__construct('The request body is not valid JSON: ' . $previous->getMessage(), 0, $previous);
    }
}

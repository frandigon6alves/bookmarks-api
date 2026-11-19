<?php

declare(strict_types=1);

namespace Bookmarks\Application\Validation;

use RuntimeException;

final class ValidationFailed extends RuntimeException
{
    /**
     * @param non-empty-list<Violation> $violations
     */
    private function __construct(
        public readonly InputSource $source,
        public readonly array $violations,
    ) {
        parent::__construct(sprintf('%d validation error(s) in the request %s.', count($violations), strtolower($source->name)));
    }

    /**
     * @param non-empty-list<Violation> $violations
     */
    public static function inBody(array $violations): self
    {
        return new self(InputSource::Body, $violations);
    }

    /**
     * @param non-empty-list<Violation> $violations
     */
    public static function inQuery(array $violations): self
    {
        return new self(InputSource::Query, $violations);
    }
}

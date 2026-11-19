<?php

declare(strict_types=1);

namespace Bookmarks\Application\Validation;

/**
 * One validation failure. The message key is translated by the HTTP layer,
 * so the application layer stays independent of the request locale.
 */
final readonly class Violation
{
    /**
     * @param string|null $field field or query parameter name; null when the whole input is wrong
     * @param array<string, int|string> $parameters placeholder values, without the surrounding "%"
     */
    public function __construct(
        public ?string $field,
        public string $messageKey,
        public array $parameters = [],
    ) {}
}

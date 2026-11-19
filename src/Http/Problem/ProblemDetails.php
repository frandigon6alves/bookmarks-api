<?php

declare(strict_types=1);

namespace Bookmarks\Http\Problem;

/**
 * An RFC 9457 problem details object.
 */
final readonly class ProblemDetails
{
    public const string CONTENT_TYPE = 'application/problem+json';

    /**
     * @param array<string, mixed> $extensions extra members, e.g. "errors"
     * @param array<string, string> $headers extra response headers, e.g. "Allow"
     */
    public function __construct(
        public string $type,
        public string $title,
        public int $status,
        public string $detail,
        public ?string $instance = null,
        public array $extensions = [],
        public array $headers = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $members = [
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
        ];
        if ($this->instance !== null) {
            $members['instance'] = $this->instance;
        }

        return $members + $this->extensions;
    }
}

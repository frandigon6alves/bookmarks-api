<?php

declare(strict_types=1);

namespace Bookmarks\Http\Problem;

use Bookmarks\Application\Exception\BookmarkNotFound;
use Bookmarks\Application\Exception\DuplicateUrl;
use Bookmarks\Application\Validation\InputSource;
use Bookmarks\Application\Validation\ValidationFailed;
use Bookmarks\Application\Validation\Violation;
use Bookmarks\Http\MalformedJson;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Maps exceptions to translated problem details. Problem "type" values are
 * relative URIs documented in the README; generic HTTP errors use about:blank.
 */
final readonly class ProblemDetailsFactory
{
    public function __construct(private TranslatorInterface $translator) {}

    public function create(Throwable $exception, string $locale, ?string $instance = null, bool $debug = false): ProblemDetails
    {
        return match (true) {
            $exception instanceof ValidationFailed => $this->validation($exception, $locale, $instance),
            $exception instanceof MalformedJson => $this->problem(
                '/problems/malformed-json',
                400,
                'problem.malformed_json',
                $locale,
                $instance,
            ),
            $exception instanceof BookmarkNotFound => $this->problem(
                '/problems/bookmark-not-found',
                404,
                'problem.bookmark_not_found',
                $locale,
                $instance,
                ['%id%' => $exception->id],
            ),
            $exception instanceof DuplicateUrl => $this->problem(
                '/problems/duplicate-url',
                409,
                'problem.duplicate_url',
                $locale,
                $instance,
                ['%id%' => $exception->existingId],
                ['existing_id' => $exception->existingId],
            ),
            $exception instanceof HttpMethodNotAllowedException => $this->problem(
                'about:blank',
                405,
                'problem.method_not_allowed',
                $locale,
                $instance,
                ['%methods%' => implode(', ', $exception->getAllowedMethods())],
                headers: ['Allow' => implode(', ', $exception->getAllowedMethods())],
            ),
            $exception instanceof HttpNotFoundException => $this->problem('about:blank', 404, 'problem.not_found', $locale, $instance),
            $exception instanceof HttpException => $this->problem(
                'about:blank',
                $exception->getCode() >= 400 && $exception->getCode() < 600 ? $exception->getCode() : 500,
                'problem.http_error',
                $locale,
                $instance,
            ),
            default => $this->problem(
                'about:blank',
                500,
                'problem.internal',
                $locale,
                $instance,
                extensions: $debug ? ['debug' => ['exception' => $exception::class, 'message' => $exception->getMessage()]] : [],
            ),
        };
    }

    private function validation(ValidationFailed $exception, string $locale, ?string $instance): ProblemDetails
    {
        $errors = array_map(
            fn(Violation $violation): array => array_filter([
                'field' => $violation->field,
                'code' => substr($violation->messageKey, strlen('validation.')),
                'message' => $this->translator->trans(
                    $violation->messageKey,
                    self::placeholders($violation->parameters),
                    null,
                    $locale,
                ),
            ], static fn(?string $value): bool => $value !== null),
            $exception->violations,
        );

        return $exception->source === InputSource::Query
            ? $this->problem('/problems/invalid-query', 400, 'problem.invalid_query', $locale, $instance, extensions: ['errors' => $errors])
            : $this->problem('/problems/validation-error', 422, 'problem.validation', $locale, $instance, extensions: ['errors' => $errors]);
    }

    /**
     * @param array<string, int|string> $parameters
     * @param array<string, mixed> $extensions
     * @param array<string, string> $headers
     */
    private function problem(
        string $type,
        int $status,
        string $key,
        string $locale,
        ?string $instance,
        array $parameters = [],
        array $extensions = [],
        array $headers = [],
    ): ProblemDetails {
        return new ProblemDetails(
            $type,
            $this->translator->trans($key . '.title', [], null, $locale),
            $status,
            $this->translator->trans($key . '.detail', $parameters, null, $locale),
            $instance,
            $extensions,
            $headers,
        );
    }

    /**
     * @param array<string, int|string> $parameters
     *
     * @return array<string, int|string>
     */
    private static function placeholders(array $parameters): array
    {
        $placeholders = [];
        foreach ($parameters as $name => $value) {
            $placeholders['%' . $name . '%'] = $value;
        }

        return $placeholders;
    }
}

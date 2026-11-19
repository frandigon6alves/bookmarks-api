<?php

declare(strict_types=1);

namespace Bookmarks\Http\Problem;

use Bookmarks\Http\Json;
use Bookmarks\Http\Middleware\LocaleMiddleware;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Slim default error handler that renders every exception as problem+json.
 */
final readonly class ProblemErrorHandler
{
    public function __construct(
        private ProblemDetailsFactory $factory,
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        $problem = $this->factory->create(
            $exception,
            LocaleMiddleware::localeOf($request),
            $request->getUri()->getPath(),
            $displayErrorDetails,
        );

        if ($problem->status >= 500 && $logErrors) {
            error_log($logErrorDetails ? (string) $exception : $exception->getMessage());
        }

        $response = Json::respond(
            $this->responseFactory->createResponse(),
            $problem->toArray(),
            $problem->status,
            ProblemDetails::CONTENT_TYPE,
        );
        foreach ($problem->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}

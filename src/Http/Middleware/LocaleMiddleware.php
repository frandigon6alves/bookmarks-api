<?php

declare(strict_types=1);

namespace Bookmarks\Http\Middleware;

use Bookmarks\I18n\LocaleNegotiator;
use Bookmarks\I18n\Locales;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Negotiates the locale from Accept-Language, stores it as a request
 * attribute and announces it with Content-Language on every response,
 * including error responses.
 */
final readonly class LocaleMiddleware implements MiddlewareInterface
{
    public const string ATTRIBUTE = 'locale';

    public function __construct(private LocaleNegotiator $negotiator) {}

    public static function localeOf(ServerRequestInterface $request): string
    {
        $locale = $request->getAttribute(self::ATTRIBUTE);

        return is_string($locale) ? $locale : Locales::DEFAULT;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $locale = $this->negotiator->negotiate($request->getHeaderLine('Accept-Language'));

        return $handler->handle($request->withAttribute(self::ATTRIBUTE, $locale))
            ->withHeader('Content-Language', Locales::toLanguageTag($locale))
            ->withAddedHeader('Vary', 'Accept-Language');
    }
}

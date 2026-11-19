<?php

declare(strict_types=1);

namespace Bookmarks\Http;

use Bookmarks\Application\BookmarkService;
use Bookmarks\Application\Clock;
use Bookmarks\Application\Validation\BookmarkValidator;
use Bookmarks\Application\Validation\ListQueryParser;
use Bookmarks\Http\Handler\CreateBookmarkHandler;
use Bookmarks\Http\Handler\DeleteBookmarkHandler;
use Bookmarks\Http\Handler\HealthHandler;
use Bookmarks\Http\Handler\ListBookmarksHandler;
use Bookmarks\Http\Handler\ListTagsHandler;
use Bookmarks\Http\Handler\ShowBookmarkHandler;
use Bookmarks\Http\Handler\UpdateBookmarkHandler;
use Bookmarks\Http\Middleware\LocaleMiddleware;
use Bookmarks\Http\Problem\ProblemDetailsFactory;
use Bookmarks\Http\Problem\ProblemErrorHandler;
use Bookmarks\I18n\LocaleNegotiator;
use Bookmarks\I18n\TranslatorFactory;
use Bookmarks\Infrastructure\Persistence\PdoBookmarkRepository;
use Bookmarks\Infrastructure\SystemClock;
use PDO;
use Slim\App;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Wires the whole application by hand: the object graph is small enough
 * that a DI container would add more indirection than it removes.
 */
final class ApplicationFactory
{
    private function __construct() {}

    /**
     * @return App<null>
     */
    public static function create(PDO $pdo, ?Clock $clock = null, bool $debug = false, bool $logErrors = true): App
    {
        $responseFactory = new ResponseFactory();
        $app = new App($responseFactory);

        $service = new BookmarkService(
            new PdoBookmarkRepository($pdo),
            new BookmarkValidator(),
            new ListQueryParser(),
            $clock ?? new SystemClock(),
        );

        $app->get('/health', new HealthHandler($pdo));
        $app->get('/bookmarks', new ListBookmarksHandler($service));
        $app->post('/bookmarks', new CreateBookmarkHandler($service));
        $app->get('/bookmarks/{id:[0-9]+}', new ShowBookmarkHandler($service));
        $app->patch('/bookmarks/{id:[0-9]+}', new UpdateBookmarkHandler($service));
        $app->delete('/bookmarks/{id:[0-9]+}', new DeleteBookmarkHandler($service));
        $app->get('/tags', new ListTagsHandler($service));

        // Slim runs middleware last-in, first-out: LocaleMiddleware wraps the
        // error middleware so error responses are translated and tagged too.
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware($debug, $logErrors, $logErrors)->setDefaultErrorHandler(
            new ProblemErrorHandler(new ProblemDetailsFactory(TranslatorFactory::create()), $responseFactory),
        );
        $app->add(new LocaleMiddleware(new LocaleNegotiator()));

        return $app;
    }
}

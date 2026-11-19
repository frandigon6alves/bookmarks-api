<?php

declare(strict_types=1);

namespace Bookmarks\Http\Handler;

use Bookmarks\Application\BookmarkService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class DeleteBookmarkHandler
{
    public function __construct(private BookmarkService $service) {}

    /**
     * @param array{id: string} $args
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->service->delete((int) $args['id']);

        return $response->withStatus(204);
    }
}

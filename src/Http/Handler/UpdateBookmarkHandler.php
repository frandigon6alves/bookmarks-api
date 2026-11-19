<?php

declare(strict_types=1);

namespace Bookmarks\Http\Handler;

use Bookmarks\Application\BookmarkService;
use Bookmarks\Http\BookmarkSerializer;
use Bookmarks\Http\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class UpdateBookmarkHandler
{
    public function __construct(private BookmarkService $service) {}

    /**
     * @param array{id: string} $args
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $bookmark = $this->service->update((int) $args['id'], Json::decodeBody($request));

        return Json::respond($response, ['data' => BookmarkSerializer::toArray($bookmark)]);
    }
}

<?php

declare(strict_types=1);

namespace Bookmarks\Http\Handler;

use Bookmarks\Application\BookmarkService;
use Bookmarks\Http\BookmarkSerializer;
use Bookmarks\Http\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class CreateBookmarkHandler
{
    public function __construct(private BookmarkService $service) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $bookmark = $this->service->create(Json::decodeBody($request));

        return Json::respond($response, ['data' => BookmarkSerializer::toArray($bookmark)], 201)
            ->withHeader('Location', '/bookmarks/' . $bookmark->id);
    }
}

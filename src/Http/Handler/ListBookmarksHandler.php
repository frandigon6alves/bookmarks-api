<?php

declare(strict_types=1);

namespace Bookmarks\Http\Handler;

use Bookmarks\Application\BookmarkService;
use Bookmarks\Application\Cursor;
use Bookmarks\Http\BookmarkSerializer;
use Bookmarks\Http\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListBookmarksHandler
{
    public function __construct(private BookmarkService $service) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $page = $this->service->list($request->getQueryParams());

        return Json::respond($response, [
            'data' => array_map(BookmarkSerializer::toArray(...), $page->items),
            'meta' => [
                'limit' => $page->limit,
                'next_cursor' => $page->nextBeforeId === null ? null : Cursor::encode($page->nextBeforeId),
            ],
        ]);
    }
}

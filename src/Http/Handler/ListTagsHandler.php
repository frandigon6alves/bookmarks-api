<?php

declare(strict_types=1);

namespace Bookmarks\Http\Handler;

use Bookmarks\Application\BookmarkService;
use Bookmarks\Domain\TagCount;
use Bookmarks\Http\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListTagsHandler
{
    public function __construct(private BookmarkService $service) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Json::respond($response, [
            'data' => array_map(
                static fn(TagCount $tag): array => ['name' => $tag->name, 'count' => $tag->count],
                $this->service->tags(),
            ),
        ]);
    }
}

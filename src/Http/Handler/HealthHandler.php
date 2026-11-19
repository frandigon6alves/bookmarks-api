<?php

declare(strict_types=1);

namespace Bookmarks\Http\Handler;

use Bookmarks\Http\Json;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class HealthHandler
{
    public function __construct(private PDO $pdo) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->pdo->query('SELECT 1');

        return Json::respond($response, ['status' => 'ok']);
    }
}

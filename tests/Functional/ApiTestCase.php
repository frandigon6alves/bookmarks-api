<?php

declare(strict_types=1);

namespace Bookmarks\Tests\Functional;

use Bookmarks\Http\ApplicationFactory;
use Bookmarks\Tests\Support\Database;
use Bookmarks\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Boots the real Slim application on a fresh in-memory SQLite database and
 * dispatches requests in-process (no web server involved).
 */
abstract class ApiTestCase extends TestCase
{
    protected FixedClock $clock;

    /** @var App<null> */
    private App $app;

    protected function setUp(): void
    {
        $this->clock = new FixedClock();
        $this->app = ApplicationFactory::create(Database::migratedInMemory(), $this->clock, logErrors: false);
    }

    /**
     * @param array<string, string> $headers
     */
    protected function request(string $method, string $uri, mixed $body = null, array $headers = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri);
        parse_str($request->getUri()->getQuery(), $query);
        $request = $request->withQueryParams($query);

        if ($body !== null) {
            $request->getBody()->write(is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR));
            $request = $request->withHeader('Content-Type', 'application/json');
        }
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->app->handle($request);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return int id of the created bookmark
     */
    protected function createBookmark(array $payload): int
    {
        $response = $this->request('POST', '/bookmarks', $payload);
        self::assertSame(201, $response->getStatusCode(), (string) $response->getBody());

        $id = self::json($response, 'data.id');
        self::assertIsInt($id);

        return $id;
    }

    /**
     * Reads a value from the decoded JSON body by dotted path, e.g. "data.0.tags".
     */
    protected static function json(ResponseInterface $response, string $path = ''): mixed
    {
        $value = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        if ($path === '') {
            return $value;
        }

        foreach (explode('.', $path) as $segment) {
            self::assertIsArray($value, sprintf('Cannot read "%s" from the response body.', $path));
            self::assertArrayHasKey(ctype_digit($segment) ? (int) $segment : $segment, $value, sprintf('Missing "%s".', $path));
            $value = $value[ctype_digit($segment) ? (int) $segment : $segment];
        }

        return $value;
    }

    protected static function assertProblem(ResponseInterface $response, int $status, string $type): void
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getBody());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame($type, self::json($response, 'type'));
        self::assertSame($status, self::json($response, 'status'));
        self::assertIsString(self::json($response, 'title'));
        self::assertIsString(self::json($response, 'detail'));
    }
}

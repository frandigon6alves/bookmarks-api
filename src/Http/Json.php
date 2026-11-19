<?php

declare(strict_types=1);

namespace Bookmarks\Http;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class Json
{
    private const int ENCODE_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    private function __construct() {}

    /**
     * @param array<mixed> $data
     */
    public static function respond(
        ResponseInterface $response,
        array $data,
        int $status = 200,
        string $contentType = 'application/json',
    ): ResponseInterface {
        $response->getBody()->write(json_encode($data, self::ENCODE_FLAGS));

        return $response->withStatus($status)->withHeader('Content-Type', $contentType);
    }

    /**
     * Decodes the request body, keeping JSON objects as stdClass so that
     * "{}" and "[]" stay distinguishable for validation.
     *
     * @throws MalformedJson
     */
    public static function decodeBody(ServerRequestInterface $request): mixed
    {
        try {
            return json_decode((string) $request->getBody(), false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new MalformedJson($exception);
        }
    }
}

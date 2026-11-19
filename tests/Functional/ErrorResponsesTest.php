<?php

declare(strict_types=1);

namespace Bookmarks\Tests\Functional;

/**
 * Error responses: RFC 9457 shape, status codes and locale negotiation.
 */
final class ErrorResponsesTest extends ApiTestCase
{
    public function testValidationErrorsInEnglishByDefault(): void
    {
        $response = $this->request('POST', '/bookmarks', ['url' => 'not a url', 'tags' => ['ok', 'Bad Tag!']]);

        self::assertProblem($response, 422, '/problems/validation-error');
        self::assertSame('en', $response->getHeaderLine('Content-Language'));
        self::assertSame('Accept-Language', $response->getHeaderLine('Vary'));
        self::assertSame([
            'type' => '/problems/validation-error',
            'title' => 'Validation failed',
            'status' => 422,
            'detail' => 'The request body contains invalid data. See "errors" for details.',
            'instance' => '/bookmarks',
            'errors' => [
                ['field' => 'title', 'code' => 'required', 'message' => 'This field is required.'],
                ['field' => 'url', 'code' => 'url.invalid', 'message' => 'This field must be an absolute http or https URL.'],
                [
                    'field' => 'tags[1]',
                    'code' => 'tag.invalid',
                    'message' => 'Tags may only contain lowercase letters, digits and single hyphens, up to 30 characters.',
                ],
            ],
        ], self::json($response));
    }

    public function testValidationErrorsInSpanish(): void
    {
        $response = $this->request(
            'POST',
            '/bookmarks',
            ['url' => 'ftp://example.com', 'title' => str_repeat('a', 201)],
            ['Accept-Language' => 'es-ES,es;q=0.9,en;q=0.5'],
        );

        self::assertProblem($response, 422, '/problems/validation-error');
        self::assertSame('es', $response->getHeaderLine('Content-Language'));
        self::assertSame('La validación ha fallado', self::json($response, 'title'));
        self::assertSame(
            'El cuerpo de la petición contiene datos no válidos. Consulta "errors" para más detalles.',
            self::json($response, 'detail'),
        );
        self::assertSame([
            ['field' => 'url', 'code' => 'url.invalid', 'message' => 'Este campo debe ser una URL absoluta http o https.'],
            ['field' => 'title', 'code' => 'too_long', 'message' => 'Este campo debe tener como máximo 200 caracteres.'],
        ], self::json($response, 'errors'));
    }

    public function testNotFoundInBrazilianPortuguese(): void
    {
        $response = $this->request('GET', '/bookmarks/42', headers: ['Accept-Language' => 'pt-BR']);

        self::assertProblem($response, 404, '/problems/bookmark-not-found');
        self::assertSame('pt-BR', $response->getHeaderLine('Content-Language'));
        self::assertSame('Favorito não encontrado', self::json($response, 'title'));
        self::assertSame('Não existe favorito com o id 42.', self::json($response, 'detail'));
        self::assertSame('/bookmarks/42', self::json($response, 'instance'));
    }

    public function testSuccessfulResponsesAlsoCarryContentLanguage(): void
    {
        $response = $this->request('GET', '/bookmarks', headers: ['Accept-Language' => 'pt']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('pt-BR', $response->getHeaderLine('Content-Language'));
    }

    public function testUnsupportedLanguagesFallBackToEnglish(): void
    {
        $response = $this->request('GET', '/bookmarks/1', headers: ['Accept-Language' => 'de-DE, fr;q=0.8']);

        self::assertSame('en', $response->getHeaderLine('Content-Language'));
        self::assertSame('There is no bookmark with id 1.', self::json($response, 'detail'));
    }

    public function testMalformedJson(): void
    {
        $response = $this->request('POST', '/bookmarks', '{"url": ', ['Accept-Language' => 'es']);

        self::assertProblem($response, 400, '/problems/malformed-json');
        self::assertSame('JSON mal formado', self::json($response, 'title'));
    }

    public function testBodyMustBeAnObject(): void
    {
        $response = $this->request('POST', '/bookmarks', '["https://example.com"]');

        self::assertProblem($response, 422, '/problems/validation-error');
        self::assertSame([['code' => 'body.not_object', 'message' => 'The request body must be a JSON object.']], self::json($response, 'errors'));
    }

    public function testDuplicateUrlIsAConflict(): void
    {
        $id = $this->createBookmark(['url' => 'https://example.com', 'title' => 'Example']);

        $response = $this->request('POST', '/bookmarks', ['url' => 'https://example.com', 'title' => 'Again']);

        self::assertProblem($response, 409, '/problems/duplicate-url');
        self::assertSame($id, self::json($response, 'existing_id'));
    }

    public function testInvalidQueryParametersAreABadRequest(): void
    {
        $response = $this->request('GET', '/bookmarks?limit=500&cursor=nope', headers: ['Accept-Language' => 'es']);

        self::assertProblem($response, 400, '/problems/invalid-query');
        self::assertSame([
            ['field' => 'limit', 'code' => 'limit.range', 'message' => 'El límite debe ser un número entero entre 1 y 100.'],
            [
                'field' => 'cursor',
                'code' => 'cursor.invalid',
                'message' => 'El cursor no es válido. Usa el valor next_cursor de una respuesta anterior.',
            ],
        ], self::json($response, 'errors'));
    }

    public function testUnknownRoute(): void
    {
        $response = $this->request('GET', '/nope');

        self::assertProblem($response, 404, 'about:blank');
        self::assertSame('Not found', self::json($response, 'title'));
    }

    public function testNonNumericIdDoesNotMatchARoute(): void
    {
        self::assertProblem($this->request('GET', '/bookmarks/abc'), 404, 'about:blank');
    }

    public function testMethodNotAllowedListsAllowedMethods(): void
    {
        $response = $this->request('PUT', '/bookmarks/1', ['title' => 'x'], ['Accept-Language' => 'es']);

        self::assertProblem($response, 405, 'about:blank');
        self::assertSame('GET, PATCH, DELETE', $response->getHeaderLine('Allow'));
        self::assertSame('Este recurso solo admite: GET, PATCH, DELETE.', self::json($response, 'detail'));
    }
}

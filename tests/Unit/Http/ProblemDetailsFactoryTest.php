<?php

declare(strict_types=1);

namespace Bookmarks\Tests\Unit\Http;

use Bookmarks\Application\Exception\DuplicateUrl;
use Bookmarks\Application\Validation\ValidationFailed;
use Bookmarks\Application\Validation\Violation;
use Bookmarks\Http\Problem\ProblemDetailsFactory;
use Bookmarks\I18n\TranslatorFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProblemDetailsFactoryTest extends TestCase
{
    private ProblemDetailsFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ProblemDetailsFactory(TranslatorFactory::create());
    }

    public function testValidationErrorsAreTranslatedWithPlaceholders(): void
    {
        $problem = $this->factory->create(
            ValidationFailed::inBody([new Violation('title', 'validation.too_long', ['max' => 200]), new Violation(null, 'validation.body.not_object')]),
            'pt_BR',
            '/bookmarks',
        );

        self::assertSame([
            'type' => '/problems/validation-error',
            'title' => 'Falha na validação',
            'status' => 422,
            'detail' => 'O corpo da requisição contém dados inválidos. Veja "errors" para mais detalhes.',
            'instance' => '/bookmarks',
            'errors' => [
                ['field' => 'title', 'code' => 'too_long', 'message' => 'Este campo deve ter no máximo 200 caracteres.'],
                ['code' => 'body.not_object', 'message' => 'O corpo da requisição deve ser um objeto JSON.'],
            ],
        ], $problem->toArray());
    }

    public function testDuplicateUrlExposesTheExistingId(): void
    {
        $problem = $this->factory->create(new DuplicateUrl('https://example.com', 3), 'en');

        self::assertSame(409, $problem->status);
        self::assertSame('This URL is already saved as bookmark 3.', $problem->detail);
        self::assertSame(3, $problem->toArray()['existing_id']);
        self::assertArrayNotHasKey('instance', $problem->toArray());
    }

    public function testUnexpectedErrorsHideInternalsUnlessDebugging(): void
    {
        $exception = new RuntimeException('SQLSTATE[HY000]: secret details');

        $hidden = $this->factory->create($exception, 'es');
        self::assertSame(500, $hidden->status);
        self::assertSame('Error interno del servidor', $hidden->title);
        self::assertStringNotContainsString('SQLSTATE', (string) json_encode($hidden->toArray()));

        $debug = $this->factory->create($exception, 'en', debug: true);
        self::assertSame(['exception' => RuntimeException::class, 'message' => 'SQLSTATE[HY000]: secret details'], $debug->toArray()['debug']);
    }
}

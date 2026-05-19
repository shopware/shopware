<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderResult;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(MailTemplateRenderResult::class)]
#[Package('after-sales')]
class MailTemplateRenderResultTest extends TestCase
{
    /**
     * @return iterable<string, array{
     *     error: \Throwable,
     *     expectedTitle: string,
     *     expectedMessage: string
     * }>
     */
    public static function errorProvider(): iterable
    {
        yield 'twig syntax error' => [
            'error' => AdapterException::invalidTemplateSyntax('unexpected end of template.'),
            'expectedTitle' => 'Twig syntax error',
            'expectedMessage' => 'unexpected end of template.',
        ];

        yield 'rendering error' => [
            'error' => AdapterException::renderingTemplateFailed('variable "foo" does not exist.'),
            'expectedTitle' => 'Rendering error',
            'expectedMessage' => 'variable "foo" does not exist.',
        ];

        yield 'generic error' => [
            'error' => new \RuntimeException('broken template'),
            'expectedTitle' => 'Error',
            'expectedMessage' => 'broken template',
        ];
    }

    #[DataProvider('errorProvider')]
    public function testErrorFromThrowable(\Throwable $error, string $expectedTitle, string $expectedMessage): void
    {
        $result = MailTemplateRenderResult::errorFromThrowable($error);

        static::assertSame(MailTemplateRenderResult::TYPE_ERROR, $result->getType());
        static::assertSame($error->getMessage(), $result->getContent());
        static::assertSame($expectedTitle, $result->getErrorTitle());
        static::assertSame($expectedMessage, $result->getErrorMessage());
    }
}

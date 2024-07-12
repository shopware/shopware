<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Symfony\Component\HttpFoundation\Response;
use Twig\Node\Expression\AbstractExpression;

/**
 * @internal
 */
#[CoversClass(AdapterException::class)]
class AdapterExceptionTest extends TestCase
{
    public function testUnexpectedTwigExpression(): void
    {
        /** @var AbstractExpression&MockObject $expression */
        $expression = $this->createMock(AbstractExpression::class);
        $type = $expression::class;

        $exception = AdapterException::unexpectedTwigExpression($expression);

        static::assertSame(Response::HTTP_NOT_ACCEPTABLE, $exception->getStatusCode());
        static::assertSame(AdapterException::UNEXPECTED_TWIG_EXPRESSION, $exception->getErrorCode());
        static::assertSame(sprintf('Unexpected Expression of type "%s".', $type), $exception->getMessage());
        static::assertSame(['type' => $type], $exception->getParameters());
    }

    public function testMissingExtendsTemplate(): void
    {
        $exception = AdapterException::missingExtendsTemplate('test');

        static::assertSame(Response::HTTP_NOT_ACCEPTABLE, $exception->getStatusCode());
        static::assertSame(AdapterException::MISSING_EXTENDING_TWIG_TEMPLATE, $exception->getErrorCode());
        static::assertSame('Template "test" does not have an extending template.', $exception->getMessage());
        static::assertSame(['template' => 'test'], $exception->getParameters());
    }

    public function testInvalidTemplateScope(): void
    {
        $exception = AdapterException::invalidTemplateScope('test');

        static::assertSame(Response::HTTP_NOT_ACCEPTABLE, $exception->getStatusCode());
        static::assertSame(AdapterException::TEMPLATE_SCOPE_DEFINITION_ERROR, $exception->getErrorCode());
        static::assertSame('Template scope is wronly defined: test', $exception->getMessage());
        static::assertSame(['scope' => 'test'], $exception->getParameters());
    }

    public function testMissingDependency(): void
    {
        $exception = AdapterException::missingDependency('test');

        static::assertSame(Response::HTTP_FAILED_DEPENDENCY, $exception->getStatusCode());
        static::assertSame(AdapterException::MISSING_DEPENDENCY_ERROR_CODE, $exception->getErrorCode());
        static::assertSame('Missing dependency "test". Check the suggested composer dependencies for version and install the package.', $exception->getMessage());
        static::assertSame(['dependency' => 'test'], $exception->getParameters());
    }

    public function testInvalidTemplateSyntax(): void
    {
        $exception = AdapterException::invalidTemplateSyntax('test');
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(AdapterException::INVALID_TEMPLATE_SYNTAX, $exception->getErrorCode());
        static::assertSame('Failed rendering Twig string template due syntax error: "test"', $exception->getMessage());
        static::assertSame(['message' => 'test'], $exception->getParameters());
    }
}

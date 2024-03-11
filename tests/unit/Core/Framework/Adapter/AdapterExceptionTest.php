<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
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

        try {
            throw AdapterException::unexpectedTwigExpression($expression);
        } catch (AdapterException $exception) {
            static::assertSame('FRAMEWORK__UNEXPECTED_TWIG_EXPRESSION', $exception->getErrorCode());
            static::assertSame(sprintf('Unexpected Expression of type "%s".', $type), $exception->getMessage());
            static::assertSame(['type' => $type], $exception->getParameters());
        }
    }

    public function testMissingExtendsTemplate(): void
    {
        try {
            throw AdapterException::missingExtendsTemplate('test');
        } catch (AdapterException $exception) {
            static::assertSame('FRAMEWORK__MISSING_EXTENDING_TWIG_TEMPLATE', $exception->getErrorCode());
            static::assertSame('Template "test" does not have an extending template.', $exception->getMessage());
            static::assertSame(['template' => 'test'], $exception->getParameters());
        }
    }

    public function testInvalidTemplateScope(): void
    {
        try {
            throw AdapterException::invalidTemplateScope('test');
        } catch (AdapterException $exception) {
            static::assertSame('FRAMEWORK__TEMPLATE_SCOPE_DEFINITION_ERROR', $exception->getErrorCode());
            static::assertSame('Template scope is wronly defined: test', $exception->getMessage());
            static::assertSame(['scope' => 'test'], $exception->getParameters());
        }
    }

    public function testMissingDependency(): void
    {
        try {
            throw AdapterException::missingDependency('test');
        } catch (AdapterException $exception) {
            static::assertSame('FRAMEWORK__FILESYSTEM_ADAPTER_DEPENDENCY_MISSING', $exception->getErrorCode());
            static::assertSame('Missing dependency "test". Check the suggested composer dependencies for version and install the package.', $exception->getMessage());
            static::assertSame(['dependency' => 'test'], $exception->getParameters());
        }
    }

    public function testInvalidTemplateSyntax(): void
    {
        try {
            throw AdapterException::invalidTemplateSyntax('test');
        } catch (AdapterException $exception) {
            static::assertSame('FRAMEWORK__INVALID_TEMPLATE_SYNTAX', $exception->getErrorCode());
            static::assertSame('Failed rendering Twig string template due syntax error: "test"', $exception->getMessage());
            static::assertSame(['message' => 'test'], $exception->getParameters());
        }
    }
}

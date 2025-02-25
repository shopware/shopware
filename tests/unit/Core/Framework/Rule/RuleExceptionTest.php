<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\RuleException;
use Shopware\Core\Framework\Script\Exception\ScriptExecutionFailedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(RuleException::class)]
#[Group('rules')]
class RuleExceptionTest extends TestCase
{
    public function testScriptExecutionFailed(): void
    {
        $previous = new \Exception();
        $exception = RuleException::scriptExecutionFailed('testHook', 'testScript', $previous);

        static::assertInstanceOf(ScriptExecutionFailedException::class, $exception);
        static::assertEquals($previous, $exception->getPrevious());
    }

    public function testUnsupportedOperator(): void
    {
        $exception = RuleException::unsupportedOperator('$', 'testClass');

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals('FRAMEWORK__RULE_OPERATOR_NOT_SUPPORTED', $exception->getErrorCode());
        static::assertEquals('Unsupported operator $ in testClass', $exception->getMessage());
        static::assertEquals(['operator' => '$', 'class' => 'testClass'], $exception->getParameters());
    }

    public function testUnsupportedValue(): void
    {
        $exception = RuleException::unsupportedValue('testType', 'testClass');

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals('FRAMEWORK__RULE_VALUE_NOT_SUPPORTED', $exception->getErrorCode());
        static::assertEquals('Unsupported value of type testType in testClass', $exception->getMessage());
        static::assertEquals(['type' => 'testType', 'class' => 'testClass'], $exception->getParameters());
    }
}

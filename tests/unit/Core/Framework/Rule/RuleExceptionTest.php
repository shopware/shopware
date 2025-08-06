<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\RuleException;
use Shopware\Core\Framework\Script\Exception\ScriptExecutionFailedException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
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
        static::assertSame($previous, $exception->getPrevious());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testUnsupportedOperator(): void
    {
        $exception = RuleException::unsupportedOperator('$', 'testClass');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('CONTENT__RULE_OPERATOR_NOT_SUPPORTED', $exception->getErrorCode());
        static::assertSame('Unsupported operator $ in testClass', $exception->getMessage());
        static::assertSame(['operator' => '$', 'class' => 'testClass'], $exception->getParameters());
    }

    public function testUnsupportedValue(): void
    {
        $exception = RuleException::unsupportedValue('testType', 'testClass');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(RuleException::VALUE_NOT_SUPPORTED, $exception->getErrorCode());
        static::assertSame('Unsupported value of type testType in testClass', $exception->getMessage());
        static::assertSame(['type' => 'testType', 'class' => 'testClass'], $exception->getParameters());
    }
}

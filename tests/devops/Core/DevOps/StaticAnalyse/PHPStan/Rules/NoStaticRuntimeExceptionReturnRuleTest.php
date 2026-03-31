<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoRuntimeExceptionInDomainExceptionsRule;

/**
 * @internal
 *
 * @extends RuleTestCase<NoRuntimeExceptionInDomainExceptionsRule>
 */
class NoStaticRuntimeExceptionReturnRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        // If the class does not extend HttpException, skip
        $this->analyse([__DIR__ . '/data/NoStaticRuntimeExceptionReturnRuleTest/NotException.php'], []);

        $this->analyse([
            __DIR__ . '/data/NoStaticRuntimeExceptionReturnRuleTest/DummyException.php',
        ], [
            [
                'Domain exception factory method Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoStaticRuntimeExceptionReturnRuleTest\DummyException::runtimeException() might return \RuntimeException, however the ExceptionClass itself already extends \RuntimeException, therefore it should only return self.',
                9,
            ],
            [
                'Domain exception factory method Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoStaticRuntimeExceptionReturnRuleTest\DummyException::runtimeExceptionOrSelf() might return \RuntimeException, however the ExceptionClass itself already extends \RuntimeException, therefore it should only return self.',
                14,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoRuntimeExceptionInDomainExceptionsRule();
    }
}

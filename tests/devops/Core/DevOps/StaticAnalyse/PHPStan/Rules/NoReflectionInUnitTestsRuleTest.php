<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoReflectionInUnitTestsRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoReflectionInUnitTestsRule>
 */
#[Package('framework')]
class NoReflectionInUnitTestsRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/NoReflectionInUnitTestsRule/Cases.php'], [
            [\sprintf(NoReflectionInUnitTestsRule::ERROR_REFLECTION, '\ReflectionClass'), 14],
            [\sprintf(NoReflectionInUnitTestsRule::ERROR_REFLECTION, '\ReflectionProperty'), 15],
            [\sprintf(NoReflectionInUnitTestsRule::ERROR_REFLECTION, '\ReflectionMethod'), 16],
            [\sprintf(NoReflectionInUnitTestsRule::ERROR_REFLECTION, '\ReflectionObject'), 17],
            [\sprintf(NoReflectionInUnitTestsRule::ERROR_REFLECTION, '\ReflectionFunction'), 18],
            [\sprintf(NoReflectionInUnitTestsRule::ERROR_REFLECTION, '\ReflectionClassConstant'), 19],
            [\sprintf(NoReflectionInUnitTestsRule::ERROR_CLOSURE_BINDING, 'bind'), 26],
            [\sprintf(NoReflectionInUnitTestsRule::ERROR_CLOSURE_BINDING, 'bindTo'), 27],
            [\sprintf(NoReflectionInUnitTestsRule::ERROR_CLOSURE_BINDING, 'call'), 28],
            [\sprintf(NoReflectionInUnitTestsRule::ERROR_REFLECTION, '\ReflectionClass'), 48],
        ]);
    }

    public function testRuleDoesNotEnforceOutsideEnabledNamespaces(): void
    {
        $this->analyse([__DIR__ . '/data/NoReflectionInUnitTestsRule/CasesOutOfScope.php'], []);
    }

    protected function getRule(): Rule
    {
        return new NoReflectionInUnitTestsRule(
            new Configuration(['reflectionInUnitTestsEnabledNamespaces' => ['Shopware\\Tests\\Unit\\']]),
        );
    }
}

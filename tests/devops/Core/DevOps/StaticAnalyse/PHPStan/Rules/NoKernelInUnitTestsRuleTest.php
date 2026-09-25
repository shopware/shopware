<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoKernelInUnitTestsRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;

require_once __DIR__ . '/data/NoKernelInUnitTestsRule/LocalFixtureBehaviour.php';

/**
 * @internal
 *
 * @extends RuleTestCase<NoKernelInUnitTestsRule>
 */
#[Package('framework')]
class NoKernelInUnitTestsRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/NoKernelInUnitTestsRule/Cases.php'], [
            [\sprintf(NoKernelInUnitTestsRule::ERROR_TRAIT, KernelTestBehaviour::class), 14],
            // the nested behaviour is reported together with the one it composes; PHPStan orders one line by message
            [\sprintf(NoKernelInUnitTestsRule::ERROR_TRAIT, BasicTestDataBehaviour::class), 27],
            [\sprintf(NoKernelInUnitTestsRule::ERROR_TRAIT, SalesChannelApiTestBehaviour::class), 27],
            // the local trait itself is harmless, the kernel behaviour it composes is reported
            [\sprintf(NoKernelInUnitTestsRule::ERROR_TRAIT, KernelTestBehaviour::class), 40],
            // NOT flagged: 53 (a lifecycle call is NoKernelLifecycleManagerInUnitTestsRule's job), 66 (EnvTestBehaviour
            // restores state and never boots)
        ]);
    }

    public function testRuleDoesNotEnforceOutsideEnabledNamespaces(): void
    {
        $this->analyse([__DIR__ . '/data/NoKernelInUnitTestsRule/CasesOutOfScope.php'], []);
    }

    protected function getRule(): Rule
    {
        return new NoKernelInUnitTestsRule(
            new Configuration(['kernelInUnitTestsEnabledNamespaces' => ['Shopware\\Tests\\Unit\\']]),
        );
    }
}

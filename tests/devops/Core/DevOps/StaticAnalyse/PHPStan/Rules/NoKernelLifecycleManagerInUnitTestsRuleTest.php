<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoKernelLifecycleManagerInUnitTestsRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;

require_once __DIR__ . '/data/NoKernelLifecycleManagerInUnitTestsRule/LifecycleFixtureBehaviour.php';
require_once __DIR__ . '/data/NoKernelLifecycleManagerInUnitTestsRule/BaseCases.php';
require_once __DIR__ . '/data/NoKernelLifecycleManagerInUnitTestsRule/ComposedTraitCases.php';
// the real EnvTestBehaviour is analysed in the scope of HarmlessCases, so PHPStan must be able to load that class
require_once __DIR__ . '/data/NoKernelLifecycleManagerInUnitTestsRule/Cases.php';

/**
 * @internal
 *
 * @extends RuleTestCase<NoKernelLifecycleManagerInUnitTestsRule>
 */
#[Package('framework')]
class NoKernelLifecycleManagerInUnitTestsRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([
            __DIR__ . '/data/NoKernelLifecycleManagerInUnitTestsRule/Cases.php',
            __DIR__ . '/data/NoKernelLifecycleManagerInUnitTestsRule/ComposedTraitCases.php',
            // PHPStan only reports trait errors when the trait file is part of the analysed set
            __DIR__ . '/data/NoKernelLifecycleManagerInUnitTestsRule/LifecycleFixtureBehaviour.php',
            __DIR__ . '/data/NoKernelLifecycleManagerInUnitTestsRule/BaseCases.php',
            // the real trait, analysed in the scope of HarmlessCases: its shutdown call must stay silent
            $this->envTestBehaviourFile(),
        ], [
            // errors come ordered by file path: BaseCases, Cases, LifecycleFixtureBehaviour
            // the call in the parent test class is reported when the parent is analysed
            [\sprintf(NoKernelLifecycleManagerInUnitTestsRule::ERROR_LIFECYCLE_MANAGER, 'getContainer'), 16],
            [\sprintf(NoKernelLifecycleManagerInUnitTestsRule::ERROR_LIFECYCLE_MANAGER, 'getKernel'), 16],
            // the call hidden in the composed trait is reported where it stands, in the scope of the composing class
            [\sprintf(NoKernelLifecycleManagerInUnitTestsRule::ERROR_LIFECYCLE_MANAGER, 'getKernel'), 16],
            // NOT flagged: HarmlessCases, neither its own shutdown call nor the one in EnvTestBehaviour
        ]);
    }

    public function testRuleDoesNotEnforceOutsideEnabledNamespaces(): void
    {
        $this->analyse([__DIR__ . '/data/NoKernelLifecycleManagerInUnitTestsRule/CasesOutOfScope.php'], []);
    }

    protected function getRule(): Rule
    {
        return new NoKernelLifecycleManagerInUnitTestsRule(
            new Configuration(['kernelInUnitTestsEnabledNamespaces' => ['Shopware\\Tests\\Unit\\']]),
        );
    }

    private function envTestBehaviourFile(): string
    {
        $file = (new \ReflectionClass(EnvTestBehaviour::class))->getFileName();
        static::assertIsString($file);

        return $file;
    }
}

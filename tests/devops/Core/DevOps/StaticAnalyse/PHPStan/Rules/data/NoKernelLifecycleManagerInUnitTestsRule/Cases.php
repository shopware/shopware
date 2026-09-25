<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoKernelLifecycleManagerInUnitTestsRule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

/**
 * @internal
 */
class DirectCallCases extends TestCase
{
    public function testOne(): void
    {
        $kernel = KernelLifecycleManager::getKernel();

        static::assertNotNull($kernel);
    }
}

/**
 * @internal
 */
class InheritedCases extends BaseCases
{
    public function testOne(): void
    {
        static::assertNotNull($this->container());
    }
}

/**
 * @internal
 */
class HarmlessCases extends TestCase
{
    use EnvTestBehaviour;

    public function testOne(): void
    {
        KernelLifecycleManager::ensureKernelShutdown();

        static::assertTrue(true);
    }
}

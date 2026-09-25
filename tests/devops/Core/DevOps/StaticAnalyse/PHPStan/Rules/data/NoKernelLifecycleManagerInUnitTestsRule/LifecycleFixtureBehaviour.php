<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoKernelLifecycleManagerInUnitTestsRule;

use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A fixture trait hiding the lifecycle call from the test class body; loaded by the rule test so PHPStan
 * analyses it in the scope of the composing class.
 */
trait LifecycleFixtureBehaviour
{
    private function container(): ContainerInterface
    {
        return KernelLifecycleManager::getKernel()->getContainer();
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoKernelLifecycleManagerInUnitTestsRule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A base test class hiding the lifecycle call from its subclasses; loaded by the rule test.
 */
abstract class BaseCases extends TestCase
{
    protected function container(): ContainerInterface
    {
        return KernelLifecycleManager::getContainer();
    }
}

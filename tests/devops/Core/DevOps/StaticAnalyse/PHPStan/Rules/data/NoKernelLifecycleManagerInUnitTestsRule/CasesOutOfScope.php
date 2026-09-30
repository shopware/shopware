<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Example\NoKernelLifecycleManagerInUnitTestsRule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

/**
 * @internal
 *
 * The migration suite runs against a database, so reaching the kernel is legitimate there.
 */
class CasesOutOfScope extends TestCase
{
    public function testOne(): void
    {
        static::assertNotNull(KernelLifecycleManager::getKernel());
    }
}

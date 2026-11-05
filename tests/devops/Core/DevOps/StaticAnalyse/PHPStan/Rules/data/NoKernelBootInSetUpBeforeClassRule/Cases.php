<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoKernelBootInSetUpBeforeClassRule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

/**
 * @internal
 */
class BootsInStaticHooks extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        KernelLifecycleManager::bootKernel(true, 'cache-id');
    }

    public static function tearDownAfterClass(): void
    {
        KernelLifecycleManager::bootKernel(true, 'cache-id');
    }
}

/**
 * @internal
 */
class CreatesKernelInStaticHook extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        KernelLifecycleManager::createKernel();
    }
}

/**
 * @internal
 */
class BootsLazily extends TestCase
{
    private static bool $booted = false;

    public static function tearDownAfterClass(): void
    {
        KernelLifecycleManager::ensureKernelShutdown();
        self::$booted = false;
    }

    protected function setUp(): void
    {
        if (!self::$booted) {
            KernelLifecycleManager::bootKernel(true, 'cache-id');
            self::$booted = true;
        }
    }
}

/**
 * @internal not a test class: the rule must not fire outside TestCase subclasses
 */
class SomeSupportClass
{
    public static function setUpBeforeClass(): void
    {
        KernelLifecycleManager::bootKernel(true, 'cache-id');
    }
}

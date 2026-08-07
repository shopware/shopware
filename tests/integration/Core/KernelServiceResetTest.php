<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Kernel\HttpCacheKernel;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestResetCounter;
use Symfony\Component\HttpFoundation\Request;

/**
 * Covers the request lifecycle contract of `Shopware\Core\Kernel` that long running
 * runtimes (FrankenPHP, RoadRunner, Swoole) rely on: Symfony resets all `kernel.reset`
 * services between two requests handled by the same kernel instance.
 *
 * @internal
 */
#[Package('framework')]
class KernelServiceResetTest extends TestCase
{
    public function testServicesAreResetBetweenTwoRequests(): void
    {
        $kernel = KernelLifecycleManager::createKernel();
        $kernel->boot();

        try {
            $counter = $kernel->getContainer()->get(TestResetCounter::class);
            static::assertInstanceOf(TestResetCounter::class, $counter);

            $kernel->handle(Request::create('/api/_info/version'));

            // the reset happens lazily at the start of the next request, not after the previous one
            static::assertSame(0, $counter->resetCount);

            $kernel->handle(Request::create('/api/_info/version'));

            static::assertSame(1, $counter->resetCount);
        } finally {
            $kernel->shutdown();
        }
    }

    /**
     * Symfony's `http_cache` service must not exist: `Symfony\Component\HttpKernel\Kernel::handle()`
     * would route the first request of a process through it, bypassing Shopware's own HTTP cache
     * and skipping the service reset bookkeeping.
     */
    public function testSymfonyHttpCacheIsDisabled(): void
    {
        $container = KernelLifecycleManager::getKernel()->getContainer();

        static::assertFalse($container->has('http_cache'));
    }

    public function testHttpKernelIsDecoratedWithShopwareHttpCacheKernel(): void
    {
        $httpKernel = KernelLifecycleManager::getKernel()->getContainer()->get('http_kernel');

        static::assertInstanceOf(HttpCacheKernel::class, $httpKernel);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Framework\Adapter\Kernel;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Doctrine\DBAL\Driver\Middleware;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Doctrine\ProfilingMiddleware;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Adapter\Kernel\KernelFactory
 */
#[Package('core')]
class KernelFactoryTest extends TestCase
{
    public function testProfilingMiddlewareIsAddedWhenFlagPresent(): void
    {
        if (!InstalledVersions::isInstalled('symfony/doctrine-bridge')) {
            static::markTestSkipped('profiler not installed');
        }

        $_SERVER['argv'][] = '--profile';

        /** @var \Shopware\Core\Kernel $kernel */
        $kernel = KernelFactory::create(
            'dev',
            true,
            new ClassLoader(),
        );

        $middlewares = array_map(
            fn (Middleware $middleware) => $middleware::class,
            $kernel::getConnection()->getConfiguration()->getMiddlewares()
        );

        static::assertContains(ProfilingMiddleware::class, $middlewares);
    }
}

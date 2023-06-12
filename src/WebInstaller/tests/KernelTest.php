<?php
declare(strict_types=1);

namespace Shopware\WebInstaller\Tests;

use Shopware\WebInstaller\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Routing\Router;

/**
 * @internal
 *
 * @covers \App\Kernel
 */
class KernelTest extends TestCase
{
    public function testKernel(): void
    {
        $kernel = new Kernel('test', true);

        static::assertSame('test', $kernel->getEnvironment());
    }

    public function testBundles(): void
    {
        $kernel = new Kernel('test', true);

        $bundles = $kernel->registerBundles();

        static::assertCount(2, $bundles);
        static::assertInstanceOf(FrameworkBundle::class, $bundles[0]);
        static::assertInstanceOf(TwigBundle::class, $bundles[1]);
    }

    public function testProjectDir(): void
    {
        $kernel = new Kernel('test', true);

        static::assertSame(realpath(__DIR__ . '/../src'), $kernel->getProjectDir());
    }

    public function testCacheDir(): void
    {
        $kernel = new Kernel('test', true);

        static::assertSame(sys_get_temp_dir() . '/shopware-recovery@git_commit_short@/', $kernel->getCacheDir());
        static::assertSame(sys_get_temp_dir() . '/shopware-recovery@git_commit_short@/', $kernel->getLogDir());
    }

    public function testBuildKernel(): void
    {
        $kernel = new Kernel('test', true);
        $kernel->boot();

        static::assertTrue($kernel->getContainer()->has('router'));

        /** @var Router $router */
        $router = $kernel->getContainer()->get('router');
        static::assertCount(12, $router->getRouteCollection());
    }
}

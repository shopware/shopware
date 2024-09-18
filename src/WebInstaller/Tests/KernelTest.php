<?php
declare(strict_types=1);

namespace Shopware\WebInstaller\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\WebInstaller\Kernel;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Routing\Router;

/**
 * @internal
 */
#[CoversClass(Kernel::class)]
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

        static::assertSame(realpath(__DIR__ . '/..'), $kernel->getProjectDir());
    }

    public function testCacheDir(): void
    {
        $kernel = new Kernel('test', true);

        /** @var string $fileName */
        $fileName = (new \ReflectionClass($kernel))->getFileName();
        $kernelPath = Hasher::hash(\dirname($fileName));

        static::assertSame(sys_get_temp_dir() . '/shopware-recovery@git_commit_short@' . $kernelPath . '/', $kernel->getCacheDir());
        static::assertSame(sys_get_temp_dir() . '/shopware-recovery@git_commit_short@' . $kernelPath . '/', $kernel->getLogDir());
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

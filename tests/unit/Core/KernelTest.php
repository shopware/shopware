<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Kernel;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RouteCollection;
use Symfony\UX\TwigComponent\TwigComponentBundle;

/**
 * @internal
 */
#[CoversClass(Kernel::class)]
class KernelTest extends TestCase
{
    private string $tmpProjectDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->tmpProjectDir = __DIR__ . '/tmpToBeRemoved';
        $this->filesystem = new Filesystem();
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tmpProjectDir);
    }

    public function testGetCacheDir(): void
    {
        static::assertStringStartsWith($this->tmpProjectDir . '/var/cache/fooBar_h', $this->createKernel()->getCacheDir());
    }

    public function testDumpContainerDumpsPreloadFile(): void
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('kernel.cache_dir', $this->tmpProjectDir . '/var/cache/fooBar_h123abc');
        $containerBuilder->compile();

        (new \ReflectionMethod(Kernel::class, 'dumpContainer'))->invoke(
            $this->createKernel(),
            new ConfigCache($this->tmpProjectDir . '/cache-file', true),
            $containerBuilder,
            'Shopware_Core_KernelDevDebugContainer',
            'Container',
        );

        static::assertTrue($this->filesystem->exists($this->tmpProjectDir . '/var/cache/CACHEDIR.TAG'));
        static::assertTrue($this->filesystem->exists($this->tmpProjectDir . '/var/cache/opcache-preload.php'));
    }

    public function testDumpContainerDoesNotDumpPreloadFileIfWarmupCacheDirIsGiven(): void
    {
        $containerBuilder = new ContainerBuilder();
        // An underscore at the end indicates a warmup cache directory
        $containerBuilder->setParameter('kernel.cache_dir', $this->tmpProjectDir . '/var/cache/fooBar_h123abc_');
        $containerBuilder->compile();

        (new \ReflectionMethod(Kernel::class, 'dumpContainer'))->invoke(
            $this->createKernel(),
            new ConfigCache($this->tmpProjectDir . '/cache', true),
            $containerBuilder,
            'Shopware_Core_KernelDevDebugContainer',
            'Container',
        );

        static::assertTrue($this->filesystem->exists($this->tmpProjectDir . '/var/cache/CACHEDIR.TAG'));

        // Do not create the preload file in warmup cache
        static::assertFalse($this->filesystem->exists($this->tmpProjectDir . '/var/cache/opcache-preload.php'));
    }

    public function testRegisterBundlesAutoAddsTwigComponentBundleWhenMissingPreV68(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $this->writeBundlesConfig([]);
        $this->expectUserDeprecationMessageMatches('/TwigComponentBundle bundle should be added/');

        $bundles = iterator_to_array($this->createKernel()->registerBundles());

        static::assertSame([TwigComponentBundle::class], array_values(array_map(
            static fn (object $bundle): string => $bundle::class,
            $bundles
        )));
    }

    public function testRegisterBundlesDoesNotDuplicateTwigComponentBundleWhenConfiguredPreV68(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $this->writeBundlesConfig([
            TwigComponentBundle::class => ['all' => true],
        ]);

        $bundles = iterator_to_array($this->createKernel()->registerBundles());

        static::assertSame([TwigComponentBundle::class], array_values(array_map(
            static fn (object $bundle): string => $bundle::class,
            $bundles
        )));
    }

    public function testConfigureRoutesImportsProjectRoutesScopedToEnvironment(): void
    {
        $confDir = $this->tmpProjectDir . '/config';

        $captured = $this->captureRouteImports('test');

        static::assertContains([$confDir . '/{routes}/*' . Kernel::CONFIG_EXTS, 'glob'], $captured);
        static::assertContains([$confDir . '/{routes}/test/**/*' . Kernel::CONFIG_EXTS, 'glob'], $captured);
        static::assertContains([$confDir . '/{routes}' . Kernel::CONFIG_EXTS, 'glob'], $captured);
    }

    public function testConfigureRoutesDoesNotImportForeignEnvironmentGlobs(): void
    {
        $confDir = $this->tmpProjectDir . '/config';

        $captured = $this->captureRouteImports('prod');

        static::assertContains([$confDir . '/{routes}/prod/**/*' . Kernel::CONFIG_EXTS, 'glob'], $captured);
        static::assertNotContains([$confDir . '/{routes}/test/**/*' . Kernel::CONFIG_EXTS, 'glob'], $captured);
    }

    private function createKernel(string $environment = 'fooBar'): Kernel
    {
        return new Kernel(
            $environment,
            true,
            $this->createMock(StaticKernelPluginLoader::class),
            'cacheId',
            '6.6.6',
            $this->createMock(Connection::class),
            $this->tmpProjectDir,
        );
    }

    /**
     * @return list<array{0: mixed, 1: ?string}>
     */
    private function captureRouteImports(string $environment): array
    {
        $captured = [];
        $loader = $this->createMock(PhpFileLoader::class);
        $loader->method('import')->willReturnCallback(
            function (mixed $resource, ?string $type = null) use (&$captured): array {
                $captured[] = [$resource, $type];

                return [];
            }
        );

        (new \ReflectionMethod(Kernel::class, 'configureRoutes'))->invoke(
            $this->createKernel($environment),
            new RoutingConfigurator(new RouteCollection(), $loader, '/tmp', '/tmp'),
        );

        return $captured;
    }

    /**
     * @param array<string, array<string, bool>> $bundles
     */
    private function writeBundlesConfig(array $bundles): void
    {
        $configDir = $this->tmpProjectDir . '/config';
        $this->filesystem->mkdir($configDir);
        $this->filesystem->dumpFile(
            $configDir . '/bundles.php',
            "<?php\n\nreturn " . var_export($bundles, true) . ";\n"
        );
    }
}

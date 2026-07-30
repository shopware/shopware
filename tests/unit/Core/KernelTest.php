<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Kernel;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RouteCollection;
use Symfony\UX\TwigComponent\TwigComponentBundle;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Kernel::class)]
class KernelTest extends TestCase
{
    private const EMPTY_SERVICES_XML = '<?xml version="1.0" ?><container xmlns="http://symfony.com/schema/dic/services"></container>';

    private const EMPTY_ROUTES_XML = '<?xml version="1.0" ?><routes xmlns="http://symfony.com/schema/routing"></routes>';

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

    public function testConfigureContainerTriggersDeprecationForXmlPackageConfiguration(): void
    {
        $path = $this->tmpProjectDir . '/config/packages/unit_test.xml';
        $this->filesystem->dumpFile($path, self::EMPTY_SERVICES_XML);

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: The XML configuration file "%s" in the project configuration directory is deprecated and will not be loaded in v6.8.0.0. Migrate the package configuration to YAML or PHP format.',
            $path,
        )));

        $this->captureContainerLoads('fooBar');
    }

    public function testConfigureContainerTriggersDeprecationForXmlServiceDefinitions(): void
    {
        $path = $this->tmpProjectDir . '/config/services.xml';
        $this->filesystem->dumpFile($path, self::EMPTY_SERVICES_XML);

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: The XML configuration file "%s" in the project configuration directory is deprecated and will not be loaded in v6.8.0.0. Migrate the service definitions to PHP format (services.php).',
            $path,
        )));

        $this->captureContainerLoads('fooBar');
    }

    public function testConfigureContainerTriggersDeprecationForEnvironmentSpecificXmlServiceDefinitions(): void
    {
        $path = $this->tmpProjectDir . '/config/services_fooBar.xml';
        $this->filesystem->dumpFile($path, self::EMPTY_SERVICES_XML);

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: The XML configuration file "%s" in the project configuration directory is deprecated and will not be loaded in v6.8.0.0. Migrate the service definitions to PHP format (services_fooBar.php).',
            $path,
        )));

        $this->captureContainerLoads('fooBar');
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfigureContainerStillLoadsConfigGlobsWhenDeprecationsAreDisabled(): void
    {
        $confDir = $this->tmpProjectDir . '/config';
        $this->filesystem->dumpFile($confDir . '/services.xml', self::EMPTY_SERVICES_XML);

        $captured = $this->captureContainerLoads('fooBar');

        static::assertContains([$confDir . '/{services}' . Kernel::CONFIG_EXTS, 'glob'], $captured);
    }

    public function testConfigureRoutesTriggersDeprecationForXmlRouteDefinitions(): void
    {
        $path = $this->tmpProjectDir . '/config/routes.xml';
        $this->filesystem->dumpFile($path, self::EMPTY_ROUTES_XML);

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: The XML configuration file "%s" in the project configuration directory is deprecated and will not be loaded in v6.8.0.0. Migrate the route definitions to PHP format (routes.php).',
            $path,
        )));

        $this->captureRouteImports('test');
    }

    public function testConfigureRoutesTriggersDeprecationForXmlFilesInRoutesDirectory(): void
    {
        $path = $this->tmpProjectDir . '/config/routes/nested.xml';
        $this->filesystem->dumpFile($path, self::EMPTY_ROUTES_XML);

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: The XML configuration file "%s" in the project configuration directory is deprecated and will not be loaded in v6.8.0.0. Migrate the route definitions to PHP format (nested.php).',
            $path,
        )));

        $this->captureRouteImports('test');
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfigureRoutesStillReachesRouteImportWhenDeprecationsAreDisabled(): void
    {
        $confDir = $this->tmpProjectDir . '/config';
        $this->filesystem->dumpFile($confDir . '/routes.xml', self::EMPTY_ROUTES_XML);

        $captured = $this->captureRouteImports('test');

        static::assertContains([$confDir . '/{routes}' . Kernel::CONFIG_EXTS, 'glob'], $captured);
    }

    private function createKernel(string $environment = 'fooBar'): KernelStub
    {
        return new KernelStub(
            $environment,
            true,
            static::createStub(StaticKernelPluginLoader::class),
            'cacheId',
            '6.6.6',
            static::createStub(Connection::class),
            $this->tmpProjectDir,
        );
    }

    /**
     * @return list<array{0: mixed, 1: ?string}>
     */
    private function captureContainerLoads(string $environment): array
    {
        $captured = [];
        $loader = static::createStub(LoaderInterface::class);
        $loader->method('load')->willReturnCallback(
            function (mixed $resource, ?string $type = null) use (&$captured): mixed {
                $captured[] = [$resource, $type];

                return null;
            }
        );

        $this->createKernel($environment)->runConfigureContainer(new ContainerBuilder(), $loader);

        return $captured;
    }

    /**
     * @return list<array{0: mixed, 1: ?string}>
     */
    private function captureRouteImports(string $environment): array
    {
        $captured = [];
        $loader = static::createStub(PhpFileLoader::class);
        $loader->method('import')->willReturnCallback(
            function (mixed $resource, ?string $type = null) use (&$captured): array {
                $captured[] = [$resource, $type];

                return [];
            }
        );

        $this->createKernel($environment)->runConfigureRoutes(
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

/**
 * @internal
 */
class KernelStub extends Kernel
{
    public function runConfigureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $this->configureContainer($container, $loader);
    }

    public function runConfigureRoutes(RoutingConfigurator $routes): void
    {
        $this->configureRoutes($routes);
    }
}

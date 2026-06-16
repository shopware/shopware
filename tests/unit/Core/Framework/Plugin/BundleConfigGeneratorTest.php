<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\BundleConfigGenerator;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Kernel;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[CoversClass(BundleConfigGenerator::class)]
class BundleConfigGeneratorTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = __DIR__ . '/fixtures/BundleConfigGenerator/project';
    }

    public function testConstructorThrowsException(): void
    {
        $this->expectExceptionObject(PluginException::invalidContainerParameter('kernel.project_dir', 'string'));
        new BundleConfigGenerator(
            $this->createMock(Kernel::class),
            $this->createMock(ActiveAppsLoader::class)
        );
    }

    public function testGetConfigBuildsBundleAndAppConfigAndSkipsInactivePlugin(): void
    {
        $coreBundlePath = $this->projectDir . '/src/CoreBundle';

        $activePluginPath = $this->projectDir . '/extensions/plugins/ActivePlugin';

        $inactivePluginPath = $this->projectDir . '/extensions/plugins/InactivePlugin';

        $appRelativePath = 'extensions/apps/SwagDemoApp';

        $coreBundle = new class($coreBundlePath) extends Bundle {
            public function __construct(private string $bundlePath)
            {
            }

            public function getPath(): string
            {
                return $this->bundlePath;
            }
        };

        $activePlugin = new class(true, $activePluginPath, $this->projectDir) extends Plugin {
            public function getPath(): string
            {
                return $this->getBasePath();
            }
        };

        $inactivePlugin = new class(true, $inactivePluginPath, $this->projectDir) extends Plugin {
            public function getPath(): string
            {
                return $this->getBasePath();
            }
        };

        $coreBundleName = $coreBundle->getName();
        $activePluginName = $activePlugin->getName();
        $inactivePluginName = $inactivePlugin->getName();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('getParameter')->with('kernel.project_dir')->willReturn($this->projectDir);
        $container->method('get')
            ->with(StorefrontPluginRegistry::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)
            ->willReturn(null);

        $pluginLoader = $this->createMock(KernelPluginLoader::class);
        $pluginLoader->method('getPluginInstances')->willReturn(new KernelPluginCollection([$activePlugin::class => $activePlugin]));

        $kernel = $this->createMock(Kernel::class);
        $kernel->method('getContainer')->willReturn($container);
        $kernel->method('getPluginLoader')->willReturn($pluginLoader);
        $kernel->method('getBundles')->willReturn([$coreBundle, $activePlugin, $inactivePlugin]);

        $activeAppsLoader = $this->createMock(ActiveAppsLoader::class);
        $activeAppsLoader->method('getActiveApps')->willReturn([
            ['name' => 'SwagDemoApp', 'path' => $appRelativePath],
        ]);

        $generator = new BundleConfigGenerator($kernel, $activeAppsLoader);
        $config = $generator->getConfig();

        static::assertArrayHasKey($coreBundleName, $config);
        static::assertArrayHasKey($activePluginName, $config);
        static::assertArrayHasKey('SwagDemoApp', $config);
        static::assertArrayNotHasKey($inactivePluginName, $config);

        static::assertSame('src/CoreBundle/', $config[$coreBundleName]['basePath']);
        static::assertArrayHasKey('administration', $config[$coreBundleName]);
        static::assertSame('Resources/app/administration/src/main.js', $config[$coreBundleName]['administration']['entryFilePath']);
        static::assertSame('Resources/app/administration/build/webpack.config.ts', $config[$coreBundleName]['administration']['webpack']);
        static::assertSame('Resources/app/storefront/src/main.ts', $config[$coreBundleName]['storefront']['entryFilePath']);
        static::assertSame('Resources/app/storefront/build/webpack.config.cts', $config[$coreBundleName]['storefront']['webpack']);
        static::assertTrue($config[$coreBundleName]['storefront']['hasComponentAssets']);
        static::assertSame([], $config[$coreBundleName]['storefront']['styleFiles']);

        static::assertSame('extensions/plugins/ActivePlugin/', $config[$activePluginName]['basePath']);
        static::assertNotSame('', $config[$activePluginName]['technicalName']);

        static::assertSame($appRelativePath . '/', $config['SwagDemoApp']['basePath']);
        static::assertSame('swag-demo-app', $config['SwagDemoApp']['technicalName']);
        static::assertNull($config['SwagDemoApp']['storefront']['entryFilePath']);
        static::assertSame('Resources/app/storefront/build/webpack.config.ts', $config['SwagDemoApp']['storefront']['webpack']);
    }

    public function testGetStyleFilesUsesRegistryConfigAndReturnsJoinedPaths(): void
    {
        $bundlePath = $this->projectDir . '/custom/plugins/SwagPlugin';
        $bundle = new class($bundlePath) extends Bundle {
            public function __construct(private string $bundlePath)
            {
            }

            public function getPath(): string
            {
                return $this->bundlePath;
            }
        };
        $bundleName = $bundle->getName();

        $registry = $this->createMock(StorefrontPluginRegistry::class);

        $configuration = new StorefrontPluginConfiguration($bundleName);
        $configuration->setStyleFiles(FileCollection::createFromArray([
            'app/storefront/src/scss/base.scss',
            'app/storefront/src/scss/overrides.scss',
        ]));

        $registry->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection([$configuration])
        );

        $kernel = $this->createKernelWithBundles([$bundle], $registry);
        $generator = new BundleConfigGenerator($kernel, $this->createMock(ActiveAppsLoader::class));
        $config = $generator->getConfig();

        static::assertSame(
            [
                'custom/plugins/SwagPlugin/Resources/app/storefront/src/scss/base.scss',
                'custom/plugins/SwagPlugin/Resources/app/storefront/src/scss/overrides.scss',
            ],
            $config[$bundleName]['storefront']['styleFiles']
        );
    }

    public function testHasStorefrontComponentAssetsIgnoresNonBuildableFiles(): void
    {
        $bundlePath = $this->projectDir . '/extensions/plugins/IgnoredAssets';

        $bundle = new class($bundlePath) extends Bundle {
            public function __construct(private string $bundlePath)
            {
            }

            public function getPath(): string
            {
                return $this->bundlePath;
            }
        };
        $bundleName = $bundle->getName();

        $kernel = $this->createKernelWithBundles([$bundle]);
        $generator = new BundleConfigGenerator($kernel, $this->createMock(ActiveAppsLoader::class));
        $config = $generator->getConfig();

        static::assertFalse($config[$bundleName]['storefront']['hasComponentAssets']);
    }

    /**
     * @param list<Bundle> $bundles
     */
    private function createKernelWithBundles(array $bundles, ?StorefrontPluginRegistry $registry = null): Kernel
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('getParameter')->with('kernel.project_dir')->willReturn($this->projectDir);
        $container->method('get')
            ->with(StorefrontPluginRegistry::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)
            ->willReturn($registry);

        $pluginLoader = $this->createMock(KernelPluginLoader::class);
        $pluginLoader->method('getPluginInstances')->willReturn(new KernelPluginCollection([]));

        $kernel = $this->createMock(Kernel::class);
        $kernel->method('getContainer')->willReturn($container);
        $kernel->method('getPluginLoader')->willReturn($pluginLoader);
        $kernel->method('getBundles')->willReturn($bundles);

        return $kernel;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Plugin\KernelPluginLoader;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Test\Plugin\PluginIntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\Plugin\_fixtures\bundles\FooBarBundle;
use Shopware\Tests\Integration\Core\Framework\Plugin\_fixtures\bundles\GizmoBundle;
use SwagTestPlugin\SwagTestFake;
use SwagTestPlugin\SwagTestPlugin;
use SwagTestWithBundle\SwagTestWithBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
class StaticKernelPluginLoaderTest extends TestCase
{
    use PluginIntegrationTestBehaviour;

    public function testNoPlugins(): void
    {
        $loader = $this->createKernelPluginLoaderWithPlugins([]);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertEmpty($loader->getPluginInfos());
        static::assertEmpty($loader->getPluginInstances()->all());
    }

    public function testNoKernelPluginsWithoutInit(): void
    {
        $activePluginData = $this->getActivePlugin()->jsonSerialize();
        $loader = $this->createKernelPluginLoaderWithPlugins([$activePluginData]);

        static::assertCount(1, $loader->getPluginInfos());
        static::assertEmpty($loader->getPluginInstances()->all());
    }

    public function testKernelPluginsAfterInit(): void
    {
        $activePluginData = $this->getActivePlugin()->jsonSerialize();
        $loader = $this->createKernelPluginLoaderWithPlugins([$activePluginData]);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertCount(1, $loader->getPluginInfos());
        $kernelPlugins = $loader->getPluginInstances();
        static::assertCount(1, $kernelPlugins->all());
        static::assertInstanceOf(Plugin::class, $kernelPlugins->get($activePluginData['baseClass']));
    }

    public function testNonExistingPluginIsSkipped(): void
    {
        $active = $this->getActivePlugin();
        /** @phpstan-ignore argument.type (for test purpose) */
        $active->setBaseClass('SomeNotExistingBaseClass');

        $plugins = [$active->jsonSerialize()];
        $loader = $this->createKernelPluginLoaderWithPlugins($plugins);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertCount(1, $loader->getPluginInfos());
        $kernelPlugins = $loader->getPluginInstances()->all();
        static::assertCount(0, $kernelPlugins);
    }

    #[RunInSeparateProcess]
    public function testManagedByComposerIsSkipped(): void
    {
        $active = $this->getActivePlugin();
        $active->setManagedByComposer(true);
        $plugins = [$active->jsonSerialize()];

        $loader = $this->createKernelPluginLoaderWithPlugins($plugins);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertCount(1, $loader->getPluginInfos());
        $kernelPlugins = $loader->getPluginInstances()->all();
        static::assertCount(0, $kernelPlugins);
    }

    public function testExpectExceptionOnMissingAutoload(): void
    {
        $active = $this->getActivePlugin()->jsonSerialize();
        unset($active['autoload']);
        $plugins = [$active];

        $loader = $this->createKernelPluginLoaderWithPlugins($plugins);

        $this->expectExceptionObject(PluginException::kernelPluginLoaderError('SwagTestPlugin', 'Unable to register plugin "SwagTestPlugin\SwagTestPlugin" in autoload. Required property `autoload` missing.'));
        $loader->initializePlugins(TEST_PROJECT_DIR);
    }

    public function testExpectExceptionOnMissingAutoloadPsr(): void
    {
        $active = $this->getActivePlugin();
        $active->setAutoload([]);
        $plugins = [$active->jsonSerialize()];

        $loader = $this->createKernelPluginLoaderWithPlugins($plugins);

        $this->expectExceptionObject(PluginException::kernelPluginLoaderError('SwagTestPlugin', 'Unable to register plugin "SwagTestPlugin\SwagTestPlugin" in autoload. Required property `psr-4` or `psr-0` missing in property autoload.'));
        $loader->initializePlugins(TEST_PROJECT_DIR);
    }

    public function testGetPluginInstance(): void
    {
        $activePluginData = $this->getActivePlugin()->jsonSerialize();
        $loader = $this->createKernelPluginLoaderWithPlugins([$activePluginData]);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertCount(1, $loader->getPluginInfos());

        $class = $activePluginData['baseClass'];
        $kernelPlugin = $loader->getPluginInstances()->get($class);
        static::assertNotEmpty($kernelPlugin);
        static::assertSame($kernelPlugin, $loader->getPluginInstance($class));
    }

    public function testGetPluginInstanceNotActive(): void
    {
        $pluginData = $this->getInstalledInactivePlugin()->jsonSerialize();
        $loader = $this->createKernelPluginLoaderWithPlugins([$pluginData]);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertCount(1, $loader->getPluginInfos());

        $class = $pluginData['baseClass'];
        $kernelPlugin = $loader->getPluginInstances()->get($class);
        static::assertNotEmpty($kernelPlugin);
        static::assertNull($loader->getPluginInstance($class));
    }

    public function testGetPluginDir(): void
    {
        $projectDir = TEST_PROJECT_DIR;

        $loader = $this->createKernelPluginLoaderWithOptionalPluginDirectory();
        static::assertSame($projectDir . '/custom/plugins', $loader->getPluginDir($projectDir));

        $loader = $this->createKernelPluginLoaderWithOptionalPluginDirectory('foo/bar');
        static::assertSame($projectDir . '/foo/bar', $loader->getPluginDir($projectDir));
    }

    public function testGetPluginDirAbsolute(): void
    {
        $projectDir = TEST_PROJECT_DIR;

        $loader = $this->createKernelPluginLoaderWithOptionalPluginDirectory($projectDir . '/custom/plugins');
        static::assertSame($projectDir . '/custom/plugins', $loader->getPluginDir($projectDir));

        $loader = $this->createKernelPluginLoaderWithOptionalPluginDirectory('/foo/bar');
        static::assertSame('/foo/bar', $loader->getPluginDir($projectDir));
    }

    public function testGetClassLoader(): void
    {
        $loader = $this->createKernelPluginLoaderWithOptionalPluginDirectory();
        static::assertSame($this->classLoader, $loader->getClassLoader());
    }

    public function testGetBundlesNoInit(): void
    {
        $activePluginData = $this->getActivePlugin()->jsonSerialize();
        $loader = $this->createKernelPluginLoaderWithPlugins([$activePluginData]);

        $bundles = iterator_to_array($loader->getBundles());

        static::assertEmpty($bundles);
    }

    public function testGetBundlesNoPlugins(): void
    {
        $loader = $this->createKernelPluginLoaderWithOptionalPluginDirectory();
        $loader->initializePlugins(TEST_PROJECT_DIR);

        $bundles = iterator_to_array($loader->getBundles());

        static::assertCount(1, $bundles);
        static::assertSame($loader, $bundles[0]);
    }

    public function testGetBundles(): void
    {
        $activePluginData = $this->getActivePlugin()->jsonSerialize();
        $loader = $this->createKernelPluginLoaderWithPlugins([$activePluginData]);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        $bundles = iterator_to_array($loader->getBundles());

        static::assertCount(4, $bundles);
        static::assertInstanceOf(GizmoBundle::class, $bundles[0]);
        static::assertInstanceOf(SwagTestPlugin::class, $bundles[1]);
        static::assertInstanceOf(FooBarBundle::class, $bundles[2]);
        static::assertSame($loader, $bundles[3]);
    }

    public function testGetBundlesWithAdditionalBundlesThatAreDuplicatesButKeepOrder(): void
    {
        $activePluginData = $this->getActivePlugin()->jsonSerialize();
        $activePluginDataWithUnneededBundles = $this->getActivePluginWithBundle()->jsonSerialize();
        $loader = $this->createKernelPluginLoaderWithPlugins([$activePluginData, $activePluginDataWithUnneededBundles]);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        $bundles = iterator_to_array($loader->getBundles([], ['FrameworkBundle']));

        static::assertCount(5, $bundles);
        static::assertInstanceOf(GizmoBundle::class, $bundles[0]);
        static::assertInstanceOf(SwagTestPlugin::class, $bundles[1]);
        static::assertInstanceOf(FooBarBundle::class, $bundles[2]);
        static::assertInstanceOf(SwagTestWithBundle::class, $bundles[3]);
        static::assertSame($loader, $bundles[4]);
    }

    public function testGetBundlesNoActive(): void
    {
        $pluginData = $this->getInstalledInactivePlugin()->jsonSerialize();
        $loader = $this->createKernelPluginLoaderWithPlugins([$pluginData]);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        $bundles = iterator_to_array($loader->getBundles());

        static::assertCount(1, $bundles);
        static::assertSame($loader, $bundles[0]);
    }

    public function testExpectExceptionWithFakePlugin(): void
    {
        $plugin = $this->getActivePlugin();
        /** @phpstan-ignore argument.type (for test purpose) */
        $plugin->setBaseClass(SwagTestFake::class);

        $loader = $this->createKernelPluginLoaderWithPlugins([$plugin->jsonSerialize()]);

        $this->expectExceptionObject(PluginException::kernelPluginLoaderError('SwagTestPlugin', 'Plugin class "SwagTestPlugin\SwagTestFake" must extend "Shopware\Core\Framework\Plugin"'));
        $loader->initializePlugins(TEST_PROJECT_DIR);
    }

    public function testBuildNoInitShouldNotChangeContainer(): void
    {
        $activePluginData = $this->getActivePlugin()->jsonSerialize();
        $loader = $this->createKernelPluginLoaderWithPlugins([$activePluginData]);

        $emptyContainer = new ContainerBuilder();
        $container = new ContainerBuilder();

        static::assertEquals($emptyContainer, $container);
        $loader->build($container);

        static::assertEquals($emptyContainer, $container);
    }

    public function testBuildInactivePluginShouldNotChangeContainer(): void
    {
        $pluginData = $this->getInstalledInactivePlugin()->jsonSerialize();
        $loader = $this->createKernelPluginLoaderWithPlugins([$pluginData]);

        $emptyContainer = new ContainerBuilder();
        $container = new ContainerBuilder();

        static::assertEquals($emptyContainer, $container);
        $loader->build($container);

        static::assertEquals($emptyContainer, $container);
    }

    public function testBuild(): void
    {
        $activePluginData = $this->getActivePlugin()->jsonSerialize();
        $loader = $this->createKernelPluginLoaderWithPlugins([$activePluginData]);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        $container = new ContainerBuilder();
        $loader->build($container);

        $definition = $container->getDefinition(SwagTestPlugin::class);

        static::assertTrue($definition->isAutowired());
        static::assertTrue($definition->isPublic());
    }

    public function testBuildWithExistingDefinition(): void
    {
        $activePluginData = $this->getActivePlugin()->jsonSerialize();
        $loader = $this->createKernelPluginLoaderWithPlugins([$activePluginData]);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        $container = new ContainerBuilder();

        $definition = new Definition();
        $definition->setAutowired(false);
        $definition->setPublic(false);
        $container->setDefinition(SwagTestPlugin::class, $definition);

        $loader->build($container);

        $actualDefinition = $container->getDefinition(SwagTestPlugin::class);
        static::assertSame($definition, $actualDefinition);
        static::assertTrue($actualDefinition->isAutowired());
        static::assertTrue($actualDefinition->isPublic());
    }

    public function testPsr0IsAddedToClassMap(): void
    {
        $classLoader = $this->createMock(ClassLoader::class);

        $plugin = $this->getInstalledInactivePlugin();
        $plugin->setPath(TEST_PROJECT_DIR . '/custom/plugins/TestPlugin');
        $plugin->setAutoload([
            'psr-0' => [
                'Test_' => 'src',
            ],
        ]);

        $classLoader->expects($this->once())->method('add')->with('Test_', [
            TEST_PROJECT_DIR . '/custom/plugins/TestPlugin/src',
        ], false);

        $loader = $this->createKernelPluginLoaderWithPlugins([$plugin->jsonSerialize()], $classLoader);
        $loader->initializePlugins(TEST_PROJECT_DIR);
    }

    public function testExpectExceptionExternalPath(): void
    {
        $classLoader = $this->createMock(ClassLoader::class);

        $plugin = $this->getInstalledInactivePlugin();
        $plugin->setPath('/custom/plugins/TestPlugin');
        $plugin->setAutoload([
            'psr-0' => [
                'Test_' => 'src',
            ],
        ]);

        $this->expectExceptionObject(PluginException::kernelPluginLoaderError('SwagTestPlugin', 'Plugin dir /custom/plugins/TestPlugin needs to be a sub-directory of the project dir ' . TEST_PROJECT_DIR));

        $loader = $this->createKernelPluginLoaderWithPlugins([$plugin->jsonSerialize()], $classLoader);
        $loader->initializePlugins(TEST_PROJECT_DIR);
    }

    public function testPsr0WithRelativePathIsAddedToClassMap(): void
    {
        $classLoader = $this->createMock(ClassLoader::class);

        $plugin = $this->getInstalledInactivePlugin();
        $plugin->setPath('custom/plugins/TestPlugin');
        $plugin->setAutoload([
            'psr-0' => [
                'Test_' => 'src',
            ],
        ]);

        $classLoader->expects($this->once())->method('add')->with('Test_', [
            TEST_PROJECT_DIR . '/custom/plugins/TestPlugin/src',
        ], false);

        $loader = $this->createKernelPluginLoaderWithPlugins([$plugin->jsonSerialize()], $classLoader);
        $loader->initializePlugins(TEST_PROJECT_DIR);
    }

    public function testPsr0ArrayIsAddedToClassMap(): void
    {
        $classLoader = $this->createMock(ClassLoader::class);

        $plugin = $this->getInstalledInactivePlugin();
        $plugin->setPath('custom/plugins/TestPlugin');
        $plugin->setAutoload([
            'psr-0' => [
                'Test_' => ['src', 'components'],
            ],
        ]);

        $classLoader->expects($this->once())->method('add')->with('Test_', [
            TEST_PROJECT_DIR . '/custom/plugins/TestPlugin/src',
            TEST_PROJECT_DIR . '/custom/plugins/TestPlugin/components',
        ], false);

        $loader = $this->createKernelPluginLoaderWithPlugins([$plugin->jsonSerialize()], $classLoader);
        $loader->initializePlugins(TEST_PROJECT_DIR);
    }

    /**
     * @param list<array<string, mixed>> $plugins
     */
    private function createKernelPluginLoaderWithPlugins(array $plugins, ?ClassLoader $classLoader = null): StaticKernelPluginLoader
    {
        if ($classLoader === null) {
            $classLoader = $this->classLoader;
        }

        /** @phpstan-ignore argument.type (For test purposes it is enough to not provide fully fledged plugin information) */
        return new StaticKernelPluginLoader($classLoader, plugins: $plugins);
    }

    private function createKernelPluginLoaderWithOptionalPluginDirectory(?string $pluginDirectory = null): StaticKernelPluginLoader
    {
        return new StaticKernelPluginLoader($this->classLoader, $pluginDirectory);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin\KernelPluginLoader;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Exception\KernelPluginLoaderException;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Test\Plugin\_fixture\bundles\FooBarBundle;
use Shopware\Core\Framework\Test\Plugin\_fixture\bundles\GizmoBundle;
use Shopware\Core\Framework\Test\Plugin\PluginIntegrationTestBehaviour;
use SwagTest\SwagTest;
use SwagTest\SwagTestFake;
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
        $loader = new StaticKernelPluginLoader($this->classLoader, null, []);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertEmpty($loader->getPluginInfos());
        static::assertEmpty($loader->getPluginInstances()->all());
    }

    public function testNoKernelPluginsWithoutInit(): void
    {
        $activePluginData = $this->getActivePlugin()->jsonSerialize();
        $loader = new StaticKernelPluginLoader($this->classLoader, null, [$activePluginData]);

        static::assertCount(1, $loader->getPluginInfos());
        static::assertEmpty($loader->getPluginInstances()->all());
    }

    public function testKernelPluginsAfterInit(): void
    {
        $activePluginData = $this->getActivePlugin()->jsonSerialize();
        $loader = new StaticKernelPluginLoader($this->classLoader, null, [$activePluginData]);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertCount(1, $loader->getPluginInfos());
        $kernelPlugins = $loader->getPluginInstances();
        static::assertCount(1, $kernelPlugins->all());
        static::assertInstanceOf(Plugin::class, $kernelPlugins->get($activePluginData['baseClass']));
    }

    public function testNonExistingPluginIsSkipped(): void
    {
        $active = $this->getActivePlugin();
        $active->setBaseClass('SomeNotExistingBaseClass');

        $plugins = [$active->jsonSerialize()];
        $loader = new StaticKernelPluginLoader($this->classLoader, null, $plugins);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertCount(1, $loader->getPluginInfos());
        $kernelPlugins = $loader->getPluginInstances()->all();
        static::assertCount(0, $kernelPlugins);
    }

    public function testManagedByComposerIsSkipped(): void
    {
        $active = $this->getActivePlugin();
        $active->setManagedByComposer(true);
        $plugins = [$active->jsonSerialize()];

        $loader = new StaticKernelPluginLoader($this->classLoader, null, $plugins);
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

        $loader = new StaticKernelPluginLoader($this->classLoader, null, $plugins);

        $this->expectException(KernelPluginLoaderException::class);
        $this->expectExceptionMessage('Failed to load plugin "SwagTest". Reason: Unable to register plugin "SwagTest\SwagTest" in autoload. Required property `autoload` missing.');
        $loader->initializePlugins(TEST_PROJECT_DIR);
    }

    public function testExpectExceptionOnMissingAutoloadPsr(): void
    {
        $active = $this->getActivePlugin();
        $active->setAutoload([]);
        $plugins = [$active->jsonSerialize()];

        $loader = new StaticKernelPluginLoader($this->classLoader, null, $plugins);

        $this->expectException(KernelPluginLoaderException::class);
        $this->expectExceptionMessage('Failed to load plugin "SwagTest". Reason: Unable to register plugin "SwagTest\SwagTest" in autoload. Required property `psr-4` or `psr-0` missing in property autoload.');
        $loader->initializePlugins(TEST_PROJECT_DIR);
    }

    public function testGetPluginInstance(): void
    {
        $activePluginData = $this->getActivePlugin()->jsonSerialize();
        $loader = new StaticKernelPluginLoader($this->classLoader, null, [$activePluginData]);
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
        $loader = new StaticKernelPluginLoader($this->classLoader, null, [$pluginData]);
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

        $loader = new StaticKernelPluginLoader($this->classLoader);
        static::assertSame($projectDir . '/custom/plugins', $loader->getPluginDir($projectDir));

        $loader = new StaticKernelPluginLoader($this->classLoader, 'foo/bar');
        static::assertSame($projectDir . '/foo/bar', $loader->getPluginDir($projectDir));
    }

    public function testGetPluginDirAbsolute(): void
    {
        $projectDir = TEST_PROJECT_DIR;

        $loader = new StaticKernelPluginLoader($this->classLoader, $projectDir . '/custom/plugins');
        static::assertSame($projectDir . '/custom/plugins', $loader->getPluginDir($projectDir));

        $loader = new StaticKernelPluginLoader($this->classLoader, '/foo/bar');
        static::assertSame('/foo/bar', $loader->getPluginDir($projectDir));
    }

    public function testGetClassLoader(): void
    {
        $loader = new StaticKernelPluginLoader($this->classLoader);
        static::assertSame($this->classLoader, $loader->getClassLoader());
    }

    public function testGetBundlesNoInit(): void
    {
        $activePluginData = $this->getActivePlugin()->jsonSerialize();
        $loader = new StaticKernelPluginLoader($this->classLoader, null, [$activePluginData]);

        $bundles = iterator_to_array($loader->getBundles());

        static::assertEmpty($bundles);
    }

    public function testGetBundlesNoPlugins(): void
    {
        $loader = new StaticKernelPluginLoader($this->classLoader);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        $bundles = iterator_to_array($loader->getBundles());

        static::assertCount(1, $bundles);
        static::assertSame($loader, $bundles[0]);
    }

    public function testGetBundles(): void
    {
        $activePluginData = $this->getActivePlugin()->jsonSerialize();
        $loader = new StaticKernelPluginLoader($this->classLoader, null, [$activePluginData]);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        $bundles = iterator_to_array($loader->getBundles());

        static::assertCount(4, $bundles);
        static::assertInstanceOf(GizmoBundle::class, $bundles[0]);
        static::assertInstanceOf(SwagTest::class, $bundles[1]);
        static::assertInstanceOf(FooBarBundle::class, $bundles[2]);
        static::assertSame($loader, $bundles[3]);
    }

    public function testGetBundlesWithAdditionalBundlesThatAreDuplicatesButKeepOrder(): void
    {
        $activePluginData = $this->getActivePlugin()->jsonSerialize();
        $activePluginDataWithUnneededBundles = $this->getActivePluginWithBundle()->jsonSerialize();
        $loader = new StaticKernelPluginLoader($this->classLoader, null, [
            $activePluginData, $activePluginDataWithUnneededBundles,
        ]);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        $bundles = iterator_to_array($loader->getBundles([], ['FrameworkBundle']));

        static::assertCount(5, $bundles);
        static::assertInstanceOf(GizmoBundle::class, $bundles[0]);
        static::assertInstanceOf(SwagTest::class, $bundles[1]);
        static::assertInstanceOf(FooBarBundle::class, $bundles[2]);
        static::assertInstanceOf(SwagTestWithBundle::class, $bundles[3]);
        static::assertSame($loader, $bundles[4]);
    }

    public function testGetBundlesNoActive(): void
    {
        $pluginData = $this->getInstalledInactivePlugin()->jsonSerialize();
        $loader = new StaticKernelPluginLoader($this->classLoader, null, [$pluginData]);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        $bundles = iterator_to_array($loader->getBundles());

        static::assertCount(1, $bundles);
        static::assertSame($loader, $bundles[0]);
    }

    public function testExpectExceptionWithFakePlugin(): void
    {
        $fakePluginData = $this->getFakePlugin()->jsonSerialize();
        $loader = new StaticKernelPluginLoader($this->classLoader, null, [$fakePluginData]);

        $this->expectException(KernelPluginLoaderException::class);
        $this->expectExceptionMessage('Failed to load plugin "SwagTest". Reason: Plugin class "SwagTest\SwagTestFake" must extend "Shopware\Core\Framework\Plugin"');
        $loader->initializePlugins(TEST_PROJECT_DIR);
    }

    public function testBuildNoInitShouldNotChangeContainer(): void
    {
        $activePluginData = $this->getActivePlugin()->jsonSerialize();
        $loader = new StaticKernelPluginLoader($this->classLoader, null, [$activePluginData]);

        $emptyContainer = new ContainerBuilder();
        $container = new ContainerBuilder();

        static::assertEquals($emptyContainer, $container);
        $loader->build($container);

        static::assertEquals($emptyContainer, $container);
    }

    public function testBuildInactivePluginShouldNotChangeContainer(): void
    {
        $pluginData = $this->getInstalledInactivePlugin()->jsonSerialize();
        $loader = new StaticKernelPluginLoader($this->classLoader, null, [$pluginData]);

        $emptyContainer = new ContainerBuilder();
        $container = new ContainerBuilder();

        static::assertEquals($emptyContainer, $container);
        $loader->build($container);

        static::assertEquals($emptyContainer, $container);
    }

    public function testBuild(): void
    {
        $activePluginData = $this->getActivePlugin()->jsonSerialize();
        $loader = new StaticKernelPluginLoader($this->classLoader, null, [$activePluginData]);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        $container = new ContainerBuilder();
        $loader->build($container);

        $definition = $container->getDefinition(SwagTest::class);
        static::assertNotNull($definition);
        static::assertTrue($definition->isAutowired());
        static::assertTrue($definition->isPublic());
    }

    public function testBuildWithExistingDefinition(): void
    {
        $activePluginData = $this->getActivePlugin()->jsonSerialize();
        $loader = new StaticKernelPluginLoader($this->classLoader, null, [$activePluginData]);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        $container = new ContainerBuilder();

        $definition = new Definition();
        $definition->setAutowired(false);
        $definition->setPublic(false);
        $container->setDefinition(SwagTest::class, $definition);

        $loader->build($container);

        $actualDefinition = $container->getDefinition(SwagTest::class);
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

        $classLoader->expects(static::once())->method('add')->with('Test_', [
            TEST_PROJECT_DIR . '/custom/plugins/TestPlugin/src',
        ], false);

        $loader = new StaticKernelPluginLoader($classLoader, null, [$plugin->jsonSerialize()]);
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

        $this->expectException(KernelPluginLoaderException::class);
        $this->expectExceptionMessage('Failed to load plugin "SwagTest". Reason: Plugin dir /custom/plugins/TestPlugin needs to be a sub-directory of the project dir ' . TEST_PROJECT_DIR);

        $loader = new StaticKernelPluginLoader($classLoader, null, [$plugin->jsonSerialize()]);
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

        $classLoader->expects(static::once())->method('add')->with('Test_', [
            TEST_PROJECT_DIR . '/custom/plugins/TestPlugin/src',
        ], false);

        $loader = new StaticKernelPluginLoader($classLoader, null, [$plugin->jsonSerialize()]);
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

        $classLoader->expects(static::once())->method('add')->with('Test_', [
            TEST_PROJECT_DIR . '/custom/plugins/TestPlugin/src',
            TEST_PROJECT_DIR . '/custom/plugins/TestPlugin/components',
        ], false);

        $loader = new StaticKernelPluginLoader($classLoader, null, [$plugin->jsonSerialize()]);
        $loader->initializePlugins(TEST_PROJECT_DIR);
    }

    private function getFakePlugin(): PluginEntity
    {
        $plugin = $this->getActivePlugin();
        $plugin->setBaseClass(SwagTestFake::class);

        return $plugin;
    }
}

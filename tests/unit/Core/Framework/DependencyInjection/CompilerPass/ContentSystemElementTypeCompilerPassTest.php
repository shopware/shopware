<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ContentSystemElementTypeCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemElementTypeCompilerPass::class)]
class ContentSystemElementTypeCompilerPassTest extends TestCase
{
    /**
     * Fixtures root directory. Sub-directories mirror the directory layout expected by the compiler pass.
     *
     * fixtures/
     *   bundle-a/Resources/content-system/types/ — standard bundle path, ships element types
     *   test-plugin/Resources/content-system/types/ — default plugin path, ships element types
     *   test-plugin-custom/custom-types/ — custom plugin path, ships element types
     *   apps/test-app/Resources/content-system/types/ — app path, ships element types
     */
    private const FIXTURES_DIR = __DIR__ . '/fixtures';

    private ContentSystemElementTypeCompilerPass $pass;

    protected function setUp(): void
    {
        $this->pass = new ContentSystemElementTypeCompilerPass();
    }

    #[TestDox('scans non-plugin bundles using the standard type directory path')]
    public function testScansNonPluginBundlesForTypes(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', [
            'BundleA' => ['path' => self::FIXTURES_DIR . '/bundle-a'],
        ]);
        $container->setParameter('kernel.active_plugins', []);

        $this->pass->process($container);

        $directories = $this->extractDirectories($container);
        $bundleDir = $this->findBySource($directories, 'bundle:BundleA');
        static::assertNotNull($bundleDir);
        static::assertSame(self::FIXTURES_DIR . '/bundle-a/Resources/content-system/types', $bundleDir->getArgument(1));
        static::assertSame('Sw', $bundleDir->getArgument(2));
    }

    #[TestDox('loads active plugins from their configured type directory')]
    public function testLoadsPluginTypesFromConfiguredDirectory(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', [
            FixturePlugin::class => [
                'name' => 'FixturePlugin',
                'path' => self::FIXTURES_DIR . '/test-plugin',
                'class' => FixturePlugin::class,
            ],
        ]);

        $this->pass->process($container);

        $directories = $this->extractDirectories($container);
        $pluginDir = $this->findBySource($directories, 'plugin:FixturePlugin');
        static::assertNotNull($pluginDir);
        static::assertSame('FixturePlugin', $pluginDir->getArgument(2));
    }

    #[TestDox('registers app type directory from filesystem in dev environment')]
    public function testRegistersAppTypeDirectoryInDevEnvironment(): void
    {
        $container = $this->buildContainer('dev');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);
        // project_dir + app_path + Resources/content-system/types = fixture app dir
        $container->setParameter('kernel.project_dir', self::FIXTURES_DIR . '/apps');

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([
                ['path' => 'test-app', 'name' => 'TestApp'],
            ]);
        $container->set(Connection::class, $connection);

        $this->pass->process($container);

        $directories = $this->extractDirectories($container);
        $appDir = $this->findBySource($directories, 'app:TestApp');
        static::assertNotNull($appDir);
        static::assertSame('TestApp', $appDir->getArgument(2));
    }

    #[TestDox('skips active-plugin bundles during bundle-metadata loading')]
    public function testSkipsActivePluginBundlesDuringBundleScan(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', [
            // MyPlugin path points at the fixture bundle, but it is an active plugin — skipped
            'MyPlugin' => ['path' => self::FIXTURES_DIR . '/bundle-a'],
        ]);
        $container->setParameter('kernel.active_plugins', [
            FixturePlugin::class => [
                // Plugin path is non-existent, so no types come from here either
                'name' => 'MyPlugin',
                'path' => '/non-existent-plugin-path',
                'class' => FixturePlugin::class,
            ],
        ]);

        $this->pass->process($container);

        $directories = $this->extractDirectories($container);
        static::assertNull($this->findBySource($directories, 'bundle:MyPlugin'));
    }

    #[TestDox('feeds both loaders the same directory set covering core, bundle, plugin, and dev-app entries')]
    public function testFeedsBothLoadersTheSameDirectorySet(): void
    {
        $container = $this->buildContainerWithBothLoaders('dev');
        $container->setParameter('kernel.bundles_metadata', [
            'BundleA' => ['path' => self::FIXTURES_DIR . '/bundle-a'],
        ]);
        $container->setParameter('kernel.active_plugins', [
            FixturePluginWithCustomTypeDir::class => [
                'name' => 'FixturePluginWithCustomTypeDir',
                'path' => self::FIXTURES_DIR . '/test-plugin-custom',
                'class' => FixturePluginWithCustomTypeDir::class,
            ],
        ]);
        $container->setParameter('kernel.project_dir', self::FIXTURES_DIR . '/apps');

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['path' => 'test-app', 'name' => 'TestApp']]);
        $container->set(Connection::class, $connection);

        $this->pass->process($container);

        $typeDirs = $this->extractDirectories($container, YamlTypeLoader::class);
        $bindingDirs = $this->extractDirectories($container, YamlBindingSpecificationLoader::class);

        // Both loaders scan the identical discovered directory set (same source/path/prefix per entry, same order).
        static::assertEquals($typeDirs, $bindingDirs);

        $core = $this->findBySource($typeDirs, 'core');
        static::assertNotNull($core);
        $corePath = $core->getArgument(1);
        static::assertIsString($corePath);
        static::assertStringEndsWith('ContentSystem/Layout/Type/Definitions', $corePath);
        static::assertSame('Sw', $core->getArgument(2));

        $bundle = $this->findBySource($typeDirs, 'bundle:BundleA');
        static::assertNotNull($bundle);
        static::assertSame(self::FIXTURES_DIR . '/bundle-a/Resources/content-system/types', $bundle->getArgument(1));
        static::assertSame('Sw', $bundle->getArgument(2));

        $plugin = $this->findBySource($typeDirs, 'plugin:FixturePluginWithCustomTypeDir');
        static::assertNotNull($plugin);
        static::assertSame(self::FIXTURES_DIR . '/test-plugin-custom/custom-types', $plugin->getArgument(1));
        static::assertSame('FixturePluginWithCustomTypeDir', $plugin->getArgument(2));

        $app = $this->findBySource($typeDirs, 'app:TestApp');
        static::assertNotNull($app);
        static::assertSame(self::FIXTURES_DIR . '/apps/test-app/Resources/content-system/types', $app->getArgument(1));
        static::assertSame('TestApp', $app->getArgument(2));
    }

    #[TestDox('uses custom type directory when plugin overrides default')]
    public function testUsesCustomTypeDirectoryWhenPluginOverridesDefault(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', [
            FixturePluginWithCustomTypeDir::class => [
                'name' => 'FixturePluginWithCustomTypeDir',
                'path' => self::FIXTURES_DIR . '/test-plugin-custom',
                'class' => FixturePluginWithCustomTypeDir::class,
            ],
        ]);

        $this->pass->process($container);

        $directories = $this->extractDirectories($container);
        $pluginDir = $this->findBySource($directories, 'plugin:FixturePluginWithCustomTypeDir');
        static::assertNotNull($pluginDir);
        static::assertSame(self::FIXTURES_DIR . '/test-plugin-custom/custom-types', $pluginDir->getArgument(1));
        static::assertSame('FixturePluginWithCustomTypeDir', $pluginDir->getArgument(2));
    }

    #[TestDox('skips app loading in production environment')]
    public function testSkipsAppLoadingInProductionEnvironment(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');
        $container->set(Connection::class, $connection);

        $this->pass->process($container);

        $directories = $this->extractDirectories($container);
        $appSources = array_filter($directories, static fn (Definition $dir) => str_starts_with($dir->getArgument(0), 'app:'));
        static::assertSame([], array_values($appSources));
    }

    #[TestDox('does nothing when both loader services are absent')]
    public function testRegistersNothingWhenBothLoaderServicesAreAbsent(): void
    {
        // Neither YamlTypeLoader nor YamlBindingSpecificationLoader is defined, and no parameters are set at
        // all: a missing guard would throw fetching kernel.bundles_metadata instead of returning early, so a
        // regressed guard fails this test loudly rather than silently.
        $container = new ContainerBuilder();
        $definitionsBefore = $container->getDefinitions();

        $this->pass->process($container);

        static::assertSame($definitionsBefore, $container->getDefinitions());
    }

    #[TestDox('feeds the binding loader even when the type loader service is absent')]
    public function testFeedsBindingLoaderWhenTypeLoaderIsAbsent(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);
        $container->setDefinition(YamlBindingSpecificationLoader::class, new Definition(YamlBindingSpecificationLoader::class));

        $this->pass->process($container);

        $directories = $this->extractDirectories($container, YamlBindingSpecificationLoader::class);
        static::assertNotNull($this->findBySource($directories, 'core'));
    }

    #[TestDox('continues compiling when database is unavailable during app loading')]
    public function testContinuesCompilingWhenDatabaseIsUnavailable(): void
    {
        $container = $this->buildContainer('dev');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);
        $container->setParameter('kernel.project_dir', '/project');

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willThrowException(static::createStub(DbalException::class));
        $container->set(Connection::class, $connection);

        // Must not throw — DB failures are silently swallowed
        $this->pass->process($container);

        // Verify core directory was still collected even when app loading fails
        $directories = $this->extractDirectories($container);
        $coreDir = $this->findBySource($directories, 'core');
        static::assertNotNull($coreDir);
    }

    #[TestDox('throws when kernel.project_dir is not a string during app loading')]
    public function testThrowsWhenProjectDirIsNotAStringInDevEnvironment(): void
    {
        $container = $this->buildContainer('dev');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);
        $container->setParameter('kernel.project_dir', 123);
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['path' => 'test-app', 'name' => 'TestApp'],
        ]);
        $container->set(Connection::class, $connection);

        $this->expectExceptionObject(DependencyInjectionException::projectDirNotInContainer());
        $this->pass->process($container);
    }

    #[TestDox('throws when kernel.bundles_metadata is not an array')]
    public function testThrowsWhenBundlesMetadataIsNotAnArray(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', 'not-an-array');
        $container->setParameter('kernel.active_plugins', []);

        $this->expectExceptionObject(DependencyInjectionException::bundlesMetadataIsNotAnArray());

        $this->pass->process($container);
    }

    /**
     * @param array<string, mixed> $bundlesMetadata
     */
    #[DataProvider('throwsForInvalidActivePluginsProvider')]
    #[TestDox('throws for invalid active plugins configuration')]
    public function testThrowsForInvalidActivePluginsConfiguration(array $bundlesMetadata, mixed $activePlugins, DependencyInjectionException $expectedException): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', $bundlesMetadata);
        $container->setParameter('kernel.active_plugins', $activePlugins);

        $this->expectExceptionObject($expectedException);

        $this->pass->process($container);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, mixed, DependencyInjectionException}>
     */
    public static function throwsForInvalidActivePluginsProvider(): iterable
    {
        yield 'active_plugins is not an array' => [
            ['SomeBundle' => ['path' => '/some/path']],
            'not-an-array',
            DependencyInjectionException::parameterHasWrongType('kernel.active_plugins', 'array', 'string'),
        ];
        yield 'plugin key is not a valid class' => [
            [],
            [
                'NonExistentClass\\That\\DoesNotExist' => [
                    'name' => 'MyPlugin',
                    'path' => '/my-plugin',
                    'class' => 'NonExistentClass\\That\\DoesNotExist',
                ],
            ],
            DependencyInjectionException::parameterHasWrongType(
                'kernel.active_plugins',
                'array<class-string, array>',
                'entry key "NonExistentClass\\That\\DoesNotExist" is not a valid class'
            ),
        ];
        yield 'plugin entry missing required metadata fields' => [
            [],
            [
                FixturePlugin::class => [
                    'name' => 'FixturePlugin',
                ],
            ],
            DependencyInjectionException::parameterHasWrongType(
                'kernel.active_plugins',
                'array{name: string, path: string, class: string}',
                \sprintf('entry for "%s" has missing or invalid metadata', FixturePlugin::class)
            ),
        ];
    }

    private function buildContainer(string $environment): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', $environment);

        $loaderDef = new Definition(YamlTypeLoader::class);
        $container->setDefinition(YamlTypeLoader::class, $loaderDef);

        return $container;
    }

    private function buildContainerWithBothLoaders(string $environment): ContainerBuilder
    {
        $container = $this->buildContainer($environment);
        $container->setDefinition(YamlBindingSpecificationLoader::class, new Definition(YamlBindingSpecificationLoader::class));

        return $container;
    }

    /**
     * @param class-string $loaderClass
     *
     * @return list<Definition>
     */
    private function extractDirectories(ContainerBuilder $container, string $loaderClass = YamlTypeLoader::class): array
    {
        return $container->getDefinition($loaderClass)->getArgument('$directories');
    }

    /**
     * @param list<Definition> $directories
     */
    private function findBySource(array $directories, string $source): ?Definition
    {
        foreach ($directories as $dir) {
            if ($dir->getArgument(0) === $source) {
                return $dir;
            }
        }

        return null;
    }
}

/**
 * @internal
 */
class FixturePlugin extends Plugin
{
}

/**
 * @internal
 */
class FixturePluginWithCustomTypeDir extends Plugin
{
    public static function getContentTypeDirectory(): string
    {
        return 'custom-types';
    }
}

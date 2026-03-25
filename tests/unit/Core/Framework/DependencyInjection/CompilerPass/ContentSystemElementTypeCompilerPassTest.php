<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ContentSystemElementTypeCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(ContentSystemElementTypeCompilerPass::class)]
class ContentSystemElementTypeCompilerPassTest extends TestCase
{
    /**
     * Fixtures root directory. Sub-directories mirror the directory layout expected by the compiler pass.
     *
     * fixtures/
     *   bundle-a/Resources/content-system/types/ — standard bundle path (type: Sw:Test:Element)
     *   test-plugin/Resources/content-system/types/ — default plugin path (type: Sw:Plugin:Element)
     *   test-plugin-custom/custom-types/ — custom plugin path (type: Sw:CustomPlugin:Element)
     *   apps/test-app/Resources/content-system/types/ — app path (type: Sw:App:Element)
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
            // bundle-a ships 'Sw:Test:Element' at Resources/content-system/types/
            'BundleA' => ['path' => self::FIXTURES_DIR . '/bundle-a'],
        ]);
        $container->setParameter('kernel.active_plugins', []);

        $this->pass->process($container);

        $directories = $this->extractDirectories($container);
        static::assertArrayHasKey('bundle:BundleA', $directories);
        static::assertSame(self::FIXTURES_DIR . '/bundle-a/Resources/content-system/types', $directories['bundle:BundleA']);
    }

    #[TestDox('loads active plugins from their configured type directory')]
    public function testLoadsPluginTypesFromConfiguredDirectory(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', [
            // test-plugin ships 'Sw:Plugin:Element' at Resources/content-system/types/
            FixturePlugin::class => [
                'name' => 'FixturePlugin',
                'path' => self::FIXTURES_DIR . '/test-plugin',
                'class' => FixturePlugin::class,
            ],
        ]);

        $this->pass->process($container);

        $directories = $this->extractDirectories($container);
        static::assertArrayHasKey('plugin:FixturePlugin', $directories);
    }

    #[TestDox('loads app types from filesystem in dev environment')]
    public function testAppLoadingInDevEnvironment(): void
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
        static::assertArrayHasKey('app:TestApp', $directories);
    }

    #[TestDox('skips active-plugin bundles during bundle-metadata loading')]
    public function testSkipsActivePluginBundlesDuringBundleScan(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', [
            // MyPlugin path points at the fixture bundle, but it is an active plugin → skipped
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
        static::assertArrayNotHasKey('bundle:MyPlugin', $directories);
    }

    #[TestDox('uses the overridden path for plugins with a custom type directory')]
    public function testPluginWithCustomTypeDirectoryIsRespected(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', [
            // test-plugin-custom ships 'Sw:CustomPlugin:Element' at custom-types/
            FixturePluginWithCustomTypeDir::class => [
                'name' => 'FixturePluginWithCustomTypeDir',
                'path' => self::FIXTURES_DIR . '/test-plugin-custom',
                'class' => FixturePluginWithCustomTypeDir::class,
            ],
        ]);

        $this->pass->process($container);

        $directories = $this->extractDirectories($container);
        static::assertArrayHasKey('plugin:FixturePluginWithCustomTypeDir', $directories);
        static::assertSame(self::FIXTURES_DIR . '/test-plugin-custom/custom-types', $directories['plugin:FixturePluginWithCustomTypeDir']);
    }

    #[TestDox('skips app loading when environment is not dev')]
    public function testAppLoadingIsSkippedInProductionEnvironment(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');
        $container->set(Connection::class, $connection);

        $this->pass->process($container);

        $directories = $this->extractDirectories($container);
        $appKeys = array_filter(array_keys($directories), static fn (string $key) => str_starts_with($key, 'app:'));
        static::assertSame([], array_values($appKeys));
    }

    #[TestDox('returns early without loading any directories when YamlTypeLoader definition is missing')]
    public function testProcessReturnsEarlyWhenYamlLoaderIsMissing(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);
        $container->setParameter('kernel.environment', 'prod');

        // No YamlTypeLoader definition — must not throw
        $this->pass->process($container);

        static::assertFalse($container->hasDefinition(YamlTypeLoader::class));
    }

    #[TestDox('swallows DB exception during app loading')]
    public function testAppLoadingContinuesWhenDbQueryFails(): void
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
        static::assertArrayHasKey('core', $directories);
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

    #[TestDox('throws when kernel.active_plugins is not an array')]
    public function testThrowsWhenActivePluginsIsNotAnArray(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', ['SomeBundle' => ['path' => '/some/path']]);
        $container->setParameter('kernel.active_plugins', 'not-an-array');

        $this->expectExceptionObject(DependencyInjectionException::parameterHasWrongType(
            'kernel.active_plugins',
            'array',
            'string'
        ));

        $this->pass->process($container);
    }

    #[TestDox('throws when a plugin entry key is not a valid class-string')]
    public function testThrowsWhenPluginKeyIsNotAValidClass(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', [
            'NonExistentClass\\That\\DoesNotExist' => [
                'name' => 'MyPlugin',
                'path' => '/my-plugin',
                'class' => 'NonExistentClass\\That\\DoesNotExist',
            ],
        ]);

        $this->expectExceptionObject(DependencyInjectionException::parameterHasWrongType(
            'kernel.active_plugins',
            'array<class-string, array>',
            'entry key "NonExistentClass\\That\\DoesNotExist" is not a valid class'
        ));

        $this->pass->process($container);
    }

    #[TestDox('throws when a plugin entry is missing required metadata fields')]
    public function testThrowsWhenPluginMetadataIsMissingFields(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', [
            FixturePlugin::class => [
                'name' => 'FixturePlugin',
                // Missing 'path' and 'class' fields
            ],
        ]);

        $this->expectExceptionObject(DependencyInjectionException::parameterHasWrongType(
            'kernel.active_plugins',
            'array{name: string, path: string, class: string}',
            \sprintf('entry for "%s" has missing or invalid metadata', FixturePlugin::class)
        ));

        $this->pass->process($container);
    }

    private function buildContainer(string $environment): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', $environment);

        $loaderDef = new Definition(YamlTypeLoader::class);
        $container->setDefinition(YamlTypeLoader::class, $loaderDef);

        return $container;
    }

    /**
     * @return array<string, string>
     */
    private function extractDirectories(ContainerBuilder $container): array
    {
        return $container->getDefinition(YamlTypeLoader::class)->getArgument('$directories');
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

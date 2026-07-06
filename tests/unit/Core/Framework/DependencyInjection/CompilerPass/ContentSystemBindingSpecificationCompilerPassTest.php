<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ContentSystemBindingSpecificationCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(ContentSystemBindingSpecificationCompilerPass::class)]
class ContentSystemBindingSpecificationCompilerPassTest extends TestCase
{
    private ContentSystemBindingSpecificationCompilerPass $pass;

    protected function setUp(): void
    {
        $this->pass = new ContentSystemBindingSpecificationCompilerPass();
    }

    #[TestDox('injects the core element-type definitions directory with the Sw prefix for inline scanning')]
    public function testInjectsCoreTypeDefinitionsDirectoryWithSwPrefix(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);

        $this->pass->process($container);

        $typeCoreDir = $this->findBySourcePrefix($this->extractDirectories($container), 'core', 'Sw');
        static::assertNotNull($typeCoreDir);
        static::assertStringEndsWith('ContentSystem/Layout/Type/Definitions', $this->path($typeCoreDir));
    }

    #[TestDox('scans a non-plugin bundle type directory with the Sw prefix')]
    public function testScansBundleTypeDirectoryWithSwPrefix(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', ['BundleA' => ['path' => '/bundles/bundle-a']]);
        $container->setParameter('kernel.active_plugins', []);

        $this->pass->process($container);

        $bundleTypeDir = $this->findBySourcePrefix($this->extractDirectories($container), 'bundle:BundleA', 'Sw');
        static::assertNotNull($bundleTypeDir);
        static::assertSame('/bundles/bundle-a/Resources/content-system/types', $this->path($bundleTypeDir));
    }

    #[TestDox('scans an active plugin content-type directory with the plugin-name prefix, honoring getContentTypeDirectory')]
    public function testScansPluginContentTypeDirectoryWithPluginNamePrefix(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', [
            BindingFixturePluginWithCustomTypeDir::class => [
                'name' => 'BindingFixturePluginWithCustomTypeDir',
                'path' => '/plugins/fixture',
                'class' => BindingFixturePluginWithCustomTypeDir::class,
            ],
        ]);

        $this->pass->process($container);

        $pluginTypeDir = $this->findBySourcePrefix(
            $this->extractDirectories($container),
            'plugin:BindingFixturePluginWithCustomTypeDir',
            'BindingFixturePluginWithCustomTypeDir',
        );
        static::assertNotNull($pluginTypeDir);
        static::assertSame('/plugins/fixture/custom-content-types', $this->path($pluginTypeDir));
    }

    #[TestDox('registers the app element-type directory with the app-name prefix in dev')]
    public function testRegistersAppTypeDirectoryWithAppNamePrefixInDev(): void
    {
        $container = $this->buildContainer('dev');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);
        $container->setParameter('kernel.project_dir', '/project');

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['path' => 'custom/apps/TestApp', 'name' => 'TestApp']]);
        $container->set(Connection::class, $connection);

        $this->pass->process($container);

        $appTypeDir = $this->findBySourcePrefix($this->extractDirectories($container), 'app:TestApp', 'TestApp');
        static::assertNotNull($appTypeDir);
        static::assertSame('/project/custom/apps/TestApp/Resources/content-system/types', $this->path($appTypeDir));
    }

    #[TestDox('does not touch the database in prod, where app bindings load from the database loader')]
    public function testSkipsAppLoadingInProd(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');
        $container->set(Connection::class, $connection);

        $this->pass->process($container);

        $appDirs = array_filter(
            $this->extractDirectories($container),
            static fn (Definition $dir): bool => str_starts_with((string) $dir->getArgument(0), 'app:'),
        );
        static::assertSame([], array_values($appDirs));
    }

    #[TestDox('does nothing when the YAML loader service is absent')]
    public function testSkipsWhenLoaderAbsent(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        $this->expectNotToPerformAssertions();
        $this->pass->process($container);
    }

    #[TestDox('keeps compiling when the database is unavailable during app discovery')]
    public function testContinuesWhenDatabaseUnavailable(): void
    {
        $container = $this->buildContainer('dev');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);
        $container->setParameter('kernel.project_dir', '/project');

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willThrowException(static::createStub(DbalException::class));
        $container->set(Connection::class, $connection);

        $this->pass->process($container);

        static::assertNotNull($this->findBySource($this->extractDirectories($container), 'core'));
    }

    #[TestDox('fails hard when bundles metadata is not an array')]
    public function testThrowsWhenBundlesMetadataNotArray(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', 'not-an-array');
        $container->setParameter('kernel.active_plugins', []);

        $this->expectExceptionObject(DependencyInjectionException::bundlesMetadataIsNotAnArray());

        $this->pass->process($container);
    }

    #[TestDox('fails hard when active plugins is not an array')]
    public function testThrowsWhenActivePluginsNotArray(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', 'not-an-array');

        $this->expectExceptionObject(DependencyInjectionException::parameterHasWrongType('kernel.active_plugins', 'array', 'string'));

        $this->pass->process($container);
    }

    #[TestDox('fails hard when the project directory is not a string in dev')]
    public function testThrowsWhenProjectDirNotString(): void
    {
        $container = $this->buildContainer('dev');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);
        $container->setParameter('kernel.project_dir', 123);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['path' => 'apps/TestApp', 'name' => 'TestApp']]);
        $container->set(Connection::class, $connection);

        $this->expectExceptionObject(DependencyInjectionException::projectDirNotInContainer());

        $this->pass->process($container);
    }

    private function buildContainer(string $environment): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', $environment);
        $container->setDefinition(YamlBindingSpecificationLoader::class, new Definition(YamlBindingSpecificationLoader::class));

        return $container;
    }

    /**
     * @return list<Definition>
     */
    private function extractDirectories(ContainerBuilder $container): array
    {
        $directories = $container->getDefinition(YamlBindingSpecificationLoader::class)->getArgument('$directories');
        static::assertIsArray($directories);

        return array_values($directories);
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

    /**
     * @param list<Definition> $directories
     */
    private function findBySourcePrefix(array $directories, string $source, string $prefix): ?Definition
    {
        foreach ($directories as $dir) {
            if ($dir->getArgument(0) === $source && $dir->getArgument(2) === $prefix) {
                return $dir;
            }
        }

        return null;
    }

    private function path(Definition $directory): string
    {
        $path = $directory->getArgument(1);
        static::assertIsString($path);

        return $path;
    }
}

/**
 * @internal
 */
class BindingFixturePluginWithCustomTypeDir extends Plugin
{
    public static function getContentTypeDirectory(): string
    {
        return 'custom-content-types';
    }
}

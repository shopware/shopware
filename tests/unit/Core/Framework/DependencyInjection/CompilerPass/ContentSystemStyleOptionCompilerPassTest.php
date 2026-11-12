<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\YamlStyleOptionLoader;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ContentSystemStyleOptionCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemStyleOptionCompilerPass::class)]
class ContentSystemStyleOptionCompilerPassTest extends TestCase
{
    private const STYLE_DIR = '/Resources/content-system/style-options';

    private ContentSystemStyleOptionCompilerPass $pass;

    protected function setUp(): void
    {
        $this->pass = new ContentSystemStyleOptionCompilerPass();
    }

    #[TestDox('injects the core definitions directory')]
    public function testInjectsCoreDirectory(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);

        $this->pass->process($container);

        $coreDir = $this->findBySource($this->extractDirectories($container), 'core');
        static::assertNotNull($coreDir);
        static::assertStringEndsWith('Layout/Element/Style/Definitions', $this->path($coreDir));
    }

    #[TestDox('scans a non-plugin bundle using the fixed convention directory and bundle label')]
    public function testScansBundleWithFixedDirectory(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', ['BundleA' => ['path' => '/bundles/bundle-a']]);
        $container->setParameter('kernel.active_plugins', []);

        $this->pass->process($container);

        $bundleDir = $this->findBySource($this->extractDirectories($container), 'bundle:BundleA');
        static::assertNotNull($bundleDir);
        static::assertSame('/bundles/bundle-a' . self::STYLE_DIR, $this->path($bundleDir));
    }

    #[TestDox('labels a bundle that is an active plugin with the plugin prefix, sharing the fixed directory')]
    public function testLabelsActivePluginBundleAsPlugin(): void
    {
        $container = $this->buildContainer('prod');
        $container->setParameter('kernel.bundles_metadata', ['MyPlugin' => ['path' => '/plugins/my-plugin']]);
        $container->setParameter('kernel.active_plugins', [
            'My\\Plugin\\MyPlugin' => ['name' => 'MyPlugin', 'path' => '/plugins/my-plugin', 'class' => 'My\\Plugin\\MyPlugin'],
        ]);

        $this->pass->process($container);

        $directories = $this->extractDirectories($container);
        $pluginDir = $this->findBySource($directories, 'plugin:MyPlugin');
        static::assertNotNull($pluginDir);
        static::assertSame('/plugins/my-plugin' . self::STYLE_DIR, $this->path($pluginDir));
        static::assertNull($this->findBySource($directories, 'bundle:MyPlugin'));
    }

    #[TestDox('registers app directories from the filesystem in dev')]
    public function testRegistersAppDirectoryInDev(): void
    {
        $container = $this->buildContainer('dev');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);
        $container->setParameter('kernel.project_dir', '/project');

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['path' => 'custom/apps/TestApp', 'name' => 'TestApp']]);
        $container->set(Connection::class, $connection);

        $this->pass->process($container);

        $appDir = $this->findBySource($this->extractDirectories($container), 'app:TestApp');
        static::assertNotNull($appDir);
        static::assertSame('/project/custom/apps/TestApp' . self::STYLE_DIR, $this->path($appDir));
    }

    #[TestDox('does not touch the database in prod, where app options load from the database loader')]
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
        $container->setDefinition(YamlStyleOptionLoader::class, new Definition(YamlStyleOptionLoader::class));

        return $container;
    }

    /**
     * @return list<Definition>
     */
    private function extractDirectories(ContainerBuilder $container): array
    {
        $directories = $container->getDefinition(YamlStyleOptionLoader::class)->getArgument('$directories');
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

    private function path(Definition $directory): string
    {
        $path = $directory->getArgument(1);
        static::assertIsString($path);

        return $path;
    }
}

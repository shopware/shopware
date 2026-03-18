<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Components;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentHelper;
use Symfony\UX\TwigComponent\ComponentFactory;
use Symfony\UX\TwigComponent\ComponentMetadata;

/**
 * @internal
 */
#[CoversClass(TwigComponentHelper::class)]
class TwigComponentHelperTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/twig_component_helper_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTempDir($this->tempDir);
    }

    public function testGetComponentsReturnsEmptyCollectionWhenNoBundlesOrApps(): void
    {
        $bundlePath = $this->tempDir . '/EmptyBundle';
        $componentDir = $bundlePath . '/' . TwigComponentHelper::COMPONENT_DIRECTORY;
        mkdir($componentDir, 0777, true);

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn([
            'EmptyBundle' => [],
        ]);

        $componentFactory = $this->createComponentFactory();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $helper = new TwigComponentHelper(
            [
                'EmptyBundle' => ['path' => $bundlePath],
            ],
            $namespaceHierarchyBuilder,
            $componentFactory,
            $connection,
            $this->createMock(SourceResolver::class)
        );

        $components = $helper->getComponents();

        static::assertCount(0, $components);
    }

    public function testGetComponentsFindsComponentsFromBundles(): void
    {
        $bundlePath = $this->tempDir . '/TestBundle';
        $componentDir = $bundlePath . '/' . TwigComponentHelper::COMPONENT_DIRECTORY;
        mkdir($componentDir, 0777, true);

        file_put_contents($componentDir . '/Button.html.twig', '<button>{{ label }}</button>');
        file_put_contents($componentDir . '/Card.html.twig', '<div>Card</div>');

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn([
            'TestBundle' => [],
        ]);

        $componentFactory = $this->createComponentFactory();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $helper = new TwigComponentHelper(
            [
                'TestBundle' => ['path' => $bundlePath],
            ],
            $namespaceHierarchyBuilder,
            $componentFactory,
            $connection,
            $this->createMock(SourceResolver::class)
        );

        $components = $helper->getComponents();

        static::assertCount(2, $components);
        static::assertTrue($components->has('TestBundle:Button'));
        static::assertTrue($components->has('TestBundle:Card'));
    }

    public function testGetComponentsFindsNestedComponents(): void
    {
        $bundlePath = $this->tempDir . '/TestBundle';
        $componentDir = $bundlePath . '/' . TwigComponentHelper::COMPONENT_DIRECTORY;
        $nestedDir = $componentDir . '/Forms/Input';
        mkdir($nestedDir, 0777, true);

        file_put_contents($nestedDir . '/Text.html.twig', '<input type="text" />');

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn([
            'TestBundle' => [],
        ]);

        $componentFactory = $this->createComponentFactory();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $helper = new TwigComponentHelper(
            [
                'TestBundle' => ['path' => $bundlePath],
            ],
            $namespaceHierarchyBuilder,
            $componentFactory,
            $connection,
            $this->createMock(SourceResolver::class)
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        static::assertTrue($components->has('TestBundle:Forms:Input:Text'));

        $component = $components->get('TestBundle:Forms:Input:Text');
        static::assertSame('Forms:Input:Text', $component->name);
        static::assertSame('TestBundle', $component->namespace);
    }

    public function testGetComponentsExcludesFilesInUnderscoreDirectories(): void
    {
        $bundlePath = $this->tempDir . '/TestBundle';
        $componentDir = $bundlePath . '/' . TwigComponentHelper::COMPONENT_DIRECTORY;
        $normalDir = $componentDir . '/ui';
        $privateDir = $normalDir . '/_private';
        mkdir($privateDir, 0777, true);

        file_put_contents($componentDir . '/Button.html.twig', '<button>{{ label }}</button>');
        file_put_contents($privateDir . '/Internal.html.twig', '<div>Internal</div>');

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn([
            'TestBundle' => [],
        ]);

        $componentFactory = $this->createComponentFactory();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $helper = new TwigComponentHelper(
            [
                'TestBundle' => ['path' => $bundlePath],
            ],
            $namespaceHierarchyBuilder,
            $componentFactory,
            $connection,
            $this->createMock(SourceResolver::class)
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        static::assertTrue($components->has('TestBundle:Button'));
        static::assertFalse($components->has('TestBundle:ui:_private:Internal'));
    }

    public function testGetComponentsIncludesMetadataWhenRequested(): void
    {
        $bundlePath = $this->tempDir . '/TestBundle';
        $componentDir = $bundlePath . '/' . TwigComponentHelper::COMPONENT_DIRECTORY;
        mkdir($componentDir, 0777, true);

        file_put_contents($componentDir . '/Button.html.twig', '<button>{{ label }}</button>');

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn([
            'TestBundle' => [],
        ]);

        $metadata = new ComponentMetadata([
            'key' => 'Button',
            'template' => 'components/Button.html.twig',
            'class' => 'App\\Component\\Button',
            'service_id' => 'app.component.button',
        ]);

        $componentFactory = $this->createComponentFactory($metadata);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $helper = new TwigComponentHelper(
            [
                'TestBundle' => ['path' => $bundlePath],
            ],
            $namespaceHierarchyBuilder,
            $componentFactory,
            $connection,
            $this->createMock(SourceResolver::class)
        );

        $components = $helper->getComponents(includeMetadata: true);

        static::assertCount(1, $components);
        $component = $components->get('TestBundle:Button');
        static::assertNotNull($component);

        $componentMetadata = $component->metadata;
        static::assertNotNull($componentMetadata);
        static::assertSame('Button', $componentMetadata->getName());
        static::assertSame('components/Button.html.twig', $componentMetadata->getTemplate());
    }

    public function testGetComponentsDoesNotIncludeMetadataByDefault(): void
    {
        $bundlePath = $this->tempDir . '/TestBundle';
        $componentDir = $bundlePath . '/' . TwigComponentHelper::COMPONENT_DIRECTORY;
        mkdir($componentDir, 0777, true);

        file_put_contents($componentDir . '/Button.html.twig', '<button>{{ label }}</button>');

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn([
            'TestBundle' => [],
        ]);

        $componentFactory = $this->createComponentFactory();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $helper = new TwigComponentHelper(
            [
                'TestBundle' => ['path' => $bundlePath],
            ],
            $namespaceHierarchyBuilder,
            $componentFactory,
            $connection,
            $this->createMock(SourceResolver::class)
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        $component = $components->get('TestBundle:Button');
        static::assertNotNull($component);
        static::assertNull($component->metadata);
    }

    /**
     * Regression test: an app whose component lives in a subdirectory
     */
    public function testGetComponentsFromAppInSubdirectoryHasCorrectComponentName(): void
    {
        $appRelPath = 'TestApp';
        $componentDir = $this->tempDir . '/' . $appRelPath . '/' . TwigComponentHelper::COMPONENT_DIRECTORY;
        $customDir = $componentDir . '/Custom';
        mkdir($customDir, 0777, true);
        file_put_contents($customDir . '/Test.html.twig', '<div>Test</div>');

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['namespace' => 'TestApp'],
        ]);

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->method('filesystemForAppName')
            ->with('TestApp')
            ->willReturn(new Filesystem($this->tempDir . '/' . $appRelPath));

        $helper = new TwigComponentHelper(
            [],
            $this->createMock(NamespaceHierarchyBuilder::class),
            $this->createComponentFactory(),
            $connection,
            $sourceResolver
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);

        // Name must include the 'Custom' subdirectory segment.
        static::assertTrue($components->has('TestApp:Custom:Test'), 'Component should be named "Custom:Test", not just "Test"');
        static::assertFalse($components->has('TestApp:Test'), 'Component must not be named without its subdirectory');

        $component = $components->get('TestApp:Custom:Test');
        static::assertSame('Custom:Test', $component->name);
        static::assertSame('TestApp', $component->namespace);

        // getRelativeNamespaceDirectory() must include 'Custom' subdirectory
        static::assertSame('TestApp/Custom', $component->getRelativeNamespaceDirectory());
    }

    public function testGetComponentsFromAppWithMultipleTemplatesRegistersRootDirOnce(): void
    {
        // Two templates in different subdirectories from the same app should both
        // be found when the root components/ dir is used as the Finder base.
        $appRelPath = 'MultiTemplateApp';
        $componentDir = $this->tempDir . '/' . $appRelPath . '/' . TwigComponentHelper::COMPONENT_DIRECTORY;
        mkdir($componentDir . '/Custom', 0777, true);
        mkdir($componentDir . '/Other', 0777, true);
        file_put_contents($componentDir . '/Custom/Test.html.twig', '<div>Test</div>');
        file_put_contents($componentDir . '/Other/Widget.html.twig', '<div>Widget</div>');

        $connection = $this->createMock(Connection::class);
        // DISTINCT in the query means both templates produce one row per app.
        $connection->method('fetchAllAssociative')->willReturn([
            ['namespace' => 'MultiTemplateApp'],
        ]);

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->method('filesystemForAppName')
            ->with('MultiTemplateApp')
            ->willReturn(new Filesystem($this->tempDir . '/' . $appRelPath));

        $helper = new TwigComponentHelper(
            [],
            $this->createMock(NamespaceHierarchyBuilder::class),
            $this->createComponentFactory(),
            $connection,
            $sourceResolver
        );

        $components = $helper->getComponents();

        static::assertCount(2, $components);
        static::assertTrue($components->has('MultiTemplateApp:Custom:Test'));
        static::assertTrue($components->has('MultiTemplateApp:Other:Widget'));
    }

    public function testGetComponentsHandlesMultipleBundles(): void
    {
        $bundle1Path = $this->tempDir . '/Bundle1';
        $component1Dir = $bundle1Path . '/' . TwigComponentHelper::COMPONENT_DIRECTORY;
        mkdir($component1Dir, 0777, true);
        file_put_contents($component1Dir . '/Button.html.twig', '<button>Bundle1</button>');

        $bundle2Path = $this->tempDir . '/Bundle2';
        $component2Dir = $bundle2Path . '/' . TwigComponentHelper::COMPONENT_DIRECTORY;
        mkdir($component2Dir, 0777, true);
        file_put_contents($component2Dir . '/Card.html.twig', '<div>Bundle2</div>');

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn([
            'Bundle1' => [],
            'Bundle2' => [],
        ]);

        $componentFactory = $this->createComponentFactory();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $helper = new TwigComponentHelper(
            [
                'Bundle1' => ['path' => $bundle1Path],
                'Bundle2' => ['path' => $bundle2Path],
            ],
            $namespaceHierarchyBuilder,
            $componentFactory,
            $connection,
            $this->createMock(SourceResolver::class)
        );

        $components = $helper->getComponents();

        static::assertCount(2, $components);
        static::assertTrue($components->has('Bundle1:Button'));
        static::assertTrue($components->has('Bundle2:Card'));
    }

    public function testGetComponentsSkipsBundlesWithoutComponentDirectory(): void
    {
        $bundle1Path = $this->tempDir . '/Bundle1';
        $component1Dir = $bundle1Path . '/' . TwigComponentHelper::COMPONENT_DIRECTORY;
        mkdir($component1Dir, 0777, true);
        file_put_contents($component1Dir . '/Button.html.twig', '<button>Bundle1</button>');

        $bundle2Path = $this->tempDir . '/Bundle2';
        mkdir($bundle2Path, 0777, true);

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn([
            'Bundle1' => [],
            'Bundle2' => [],
        ]);

        $componentFactory = $this->createComponentFactory();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $helper = new TwigComponentHelper(
            [
                'Bundle1' => ['path' => $bundle1Path],
                'Bundle2' => ['path' => $bundle2Path],
            ],
            $namespaceHierarchyBuilder,
            $componentFactory,
            $connection,
            $this->createMock(SourceResolver::class)
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        static::assertTrue($components->has('Bundle1:Button'));
        static::assertFalse($components->has('Bundle2:*'));
    }

    public function testGetComponentsHandlesStorefrontNamespace(): void
    {
        $storefrontPath = $this->tempDir . '/Storefront';
        $componentDir = $storefrontPath . '/' . TwigComponentHelper::COMPONENT_DIRECTORY;
        mkdir($componentDir, 0777, true);
        file_put_contents($componentDir . '/Button.html.twig', '<button>Storefront</button>');

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn([
            'Storefront' => [],
        ]);

        $componentFactory = $this->createComponentFactory();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $helper = new TwigComponentHelper(
            [
                'Storefront' => ['path' => $storefrontPath],
            ],
            $namespaceHierarchyBuilder,
            $componentFactory,
            $connection,
            $this->createMock(SourceResolver::class)
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        static::assertTrue($components->has('Button'));
        static::assertFalse($components->has('Storefront:Button'));
    }

    public function testGetComponentsSkipsAppWhenFilesystemThrows(): void
    {
        // A valid bundle dir is required so Finder->in() has at least one directory
        $bundleDir = $this->tempDir . '/BundleForAppTest/' . TwigComponentHelper::COMPONENT_DIRECTORY;
        mkdir($bundleDir, 0777, true);

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn([
            'BundleForAppTest' => [],
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['namespace' => 'BrokenApp'],
        ]);

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->method('filesystemForAppName')
            ->with('BrokenApp')
            ->willThrowException(new \RuntimeException('Filesystem unavailable'));

        $helper = new TwigComponentHelper(
            ['BundleForAppTest' => ['path' => $this->tempDir . '/BundleForAppTest']],
            $namespaceHierarchyBuilder,
            $this->createComponentFactory(),
            $connection,
            $sourceResolver
        );

        // Exception from filesystemForAppName must be caught and the app skipped
        $components = $helper->getComponents();

        static::assertCount(0, $components);
    }

    public function testGetComponentsSkipsAppWhenComponentDirDoesNotExist(): void
    {
        // A valid bundle dir is required so Finder->in() has at least one directory
        $bundleDir = $this->tempDir . '/BundleForAppTest2/' . TwigComponentHelper::COMPONENT_DIRECTORY;
        mkdir($bundleDir, 0777, true);

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn([
            'BundleForAppTest2' => [],
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['namespace' => 'AppWithNoComponents'],
        ]);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('has')->willReturn(false);

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->method('filesystemForAppName')
            ->with('AppWithNoComponents')
            ->willReturn($filesystem);

        $helper = new TwigComponentHelper(
            ['BundleForAppTest2' => ['path' => $this->tempDir . '/BundleForAppTest2']],
            $namespaceHierarchyBuilder,
            $this->createComponentFactory(),
            $connection,
            $sourceResolver
        );

        $components = $helper->getComponents();

        static::assertCount(0, $components);
    }

    private function createComponentFactory(?ComponentMetadata $metadata = null): ComponentFactory
    {
        // ComponentFactory is final, so we use reflection to create a stub instance
        $reflectionClass = new \ReflectionClass(ComponentFactory::class);
        $instance = $reflectionClass->newInstanceWithoutConstructor();

        // If metadata is provided, inject it so metadataFor() can return it
        if ($metadata !== null) {
            $configProperty = $reflectionClass->getProperty('config');
            $configProperty->setValue($instance, [
                $metadata->getName() => [
                    'key' => $metadata->getName(),
                    'template' => $metadata->getTemplate(),
                    'class' => $metadata->getClass(),
                    'service_id' => $metadata->getServiceId(),
                ],
            ]);
        }

        return $instance;
    }

    private function removeTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeTempDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}

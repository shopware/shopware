<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Components;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\MemoryFilesystemAdapter;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Util\Filesystem as UtilFilesystem;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Shopware\Storefront\Framework\Twig\Components\ComponentMetadataProviderInterface;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentHelper;
use Symfony\UX\TwigComponent\ComponentMetadata;

/**
 * @internal
 */
#[CoversClass(TwigComponentHelper::class)]
class TwigComponentHelperTest extends TestCase
{
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new MemoryFilesystemAdapter());
    }

    public function testGetComponentsReturnsEmptyCollectionWhenNoBundlesOrApps(): void
    {
        // Write a placeholder so directoryExists() returns true but no .html.twig files exist
        $this->filesystem->write('EmptyBundle/Resources/views/components/.gitkeep', '');

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn(['EmptyBundle' => []]);

        $helper = new TwigComponentHelper(
            ['EmptyBundle' => ['path' => '/EmptyBundle']],
            $namespaceHierarchyBuilder,
            $this->createComponentMetadataProvider(),
            $this->createConnectionMock(),
            $this->createMock(SourceResolver::class),
            $this->filesystem,
        );

        static::assertCount(0, $helper->getComponents());
    }

    public function testGetComponentsFindsComponentsFromBundles(): void
    {
        $this->filesystem->write('TestBundle/Resources/views/components/Button.html.twig', '<button>{{ label }}</button>');
        $this->filesystem->write('TestBundle/Resources/views/components/Card.html.twig', '<div>Card</div>');

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn(['TestBundle' => []]);

        $helper = new TwigComponentHelper(
            ['TestBundle' => ['path' => '/TestBundle']],
            $namespaceHierarchyBuilder,
            $this->createComponentMetadataProvider(),
            $this->createConnectionMock(),
            $this->createMock(SourceResolver::class),
            $this->filesystem,
        );

        $components = $helper->getComponents();

        static::assertCount(2, $components);
        static::assertTrue($components->has('TestBundle:Button'));
        static::assertTrue($components->has('TestBundle:Card'));
    }

    public function testGetComponentsFindsNestedComponents(): void
    {
        $this->filesystem->write('TestBundle/Resources/views/components/Forms/Input/Text.html.twig', '<input type="text" />');

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn(['TestBundle' => []]);

        $helper = new TwigComponentHelper(
            ['TestBundle' => ['path' => '/TestBundle']],
            $namespaceHierarchyBuilder,
            $this->createComponentMetadataProvider(),
            $this->createConnectionMock(),
            $this->createMock(SourceResolver::class),
            $this->filesystem,
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        static::assertTrue($components->has('TestBundle:Forms:Input:Text'));

        $component = $components->get('TestBundle:Forms:Input:Text');
        static::assertNotNull($component);
        static::assertSame('Forms:Input:Text', $component->name);
        static::assertSame('TestBundle', $component->namespace);
    }

    public function testGetComponentsExcludesFilesInUnderscoreDirectories(): void
    {
        $this->filesystem->write('TestBundle/Resources/views/components/Button.html.twig', '<button>{{ label }}</button>');
        $this->filesystem->write('TestBundle/Resources/views/components/ui/_private/Internal.html.twig', '<div>Internal</div>');

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn(['TestBundle' => []]);

        $helper = new TwigComponentHelper(
            ['TestBundle' => ['path' => '/TestBundle']],
            $namespaceHierarchyBuilder,
            $this->createComponentMetadataProvider(),
            $this->createConnectionMock(),
            $this->createMock(SourceResolver::class),
            $this->filesystem,
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        static::assertTrue($components->has('TestBundle:Button'));
        static::assertFalse($components->has('TestBundle:ui:_private:Internal'));
    }

    public function testGetComponentsIncludesMetadataWhenRequested(): void
    {
        $this->filesystem->write('TestBundle/Resources/views/components/Button.html.twig', '<button>{{ label }}</button>');

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn(['TestBundle' => []]);

        $metadata = new ComponentMetadata([
            'key' => 'Button',
            'template' => 'components/Button.html.twig',
            'class' => 'App\\Component\\Button',
            'service_id' => 'app.component.button',
        ]);

        $helper = new TwigComponentHelper(
            ['TestBundle' => ['path' => '/TestBundle']],
            $namespaceHierarchyBuilder,
            $this->createComponentMetadataProvider($metadata),
            $this->createConnectionMock(),
            $this->createMock(SourceResolver::class),
            $this->filesystem,
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
        $this->filesystem->write('TestBundle/Resources/views/components/Button.html.twig', '<button>{{ label }}</button>');

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn(['TestBundle' => []]);

        $helper = new TwigComponentHelper(
            ['TestBundle' => ['path' => '/TestBundle']],
            $namespaceHierarchyBuilder,
            $this->createComponentMetadataProvider(),
            $this->createConnectionMock(),
            $this->createMock(SourceResolver::class),
            $this->filesystem,
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
        // StaticFilesystem uses '/app-root' as its location, so path('Resources/views/components/')
        // returns '/app-root/Resources/views/components' – write virtual files there accordingly.
        $this->filesystem->write('app-root/Resources/views/components/Custom/Test.html.twig', '<div>Test</div>');

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['namespace' => 'TestApp']]);

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->method('filesystemForAppName')
            ->with('TestApp')
            ->willReturn(new StaticFilesystem(['Resources/views/components' => '']));

        $helper = new TwigComponentHelper(
            [],
            $this->createMock(NamespaceHierarchyBuilder::class),
            $this->createComponentMetadataProvider(),
            $connection,
            $sourceResolver,
            $this->filesystem,
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        static::assertTrue($components->has('TestApp:Custom:Test'), 'Component should be named "Custom:Test", not just "Test"');
        static::assertFalse($components->has('TestApp:Test'), 'Component must not be named without its subdirectory');

        $component = $components->get('TestApp:Custom:Test');
        static::assertNotNull($component);
        static::assertSame('Custom:Test', $component->name);
        static::assertSame('TestApp', $component->namespace);
        static::assertSame('TestApp/Custom', $component->getRelativeNamespaceDirectory());
    }

    public function testGetComponentsFromAppWithMultipleTemplatesRegistersRootDirOnce(): void
    {
        $this->filesystem->write('app-root/Resources/views/components/Custom/Test.html.twig', '<div>Test</div>');
        $this->filesystem->write('app-root/Resources/views/components/Other/Widget.html.twig', '<div>Widget</div>');

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['namespace' => 'MultiTemplateApp']]);

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->method('filesystemForAppName')
            ->with('MultiTemplateApp')
            ->willReturn(new StaticFilesystem(['Resources/views/components' => '']));

        $helper = new TwigComponentHelper(
            [],
            $this->createMock(NamespaceHierarchyBuilder::class),
            $this->createComponentMetadataProvider(),
            $connection,
            $sourceResolver,
            $this->filesystem,
        );

        $components = $helper->getComponents();

        static::assertCount(2, $components);
        static::assertTrue($components->has('MultiTemplateApp:Custom:Test'));
        static::assertTrue($components->has('MultiTemplateApp:Other:Widget'));
    }

    public function testGetComponentsHandlesMultipleBundles(): void
    {
        $this->filesystem->write('Bundle1/Resources/views/components/Button.html.twig', '<button>Bundle1</button>');
        $this->filesystem->write('Bundle2/Resources/views/components/Card.html.twig', '<div>Bundle2</div>');

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn([
            'Bundle1' => [],
            'Bundle2' => [],
        ]);

        $helper = new TwigComponentHelper(
            [
                'Bundle1' => ['path' => '/Bundle1'],
                'Bundle2' => ['path' => '/Bundle2'],
            ],
            $namespaceHierarchyBuilder,
            $this->createComponentMetadataProvider(),
            $this->createConnectionMock(),
            $this->createMock(SourceResolver::class),
            $this->filesystem,
        );

        $components = $helper->getComponents();

        static::assertCount(2, $components);
        static::assertTrue($components->has('Bundle1:Button'));
        static::assertTrue($components->has('Bundle2:Card'));
    }

    public function testGetComponentsSkipsBundlesWithoutComponentDirectory(): void
    {
        $this->filesystem->write('Bundle1/Resources/views/components/Button.html.twig', '<button>Bundle1</button>');
        // Nothing written for Bundle2 – directoryExists() returns false and the bundle is skipped

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn([
            'Bundle1' => [],
            'Bundle2' => [],
        ]);

        $helper = new TwigComponentHelper(
            [
                'Bundle1' => ['path' => '/Bundle1'],
                'Bundle2' => ['path' => '/Bundle2'],
            ],
            $namespaceHierarchyBuilder,
            $this->createComponentMetadataProvider(),
            $this->createConnectionMock(),
            $this->createMock(SourceResolver::class),
            $this->filesystem,
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        static::assertTrue($components->has('Bundle1:Button'));
        static::assertFalse($components->has('Bundle2:*'));
    }

    public function testGetComponentsHandlesStorefrontNamespace(): void
    {
        $this->filesystem->write('Storefront/Resources/views/components/Button.html.twig', '<button>Storefront</button>');

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn(['Storefront' => []]);

        $helper = new TwigComponentHelper(
            ['Storefront' => ['path' => '/Storefront']],
            $namespaceHierarchyBuilder,
            $this->createComponentMetadataProvider(),
            $this->createConnectionMock(),
            $this->createMock(SourceResolver::class),
            $this->filesystem,
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        static::assertTrue($components->has('Button'));
        static::assertFalse($components->has('Storefront:Button'));
    }

    public function testGetComponentsSkipsAppWhenFilesystemThrows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['namespace' => 'BrokenApp']]);

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->method('filesystemForAppName')
            ->with('BrokenApp')
            ->willThrowException(new \RuntimeException('Filesystem unavailable'));

        $helper = new TwigComponentHelper(
            [],
            $this->createMock(NamespaceHierarchyBuilder::class),
            $this->createComponentMetadataProvider(),
            $connection,
            $sourceResolver,
            $this->filesystem,
        );

        static::assertCount(0, $helper->getComponents());
    }

    public function testGetComponentsSkipsAppWhenComponentDirDoesNotExist(): void
    {
        $appFilesystem = $this->createMock(UtilFilesystem::class);
        $appFilesystem->method('has')->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['namespace' => 'AppWithNoComponents']]);

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->method('filesystemForAppName')
            ->with('AppWithNoComponents')
            ->willReturn($appFilesystem);

        $helper = new TwigComponentHelper(
            [],
            $this->createMock(NamespaceHierarchyBuilder::class),
            $this->createComponentMetadataProvider(),
            $connection,
            $sourceResolver,
            $this->filesystem,
        );

        static::assertCount(0, $helper->getComponents());
    }

    private function createComponentMetadataProvider(?ComponentMetadata $metadata = null): ComponentMetadataProviderInterface
    {
        $provider = $this->createMock(ComponentMetadataProviderInterface::class);

        if ($metadata !== null) {
            $provider->method('metadataFor')->willReturn($metadata);
        }

        return $provider;
    }

    private function createConnectionMock(): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        return $connection;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Components;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Storefront\Framework\Twig\Components\TwigComponent;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentCollection;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentHelper;
use Symfony\Component\Finder\SplFileInfo;
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

    protected function tearDown(): void
    {
        $this->removeTempDir($this->tempDir);
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

    public function testGetComponentsReturnsEmptyCollectionWhenNoBundlesOrApps(): void
    {
        $bundlePath = $this->tempDir . '/EmptyBundle';
        $componentDir = $bundlePath . '/Resources/views/storefront/components';
        mkdir($componentDir, 0777, true);

        $namespaceHierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $namespaceHierarchyBuilder->method('buildHierarchy')->willReturn([
            'EmptyBundle' => [],
        ]);

        $componentFactory = $this->createComponentFactory();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $helper = new TwigComponentHelper(
            'Resources/views/storefront/components',
            $this->tempDir,
            [
                'EmptyBundle' => ['path' => $bundlePath],
            ],
            $namespaceHierarchyBuilder,
            $componentFactory,
            $connection
        );

        $components = $helper->getComponents();

        static::assertInstanceOf(TwigComponentCollection::class, $components);
        static::assertCount(0, $components);
    }

    public function testGetComponentsFindsComponentsFromBundles(): void
    {
        $bundlePath = $this->tempDir . '/TestBundle';
        $componentDir = $bundlePath . '/Resources/views/storefront/components';
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
            'Resources/views/storefront/components',
            $this->tempDir,
            [
                'TestBundle' => ['path' => $bundlePath],
            ],
            $namespaceHierarchyBuilder,
            $componentFactory,
            $connection
        );

        $components = $helper->getComponents();

        static::assertCount(2, $components);
        static::assertTrue($components->has('TestBundle:Button'));
        static::assertTrue($components->has('TestBundle:Card'));
    }

    public function testGetComponentsFindsNestedComponents(): void
    {
        $bundlePath = $this->tempDir . '/TestBundle';
        $componentDir = $bundlePath . '/Resources/views/storefront/components';
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
            'Resources/views/storefront/components',
            $this->tempDir,
            [
                'TestBundle' => ['path' => $bundlePath],
            ],
            $namespaceHierarchyBuilder,
            $componentFactory,
            $connection
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        static::assertTrue($components->has('TestBundle:Forms:Input:Text'));

        $component = $components->get('TestBundle:Forms:Input:Text');
        static::assertSame('Forms:Input:Text', $component->getName());
        static::assertSame('TestBundle', $component->getNamespace());
    }

    public function testGetComponentsExcludesFilesInUnderscoreDirectories(): void
    {
        $bundlePath = $this->tempDir . '/TestBundle';
        $componentDir = $bundlePath . '/Resources/views/storefront/components';
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
            'Resources/views/storefront/components',
            $this->tempDir,
            [
                'TestBundle' => ['path' => $bundlePath],
            ],
            $namespaceHierarchyBuilder,
            $componentFactory,
            $connection
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        static::assertTrue($components->has('TestBundle:Button'));
        static::assertFalse($components->has('TestBundle:ui:_private:Internal'));
    }

    public function testGetComponentsIncludesMetadataWhenRequested(): void
    {
        $bundlePath = $this->tempDir . '/TestBundle';
        $componentDir = $bundlePath . '/Resources/views/storefront/components';
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
            'Resources/views/storefront/components',
            $this->tempDir,
            [
                'TestBundle' => ['path' => $bundlePath],
            ],
            $namespaceHierarchyBuilder,
            $componentFactory,
            $connection
        );

        $components = $helper->getComponents(includeMetadata: true);

        static::assertCount(1, $components);
        $component = $components->get('TestBundle:Button');
        
        $componentMetadata = $component->getMetadata();
        static::assertNotNull($componentMetadata);
        static::assertSame('Button', $componentMetadata->getName());
        static::assertSame('components/Button.html.twig', $componentMetadata->getTemplate());
    }

    public function testGetComponentsDoesNotIncludeMetadataByDefault(): void
    {
        $bundlePath = $this->tempDir . '/TestBundle';
        $componentDir = $bundlePath . '/Resources/views/storefront/components';
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
            'Resources/views/storefront/components',
            $this->tempDir,
            [
                'TestBundle' => ['path' => $bundlePath],
            ],
            $namespaceHierarchyBuilder,
            $componentFactory,
            $connection
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        $component = $components->get('TestBundle:Button');
        static::assertNull($component->getMetadata());
    }

    public function testGetComponentFromTemplate(): void
    {
        $templatePath = $this->tempDir . '/Button.html.twig';
        file_put_contents($templatePath, '<button>Test</button>');

        $splFileInfo = new SplFileInfo($templatePath, '', 'Button.html.twig');

        $helper = new TwigComponentHelper(
            'Resources/views/storefront/components',
            $this->tempDir,
            [],
            $this->createMock(NamespaceHierarchyBuilder::class),
            $this->createComponentFactory(),
            $this->createMock(Connection::class)
        );

        $component = $helper->getComponentFromTemplate($splFileInfo, 'TestNamespace');

        static::assertInstanceOf(TwigComponent::class, $component);
        static::assertSame('Button', $component->getName());
        static::assertSame($templatePath, $component->getPath());
        static::assertSame('TestNamespace', $component->getNamespace());
    }

    public function testGetComponentFromTemplateWithNestedPath(): void
    {
        $nestedDir = $this->tempDir . '/Forms/Input';
        mkdir($nestedDir, 0777, true);

        $templatePath = $nestedDir . '/Text.html.twig';
        file_put_contents($templatePath, '<input type="text" />');

        $splFileInfo = new SplFileInfo($templatePath, 'Forms/Input', 'Forms/Input/Text.html.twig');

        $helper = new TwigComponentHelper(
            'Resources/views/storefront/components',
            $this->tempDir,
            [],
            $this->createMock(NamespaceHierarchyBuilder::class),
            $this->createComponentFactory(),
            $this->createMock(Connection::class)
        );

        $component = $helper->getComponentFromTemplate($splFileInfo, 'TestNamespace');

        static::assertSame('Forms:Input:Text', $component->getName());
        static::assertSame($templatePath, $component->getPath());
        static::assertSame('TestNamespace', $component->getNamespace());
    }

    public function testGetComponentFromTemplateWithComponentsPrefix(): void
    {
        $componentDir = $this->tempDir . '/components';
        mkdir($componentDir, 0777, true);

        $templatePath = $componentDir . '/Button.html.twig';
        file_put_contents($templatePath, '<button>Test</button>');

        $splFileInfo = new SplFileInfo($templatePath, 'components', 'components/Button.html.twig');

        $helper = new TwigComponentHelper(
            'Resources/views/storefront/components',
            $this->tempDir,
            [],
            $this->createMock(NamespaceHierarchyBuilder::class),
            $this->createComponentFactory(),
            $this->createMock(Connection::class)
        );

        $component = $helper->getComponentFromTemplate($splFileInfo, 'TestNamespace');

        static::assertSame('Button', $component->getName());
        static::assertSame($templatePath, $component->getPath());
    }

    public function testGetComponentsHandlesMultipleBundles(): void
    {
        $bundle1Path = $this->tempDir . '/Bundle1';
        $component1Dir = $bundle1Path . '/Resources/views/storefront/components';
        mkdir($component1Dir, 0777, true);
        file_put_contents($component1Dir . '/Button.html.twig', '<button>Bundle1</button>');

        $bundle2Path = $this->tempDir . '/Bundle2';
        $component2Dir = $bundle2Path . '/Resources/views/storefront/components';
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
            'Resources/views/storefront/components',
            $this->tempDir,
            [
                'Bundle1' => ['path' => $bundle1Path],
                'Bundle2' => ['path' => $bundle2Path],
            ],
            $namespaceHierarchyBuilder,
            $componentFactory,
            $connection
        );

        $components = $helper->getComponents();

        static::assertCount(2, $components);
        static::assertTrue($components->has('Bundle1:Button'));
        static::assertTrue($components->has('Bundle2:Card'));
    }

    public function testGetComponentsSkipsBundlesWithoutComponentDirectory(): void
    {
        $bundle1Path = $this->tempDir . '/Bundle1';
        $component1Dir = $bundle1Path . '/Resources/views/storefront/components';
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
            'Resources/views/storefront/components',
            $this->tempDir,
            [
                'Bundle1' => ['path' => $bundle1Path],
                'Bundle2' => ['path' => $bundle2Path],
            ],
            $namespaceHierarchyBuilder,
            $componentFactory,
            $connection
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        static::assertTrue($components->has('Bundle1:Button'));
        static::assertFalse($components->has('Bundle2:*'));
    }

    public function testGetComponentsHandlesStorefrontNamespace(): void
    {
        $storefrontPath = $this->tempDir . '/Storefront';
        $componentDir = $storefrontPath . '/Resources/views/storefront/components';
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
            'Resources/views/storefront/components',
            $this->tempDir,
            [
                'Storefront' => ['path' => $storefrontPath],
            ],
            $namespaceHierarchyBuilder,
            $componentFactory,
            $connection
        );

        $components = $helper->getComponents();

        static::assertCount(1, $components);
        static::assertTrue($components->has('Button'));
        static::assertFalse($components->has('Storefront:Button'));
    }
}

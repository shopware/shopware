<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\DependencyInjection\TwigComponentBundlePass;
use Shopware\Storefront\Storefront;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(TwigComponentBundlePass::class)]
class TwigComponentBundlePassTest extends TestCase
{
    private string $tmpDir = '';

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
    }

    protected function tearDown(): void
    {
        if ($this->tmpDir !== '' && $this->filesystem->exists($this->tmpDir)) {
            $this->filesystem->remove($this->tmpDir);
        }
    }

    public function testProcessDoesNothingWhenTwigComponentParameterNotSet(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);

        (new TwigComponentBundlePass())->process($container);

        static::assertFalse($container->hasParameter('ux.twig_component.component_defaults'));
        static::assertSame([], $container->getParameter('storefront.bundle_components'));
    }

    public function testProcessDoesNothingWhenDefaultsIsNotArray(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', 'not-an-array');
        $container->setParameter('kernel.bundles', []);

        (new TwigComponentBundlePass())->process($container);

        static::assertSame('not-an-array', $container->getParameter('ux.twig_component.component_defaults'));
    }

    public function testProcessDoesNothingWhenKernelBundlesIsNotArray(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        $container->setParameter('kernel.bundles', 'not-an-array');

        (new TwigComponentBundlePass())->process($container);

        static::assertSame([], $container->getParameter('ux.twig_component.component_defaults'));
    }

    public function testProcessDoesNothingWhenKernelBundlesMetadataIsNotArray(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        $container->setParameter('kernel.bundles', []);
        $container->setParameter('kernel.bundles_metadata', 'not-an-array');

        (new TwigComponentBundlePass())->process($container);

        static::assertSame([], $container->getParameter('ux.twig_component.component_defaults'));
    }

    public function testProcessRegistersNamespaceForBundleWithComponentsDirectory(): void
    {
        $bundlePath = '/some/storefront/path';

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('exists')
            ->with($bundlePath . '/Resources/views/components')
            ->willReturn(true);

        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        $container->setParameter('kernel.bundles', ['Storefront' => Storefront::class]);
        $container->setParameter('kernel.bundles_metadata', [
            'Storefront' => ['path' => $bundlePath, 'namespace' => 'Shopware\\Storefront'],
        ]);

        (new TwigComponentBundlePass($filesystem))->process($container);

        $defaults = $container->getParameter('ux.twig_component.component_defaults');
        static::assertIsArray($defaults);

        $expectedNamespace = 'Shopware\\Storefront\\Resources\\views\\components\\';
        static::assertArrayHasKey($expectedNamespace, $defaults);
        static::assertSame('@Storefront/components', $defaults[$expectedNamespace]['template_directory']);
        static::assertSame('Storefront', $defaults[$expectedNamespace]['name_prefix']);
    }

    public function testProcessDoesNotOverwriteAlreadyRegisteredNamespace(): void
    {
        $bundlePath = '/some/storefront/path';
        $existingConfig = ['template_directory' => 'custom', 'name_prefix' => 'Custom'];
        $namespace = 'Shopware\\Storefront\\Resources\\views\\components\\';

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('exists')
            ->with($bundlePath . '/Resources/views/components')
            ->willReturn(true);

        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', [$namespace => $existingConfig]);
        $container->setParameter('kernel.bundles', ['Storefront' => Storefront::class]);
        $container->setParameter('kernel.bundles_metadata', [
            'Storefront' => ['path' => $bundlePath, 'namespace' => 'Shopware\\Storefront'],
        ]);

        (new TwigComponentBundlePass($filesystem))->process($container);

        $defaults = $container->getParameter('ux.twig_component.component_defaults');
        static::assertIsArray($defaults);
        static::assertSame($existingConfig, $defaults[$namespace]);
    }

    public function testProcessSkipsNonShopwareBundles(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        $container->setParameter('kernel.bundles', ['StdClass' => \stdClass::class]);
        $container->setParameter('kernel.bundles_metadata', []);

        (new TwigComponentBundlePass())->process($container);

        static::assertSame([], $container->getParameter('ux.twig_component.component_defaults'));
        static::assertSame([], $container->getParameter('storefront.bundle_components'));
    }

    public function testProcessSkipsBundleWithoutComponentsDirectory(): void
    {
        $bundlePath = '/some/path/without/components';

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->once())
            ->method('exists')
            ->with($bundlePath . '/Resources/views/components')
            ->willReturn(false);

        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        $container->setParameter('kernel.bundles', ['Storefront' => Storefront::class]);
        $container->setParameter('kernel.bundles_metadata', [
            'Storefront' => ['path' => $bundlePath, 'namespace' => 'Shopware\\Storefront'],
        ]);

        (new TwigComponentBundlePass($filesystem))->process($container);

        static::assertSame([], $container->getParameter('ux.twig_component.component_defaults'));
        static::assertSame([], $container->getParameter('storefront.bundle_components'));
    }

    public function testProcessSkipsNonExistentClass(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        $container->setParameter('kernel.bundles', ['Ghost' => 'NonExistent\\GhostBundle']);
        $container->setParameter('kernel.bundles_metadata', []);

        (new TwigComponentBundlePass())->process($container);

        static::assertSame([], $container->getParameter('ux.twig_component.component_defaults'));
    }

    public function testProcessSkipsBundleWithMissingMetadata(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->never())->method('exists');

        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        $container->setParameter('kernel.bundles', ['Storefront' => Storefront::class]);
        $container->setParameter('kernel.bundles_metadata', []);

        (new TwigComponentBundlePass($filesystem))->process($container);

        static::assertSame([], $container->getParameter('ux.twig_component.component_defaults'));
    }

    public function testDiscoversBundleComponentsFromRealDirectory(): void
    {
        $this->tmpDir = $this->createTempDir();
        $this->filesystem->mkdir($this->tmpDir . '/Resources/views/components/Sw');
        $this->filesystem->dumpFile($this->tmpDir . '/Resources/views/components/Sw/Button.html.twig', '<button>test</button>');
        $this->filesystem->dumpFile($this->tmpDir . '/Resources/views/components/Sw/Card.html.twig', '<div>card</div>');

        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['Storefront' => Storefront::class]);
        $container->setParameter('kernel.bundles_metadata', [
            'Storefront' => ['path' => $this->tmpDir, 'namespace' => 'Shopware\\Storefront'],
        ]);

        (new TwigComponentBundlePass())->process($container);

        $components = $container->getParameter('storefront.bundle_components');
        static::assertIsArray($components);
        static::assertCount(2, $components);

        $names = array_column($components, 'name');
        static::assertContains('Sw:Button', $names);
        static::assertContains('Sw:Card', $names);

        foreach ($components as $component) {
            static::assertSame('Storefront', $component['namespace']);
            static::assertStringEndsWith('.html.twig', $component['path']);
        }
    }

    public function testDiscoversNestedBundleComponents(): void
    {
        $this->tmpDir = $this->createTempDir();
        $this->filesystem->mkdir($this->tmpDir . '/Resources/views/components/Forms/Input');
        $this->filesystem->dumpFile($this->tmpDir . '/Resources/views/components/Forms/Input/Text.html.twig', '<input>');

        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['Storefront' => Storefront::class]);
        $container->setParameter('kernel.bundles_metadata', [
            'Storefront' => ['path' => $this->tmpDir, 'namespace' => 'Shopware\\Storefront'],
        ]);

        (new TwigComponentBundlePass())->process($container);

        $components = $container->getParameter('storefront.bundle_components');
        static::assertCount(1, $components);
        static::assertSame('Forms:Input:Text', $components[0]['name']);
        static::assertSame('Storefront', $components[0]['namespace']);
    }

    public function testExcludesFilesInUnderscorePrefixedDirectories(): void
    {
        $this->tmpDir = $this->createTempDir();
        $this->filesystem->mkdir($this->tmpDir . '/Resources/views/components/Sw');
        $this->filesystem->mkdir($this->tmpDir . '/Resources/views/components/_private');
        $this->filesystem->dumpFile($this->tmpDir . '/Resources/views/components/Sw/Button.html.twig', '<button>');
        $this->filesystem->dumpFile($this->tmpDir . '/Resources/views/components/_private/Helper.html.twig', '<div>');

        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['Storefront' => Storefront::class]);
        $container->setParameter('kernel.bundles_metadata', [
            'Storefront' => ['path' => $this->tmpDir, 'namespace' => 'Shopware\\Storefront'],
        ]);

        (new TwigComponentBundlePass())->process($container);

        $components = $container->getParameter('storefront.bundle_components');
        static::assertCount(1, $components);
        static::assertSame('Sw:Button', $components[0]['name']);
    }

    public function testDiscoversBundleComponentsFromMultipleBundles(): void
    {
        $this->tmpDir = $this->createTempDir();
        $this->filesystem->mkdir($this->tmpDir . '/Bundle1/Resources/views/components');
        $this->filesystem->mkdir($this->tmpDir . '/Bundle2/Resources/views/components');
        $this->filesystem->dumpFile($this->tmpDir . '/Bundle1/Resources/views/components/Alert.html.twig', '<div>');
        $this->filesystem->dumpFile($this->tmpDir . '/Bundle2/Resources/views/components/Badge.html.twig', '<span>');

        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', [
            'Bundle1' => Storefront::class,
            'Bundle2' => Storefront::class,
        ]);
        $container->setParameter('kernel.bundles_metadata', [
            'Bundle1' => ['path' => $this->tmpDir . '/Bundle1', 'namespace' => 'Shopware\\Bundle1'],
            'Bundle2' => ['path' => $this->tmpDir . '/Bundle2', 'namespace' => 'Shopware\\Bundle2'],
        ]);

        (new TwigComponentBundlePass())->process($container);

        $components = $container->getParameter('storefront.bundle_components');
        static::assertCount(2, $components);

        $namespaces = array_column($components, 'namespace');
        static::assertContains('Bundle1', $namespaces);
        static::assertContains('Bundle2', $namespaces);
    }

    public function testProducesEmptyListWhenMissingKernelParameters(): void
    {
        $container = new ContainerBuilder();
        // Neither kernel.bundles nor kernel.bundles_metadata set

        (new TwigComponentBundlePass())->process($container);

        static::assertSame([], $container->getParameter('storefront.bundle_components'));
    }

    private function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/twig_component_pass_test_' . uniqid();
        $this->filesystem->mkdir($dir);

        return $dir;
    }
}

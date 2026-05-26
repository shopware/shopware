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
    public function testProcessDoesNothingWhenTwigComponentParameterNotSet(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);

        (new TwigComponentBundlePass())->process($container);

        static::assertFalse($container->hasParameter('ux.twig_component.component_defaults'));
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
}

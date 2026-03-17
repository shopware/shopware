<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;
use Shopware\Storefront\DependencyInjection\TwigComponentBundlePass;
use Shopware\Storefront\Storefront;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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

    public function testProcessRegistersNamespaceForBundleWithComponentsDirectory(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        $container->setParameter('kernel.bundles', ['Storefront' => Storefront::class]);

        (new TwigComponentBundlePass())->process($container);

        $defaults = $container->getParameter('ux.twig_component.component_defaults');
        static::assertIsArray($defaults);

        $expectedNamespace = 'Shopware\\Storefront\\Resources\\views\\components\\';
        static::assertArrayHasKey($expectedNamespace, $defaults);
        static::assertSame('components', $defaults[$expectedNamespace]['template_directory']);
        static::assertSame('Storefront', $defaults[$expectedNamespace]['name_prefix']);
    }

    public function testProcessDoesNotOverwriteAlreadyRegisteredNamespace(): void
    {
        $existingConfig = ['template_directory' => 'custom', 'name_prefix' => 'Custom'];
        $namespace = 'Shopware\\Storefront\\Resources\\views\\components\\';

        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', [$namespace => $existingConfig]);
        $container->setParameter('kernel.bundles', ['Storefront' => Storefront::class]);

        (new TwigComponentBundlePass())->process($container);

        $defaults = $container->getParameter('ux.twig_component.component_defaults');
        static::assertIsArray($defaults);
        static::assertSame($existingConfig, $defaults[$namespace]);
    }

    public function testProcessSkipsNonShopwareBundles(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        $container->setParameter('kernel.bundles', ['stdClass' => \stdClass::class]);

        (new TwigComponentBundlePass())->process($container);

        static::assertSame([], $container->getParameter('ux.twig_component.component_defaults'));
    }

    public function testProcessSkipsBundleWithoutComponentsDirectory(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        $container->setParameter('kernel.bundles', ['TestBundle' => TwigComponentTestBundleWithoutComponents::class]);

        (new TwigComponentBundlePass())->process($container);

        static::assertSame([], $container->getParameter('ux.twig_component.component_defaults'));
    }

    public function testProcessSkipsNonExistentClass(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        $container->setParameter('kernel.bundles', ['Ghost' => 'NonExistent\\GhostBundle']);

        (new TwigComponentBundlePass())->process($container);

        static::assertSame([], $container->getParameter('ux.twig_component.component_defaults'));
    }

    public function testProcessSkipsClassThatThrowsReflectionExceptionOnInstantiation(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('ux.twig_component.component_defaults', []);
        // \Closure is an internal PHP class — newInstanceWithoutConstructor() throws ReflectionException
        $container->setParameter('kernel.bundles', ['Closure' => \Closure::class]);

        (new TwigComponentBundlePass())->process($container);

        static::assertSame([], $container->getParameter('ux.twig_component.component_defaults'));
    }
}

/**
 * @internal
 *
 * A Shopware Bundle that returns a path with no components directory, to test the skip branch.
 */
class TwigComponentTestBundleWithoutComponents extends Bundle
{
    public function getPath(): string
    {
        return sys_get_temp_dir();
    }
}

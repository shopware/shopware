<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentHelper;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Automatically registers Twig component namespaces for all bundles that have a components directory.
 * This allows plugins and bundles to use PHP classes for Twig components.
 */
#[Package('framework')]
class TwigComponentBundlePass implements CompilerPassInterface
{
    public function __construct(
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        // Only proceed if TwigComponentBundle is installed and configured
        if (!$container->hasParameter('ux.twig_component.component_defaults')) {
            return;
        }

        $defaults = $container->getParameter('ux.twig_component.component_defaults');
        if (!\is_array($defaults)) {
            return;
        }

        $bundles = $container->getParameter('kernel.bundles');
        if (!\is_array($bundles)) {
            return;
        }

        $bundlesMeta = $container->getParameter('kernel.bundles_metadata');
        if (!\is_array($bundlesMeta)) {
            return;
        }

        foreach ($bundles as $bundleName => $bundleClass) {
            if (!\is_string($bundleClass) || !is_a($bundleClass, Bundle::class, true)) {
                continue;
            }

            $meta = $bundlesMeta[$bundleName] ?? null;
            if (!\is_array($meta)) {
                continue;
            }

            $componentDir = rtrim($meta['path'] . '/' . TwigComponentHelper::COMPONENT_DIRECTORY, '/');

            if (!$this->filesystem->exists($componentDir)) {
                continue;
            }

            // Build the namespace for component classes in this bundle
            // Format: BundleNamespace\Resources\views\components\
            $componentNamespace = $meta['namespace'] . '\\Resources\\views\\components\\';

            if (!isset($defaults[$componentNamespace])) {
                $defaults[$componentNamespace] = [
                    'template_directory' => '@' . $bundleName . '/components',
                    'name_prefix' => $bundleName,
                ];
            }
        }

        $container->setParameter('ux.twig_component.component_defaults', $defaults);
    }
}

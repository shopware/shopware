<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Automatically registers Twig component namespaces for all bundles that have a components directory.
 * This allows plugins and bundles to use PHP classes for Twig components.
 */
#[Package('framework')]
class TwigComponentBundlePass implements CompilerPassInterface
{
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

        foreach ($bundles as $bundleClass) {
            if (!\is_string($bundleClass) || !class_exists($bundleClass)) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($bundleClass);
                $bundle = $reflection->newInstanceWithoutConstructor();
            } catch (\ReflectionException $e) {
                // Skip bundles that can't be reflected
                continue;
            }

            if (!$bundle instanceof Bundle) {
                continue;
            }

            $bundlePath = $bundle->getPath();
            $componentDir = $bundlePath . '/Resources/views/components';

            if (!is_dir($componentDir)) {
                continue;
            }

            // Build the namespace for component classes in this bundle
            // Format: BundleNamespace\Resources\views\components\
            // Note: Using lowercase to match actual directory structure (PSR-4 is case-sensitive)
            $bundleNamespace = $reflection->getNamespaceName();
            $componentNamespace = $bundleNamespace . '\\Resources\\views\\components\\';

            if (!isset($defaults[$componentNamespace])) {
                $bundleName = $bundle->getName();

                $defaults[$componentNamespace] = [
                    'template_directory' => 'components',
                    'name_prefix' => $bundleName,
                ];
            }
        }

        $container->setParameter('ux.twig_component.component_defaults', $defaults);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Automatically registers Twig component namespaces for all bundles that ship a
 * `Resources/views/components/` directory, so Symfony UX TwigComponent can resolve
 * `<twig:Bundle:Component />` tags to the correct PHP class namespace.
 *
 * @internal
 */
#[Package('framework')]
final class TwigComponentBundlePass implements CompilerPassInterface
{
    private const COMPONENT_DIRECTORY = 'Resources/views/components';

    public function __construct(
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        // Only register Symfony UX namespaces if TwigComponentBundle is installed and configured
        if (!$container->hasParameter('ux.twig_component.component_defaults')) {
            return;
        }

        $defaults = $container->getParameter('ux.twig_component.component_defaults');
        if (!\is_array($defaults)) {
            return;
        }

        if (!$container->hasParameter('kernel.bundles') || !$container->hasParameter('kernel.bundles_metadata')) {
            return;
        }

        $bundles = $container->getParameter('kernel.bundles');
        $bundlesMeta = $container->getParameter('kernel.bundles_metadata');

        if (!\is_array($bundles) || !\is_array($bundlesMeta)) {
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

            $componentDir = Path::join($meta['path'], self::COMPONENT_DIRECTORY);

            if (!$this->filesystem->exists($componentDir)) {
                continue;
            }

            // Register the PHP class namespace for Symfony UX TwigComponent.
            // Each bundle declares its own namespace via getTwigComponentNamespace(),
            // which can be overridden for non-standard directory structures.
            $componentNamespace = $bundleClass::getTwigComponentNamespace();

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

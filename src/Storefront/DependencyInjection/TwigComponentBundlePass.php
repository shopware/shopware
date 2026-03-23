<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentHelper;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

/**
 * Automatically registers Twig component namespaces for all bundles that have a components
 * directory, and pre-computes the full bundle component list so TwigComponentHelper does not
 * need to scan the filesystem at runtime.
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
        if (!$container->hasParameter('kernel.bundles') || !$container->hasParameter('kernel.bundles_metadata')) {
            $container->setParameter('storefront.bundle_components', []);

            return;
        }

        $bundles = $container->getParameter('kernel.bundles');
        $bundlesMeta = $container->getParameter('kernel.bundles_metadata');

        if (!\is_array($bundles) || !\is_array($bundlesMeta)) {
            $container->setParameter('storefront.bundle_components', []);

            return;
        }

        // Only register Symfony UX namespaces if TwigComponentBundle is installed and configured
        $defaults = $container->hasParameter('ux.twig_component.component_defaults')
            ? $container->getParameter('ux.twig_component.component_defaults')
            : null;

        if (!\is_array($defaults)) {
            $defaults = null;
        }

        $bundleComponents = [];

        foreach ($bundles as $bundleName => $bundleClass) {
            if (!\is_string($bundleClass) || !is_a($bundleClass, Bundle::class, true)) {
                continue;
            }

            $meta = $bundlesMeta[$bundleName] ?? null;
            if (!\is_array($meta)) {
                continue;
            }

            $componentDir = Path::join($meta['path'], TwigComponentHelper::COMPONENT_DIRECTORY);

            if (!$this->filesystem->exists($componentDir)) {
                continue;
            }

            // Register the PHP class namespace for Symfony UX TwigComponent.
            // Each bundle declares its own namespace via getTwigComponentNamespace(),
            // which can be overridden for non-standard directory structures.
            if ($defaults !== null) {
                $componentNamespace = $bundleClass::getTwigComponentNamespace();

                if (!isset($defaults[$componentNamespace])) {
                    $defaults[$componentNamespace] = [
                        'template_directory' => '@' . $bundleName . '/components',
                        'name_prefix' => $bundleName,
                    ];
                }
            }

            // Collect anonymous component templates
            array_push($bundleComponents, ...$this->scanComponentDirectory($componentDir, $bundleName));
        }

        $container->setParameter('storefront.bundle_components', $bundleComponents);

        if ($defaults !== null) {
            $container->setParameter('ux.twig_component.component_defaults', $defaults);
        }
    }

    /**
     * Returns all anonymous component templates found in a single bundle's components directory.
     *
     * @return list<array{name: string, namespace: string, path: string}>
     */
    private function scanComponentDirectory(string $componentDir, string $bundleName): array
    {
        $finder = new Finder();

        try {
            $finder->files()->in($componentDir)->name('*.html.twig');
        } catch (DirectoryNotFoundException) {
            return [];
        }

        $components = [];

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();

            // Skip files inside underscore-prefixed directories (e.g. _partials, _private)
            if (str_contains('/' . $relativePath, '/_')) {
                continue;
            }

            $components[] = [
                'name' => TwigComponentHelper::getComponentNameFromPath($relativePath),
                'namespace' => $bundleName,
                'path' => Path::canonicalize($file->getPathname()),
            ];
        }

        return $components;
    }
}

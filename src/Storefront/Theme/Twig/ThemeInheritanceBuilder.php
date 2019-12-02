<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Twig;

use Shopware\Storefront\Framework\ThemeInterface;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ThemeInheritanceBuilder implements ThemeInheritanceBuilderInterface
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    public function build(array $bundles, array $themes): array
    {
        $bundles = $this->filterThemes($bundles, $themes);

        return $this->sortBundles($bundles, $themes);
    }

    private function sortBundles(array $bundles, array $themes): array
    {
        $keys = array_keys($themes);

        $theme = array_shift($keys);

        $inheritance = $this->getThemeInheritance($theme, $themes);

        if (!$inheritance) {
            return $bundles;
        }

        foreach ($bundles as $bundle) {
            $key = '@' . $bundle;

            if (isset($inheritance[$key])) {
                $inheritance[$key][] = $bundle;
            } else {
                $inheritance['@Plugins'][] = $bundle;
            }
        }

        $flat = [];
        foreach ($inheritance as $namespace) {
            foreach ($namespace as $bundle) {
                $flat[] = $bundle;
            }
        }

        return array_reverse($flat);
    }

    private function getThemeInheritance(string $theme, array $themes): ?array
    {
        $names = array_keys($themes);

        $default = [
            // ensure storefront to be first
            '@Storefront' => [],
        ];

        foreach ($names as $name) {
            $name = '@' . $name;
            $default[$name] = [];
        }

        $default = $this->injectPluginWildcard($default);

        $bundle = $this->getBundle($theme);

        if (!$bundle) {
            return null;
        }

        // try to load inheritance from theme.json file
        $file = $bundle->getPath() . '/Resources/theme.json';
        if (!file_exists($file)) {
            return $default;
        }

        $config = json_decode(file_get_contents($file), true);
        if (!isset($config['views'])) {
            return $default;
        }

        $inheritance = $config['views'];

        if (empty($inheritance)) {
            return $default;
        }

        // ensure storefront is included
        if (!in_array('@Storefront', $inheritance, true)) {
            $inheritance = array_merge(['@Storefront'], $inheritance);
        }

        $tree = [];
        foreach ($inheritance as $name) {
            $tree[$name] = [];
        }

        return $this->injectPluginWildcard($tree);
    }

    private function filterThemes(array $bundles, array $themes): array
    {
        $filtered = [];

        foreach ($bundles as $bundle) {
            $bundleClass = $this->getBundle($bundle);

            if (
                $bundleClass === null

                // add all plugins
                || !($bundleClass instanceof ThemeInterface)

                // always add storefront for new routes and templates fallback
                || $bundle === StorefrontPluginRegistry::BASE_THEME_NAME

                // filter all none active themes
                || isset($themes[$bundle])
            ) {
                $filtered[] = $bundle;
            }
        }

        return $filtered;
    }

    private function getBundle(string $name): ?BundleInterface
    {
        $bundles = $this->kernel->getBundles();

        if (array_key_exists($name, $bundles)) {
            return $this->kernel->getBundle($name);
        }

        return null;
    }

    private function injectPluginWildcard(array $inheritance): array
    {
        // ensure plugin support
        if (isset($inheritance['@Plugins'])) {
            return $inheritance;
        }

        $sorted = [];
        foreach ($inheritance as $index => $name) {
            $sorted[$index] = $name;

            if ($index === '@Storefront') {
                $sorted['@Plugins'] = [];
            }
        }

        return $sorted;
    }
}

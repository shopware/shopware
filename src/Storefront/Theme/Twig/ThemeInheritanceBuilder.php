<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Twig;

use Shopware\Storefront\Framework\ThemeInterface;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ThemeInheritanceBuilder implements ThemeInheritanceBuilderInterface
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var StorefrontPluginRegistryInterface|null
     */
    private $themeRegistry;

    public function __construct(KernelInterface $kernel, ?StorefrontPluginRegistryInterface $themeRegistry = null)
    {
        $this->kernel = $kernel;
        $this->themeRegistry = $themeRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function build(array $bundles, array $themes): array
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

                continue;
            }
            if ($this->isTheme($bundle)) {
                continue;
            }

            $inheritance['@Plugins'][] = $bundle;
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

        if ($this->themeRegistry) {
            $themeConfig = $this->themeRegistry
                ->getConfigurations()
                ->getByTechnicalName($theme);

            if (!$themeConfig) {
                return $default;
            }

            $inheritance = $themeConfig->getViewInheritance();
        } else {
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
        }

        if (empty($inheritance)) {
            return $default;
        }

        $tree = [];
        foreach ($inheritance as $name) {
            $tree[$name] = [];
        }

        return $this->injectPluginWildcard($tree);
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

    private function isTheme(string $bundle): bool
    {
        if ($this->themeRegistry) {
            $themeConfig = $this->themeRegistry->getConfigurations()->getByTechnicalName($bundle);

            if ($themeConfig === null) {
                return false;
            }

            if ($themeConfig->getIsTheme()) {
                return true;
            }

            if ($bundle === StorefrontPluginRegistry::BASE_THEME_NAME) {
                return true;
            }

            return false;
        }

        $bundleClass = $this->getBundle($bundle);

        if ($bundleClass === null) {
            return false;
        }

        if ($bundleClass instanceof ThemeInterface) {
            return true;
        }

        if ($bundle === StorefrontPluginRegistry::BASE_THEME_NAME) {
            return true;
        }

        return false;
    }
}

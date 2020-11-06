<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Twig;

use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;

class ThemeInheritanceBuilder implements ThemeInheritanceBuilderInterface
{
    /**
     * @var StorefrontPluginRegistryInterface
     */
    private $themeRegistry;

    public function __construct(StorefrontPluginRegistryInterface $themeRegistry)
    {
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

    private function getThemeInheritance(string $theme, array $themes): array
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

        $themeConfig = $this->themeRegistry
            ->getConfigurations()
            ->getByTechnicalName($theme);

        if (!$themeConfig) {
            return $default;
        }

        $inheritance = $themeConfig->getViewInheritance();

        if (empty($inheritance)) {
            return $default;
        }

        $tree = [];
        foreach ($inheritance as $name) {
            $tree[$name] = [];
        }

        return $this->injectPluginWildcard($tree);
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
}

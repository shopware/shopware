<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Twig;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;

#[Package('framework')]
class ThemeInheritanceBuilder implements ThemeInheritanceBuilderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ThemeRuntimeConfigService $themeRuntimeConfigService,
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * @param array<string, int> $bundles Bundle names mapped to their priority values (lower = higher priority)
     * @param array<string, bool> $themes Active theme names mapped to their activation status
     *
     * @return array<string, int> Reordered bundles maintaining original priority values
     */
    public function build(array $bundles, array $themes): array
    {
        arsort($bundles);

        $keys = array_keys($themes);
        $theme = array_shift($keys);

        // Get inheritance slots from theme.json: ['@Storefront' => [], '@Plugins' => [], ...]
        // @ prefix indicates placeholder slots
        $inheritance = $this->getThemeInheritance((string) $theme, $themes);

        foreach (array_keys($bundles) as $bundle) {
            $key = '@' . $bundle;

            if (isset($inheritance[$key])) {
                // Bundle has explicit position in theme.json
                $inheritance[$key][] = $bundle;
                continue;
            }

            if ($this->isTheme($bundle)) {
                // Themes handled by getThemeInheritance
                continue;
            }

            // Non-explicit plugins go into @Plugins wildcard
            $inheritance['@Plugins'][] = $bundle;
        }

        /*
         * Double-reversal preserves plugin priority order:
         * Input: ['Plugin1' => 0, 'Plugin2' => 1] (sorted by priority)
         * After this reversal: ['Plugin2', 'Plugin1']
         * After final reversal: ['Plugin1', 'Plugin2'] (original order restored)
         */
        $inheritance['@Plugins'] = array_reverse($inheritance['@Plugins']);

        // Flatten inheritance slots
        $flat = [];
        foreach ($inheritance as $namespace) {
            foreach ($namespace as $bundle) {
                $flat[] = $bundle;
            }
        }

        // Reverse: last element = highest priority
        $flat = array_reverse($flat);

        // Rebuild with original priority values
        $new = [];
        foreach ($flat as $bundle) {
            $new[$bundle] = $bundles[$bundle];
        }

        return $new;
    }

    /**
     * @param array<string, bool> $themes
     *
     * @return array<string, array<int, string>>
     */
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

        $runtimeConfig = $this->themeRuntimeConfigService->getRuntimeConfigByName($theme);

        if (!$runtimeConfig) {
            return $default;
        }

        $inheritance = $runtimeConfig->viewInheritance;

        if (empty($inheritance)) {
            return $default;
        }

        $tree = [];
        foreach ($inheritance as $name) {
            $tree[$name] = [];
        }

        return $this->injectPluginWildcard($tree);
    }

    /**
     * @param array<string, array<int, string>> $inheritance
     *
     * @return array<string, array<int, string>>
     */
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
        if (\in_array($bundle, $this->themeRuntimeConfigService->getActiveThemeNames(), true)) {
            return true;
        }

        if ($bundle === StorefrontPluginRegistry::BASE_THEME_NAME) {
            return true;
        }

        return false;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Twig;

use Shopware\Core\Framework\Twig\TemplateFinder;
use Shopware\Storefront\Framework\ThemeInterface;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Twig\Error\LoaderError;

class ThemeTemplateFinder extends TemplateFinder
{
    /**
     * @var string|null
     */
    private $activeThemeName;

    /**
     * @var string|null
     */
    private $activeThemeBaseName;

    /**
     * @throws LoaderError
     */
    public function find(
        string $template,
        $ignoreMissing = false,
        ?string $startAt = null,
        ?string $activeThemeName = null,
        ?string $activeThemeBaseName = null
    ): string {
        if ($activeThemeName !== null) {
            $this->activeThemeName = $activeThemeName;
        }
        if ($activeThemeBaseName !== null) {
            $this->activeThemeBaseName = $activeThemeBaseName;
        }

        return parent::find($template, $ignoreMissing, $startAt);
    }

    protected function filterBundles(array $bundles)
    {
        $prefilteredBundles = parent::filterBundles($bundles);
        $postfilteredBundles = [];

        foreach ($prefilteredBundles as $bundle) {
            $kernelBundles = $this->kernel->getBundles();
            $bundleClass = null;
            if (array_key_exists($bundle, $kernelBundles)) {
                $bundleClass = $this->kernel->getBundle($bundle);
            }
            if (
                $bundleClass === null
                || !($bundleClass instanceof ThemeInterface)
                || $bundle === StorefrontPluginRegistry::BASE_THEME_NAME
                || $this->activeThemeName === $bundle
                || $this->activeThemeBaseName === $bundle
            ) {
                $postfilteredBundles[] = $bundle;
            }
        }

        return $postfilteredBundles;
    }
}

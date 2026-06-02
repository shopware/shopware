<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\ShopIdChangeResolver\AppsUninstalledHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

/**
 * Storefront adapter for the Core {@see AppsUninstalledHandler} port: cleans up themes for apps
 * removed by the silent uninstall strategy. Core only depends on the interface; this implementation is wired
 * in via the `shopware.apps_uninstalled_handler` tag.
 *
 * @internal
 */
#[Package('framework')]
readonly class ThemeAppsUninstalledHandler implements AppsUninstalledHandler
{
    public function __construct(
        private StorefrontPluginRegistry $themeRegistry,
        private ThemeLifecycleHandler $themeLifecycleHandler,
    ) {
    }

    public function uninstalled(AppCollection $apps, Context $context): void
    {
        $uninstalledNames = array_values($apps->map(static fn ($app): string => $app->getName()));

        foreach ($uninstalledNames as $name) {
            $config = $this->themeRegistry->getConfigurations()->getByTechnicalName($name);
            if ($config !== null) {
                $this->themeLifecycleHandler->handleThemeUninstall($config, $context);
            }
        }

        $remaining = $this->themeRegistry
            ->getConfigurations()
            ->filter(static fn (StorefrontPluginConfiguration $config): bool => !\in_array($config->getTechnicalName(), $uninstalledNames, true));

        $this->themeLifecycleHandler->refreshAllActiveThemeImportMaps($context, $remaining);
    }
}

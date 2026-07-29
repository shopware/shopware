<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\AbstractLifecycleHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

/**
 * @internal
 */
#[Package('framework')]
class ThemeAppLifecycleHandler extends AbstractLifecycleHandler
{
    public function __construct(
        private readonly StorefrontPluginRegistry $themeRegistry,
        private readonly AbstractStorefrontPluginConfigurationFactory $themeConfigFactory,
        private readonly ThemeLifecycleHandler $themeLifecycleHandler,
        private readonly ThemeLifecycleService $themeLifecycleService,
    ) {
    }

    public function activate(AppActivationContext $context): void
    {
        $this->setupTheme($context->app, $context->context);
    }

    public function update(AppPersistContext $context): void
    {
        $this->setupTheme($context->app, $context->context);
    }

    public function deactivate(AppActivationContext $context): void
    {
        $this->tearDownTheme($context->app, $context->context);
    }

    public function uninstall(AppRemovalContext $context): void
    {
        // the config was torn down by deactivate(); only the theme record remains
        if (!$context->keepUserData) {
            $this->themeLifecycleService->removeTheme($context->app->getName(), $context->context);
        }
    }

    public function delete(AppRemovalContext $context): void
    {
        // local-only delete never deactivates, so tear the config down here; the record is left in place
        $this->tearDownTheme($context->app, $context->context);
    }

    private function setupTheme(AppEntity $app, Context $context): void
    {
        if (!$app->isActive()) {
            return;
        }

        $configurationCollection = $this->themeRegistry->getConfigurations();
        $config = $configurationCollection->getByTechnicalName($app->getName());

        if (!$config) {
            $config = $this->themeConfigFactory->createFromApp($app->getName(), $app->getPath());
            $configurationCollection = clone $configurationCollection;
            $configurationCollection->add($config);
        }

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate($config, $configurationCollection, $context);
        $this->themeLifecycleHandler->refreshAllActiveThemeImportMaps($context, $configurationCollection);
    }

    private function tearDownTheme(AppEntity $app, Context $context): void
    {
        // build the config from the app, not the active-apps registry, so it works after the app is inactive
        $config = $this->themeConfigFactory->createFromApp($app->getName(), $app->getPath());

        $configurationCollection = $this->themeRegistry
            ->getConfigurations()
            ->filter(static fn (StorefrontPluginConfiguration $registeredConfig): bool => $registeredConfig->getTechnicalName() !== $app->getName());

        $this->themeLifecycleHandler->handleThemeUninstall($config, $context);
        $this->themeLifecycleHandler->refreshAllActiveThemeImportMaps($context, $configurationCollection);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppChangedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppsSilentlyUninstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class ThemeAppLifecycleHandler implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly StorefrontPluginRegistry $themeRegistry,
        private readonly AbstractStorefrontPluginConfigurationFactory $themeConfigFactory,
        private readonly ThemeLifecycleHandler $themeLifecycleHandler,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppUpdatedEvent::class => 'handleAppActivationOrUpdate',
            AppActivatedEvent::class => 'handleAppActivationOrUpdate',
            AppDeactivatedEvent::class => 'handleUninstall',
            AppsSilentlyUninstalledEvent::class => 'handleSilentUninstall',
        ];
    }

    public function handleAppActivationOrUpdate(AppChangedEvent $event): void
    {
        $app = $event->getApp();
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

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate(
            $config,
            $configurationCollection,
            $event->getContext()
        );

        $this->themeLifecycleHandler->refreshAllActiveThemeImportMaps($event->getContext(), $configurationCollection);
    }

    public function handleUninstall(AppDeactivatedEvent $event): void
    {
        $this->uninstallTheme($event->getApp(), $event->getContext());
    }

    public function handleSilentUninstall(AppsSilentlyUninstalledEvent $event): void
    {
        $uninstalledNames = [];
        foreach ($event->apps as $app) {
            $uninstalledNames[] = $app->getName();
            $config = $this->themeRegistry->getConfigurations()->getByTechnicalName($app->getName());
            if ($config !== null) {
                $this->themeLifecycleHandler->handleThemeUninstall($config, $event->context);
            }
        }

        $remaining = $this->themeRegistry
            ->getConfigurations()
            ->filter(static fn (StorefrontPluginConfiguration $registeredConfig): bool => !\in_array($registeredConfig->getTechnicalName(), $uninstalledNames, true));

        $this->themeLifecycleHandler->refreshAllActiveThemeImportMaps($event->context, $remaining);
    }

    private function uninstallTheme(AppEntity $app, Context $context): void
    {
        $appName = $app->getName();
        $config = $this->themeRegistry->getConfigurations()->getByTechnicalName($appName);
        $configurationCollection = $this->themeRegistry
            ->getConfigurations()
            ->filter(static fn (StorefrontPluginConfiguration $registeredConfig): bool => $registeredConfig->getTechnicalName() !== $appName);

        if (!$config) {
            $this->themeLifecycleHandler->refreshAllActiveThemeImportMaps($context, $configurationCollection);

            return;
        }

        $this->themeLifecycleHandler->handleThemeUninstall($config, $context);

        $this->themeLifecycleHandler->refreshAllActiveThemeImportMaps($context, $configurationCollection);
    }
}

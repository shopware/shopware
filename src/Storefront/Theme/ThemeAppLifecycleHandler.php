<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppChangedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Component\ComponentPublisher;
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
        private readonly ComponentPublisher $componentPublisher,
        private readonly SourceResolver $sourceResolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppUpdatedEvent::class => 'handleAppActivationOrUpdate',
            AppActivatedEvent::class => 'handleAppActivationOrUpdate',
            AppDeactivatedEvent::class => 'handleUninstall',
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

        $componentManifestChanged = false;

        $appPath = $this->sourceResolver->filesystemForApp($app)->path();
        if ($appPath !== '') {
            $componentManifestChanged = $this->componentPublisher->publishBundle($appPath, $app->getName());
        }

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate(
            $config,
            $configurationCollection,
            $event->getContext()
        );

        if ($componentManifestChanged) {
            $this->themeLifecycleHandler->refreshAllActiveThemeImportMaps($event->getContext(), $configurationCollection);
        }
    }

    public function handleUninstall(AppDeactivatedEvent $event): void
    {
        $appName = $event->getApp()->getName();
        $config = $this->themeRegistry->getConfigurations()->getByTechnicalName($appName);
        $configurationCollection = $this->themeRegistry
            ->getConfigurations()
            ->filter(static fn (StorefrontPluginConfiguration $registeredConfig): bool => $registeredConfig->getTechnicalName() !== $appName);

        $componentManifestChanged = $this->componentPublisher->unpublish($appName);

        if (!$config) {
            if ($componentManifestChanged) {
                $this->themeLifecycleHandler->refreshAllActiveThemeImportMaps($event->getContext(), $configurationCollection);
            }

            return;
        }

        $this->themeLifecycleHandler->handleThemeUninstall($config, $event->getContext());

        if ($componentManifestChanged) {
            $this->themeLifecycleHandler->refreshAllActiveThemeImportMaps($event->getContext(), $configurationCollection);
        }
    }
}

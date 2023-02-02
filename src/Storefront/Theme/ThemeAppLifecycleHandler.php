<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppChangedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ThemeAppLifecycleHandler implements EventSubscriberInterface
{
    /**
     * @var StorefrontPluginRegistryInterface
     */
    private $themeRegistry;

    /**
     * @var AbstractStorefrontPluginConfigurationFactory
     */
    private $themeConfigFactory;

    /**
     * @var ThemeLifecycleHandler
     */
    private $themeLifecycleHandler;

    /**
     * @internal
     */
    public function __construct(
        StorefrontPluginRegistryInterface $themeRegistry,
        AbstractStorefrontPluginConfigurationFactory $themeConfigFactory,
        ThemeLifecycleHandler $themeLifecycleHandler
    ) {
        $this->themeRegistry = $themeRegistry;
        $this->themeConfigFactory = $themeConfigFactory;
        $this->themeLifecycleHandler = $themeLifecycleHandler;
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

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate(
            $config,
            $configurationCollection,
            $event->getContext()
        );
    }

    public function handleUninstall(AppDeactivatedEvent $event): void
    {
        $config = $this->themeRegistry->getConfigurations()->getByTechnicalName($event->getApp()->getName());

        if (!$config) {
            return;
        }

        $this->themeLifecycleHandler->handleThemeUninstall($config, $event->getContext());
    }
}

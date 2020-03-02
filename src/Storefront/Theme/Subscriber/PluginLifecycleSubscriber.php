<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Subscriber;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Event\PluginPreActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUpdateEvent;
use Shopware\Storefront\Theme\Exception\InvalidThemeBundleException;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PluginLifecycleSubscriber implements EventSubscriberInterface
{
    /**
     * @var StorefrontPluginRegistryInterface
     */
    private $storefrontPluginRegistry;

    /**
     * @var string
     */
    private $projectDirectory;

    /**
     * @var StorefrontPluginConfigurationFactory
     */
    private $pluginConfigurationFactory;

    /**
     * @var ThemeLifecycleHandler
     */
    private $themeLifecycleHandler;

    public function __construct(
        StorefrontPluginRegistryInterface $storefrontPluginRegistry,
        string $projectDirectory,
        StorefrontPluginConfigurationFactory $pluginConfigurationFactory,
        ThemeLifecycleHandler $themeLifecycleHandler
    ) {
        $this->storefrontPluginRegistry = $storefrontPluginRegistry;
        $this->projectDirectory = $projectDirectory;
        $this->pluginConfigurationFactory = $pluginConfigurationFactory;
        $this->themeLifecycleHandler = $themeLifecycleHandler;
    }

    public static function getSubscribedEvents()
    {
        return [
            PluginPreActivateEvent::class => 'pluginActivate',
            PluginPreUpdateEvent::class => 'pluginUpdate',
            PluginPreDeactivateEvent::class => 'pluginDeactivateAndUninstall',
            PluginPreUninstallEvent::class => 'pluginDeactivateAndUninstall',
        ];
    }

    public function pluginActivate(PluginPreActivateEvent $event): void
    {
        // create instance of the plugin to create a configuration
        // (the kernel boot is already finished and the activated plugin is missing)
        $storefrontPluginConfig = $this->createConfigFromClassName(
            $event->getPlugin()->getPath(),
            $event->getPlugin()->getBaseClass()
        );

        // add plugin configuration to the list of all active plugin configurations
        $configurationCollection = clone $this->storefrontPluginRegistry->getConfigurations();
        $configurationCollection->add($storefrontPluginConfig);

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate(
            $storefrontPluginConfig,
            $configurationCollection,
            $event->getContext()->getContext()
        );
    }

    public function pluginUpdate(PluginPreUpdateEvent $event): void
    {
        $pluginName = $event->getPlugin()->getName();
        $config = $this->storefrontPluginRegistry->getConfigurations()->getByTechnicalName($pluginName);

        if (!$config) {
            return;
        }

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate(
            $config,
            $this->storefrontPluginRegistry->getConfigurations(),
            $event->getContext()->getContext()
        );
    }

    /**
     * @param PluginPreDeactivateEvent|PluginPreUninstallEvent $event
     */
    public function pluginDeactivateAndUninstall($event): void
    {
        $pluginName = $event->getPlugin()->getName();
        $config = $this->storefrontPluginRegistry->getConfigurations()->getByTechnicalName($pluginName);

        if (!$config) {
            return;
        }

        $this->themeLifecycleHandler->handleThemeUninstall($config, $event->getContext()->getContext());
    }

    /**
     * @throws ThemeCompileException
     * @throws InvalidThemeBundleException
     */
    private function createConfigFromClassName(string $pluginPath, string $className): StorefrontPluginConfiguration
    {
        /** @var Plugin $plugin */
        $plugin = new $className(true, $pluginPath, $this->projectDirectory);

        if (!$plugin instanceof Plugin) {
            throw new \RuntimeException(
                sprintf('Plugin class "%s" must extend "%s"', \get_class($plugin), Plugin::class)
            );
        }

        return $this->pluginConfigurationFactory->createFromBundle($plugin);
    }
}

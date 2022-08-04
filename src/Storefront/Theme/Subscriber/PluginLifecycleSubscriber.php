<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Subscriber;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUpdateEvent;
use Shopware\Storefront\Theme\Exception\InvalidThemeBundleException;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PluginLifecycleSubscriber implements EventSubscriberInterface
{
    private StorefrontPluginRegistryInterface $storefrontPluginRegistry;

    private string $projectDirectory;

    private AbstractStorefrontPluginConfigurationFactory $pluginConfigurationFactory;

    private ThemeLifecycleHandler $themeLifecycleHandler;

    private ThemeLifecycleService $themeLifecycleService;

    /**
     * @internal
     */
    public function __construct(
        StorefrontPluginRegistryInterface $storefrontPluginRegistry,
        string $projectDirectory,
        AbstractStorefrontPluginConfigurationFactory $pluginConfigurationFactory,
        ThemeLifecycleHandler $themeLifecycleHandler,
        ThemeLifecycleService $themeLifecycleService
    ) {
        $this->storefrontPluginRegistry = $storefrontPluginRegistry;
        $this->projectDirectory = $projectDirectory;
        $this->pluginConfigurationFactory = $pluginConfigurationFactory;
        $this->themeLifecycleHandler = $themeLifecycleHandler;
        $this->themeLifecycleService = $themeLifecycleService;
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents()
    {
        if (Feature::isActive('v6.5.0.0')) {
            return [
                PluginPostActivateEvent::class => 'pluginPostActivate',
                PluginPreUpdateEvent::class => 'pluginUpdate',
                PluginPreDeactivateEvent::class => 'pluginDeactivateAndUninstall',
                PluginPreUninstallEvent::class => 'pluginDeactivateAndUninstall',
                PluginPostUninstallEvent::class => 'pluginPostUninstall',
            ];
        }

        return [
            PluginPreActivateEvent::class => 'pluginActivate',
            PluginPostActivateEvent::class => 'pluginPostActivate',
            PluginPreUpdateEvent::class => 'pluginUpdate',
            PluginPreDeactivateEvent::class => 'pluginDeactivateAndUninstall',
            PluginPreUninstallEvent::class => 'pluginDeactivateAndUninstall',
            PluginPostUninstallEvent::class => 'pluginPostUninstall',
        ];
    }

    /**
     * @deprecated tag:v6.5.0 - Method will be removed. use pluginPostActivate instead
     */
    public function pluginActivate(PluginPreActivateEvent $event): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            sprintf('Method pluginActivate of Class %s is deprecated. Use method pluginPostActivate instead', static::class)
        );
        // do nothing
    }

    public function pluginPostActivate(PluginPostActivateEvent $event): void
    {
        if ($this->skipCompile($event->getContext()->getContext())) {
            return;
        }

        // create instance of the plugin to create a configuration
        // (the kernel boot is already finished and the activated plugin is missing)
        $storefrontPluginConfig = $this->createConfigFromClassName(
            $event->getPlugin()->getPath() ?: '',
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
        if ($this->skipCompile($event->getContext()->getContext())) {
            return;
        }

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
        if ($this->skipCompile($event->getContext()->getContext())) {
            return;
        }

        $pluginName = $event->getPlugin()->getName();
        $config = $this->storefrontPluginRegistry->getConfigurations()->getByTechnicalName($pluginName);

        if (!$config) {
            return;
        }

        $this->themeLifecycleHandler->handleThemeUninstall($config, $event->getContext()->getContext());
    }

    public function pluginPostUninstall(PluginPostUninstallEvent $event): void
    {
        if ($event->getContext()->keepUserData()) {
            return;
        }

        $this->themeLifecycleService->removeTheme($event->getPlugin()->getName(), $event->getContext()->getContext());
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

    private function skipCompile(Context $context): bool
    {
        return $context->hasState(Plugin\PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Subscriber;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Event\PluginLifecycleEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivationFailedEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUpdateEvent;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Storefront\Theme\Exception\InvalidThemeBundleException;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class PluginLifecycleSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly StorefrontPluginRegistry $storefrontPluginRegistry,
        private readonly string $projectDirectory,
        private readonly AbstractStorefrontPluginConfigurationFactory $pluginConfigurationFactory,
        private readonly ThemeLifecycleHandler $themeLifecycleHandler,
        private readonly ThemeLifecycleService $themeLifecycleService,
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostActivateEvent::class => 'pluginPostActivate',
            PluginPreUpdateEvent::class => 'pluginUpdate',
            PluginPostUpdateEvent::class => 'pluginPostUpdate',
            PluginPreDeactivateEvent::class => 'pluginPreDeactivate',
            PluginPostDeactivateEvent::class => 'pluginPostDeactivate',
            PluginPostDeactivationFailedEvent::class => 'pluginPostDeactivateFailed',
            PluginPreUninstallEvent::class => 'pluginPreUninstall',
            PluginPostUninstallEvent::class => 'pluginPostUninstall',
        ];
    }

    public function pluginPostActivate(PluginPostActivateEvent $event): void
    {
        $this->doPostActivate($event);
    }

    public function pluginPostDeactivateFailed(PluginPostDeactivationFailedEvent $event): void
    {
        $this->doPostActivate($event);
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

    public function pluginPostUpdate(PluginPostUpdateEvent $event): void
    {
        if ($this->skipCompile($event->getContext()->getContext())) {
            return;
        }

        $this->refreshActiveThemeImportMaps(
            $event->getContext()->getContext(),
            $this->storefrontPluginRegistry->getConfigurations()
        );
    }

    public function pluginPostDeactivate(PluginPostDeactivateEvent $event): void
    {
        $context = $event->getContext()->getContext();

        if ($this->skipCompile($context)) {
            return;
        }

        $pluginName = $event->getPlugin()->getName();
        $storefrontPluginConfigurations = $this->storefrontPluginRegistry->getConfigurations();

        $this->refreshActiveThemeImportMaps($context, $storefrontPluginConfigurations);

        $config = $storefrontPluginConfigurations->getByTechnicalName($pluginName);

        if (!$config || !$config->hasAdditionalBundles()) {
            return;
        }

        $this->themeLifecycleHandler->recompileAllActiveThemes($context);
    }

    public function pluginPreDeactivate(PluginPreDeactivateEvent $event): void
    {
        $context = $event->getContext()->getContext();

        if ($this->skipCompile($context)) {
            return;
        }

        $pluginName = $event->getPlugin()->getName();
        $storefrontPluginConfigurations = $this->storefrontPluginRegistry->getConfigurations();

        $config = $storefrontPluginConfigurations->getByTechnicalName($pluginName);

        if (!$config) {
            return;
        }

        if ($config->hasAdditionalBundles()) {
            $this->themeLifecycleHandler->deactivateTheme($config, $context);

            return;
        }

        $this->themeLifecycleHandler->handleThemeUninstall($config, $context);
    }

    public function pluginPreUninstall(PluginPreUninstallEvent $event): void
    {
        $context = $event->getContext()->getContext();

        if ($this->skipCompile($context)) {
            return;
        }

        $pluginName = $event->getPlugin()->getName();
        $storefrontPluginConfigurations = $this->storefrontPluginRegistry->getConfigurations();
        $filteredConfigurations = $storefrontPluginConfigurations->filter(
            static fn (StorefrontPluginConfiguration $registeredConfig): bool => $registeredConfig->getTechnicalName() !== $pluginName
        );

        $config = $storefrontPluginConfigurations->getByTechnicalName($pluginName);

        if ($config) {
            if ($config->hasAdditionalBundles()) {
                $this->themeLifecycleHandler->deactivateTheme($config, $context);
            } else {
                $this->themeLifecycleHandler->handleThemeUninstall($config, $context);
            }
        }

        $this->refreshActiveThemeImportMaps($context, $filteredConfigurations);
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
                \sprintf('Plugin class "%s" must extend "%s"', $plugin::class, Plugin::class)
            );
        }

        return $this->pluginConfigurationFactory->createFromBundle($plugin);
    }

    private function doPostActivate(PluginLifecycleEvent $event): void
    {
        if (!($event instanceof PluginPostActivateEvent) && !($event instanceof PluginPostDeactivationFailedEvent)) {
            return;
        }

        $context = $event->getContext()->getContext();

        if ($this->skipCompile($context)) {
            return;
        }

        // create instance of the plugin to create a configuration
        // (the kernel boot is already finished and the activated plugin is missing)
        $storefrontPluginConfig = $this->createConfigFromClassName(
            $event->getPlugin()->getPath() ?: '',
            $event->getPlugin()->getBaseClass()
        );

        // ensure plugin configuration is in the list of all active plugin configurations
        $configurationCollection = clone $this->storefrontPluginRegistry->getConfigurations();
        $configurationCollection->add($storefrontPluginConfig);

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate(
            $storefrontPluginConfig,
            $configurationCollection,
            $context
        );

        $this->refreshActiveThemeImportMaps($context, $configurationCollection);
    }

    private function skipCompile(Context $context): bool
    {
        return $context->hasState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);
    }

    private function refreshActiveThemeImportMaps(
        Context $context,
        StorefrontPluginConfigurationCollection $configurationCollection
    ): void {
        $this->themeLifecycleHandler->refreshAllActiveThemeImportMaps($context, $configurationCollection);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivationFailedEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUpdateEvent;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\Subscriber\PluginLifecycleSubscriber;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeAndPlugin\AsyncPlugin\AsyncPlugin;

/**
 * @internal
 */
#[CoversClass(PluginLifecycleSubscriber::class)]
class PluginLifecycleSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                PluginPostActivateEvent::class => 'pluginPostActivate',
                PluginPreUpdateEvent::class => 'pluginUpdate',
                PluginPostUpdateEvent::class => 'pluginPostUpdate',
                PluginPreDeactivateEvent::class => 'pluginPreDeactivate',
                PluginPostDeactivateEvent::class => 'pluginPostDeactivate',
                PluginPostDeactivationFailedEvent::class => 'pluginPostDeactivateFailed',
                PluginPreUninstallEvent::class => 'pluginPreUninstall',
                PluginPostUninstallEvent::class => 'pluginPostUninstall',
            ],
            PluginLifecycleSubscriber::getSubscribedEvents()
        );
    }

    public function testPluginUpdateSkipsCompilationWithSkipState(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);

        $updateContext = $this->createMock(UpdateContext::class);
        $updateContext->method('getContext')->willReturn($context);

        $event = $this->createMock(PluginPreUpdateEvent::class);
        $event->method('getContext')->willReturn($updateContext);

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->never())->method('handleThemeInstallOrUpdate');

        $subscriber = $this->createSubscriber(
            $this->createMock(StorefrontPluginRegistry::class),
            $themeLifecycleHandler
        );

        $subscriber->pluginUpdate($event);
    }

    public function testPluginPostUpdateRefreshesImportMaps(): void
    {
        $context = Context::createDefaultContext();
        $updateContext = $this->createMock(UpdateContext::class);
        $updateContext->method('getContext')->willReturn($context);

        $event = $this->createMock(PluginPostUpdateEvent::class);
        $event->method('getContext')->willReturn($updateContext);

        $configurations = new StorefrontPluginConfigurationCollection([new StorefrontPluginConfiguration('TestPlugin')]);

        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn($configurations);

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->once())
            ->method('refreshAllActiveThemeImportMaps')
            ->with($context, $configurations);

        $subscriber = $this->createSubscriber($registry, $themeLifecycleHandler);
        $subscriber->pluginPostUpdate($event);
    }

    public function testPluginUpdateReturnsWhenConfigurationIsMissing(): void
    {
        $plugin = new PluginEntity();
        $plugin->setName('MissingPlugin');

        $context = Context::createDefaultContext();
        $updateContext = $this->createMock(UpdateContext::class);
        $updateContext->method('getContext')->willReturn($context);

        $event = $this->createMock(PluginPreUpdateEvent::class);
        $event->method('getPlugin')->willReturn($plugin);
        $event->method('getContext')->willReturn($updateContext);

        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection());

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->never())->method('handleThemeInstallOrUpdate');

        $subscriber = $this->createSubscriber($registry, $themeLifecycleHandler);
        $subscriber->pluginUpdate($event);
    }

    public function testPluginPreDeactivateDeactivatesThemeWithAdditionalBundles(): void
    {
        $plugin = new PluginEntity();
        $plugin->setName('ComponentTestTheme');

        $config = new StorefrontPluginConfiguration('ComponentTestTheme');
        $config->setAdditionalBundles(true);

        $configurations = new StorefrontPluginConfigurationCollection([$config]);
        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn($configurations);

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->once())
            ->method('deactivateTheme')
            ->with($config, static::isInstanceOf(Context::class));

        $subscriber = $this->createSubscriber($registry, $themeLifecycleHandler);

        $deactivateContext = $this->createMock(DeactivateContext::class);
        $deactivateContext->method('getContext')->willReturn(Context::createDefaultContext());

        $event = $this->createMock(PluginPreDeactivateEvent::class);
        $event->method('getPlugin')->willReturn($plugin);
        $event->method('getContext')->willReturn($deactivateContext);

        $subscriber->pluginPreDeactivate($event);
    }

    public function testPluginPreDeactivateReturnsWhenConfigurationIsMissing(): void
    {
        $plugin = new PluginEntity();
        $plugin->setName('MissingPlugin');

        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection());

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->never())->method('deactivateTheme');
        $themeLifecycleHandler->expects($this->never())->method('handleThemeUninstall');

        $subscriber = $this->createSubscriber($registry, $themeLifecycleHandler);

        $deactivateContext = $this->createMock(DeactivateContext::class);
        $deactivateContext->method('getContext')->willReturn(Context::createDefaultContext());

        $event = $this->createMock(PluginPreDeactivateEvent::class);
        $event->method('getPlugin')->willReturn($plugin);
        $event->method('getContext')->willReturn($deactivateContext);

        $subscriber->pluginPreDeactivate($event);
    }

    public function testPluginPreDeactivateHandlesThemeUninstallWithoutAdditionalBundles(): void
    {
        $plugin = new PluginEntity();
        $plugin->setName('SimpleThemePlugin');

        $config = new StorefrontPluginConfiguration('SimpleThemePlugin');
        $config->setAdditionalBundles(false);

        $configurations = new StorefrontPluginConfigurationCollection([$config]);
        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn($configurations);

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->once())
            ->method('handleThemeUninstall')
            ->with($config, static::isInstanceOf(Context::class));
        $themeLifecycleHandler->expects($this->never())->method('deactivateTheme');

        $subscriber = $this->createSubscriber($registry, $themeLifecycleHandler);

        $deactivateContext = $this->createMock(DeactivateContext::class);
        $deactivateContext->method('getContext')->willReturn(Context::createDefaultContext());

        $event = $this->createMock(PluginPreDeactivateEvent::class);
        $event->method('getPlugin')->willReturn($plugin);
        $event->method('getContext')->willReturn($deactivateContext);

        $subscriber->pluginPreDeactivate($event);
    }

    public function testPluginPreUninstallRefreshesImportMaps(): void
    {
        $plugin = new PluginEntity();
        $plugin->setName('FakePlugin');

        $configurations = new StorefrontPluginConfigurationCollection();
        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn($configurations);

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->once())->method('refreshAllActiveThemeImportMaps');

        $subscriber = $this->createSubscriber($registry, $themeLifecycleHandler);

        $uninstallContext = $this->createMock(UninstallContext::class);
        $uninstallContext->method('getContext')->willReturn(Context::createDefaultContext());

        $event = $this->createMock(PluginPreUninstallEvent::class);
        $event->method('getPlugin')->willReturn($plugin);
        $event->method('getContext')->willReturn($uninstallContext);

        $subscriber->pluginPreUninstall($event);
    }

    public function testPluginPostDeactivateSkipsWithSkipState(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);

        $deactivateContext = $this->createMock(DeactivateContext::class);
        $deactivateContext->method('getContext')->willReturn($context);

        $event = $this->createMock(PluginPostDeactivateEvent::class);
        $event->method('getContext')->willReturn($deactivateContext);

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->never())->method('refreshAllActiveThemeImportMaps');
        $themeLifecycleHandler->expects($this->never())->method('recompileAllActiveThemes');

        $subscriber = $this->createSubscriber(
            $this->createMock(StorefrontPluginRegistry::class),
            $themeLifecycleHandler
        );

        $subscriber->pluginPostDeactivate($event);
    }

    public function testPluginPostDeactivateRefreshesImportMapsAndReturnsWhenConfigurationMissing(): void
    {
        $plugin = new PluginEntity();
        $plugin->setName('MissingPlugin');

        $context = Context::createDefaultContext();
        $deactivateContext = $this->createMock(DeactivateContext::class);
        $deactivateContext->method('getContext')->willReturn($context);

        $event = $this->createMock(PluginPostDeactivateEvent::class);
        $event->method('getPlugin')->willReturn($plugin);
        $event->method('getContext')->willReturn($deactivateContext);

        $configurations = new StorefrontPluginConfigurationCollection();
        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn($configurations);

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->once())
            ->method('refreshAllActiveThemeImportMaps')
            ->with($context, $configurations);
        $themeLifecycleHandler->expects($this->never())->method('recompileAllActiveThemes');

        $subscriber = $this->createSubscriber($registry, $themeLifecycleHandler);
        $subscriber->pluginPostDeactivate($event);
    }

    public function testPluginPostDeactivateRefreshesImportMapsAndReturnsWhenNoAdditionalBundles(): void
    {
        $plugin = new PluginEntity();
        $plugin->setName('SimpleThemePlugin');

        $config = new StorefrontPluginConfiguration('SimpleThemePlugin');
        $config->setAdditionalBundles(false);

        $configurations = new StorefrontPluginConfigurationCollection([$config]);
        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn($configurations);

        $context = Context::createDefaultContext();
        $deactivateContext = $this->createMock(DeactivateContext::class);
        $deactivateContext->method('getContext')->willReturn($context);

        $event = $this->createMock(PluginPostDeactivateEvent::class);
        $event->method('getPlugin')->willReturn($plugin);
        $event->method('getContext')->willReturn($deactivateContext);

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->once())
            ->method('refreshAllActiveThemeImportMaps')
            ->with($context, $configurations);
        $themeLifecycleHandler->expects($this->never())->method('recompileAllActiveThemes');

        $subscriber = $this->createSubscriber($registry, $themeLifecycleHandler);
        $subscriber->pluginPostDeactivate($event);
    }

    public function testPluginPostDeactivateRecompilesAllThemesForAdditionalBundles(): void
    {
        $plugin = new PluginEntity();
        $plugin->setName('ComponentTestTheme');

        $config = new StorefrontPluginConfiguration('ComponentTestTheme');
        $config->setAdditionalBundles(true);

        $configurations = new StorefrontPluginConfigurationCollection([$config]);
        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn($configurations);

        $context = Context::createDefaultContext();
        $deactivateContext = $this->createMock(DeactivateContext::class);
        $deactivateContext->method('getContext')->willReturn($context);

        $event = $this->createMock(PluginPostDeactivateEvent::class);
        $event->method('getPlugin')->willReturn($plugin);
        $event->method('getContext')->willReturn($deactivateContext);

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->once())
            ->method('refreshAllActiveThemeImportMaps')
            ->with($context, $configurations);
        $themeLifecycleHandler->expects($this->once())
            ->method('recompileAllActiveThemes')
            ->with($context);

        $subscriber = $this->createSubscriber($registry, $themeLifecycleHandler);
        $subscriber->pluginPostDeactivate($event);
    }

    public function testPluginPreUninstallSkipsWithSkipState(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);

        $uninstallContext = $this->createMock(UninstallContext::class);
        $uninstallContext->method('getContext')->willReturn($context);

        $event = $this->createMock(PluginPreUninstallEvent::class);
        $event->method('getContext')->willReturn($uninstallContext);

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->never())->method('refreshAllActiveThemeImportMaps');
        $themeLifecycleHandler->expects($this->never())->method('deactivateTheme');
        $themeLifecycleHandler->expects($this->never())->method('handleThemeUninstall');

        $subscriber = $this->createSubscriber(
            $this->createMock(StorefrontPluginRegistry::class),
            $themeLifecycleHandler
        );

        $subscriber->pluginPreUninstall($event);
    }

    public function testPluginPreUninstallDeactivatesThemeForAdditionalBundles(): void
    {
        $plugin = new PluginEntity();
        $plugin->setName('ComponentTestTheme');

        $config = new StorefrontPluginConfiguration('ComponentTestTheme');
        $config->setAdditionalBundles(true);

        $configurations = new StorefrontPluginConfigurationCollection([$config]);
        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn($configurations);

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->once())
            ->method('deactivateTheme')
            ->with($config, static::isInstanceOf(Context::class));
        $themeLifecycleHandler->expects($this->never())->method('handleThemeUninstall');
        $themeLifecycleHandler->expects($this->once())
            ->method('refreshAllActiveThemeImportMaps')
            ->with(
                static::isInstanceOf(Context::class),
                static::callback(static function (StorefrontPluginConfigurationCollection $collection): bool {
                    return $collection->getByTechnicalName('ComponentTestTheme') === null;
                })
            );

        $subscriber = $this->createSubscriber($registry, $themeLifecycleHandler);

        $uninstallContext = $this->createMock(UninstallContext::class);
        $uninstallContext->method('getContext')->willReturn(Context::createDefaultContext());

        $event = $this->createMock(PluginPreUninstallEvent::class);
        $event->method('getPlugin')->willReturn($plugin);
        $event->method('getContext')->willReturn($uninstallContext);

        $subscriber->pluginPreUninstall($event);
    }

    public function testPluginPreUninstallHandlesThemeUninstallWithoutAdditionalBundles(): void
    {
        $plugin = new PluginEntity();
        $plugin->setName('SimpleThemePlugin');

        $config = new StorefrontPluginConfiguration('SimpleThemePlugin');
        $config->setAdditionalBundles(false);

        $configurations = new StorefrontPluginConfigurationCollection([$config]);
        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn($configurations);

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->once())
            ->method('handleThemeUninstall')
            ->with($config, static::isInstanceOf(Context::class));
        $themeLifecycleHandler->expects($this->never())->method('deactivateTheme');
        $themeLifecycleHandler->expects($this->once())
            ->method('refreshAllActiveThemeImportMaps')
            ->with(
                static::isInstanceOf(Context::class),
                static::callback(static function (StorefrontPluginConfigurationCollection $collection): bool {
                    return $collection->getByTechnicalName('SimpleThemePlugin') === null;
                })
            );

        $subscriber = $this->createSubscriber($registry, $themeLifecycleHandler);

        $uninstallContext = $this->createMock(UninstallContext::class);
        $uninstallContext->method('getContext')->willReturn(Context::createDefaultContext());

        $event = $this->createMock(PluginPreUninstallEvent::class);
        $event->method('getPlugin')->willReturn($plugin);
        $event->method('getContext')->willReturn($uninstallContext);

        $subscriber->pluginPreUninstall($event);
    }

    public function testPluginPostUninstallRemovesThemeWhenUserDataNotKept(): void
    {
        $themeLifecycleService = $this->createMock(ThemeLifecycleService::class);
        $themeLifecycleService->expects($this->once())
            ->method('removeTheme')
            ->with('FakePlugin', static::isInstanceOf(Context::class));

        $subscriber = new PluginLifecycleSubscriber(
            $this->createMock(StorefrontPluginRegistry::class),
            '/var/www/html',
            $this->createMock(AbstractStorefrontPluginConfigurationFactory::class),
            $this->createMock(ThemeLifecycleHandler::class),
            $themeLifecycleService,
        );

        $plugin = new PluginEntity();
        $plugin->setName('FakePlugin');

        $uninstallContext = $this->createMock(UninstallContext::class);
        $uninstallContext->method('keepUserData')->willReturn(false);
        $uninstallContext->method('getContext')->willReturn(Context::createDefaultContext());

        $event = $this->createMock(PluginPostUninstallEvent::class);
        $event->method('getPlugin')->willReturn($plugin);
        $event->method('getContext')->willReturn($uninstallContext);

        $subscriber->pluginPostUninstall($event);
    }

    public function testPluginPostUninstallSkipsRemoveThemeWhenUserDataIsKept(): void
    {
        $themeLifecycleService = $this->createMock(ThemeLifecycleService::class);
        $themeLifecycleService->expects($this->never())->method('removeTheme');

        $subscriber = new PluginLifecycleSubscriber(
            $this->createMock(StorefrontPluginRegistry::class),
            '/var/www/html',
            $this->createMock(AbstractStorefrontPluginConfigurationFactory::class),
            $this->createMock(ThemeLifecycleHandler::class),
            $themeLifecycleService,
        );

        $plugin = new PluginEntity();
        $plugin->setName('FakePlugin');

        $uninstallContext = $this->createMock(UninstallContext::class);
        $uninstallContext->method('keepUserData')->willReturn(true);
        $uninstallContext->method('getContext')->willReturn(Context::createDefaultContext());

        $event = $this->createMock(PluginPostUninstallEvent::class);
        $event->method('getPlugin')->willReturn($plugin);
        $event->method('getContext')->willReturn($uninstallContext);

        $subscriber->pluginPostUninstall($event);
    }

    public function testPluginPostActivateInstallsThemeAndRefreshesImportMaps(): void
    {
        $context = Context::createDefaultContext();
        $activateContext = $this->createMock(ActivateContext::class);
        $activateContext->method('getContext')->willReturn($context);

        $plugin = new PluginEntity();
        $plugin->setName('AsyncPlugin');
        $plugin->setBaseClass(AsyncPlugin::class);
        $plugin->setPath('/plugins/AsyncPlugin');

        $event = $this->createMock(PluginPostActivateEvent::class);
        $event->method('getContext')->willReturn($activateContext);
        $event->method('getPlugin')->willReturn($plugin);

        $existingConfig = new StorefrontPluginConfiguration('ExistingPlugin');
        $configurations = new StorefrontPluginConfigurationCollection([$existingConfig]);

        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn($configurations);

        $newConfig = new StorefrontPluginConfiguration('AsyncPlugin');
        $factory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $factory->expects($this->once())
            ->method('createFromBundle')
            ->with(static::isInstanceOf(AsyncPlugin::class))
            ->willReturn($newConfig);

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->once())
            ->method('handleThemeInstallOrUpdate')
            ->with(
                $newConfig,
                static::callback(static function (StorefrontPluginConfigurationCollection $collection): bool {
                    return $collection->getByTechnicalName('ExistingPlugin') !== null
                        && $collection->getByTechnicalName('AsyncPlugin') !== null;
                }),
                $context
            );
        $themeLifecycleHandler->expects($this->once())
            ->method('refreshAllActiveThemeImportMaps')
            ->with(
                $context,
                static::callback(static fn (StorefrontPluginConfigurationCollection $collection): bool => $collection->getByTechnicalName('AsyncPlugin') !== null)
            );

        $subscriber = $this->createSubscriber(
            $registry,
            $themeLifecycleHandler,
            $factory
        );

        $subscriber->pluginPostActivate($event);
    }

    public function testPluginPostActivateSkipsWhenSkipStateIsSet(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);

        $activateContext = $this->createMock(ActivateContext::class);
        $activateContext->method('getContext')->willReturn($context);

        $event = $this->createMock(PluginPostActivateEvent::class);
        $event->method('getContext')->willReturn($activateContext);
        $event->method('getPlugin')->willReturn(new PluginEntity());

        $factory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $factory->expects($this->never())->method('createFromBundle');

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->never())->method('handleThemeInstallOrUpdate');
        $themeLifecycleHandler->expects($this->never())->method('refreshAllActiveThemeImportMaps');

        $subscriber = $this->createSubscriber(
            $this->createMock(StorefrontPluginRegistry::class),
            $themeLifecycleHandler,
            $factory
        );

        $subscriber->pluginPostActivate($event);
    }

    public function testPluginPostActivateThrowsForInvalidPluginClass(): void
    {
        $context = Context::createDefaultContext();
        $activateContext = $this->createMock(ActivateContext::class);
        $activateContext->method('getContext')->willReturn($context);

        $plugin = new PluginEntity();
        $plugin->setName('InvalidPlugin');
        $plugin->setPath('/plugins/InvalidPlugin');
        $plugin->assign(['baseClass' => InvalidPluginForLifecycleSubscriber::class]);

        $event = $this->createMock(PluginPostActivateEvent::class);
        $event->method('getContext')->willReturn($activateContext);
        $event->method('getPlugin')->willReturn($plugin);

        $subscriber = $this->createSubscriber(
            $this->createMock(StorefrontPluginRegistry::class),
            $this->createMock(ThemeLifecycleHandler::class)
        );

        $this->expectException(\RuntimeException::class);
        $subscriber->pluginPostActivate($event);
    }

    private function createSubscriber(
        StorefrontPluginRegistry $registry,
        ThemeLifecycleHandler $themeLifecycleHandler,
        ?AbstractStorefrontPluginConfigurationFactory $factory = null,
        ?ThemeLifecycleService $themeLifecycleService = null,
    ): PluginLifecycleSubscriber {
        return new PluginLifecycleSubscriber(
            $registry,
            '/var/www/html',
            $factory ?? $this->createMock(AbstractStorefrontPluginConfigurationFactory::class),
            $themeLifecycleHandler,
            $themeLifecycleService ?? $this->createMock(ThemeLifecycleService::class),
        );
    }
}

/**
 * @internal
 */
class InvalidPluginForLifecycleSubscriber
{
    public function __construct(
        bool $active,
        string $basePath,
        string $projectDir,
    ) {
        // Match Plugin constructor shape; values are intentionally ignored.
        $_ = [$active, $basePath, $projectDir];
    }
}

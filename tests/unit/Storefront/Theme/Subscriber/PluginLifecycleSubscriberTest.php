<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivationFailedEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUpdateEvent;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Storefront\Framework\Component\ComponentPublisher;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\Subscriber\PluginLifecycleSubscriber;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleService;

/**
 * @internal
 */
#[CoversClass(PluginLifecycleSubscriber::class)]
class PluginLifecycleSubscriberTest extends TestCase
{
    private PluginLifecycleSubscriber $pluginSubscriber;

    protected function setUp(): void
    {
        $this->pluginSubscriber = new PluginLifecycleSubscriber(
            $this->createMock(StorefrontPluginRegistry::class),
            '',
            $this->createMock(StorefrontPluginConfigurationFactory::class),
            $this->createMock(ThemeLifecycleHandler::class),
            $this->createMock(ThemeLifecycleService::class),
            $this->createMock(ComponentPublisher::class),
        );
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                PluginPostActivateEvent::class => 'pluginPostActivate',
                PluginPreUpdateEvent::class => 'pluginUpdate',
                PluginPreDeactivateEvent::class => 'pluginDeactivateAndUninstall',
                PluginPostDeactivateEvent::class => 'pluginPostDeactivate',
                PluginPostDeactivationFailedEvent::class => 'pluginPostDeactivateFailed',
                PluginPreUninstallEvent::class => 'pluginDeactivateAndUninstall',
                PluginPostUninstallEvent::class => 'pluginPostUninstall',
            ],
            PluginLifecycleSubscriber::getSubscribedEvents()
        );
    }

    public function testSkipPostCompile(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);
        $activateContextMock = $this->createMock(ActivateContext::class);
        $activateContextMock->expects($this->once())->method('getContext')->willReturn($context);
        $eventMock = $this->createMock(PluginPostActivateEvent::class);
        $eventMock->expects($this->once())->method('getContext')->willReturn($activateContextMock);
        $eventMock->expects($this->never())->method('getPlugin');

        $this->pluginSubscriber->pluginPostActivate($eventMock);
    }

    public function testPluginPostActivate(): void
    {
        $pluginMock = new PluginEntity();
        $pluginMock->setName('FakePlugin');
        $pluginMock->setPath('');
        $pluginMock->setBaseClass(FakePlugin::class);
        $eventMock = $this->createMock(PluginPostActivateEvent::class);
        $eventMock->expects($this->atLeastOnce())->method('getPlugin')->willReturn($pluginMock);
        $this->pluginSubscriber->pluginPostActivate($eventMock);
    }

    public function testPluginPostDeactivateFailed(): void
    {
        $pluginMock = new PluginEntity();
        $pluginMock->setName('FakePlugin');
        $pluginMock->setPath('');
        $pluginMock->setBaseClass(FakePlugin::class);

        $eventMock = $this->createMock(PluginPostDeactivationFailedEvent::class);
        $eventMock->expects($this->atLeastOnce())->method('getPlugin')->willReturn($pluginMock);
        $this->pluginSubscriber->pluginPostDeactivateFailed($eventMock);
    }

    public function testPluginUninstallUnpublishesComponentsWithoutStorefrontConfig(): void
    {
        $componentPublisher = $this->createMock(ComponentPublisher::class);
        $componentPublisher->expects($this->once())
            ->method('unpublish')
            ->with('FakePlugin');

        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->expects($this->once())
            ->method('getConfigurations')
            ->willReturn(new StorefrontPluginConfigurationCollection());

        $subscriber = new PluginLifecycleSubscriber(
            $registry,
            '',
            $this->createMock(StorefrontPluginConfigurationFactory::class),
            $this->createMock(ThemeLifecycleHandler::class),
            $this->createMock(ThemeLifecycleService::class),
            $componentPublisher,
        );

        $plugin = new PluginEntity();
        $plugin->setName('FakePlugin');

        $uninstallContext = $this->createMock(UninstallContext::class);
        $uninstallContext->method('getContext')->willReturn(Context::createDefaultContext());

        $event = $this->createMock(PluginPreUninstallEvent::class);
        $event->method('getPlugin')->willReturn($plugin);
        $event->method('getContext')->willReturn($uninstallContext);

        $subscriber->pluginDeactivateAndUninstall($event);
    }

    public function testPluginPostActivatePublishesBeforeCompileAndRecompilesForNonThemePlugin(): void
    {
        $context = Context::createDefaultContext();
        $activateContext = $this->createMock(ActivateContext::class);
        $activateContext->method('getContext')->willReturn($context);

        $plugin = new PluginEntity();
        $plugin->setName('FakePlugin');
        $plugin->setPath('/tmp/custom/plugins/FakePlugin');
        $plugin->setBaseClass(FakePlugin::class);

        $event = $this->createMock(PluginPostActivateEvent::class);
        $event->method('getPlugin')->willReturn($plugin);
        $event->method('getContext')->willReturn($activateContext);

        $config = new StorefrontPluginConfiguration('FakePlugin');
        $configurations = new StorefrontPluginConfigurationCollection();

        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn($configurations);

        $factory = $this->createMock(StorefrontPluginConfigurationFactory::class);
        $factory->expects($this->once())
            ->method('createFromBundle')
            ->willReturn($config);

        $published = false;
        $componentPublisher = $this->createMock(ComponentPublisher::class);
        $componentPublisher->expects($this->once())
            ->method('publishBundle')
            ->with('/tmp/custom/plugins/FakePlugin', 'FakePlugin')
            ->willReturnCallback(function () use (&$published): bool {
                $published = true;

                return true;
            });

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->once())
            ->method('handleThemeInstallOrUpdate')
            ->willReturnCallback(function () use (&$published): void {
                static::assertTrue($published, 'Component manifest must be published before theme compile/import-map refresh.');
            });
        $themeLifecycleHandler->expects($this->once())
            ->method('refreshAllActiveThemeImportMaps')
            ->with($context, $this->isInstanceOf(StorefrontPluginConfigurationCollection::class));

        $subscriber = new PluginLifecycleSubscriber(
            $registry,
            '/var/www/html',
            $factory,
            $themeLifecycleHandler,
            $this->createMock(ThemeLifecycleService::class),
            $componentPublisher,
        );

        $subscriber->pluginPostActivate($event);
    }

    public function testPluginPreDeactivateDoesNotUnpublishWhenThemeDeactivationFails(): void
    {
        $plugin = new PluginEntity();
        $plugin->setName('ComponentTestTheme');

        $config = new StorefrontPluginConfiguration('ComponentTestTheme');
        $config->setAdditionalBundles(true);

        $configurations = new StorefrontPluginConfigurationCollection([$config]);
        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn($configurations);

        $componentPublisher = $this->createMock(ComponentPublisher::class);
        $componentPublisher->expects($this->never())->method('unpublish');

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->once())
            ->method('deactivateTheme')
            ->with($config, $this->isInstanceOf(Context::class))
            ->willThrowException(new \RuntimeException('Theme is still assigned to a sales channel.'));
        $themeLifecycleHandler->expects($this->never())->method('refreshAllActiveThemeImportMaps');

        $subscriber = new PluginLifecycleSubscriber(
            $registry,
            '/var/www/html',
            $this->createMock(StorefrontPluginConfigurationFactory::class),
            $themeLifecycleHandler,
            $this->createMock(ThemeLifecycleService::class),
            $componentPublisher,
        );

        $deactivateContext = $this->createMock(DeactivateContext::class);
        $deactivateContext->method('getContext')->willReturn(Context::createDefaultContext());

        $event = $this->createMock(PluginPreDeactivateEvent::class);
        $event->method('getPlugin')->willReturn($plugin);
        $event->method('getContext')->willReturn($deactivateContext);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Theme is still assigned to a sales channel.');

        $subscriber->pluginDeactivateAndUninstall($event);
    }
}

/**
 * @internal
 */
class FakePlugin extends Plugin
{
}

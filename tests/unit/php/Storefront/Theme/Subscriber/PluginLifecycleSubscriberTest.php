<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivationFailedEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUpdateEvent;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\Subscriber\PluginLifecycleSubscriber;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleService;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Theme\Subscriber\PluginLifecycleSubscriber
 */
class PluginLifecycleSubscriberTest extends TestCase
{
    private PluginLifecycleSubscriber $pluginSubscriber;

    public function setUp(): void
    {
        $this->pluginSubscriber = new PluginLifecycleSubscriber(
            $this->createMock(StorefrontPluginRegistry::class),
            '',
            $this->createMock(StorefrontPluginConfigurationFactory::class),
            $this->createMock(ThemeLifecycleHandler::class),
            $this->createMock(ThemeLifecycleService::class),
        );
    }

    /**
     * @DisabledFeatures(features={"v6.5.0.0"})
     */
    public function testGetSubscribedEventsLegacy(): void
    {
        static::assertEquals(
            [
                PluginPreActivateEvent::class => 'pluginActivate',
                PluginPostActivateEvent::class => 'pluginPostActivate',
                PluginPreUpdateEvent::class => 'pluginUpdate',
                PluginPreDeactivateEvent::class => 'pluginDeactivateAndUninstall',
                PluginPostDeactivationFailedEvent::class => 'pluginPostDeactivateFailed',
                PluginPreUninstallEvent::class => 'pluginDeactivateAndUninstall',
                PluginPostUninstallEvent::class => 'pluginPostUninstall',
            ],
            PluginLifecycleSubscriber::getSubscribedEvents()
        );
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertEquals(
            [
                PluginPostActivateEvent::class => 'pluginPostActivate',
                PluginPreUpdateEvent::class => 'pluginUpdate',
                PluginPreDeactivateEvent::class => 'pluginDeactivateAndUninstall',
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
        $context->addState(Plugin\PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);
        $activateContextMock = $this->createMock(ActivateContext::class);
        $activateContextMock->expects(static::once())->method('getContext')->willReturn($context);
        $eventMock = $this->createMock(PluginPostActivateEvent::class);
        $eventMock->expects(static::once())->method('getContext')->willReturn($activateContextMock);
        $eventMock->expects(static::never())->method('getPlugin');

        $this->pluginSubscriber->pluginPostActivate($eventMock);
    }

    public function testPluginPostActivate(): void
    {
        $pluginMock = $this->createMock(PluginEntity::class);
        $pluginMock->expects(static::once())->method('getPath')->willReturn('');
        $pluginMock->expects(static::once())->method('getBaseClass')->willReturn('Shopware\Tests\Unit\Storefront\Theme\Subscriber\FakePlugin');
        $eventMock = $this->createMock(PluginPostActivateEvent::class);
        $eventMock->expects(static::exactly(2))->method('getPlugin')->willReturn($pluginMock);
        $this->pluginSubscriber->pluginPostActivate($eventMock);
    }

    public function testPluginPostDeactivateFailed(): void
    {
        $pluginMock = $this->createMock(PluginEntity::class);
        $pluginMock->expects(static::once())->method('getPath')->willReturn('');
        $pluginMock->expects(static::once())->method('getBaseClass')->willReturn('Shopware\Tests\Unit\Storefront\Theme\Subscriber\FakePlugin');
        $eventMock = $this->createMock(PluginPostDeactivationFailedEvent::class);
        $eventMock->expects(static::exactly(2))->method('getPlugin')->willReturn($pluginMock);
        $this->pluginSubscriber->pluginPostDeactivateFailed($eventMock);
    }
}

/**
 * @internal
 */
class FakePlugin extends Plugin
{
}

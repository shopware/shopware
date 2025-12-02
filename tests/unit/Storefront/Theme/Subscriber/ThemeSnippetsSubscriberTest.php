<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\Snippet\Event\SnippetsThemeResolveEvent;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\Subscriber\ThemeSnippetsSubscriber;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;

/**
 * @internal
 */
#[CoversClass(ThemeSnippetsSubscriber::class)]
class ThemeSnippetsSubscriberTest extends TestCase
{
    private MockObject&ThemeRuntimeConfigService $themeRuntimeConfigService;

    private MockObject&DatabaseSalesChannelThemeLoader $salesChannelThemeLoader;

    private ThemeSnippetsSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $this->salesChannelThemeLoader = $this->createMock(DatabaseSalesChannelThemeLoader::class);

        $this->subscriber = new ThemeSnippetsSubscriber(
            $this->themeRuntimeConfigService,
            $this->salesChannelThemeLoader
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ThemeSnippetsSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(SnippetsThemeResolveEvent::class, $events);
        static::assertSame('onSnippetsThemeResolve', $events[SnippetsThemeResolveEvent::class]);
    }

    public function testOnSnippetsThemeResolveWithSalesChannel(): void
    {
        $salesChannelId = 'test-sales-channel';
        $event = new SnippetsThemeResolveEvent($salesChannelId);

        $usedThemes = ['theme1', 'theme2'];
        $allThemes = ['theme1', 'theme2', 'theme3', 'theme4'];

        $this->salesChannelThemeLoader->expects($this->once())
            ->method('load')
            ->with($salesChannelId)
            ->willReturn($usedThemes);

        $this->themeRuntimeConfigService->expects($this->once())
            ->method('getActiveThemeNames')
            ->willReturn($allThemes);

        $this->subscriber->onSnippetsThemeResolve($event);

        static::assertSame(
            ['theme1', 'theme2', StorefrontPluginRegistry::BASE_THEME_NAME],
            $event->getUsedThemes()
        );

        static::assertEquals(
            ['theme3', 'theme4'],
            $event->getUnusedThemes()
        );
    }

    public function testOnSnippetsThemeResolveWithoutSalesChannel(): void
    {
        $event = new SnippetsThemeResolveEvent(null);

        $allThemes = ['theme1', 'theme2', 'theme3', 'theme4'];

        $this->salesChannelThemeLoader->expects($this->never())
            ->method('load');

        $this->themeRuntimeConfigService->expects($this->once())
            ->method('getActiveThemeNames')
            ->willReturn($allThemes);

        $this->subscriber->onSnippetsThemeResolve($event);

        static::assertSame(
            [StorefrontPluginRegistry::BASE_THEME_NAME],
            $event->getUsedThemes()
        );

        static::assertSame(
            $allThemes,
            $event->getUnusedThemes()
        );
    }
}

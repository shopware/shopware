<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\Subscriber\UpdateSubscriber;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Storefront\Theme\ThemeService;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(UpdateSubscriber::class)]
class UpdateSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [UpdatePostFinishEvent::class => 'updateFinished'],
            UpdateSubscriber::getSubscribedEvents()
        );
    }

    public function testCompilesEachThemeOnceWhenThemesAreSharedBySalesChannels(): void
    {
        $salesChannels = new SalesChannelCollection([
            $this->salesChannel('sales-channel-1', 'theme-a'),
            $this->salesChannel('sales-channel-2', 'theme-a'),
            $this->salesChannel('sales-channel-3', 'theme-b'),
            $this->salesChannel('sales-channel-4', 'theme-b'),
        ]);

        $compiledThemeIds = [];
        $themeService = static::createStub(ThemeService::class);
        $themeService->method('compileThemeById')
            ->willReturnCallback(static function (string $themeId) use (&$compiledThemeIds): array {
                $compiledThemeIds[] = $themeId;

                // one entry per sales channel the theme is assigned to
                return [$themeId, $themeId];
            });

        $this->createSubscriber($themeService, $salesChannels)
            ->updateFinished(new UpdatePostFinishEvent(Context::createDefaultContext(), 'v6.7.0.0', 'v6.7.1.0'));

        static::assertSame(['theme-a', 'theme-b'], $compiledThemeIds);
    }

    public function testFailedThemeIsReportedOnceAndNotCompiledAgain(): void
    {
        $salesChannels = new SalesChannelCollection([
            $this->salesChannel('sales-channel-1', 'theme-a'),
            $this->salesChannel('sales-channel-2', 'theme-a'),
        ]);

        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects($this->once())
            ->method('compileThemeById')
            ->willThrowException(new ThemeCompileException('theme-a'));

        $event = new UpdatePostFinishEvent(Context::createDefaultContext(), 'v6.7.0.0', 'v6.7.1.0');

        $this->createSubscriber($themeService, $salesChannels)->updateFinished($event);

        static::assertSame('Theme(s): Theme theme-a could not be recompiled.' . \PHP_EOL, $event->getPostUpdateMessage());
    }

    public function testThemesAreNotCompiledWithStateSkipAssetBuilding(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);

        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects($this->never())->method('compileThemeById');

        $this->createSubscriber($themeService, new SalesChannelCollection())
            ->updateFinished(new UpdatePostFinishEvent($context, 'v6.7.0.0', 'v6.7.1.0'));
    }

    private function createSubscriber(ThemeService $themeService, SalesChannelCollection $salesChannels): UpdateSubscriber
    {
        return new UpdateSubscriber(
            $themeService,
            static::createStub(ThemeLifecycleService::class),
            new StaticEntityRepository([$salesChannels], new SalesChannelDefinition())
        );
    }

    private function salesChannel(string $salesChannelId, string $themeId): SalesChannelEntity
    {
        $theme = new ThemeEntity();
        $theme->setId($themeId);
        $theme->setName('Theme ' . $themeId);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $salesChannel->addExtension('themes', new ThemeCollection([$theme]));

        return $salesChannel;
    }
}

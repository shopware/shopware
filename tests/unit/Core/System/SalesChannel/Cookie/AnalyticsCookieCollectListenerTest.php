<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsEntity;
use Shopware\Core\System\SalesChannel\Cookie\AnalyticsCookieCollectListener;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AnalyticsCookieCollectListener::class)]
class AnalyticsCookieCollectListenerTest extends TestCase
{
    private AnalyticsCookieCollectListener $listener;

    /**
     * @var StaticEntityRepository<SalesChannelAnalyticsCollection>
     */
    private StaticEntityRepository $analyticsRepo;

    protected function setUp(): void
    {
        $this->analyticsRepo = new StaticEntityRepository([]);
        $this->listener = new AnalyticsCookieCollectListener($this->analyticsRepo);
    }

    public function testSalesChannelHasNoAnalyticsId(): void
    {
        /** @phpstan-ignore shopware.mockingSimpleObjects (A mock is used here to ensure that the method is not called) */
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannel->expects($this->once())->method('getAnalyticsId')->willReturn(null);
        $salesChannel->expects($this->never())->method('getAnalytics');

        $context = Generator::generateSalesChannelContext(salesChannel: $salesChannel);

        $event = new CookieGroupCollectEvent(new CookieGroupCollection(), new Request(), $context);

        $this->listener->__invoke($event);
    }

    public function testSalesChannelNeedsToLoadAnalyticsButIsNotActive(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setAnalyticsId(Uuid::randomHex());
        $context = Generator::generateSalesChannelContext(salesChannel: $salesChannel);

        /** @phpstan-ignore shopware.mockingSimpleObjects (A mock is used here to ensure that the method is not called) */
        $cookieCollection = $this->createMock(CookieGroupCollection::class);
        $cookieCollection->expects($this->never())->method('get');
        $event = new CookieGroupCollectEvent($cookieCollection, new Request(), $context);

        $analyticsEntity = $this->createChannelAnalyticsEntity(active: false);

        $this->analyticsRepo->addSearch(new SalesChannelAnalyticsCollection([$analyticsEntity]));

        $this->listener->__invoke($event);
    }

    public function testStatisticalAndMarketingCookieGroupsNotPresent(): void
    {
        $context = $this->createSalesChannelContext();

        $cookieGroupCollection = new CookieGroupCollection([new CookieGroup('test')]);

        $event = new CookieGroupCollectEvent($cookieGroupCollection, new Request(), $context);
        $this->listener->__invoke($event);

        static::assertCount(1, $event->cookieGroupCollection);
    }

    public function testCookiesAreAdded(): void
    {
        $context = $this->createSalesChannelContext();

        $statisticalGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL);
        $marketingGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING);
        $cookieGroupCollection = new CookieGroupCollection([$statisticalGroup, $marketingGroup]);

        $event = new CookieGroupCollectEvent($cookieGroupCollection, new Request(), $context);

        $this->listener->__invoke($event);

        $adsCookie = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL)?->getEntries()?->get('google-analytics-enabled');
        static::assertNotNull($adsCookie);

        $adsCookie = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING)?->getEntries()?->get('google-ads-enabled');
        static::assertNotNull($adsCookie);
    }

    private function createChannelAnalyticsEntity(bool $active = true): SalesChannelAnalyticsEntity
    {
        $analyticsEntity = new SalesChannelAnalyticsEntity();
        $analyticsEntity->setId(Uuid::randomHex());
        $analyticsEntity->setActive($active);

        return $analyticsEntity;
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $analyticsEntity = $this->createChannelAnalyticsEntity();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setAnalyticsId($analyticsEntity->getId());
        $salesChannel->setAnalytics($analyticsEntity);

        return Generator::generateSalesChannelContext(salesChannel: $salesChannel);
    }
}

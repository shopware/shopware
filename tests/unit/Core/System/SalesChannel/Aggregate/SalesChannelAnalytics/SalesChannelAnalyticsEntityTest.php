<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[CoversClass(SalesChannelAnalyticsEntity::class)]
class SalesChannelAnalyticsEntityTest extends TestCase
{
    public function testGetSetTrackingId(): void
    {
        $entity = new SalesChannelAnalyticsEntity();
        $trackingId = 'test-tracking-id';
        $entity->setTrackingId($trackingId);

        static::assertSame($trackingId, $entity->getTrackingId());
    }

    public function testGetSetActive(): void
    {
        $entity = new SalesChannelAnalyticsEntity();
        $entity->setActive(true);

        static::assertTrue($entity->isActive());
    }

    public function testGetSetTrackOrders(): void
    {
        $entity = new SalesChannelAnalyticsEntity();
        $entity->setTrackOrders(true);

        static::assertTrue($entity->isTrackOrders());
    }

    public function testGetSetAnonymizeIp(): void
    {
        $entity = new SalesChannelAnalyticsEntity();
        $entity->setAnonymizeIp(true);

        static::assertTrue($entity->isAnonymizeIp());
    }

    public function testGetSetSalesChannel(): void
    {
        $entity = new SalesChannelAnalyticsEntity();
        $salesChannel = new SalesChannelEntity();
        $entity->setSalesChannel($salesChannel);

        static::assertSame($salesChannel, $entity->getSalesChannel());
    }

    public function testThatSalesChannelCanBeNull(): void
    {
        $entity = new SalesChannelAnalyticsEntity();
        static::assertNull($entity->getSalesChannel());
    }
}

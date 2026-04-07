<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Tracking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingOrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelTrackingOrderEntity::class)]
class SalesChannelTrackingOrderEntityTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $entity = new SalesChannelTrackingOrderEntity();

        $orderId = Uuid::randomHex();
        $entity->setOrderId($orderId);
        static::assertSame($orderId, $entity->getOrderId());

        $versionId = Uuid::randomHex();
        $entity->setOrderVersionId($versionId);
        static::assertSame($versionId, $entity->getOrderVersionId());

        $channelId = Uuid::randomHex();
        $entity->setSalesChannelId($channelId);
        static::assertSame($channelId, $entity->getSalesChannelId());
    }

    public function testOrderAssociationDefaultsToNull(): void
    {
        $entity = new SalesChannelTrackingOrderEntity();
        static::assertNull($entity->getOrder());
    }

    public function testOrderAssociation(): void
    {
        $entity = new SalesChannelTrackingOrderEntity();

        $order = new OrderEntity();
        $entity->setOrder($order);
        static::assertSame($order, $entity->getOrder());

        $entity->setOrder(null);
        static::assertNull($entity->getOrder());
    }

    public function testSalesChannelAssociationDefaultsToNull(): void
    {
        $entity = new SalesChannelTrackingOrderEntity();
        static::assertNull($entity->getSalesChannel());
    }

    public function testSalesChannelAssociation(): void
    {
        $entity = new SalesChannelTrackingOrderEntity();

        $channel = new SalesChannelEntity();
        $entity->setSalesChannel($channel);
        static::assertSame($channel, $entity->getSalesChannel());

        $entity->setSalesChannel(null);
        static::assertNull($entity->getSalesChannel());
    }
}

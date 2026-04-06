<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Tracking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingOrderCollection;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingOrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelTrackingOrderCollection::class)]
class SalesChannelTrackingOrderCollectionTest extends TestCase
{
    public function testCollectionAcceptsCorrectEntityType(): void
    {
        $entity = new SalesChannelTrackingOrderEntity();
        $entity->setId(Uuid::randomHex());

        $collection = new SalesChannelTrackingOrderCollection([$entity]);

        static::assertCount(1, $collection);
        static::assertSame($entity, $collection->first());
    }

    public function testCollectionIsInitiallyEmpty(): void
    {
        $collection = new SalesChannelTrackingOrderCollection();

        static::assertCount(0, $collection);
    }
}

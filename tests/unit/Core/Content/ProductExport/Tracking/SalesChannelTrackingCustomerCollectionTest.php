<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Tracking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingCustomerCollection;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingCustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelTrackingCustomerCollection::class)]
class SalesChannelTrackingCustomerCollectionTest extends TestCase
{
    public function testCollectionAcceptsCorrectEntityType(): void
    {
        $entity = new SalesChannelTrackingCustomerEntity();
        $entity->setId(Uuid::randomHex());

        $collection = new SalesChannelTrackingCustomerCollection([$entity]);

        static::assertCount(1, $collection);
        static::assertSame($entity, $collection->first());
    }

    public function testCollectionIsInitiallyEmpty(): void
    {
        $collection = new SalesChannelTrackingCustomerCollection();

        static::assertCount(0, $collection);
    }
}

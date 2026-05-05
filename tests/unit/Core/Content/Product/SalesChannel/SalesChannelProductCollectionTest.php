<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SalesChannelProductCollection::class)]
class SalesChannelProductCollectionTest extends TestCase
{
    public function testGetApiAliasReturnsUniqueAlias(): void
    {
        $collection = new SalesChannelProductCollection();

        static::assertSame('sales_channel_product_collection', $collection->getApiAlias());
    }

    public function testCollectionAcceptsSalesChannelProductEntity(): void
    {
        $entity = new SalesChannelProductEntity();
        $entity->setId(Uuid::randomHex());

        $collection = new SalesChannelProductCollection([$entity]);

        static::assertCount(1, $collection);
        static::assertSame($entity, $collection->first());
    }
}

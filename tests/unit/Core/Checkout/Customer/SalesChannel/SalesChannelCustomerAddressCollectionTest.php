<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\SalesChannel\SalesChannelCustomerAddressCollection;
use Shopware\Core\Checkout\Customer\SalesChannel\SalesChannelCustomerAddressEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SalesChannelCustomerAddressCollection::class)]
class SalesChannelCustomerAddressCollectionTest extends TestCase
{
    public function testGetApiAliasReturnsUniqueAlias(): void
    {
        $collection = new SalesChannelCustomerAddressCollection();

        static::assertSame('sales_channel_customer_address_collection', $collection->getApiAlias());
    }

    public function testCollectionAcceptsSalesChannelCustomerAddressEntity(): void
    {
        $entity = new SalesChannelCustomerAddressEntity();
        $entity->setId(Uuid::randomHex());

        $collection = new SalesChannelCustomerAddressCollection([$entity]);

        static::assertCount(1, $collection);
        static::assertSame($entity, $collection->first());
    }
}

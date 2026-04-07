<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Tracking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingCustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelTrackingCustomerEntity::class)]
class SalesChannelTrackingCustomerEntityTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $entity = new SalesChannelTrackingCustomerEntity();

        $customerId = Uuid::randomHex();
        $entity->setCustomerId($customerId);
        static::assertSame($customerId, $entity->getCustomerId());

        $channelId = Uuid::randomHex();
        $entity->setSalesChannelId($channelId);
        static::assertSame($channelId, $entity->getSalesChannelId());
    }

    public function testCustomerAssociationDefaultsToNull(): void
    {
        $entity = new SalesChannelTrackingCustomerEntity();
        static::assertNull($entity->getCustomer());
    }

    public function testCustomerAssociation(): void
    {
        $entity = new SalesChannelTrackingCustomerEntity();

        $customer = new CustomerEntity();
        $entity->setCustomer($customer);
        static::assertSame($customer, $entity->getCustomer());

        $entity->setCustomer(null);
        static::assertNull($entity->getCustomer());
    }

    public function testSalesChannelAssociationDefaultsToNull(): void
    {
        $entity = new SalesChannelTrackingCustomerEntity();
        static::assertNull($entity->getSalesChannel());
    }

    public function testSalesChannelAssociation(): void
    {
        $entity = new SalesChannelTrackingCustomerEntity();

        $channel = new SalesChannelEntity();
        $entity->setSalesChannel($channel);
        static::assertSame($channel, $entity->getSalesChannel());

        $entity->setSalesChannel(null);
        static::assertNull($entity->getSalesChannel());
    }
}

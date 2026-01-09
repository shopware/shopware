<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\SalesChannel\SalesChannelCustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerAddressSubscriber;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(CustomerAddressSubscriber::class)]
#[Package('checkout')]
class CustomerAddressSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = CustomerAddressSubscriber::getSubscribedEvents();

        static::assertArrayHasKey('sales_channel.' . CustomerEvents::CUSTOMER_ADDRESS_LOADED_EVENT, $events);
        static::assertArrayHasKey('sales_channel.customer_address.partial_loaded', $events);
    }

    public function testSalesChannelLoadedDoesNothingWithoutCustomer(): void
    {
        $subscriber = new CustomerAddressSubscriber();

        $context = Generator::generateSalesChannelContext();
        $context->assign(['customer' => null]);

        $address = new SalesChannelCustomerAddressEntity();
        $address->setId(Uuid::randomHex());

        /** @var SalesChannelEntityLoadedEvent<CustomerAddressEntity|PartialEntity> $event */
        $event = new SalesChannelEntityLoadedEvent(
            new CustomerAddressDefinition(),
            [$address],
            $context
        );

        $subscriber->salesChannelLoaded($event);

        static::assertFalse($address->isDefaultBillingAddress());
        static::assertFalse($address->isDefaultShippingAddress());
    }

    public function testSalesChannelLoadedAssignsDefaults(): void
    {
        $subscriber = new CustomerAddressSubscriber();
        $billingId = Uuid::randomHex();
        $shippingId = Uuid::randomHex();
        $otherId = Uuid::randomHex();

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddressId($billingId);
        $customer->setDefaultShippingAddressId($shippingId);

        $context = Generator::generateSalesChannelContext(customer: $customer);

        $billingAddress = new SalesChannelCustomerAddressEntity();
        $billingAddress->setId($billingId);

        $shippingAddress = new SalesChannelCustomerAddressEntity();
        $shippingAddress->setId($shippingId);

        $otherAddress = new SalesChannelCustomerAddressEntity();
        $otherAddress->setId($otherId);

        /** @var SalesChannelEntityLoadedEvent<CustomerAddressEntity|PartialEntity> $event */
        $event = new SalesChannelEntityLoadedEvent(
            new CustomerAddressDefinition(),
            [$billingAddress, $shippingAddress, $otherAddress],
            $context
        );

        $subscriber->salesChannelLoaded($event);

        static::assertTrue($billingAddress->isDefaultBillingAddress());
        static::assertFalse($billingAddress->isDefaultShippingAddress());

        static::assertFalse($shippingAddress->isDefaultBillingAddress());
        static::assertTrue($shippingAddress->isDefaultShippingAddress());

        static::assertFalse($otherAddress->isDefaultBillingAddress());
        static::assertFalse($otherAddress->isDefaultShippingAddress());
    }
}

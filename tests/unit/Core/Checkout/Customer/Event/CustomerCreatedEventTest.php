<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerCreatedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerCreatedEvent::class)]
class CustomerCreatedEventTest extends TestCase
{
    public function testWebhookPayloadContract(): void
    {
        static::assertSame([
            'customer' => ['type' => 'entity', 'entityClass' => CustomerDefinition::class, 'entityName' => CustomerDefinition::ENTITY_NAME],
            'customerId' => ['type' => 'string'],
        ], CustomerCreatedEvent::getAvailableData()->toArray());
    }

    public function testCustomerIsLoadedLazilyAndOnlyOnce(): void
    {
        $customerId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $loads = [];
        $customer = new CustomerEntity();
        $loader = static function () use (&$loads, $customer): CustomerEntity {
            $loads[] = true;

            return $customer;
        };

        $event = new CustomerCreatedEvent($context, $customerId, $loader, $salesChannelId);

        static::assertSame(CustomerCreatedEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($customerId, $event->getCustomerId());
        static::assertSame($salesChannelId, $event->getSalesChannelId());
        static::assertSame(['customerId' => $customerId], $event->getValues());
        static::assertCount(0, $loads, 'payload-known sales channel id must not trigger the lazy load');
        static::assertSame($customer, $event->getCustomer());
        static::assertSame($customer, $event->getCustomer());
        static::assertCount(1, $loads);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(CustomerDoubleOptInRegistrationEvent::class)]
class CustomerDoubleOptInRegistrationEventTest extends TestCase
{
    public function testRestoreScalarValuesCorrectly(): void
    {
        $event = new CustomerDoubleOptInRegistrationEvent(
            new CustomerEntity(),
            $this->createMock(SalesChannelContext::class),
            'my-confirm-url'
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('confirmUrl', $flow->data());
        static::assertEquals('my-confirm-url', $flow->data()['confirmUrl']);
    }

    public function testCrud(): void
    {
        $context = Generator::createSalesChannelContext();
        $customer = new CustomerEntity();
        $customer->setId('test-id');

        $event = new CustomerDoubleOptInRegistrationEvent($customer, $context, 'my-confirm-url');

        static::assertSame('my-confirm-url', $event->getConfirmUrl());
        static::assertSame($context, $event->getSalesChannelContext());
        static::assertSame($customer, $event->getCustomer());
        static::assertSame($context->getSalesChannelId(), $event->getSalesChannelId());
        static::assertSame($context->getContext(), $event->getContext());
        static::assertSame('test-id', $event->getCustomerId());
    }
}

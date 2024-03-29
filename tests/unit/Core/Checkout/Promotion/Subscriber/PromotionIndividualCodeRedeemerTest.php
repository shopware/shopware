<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\Subscriber\PromotionIndividualCodeRedeemer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(PromotionIndividualCodeRedeemer::class)]
class PromotionIndividualCodeRedeemerTest extends TestCase
{
    /**
     * This test verifies that our subscriber has the
     * correct event that its listening to.
     * This is important, because we have to ensure that
     * we save meta data in the payload of the line item
     * when the order is created.
     * This payload data helps us to reference used individual codes
     * with placed orders.
     */
    #[Group('promotions')]
    public function testSubscribeToOrderLineItemWritten(): void
    {
        $expectedEvent = CheckoutOrderPlacedEvent::class;

        // we need to have a key for the Shopware event
        static::assertArrayHasKey($expectedEvent, PromotionIndividualCodeRedeemer::getSubscribedEvents());
    }

    public function testOnOrderCreateWithOtherLineItem(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(static::never())->method('search');
        $repository->expects(static::never())->method('searchIds');
        $redeemer = new PromotionIndividualCodeRedeemer($repository);

        $lineItem = new OrderLineItemEntity();
        $lineItem->setId(Uuid::randomHex());
        $lineItem->setType('test');
        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection([$lineItem]));
        $event = new CheckoutOrderPlacedEvent(
            Context::createDefaultContext(),
            $order,
            TestDefaults::SALES_CHANNEL,
        );
        $redeemer->onOrderPlaced($event);
    }

    public function testOnOrderCreateWillProcessMultipleCodes(): void
    {
        $code = new PromotionIndividualCodeEntity();
        $code->setId(Uuid::randomHex());

        /** @var StaticEntityRepository<PromotionIndividualCodeCollection> $repository */
        $repository = new StaticEntityRepository([
            static function (Criteria $criteria) {
                $filter = $criteria->getFilters()[0];
                static::assertInstanceOf(EqualsFilter::class, $filter);
                static::assertSame('notexisting', $filter->getValue());

                return new PromotionIndividualCodeCollection();
            },
            static function (Criteria $criteria) use ($code) {
                $filter = $criteria->getFilters()[0];
                static::assertInstanceOf(EqualsFilter::class, $filter);
                static::assertSame('existing', $filter->getValue());

                return new PromotionIndividualCodeCollection([$code]);
            },
        ]);
        $redeemer = new PromotionIndividualCodeRedeemer($repository);

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $customer = new OrderCustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setFirstName('foo');
        $customer->setLastName('bar');
        $customer->setCustomerId(Uuid::randomHex());
        $order->setOrderCustomer($customer);

        $lineItem1 = new OrderLineItemEntity();
        $lineItem1->setId(Uuid::randomHex());
        $lineItem1->setType(PromotionProcessor::LINE_ITEM_TYPE);
        $lineItem1->setPayload(['code' => 'notexisting']);
        $lineItem1->setOrderId($order->getId());

        $lineItem2 = new OrderLineItemEntity();
        $lineItem2->setId(Uuid::randomHex());
        $lineItem2->setType(PromotionProcessor::LINE_ITEM_TYPE);
        $lineItem2->setPayload(['code' => 'existing']);
        $lineItem2->setOrderId($order->getId());

        $order->setLineItems(new OrderLineItemCollection([$lineItem1, $lineItem2]));
        $event = new CheckoutOrderPlacedEvent(
            Context::createDefaultContext(),
            $order,
            TestDefaults::SALES_CHANNEL,
        );
        $redeemer->onOrderPlaced($event);

        static::assertSame([[[
            'id' => $code->getId(),
            'payload' => [
                'orderId' => $order->getId(),
                'customerId' => $customer->getCustomerId(),
                'customerName' => 'foo bar',
            ],
        ]]], $repository->updates);
    }
}

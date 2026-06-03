<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\Event\PromotionCodeRedeemedEvent;
use Shopware\Core\Checkout\Promotion\Subscriber\PromotionIndividualCodeRedeemer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionIndividualCodeRedeemer::class)]
class PromotionIndividualCodeRedeemerTest extends TestCase
{
    /**
     * This test verifies that our subscriber has the
     * correct event that it's listening to.
     * This is important, because we have to ensure that
     * we save metadata in the payload of the line item
     * when the order is created.
     * This payload data helps us to reference used individual codes
     * with placed orders.
     */
    #[Group('promotions')]
    public function testSubscribeToOrderLineItemWritten(): void
    {
        // we need to have a key for the Shopware event
        static::assertArrayHasKey(OrderEvents::ORDER_LINE_ITEM_WRITTEN_EVENT, PromotionIndividualCodeRedeemer::getSubscribedEvents());
    }

    public function testOnOrderCreateWithOtherLineItem(): void
    {
        $codeRepository = $this->createMock(EntityRepository::class);
        $codeRepository->expects($this->never())->method('search');
        $codeRepository->expects($this->never())->method('searchIds');
        $redeemer = new PromotionIndividualCodeRedeemer($codeRepository, $this->createMock(EntityRepository::class), new EventDispatcher());

        $customer = new OrderCustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setFirstName('foo');
        $customer->setLastName('bar');
        $customer->setCustomerId(Uuid::randomHex());

        $lineItem = new OrderLineItemEntity();
        $lineItem->setId(Uuid::randomHex());
        $lineItem->setType('test');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setLineItems(new OrderLineItemCollection([$lineItem]));
        $order->setOrderCustomer($customer);

        $lineItem->setOrderId($order->getId());

        $context = Generator::generateSalesChannelContext();

        $event = new EntityWrittenEvent(
            'order_line_item',
            [
                new EntityWriteResult($lineItem->getId(), $lineItem->jsonSerialize(), OrderLineItemDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
            ],
            $context->getContext()
        );

        $redeemer->onOrderLineItemWritten($event);
    }

    public function testOnOrderLineItemWrittenWillProcessMultipleCodes(): void
    {
        $code = new PromotionIndividualCodeEntity();
        $code->setId(Uuid::randomHex());
        $code->setPromotionId(Uuid::randomHex());
        $code->setCode('existing');

        /** @var StaticEntityRepository<PromotionIndividualCodeCollection> $codeRepository */
        $codeRepository = new StaticEntityRepository([
            static function (Criteria $criteria) use ($code) {
                $filter = $criteria->getFilters()[0];
                static::assertInstanceOf(EqualsAnyFilter::class, $filter);
                static::assertSame(['existing'], $filter->getValue());

                return new PromotionIndividualCodeCollection([$code]);
            },
        ]);

        $orderRepository = $this->createMock(EntityRepository::class);
        $dispatcher = new EventDispatcher();
        /** @var list<PromotionCodeRedeemedEvent> $redeemedEvents */
        $redeemedEvents = [];
        $dispatcher->addListener(PromotionCodeRedeemedEvent::class, static function (PromotionCodeRedeemedEvent $event) use (&$redeemedEvents): void {
            $redeemedEvents[] = $event;
        });
        $redeemer = new PromotionIndividualCodeRedeemer($codeRepository, $orderRepository, $dispatcher);

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $customer = new OrderCustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setFirstName('foo');
        $customer->setLastName('bar');
        $customer->setCustomerId(Uuid::randomHex());
        $customer->setOrderId($order->getId());

        $lineItem1 = new OrderLineItemEntity();
        $lineItem1->setId(Uuid::randomHex());
        $lineItem1->setOrderId($order->getId());
        $lineItem1->setType('test');

        $lineItem2 = new OrderLineItemEntity();
        $lineItem2->setId(Uuid::randomHex());
        $lineItem2->setOrderId($order->getId());
        $lineItem2->setType(PromotionProcessor::LINE_ITEM_TYPE);
        $lineItem2->setPayload(['code' => 'existing']);

        $context = Context::createDefaultContext();

        $order->setLineItems(new OrderLineItemCollection([$lineItem1, $lineItem2]));
        $order->setOrderCustomer($customer);

        $orderRepository->expects($this->once())->method('search')->willReturn(
            new EntitySearchResult('order_customer', 1, new OrderCustomerCollection([$customer]), null, new Criteria(), $context),
        );

        $event = new EntityWrittenEvent(
            'order_line_item',
            [
                new EntityWriteResult($lineItem1->getId(), $lineItem1->jsonSerialize(), OrderLineItemDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
                new EntityWriteResult($lineItem2->getId(), $lineItem2->jsonSerialize(), OrderLineItemDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
            ],
            $context
        );

        $redeemer->onOrderLineItemWritten($event);

        static::assertSame([[[
            'id' => $code->getId(),
            'payload' => [
                'orderId' => $order->getId(),
                'customerId' => $customer->getCustomerId(),
                'customerName' => 'foo bar',
            ],
        ]]], $codeRepository->updates);

        static::assertCount(1, $redeemedEvents);
        static::assertSame($code->getPromotionId(), $redeemedEvents[0]->getPromotionId());
        static::assertSame($code->getId(), $redeemedEvents[0]->getCodeId());
        static::assertSame('existing', $redeemedEvents[0]->getCode());
        static::assertSame($order->getId(), $redeemedEvents[0]->getOrderId());
        static::assertSame($customer->getCustomerId(), $redeemedEvents[0]->getCustomerId());
        static::assertSame([
            'promotionId' => $code->getPromotionId(),
            'codeId' => $code->getId(),
            'code' => 'existing',
            'orderId' => $order->getId(),
            'customerId' => $customer->getCustomerId(),
        ], $redeemedEvents[0]->getValues());
    }

    public function testSameCodeAcrossMultipleLineItemsRedeemsOnce(): void
    {
        // a promotion with several discounts produces one promotion line item per
        // discount, each carrying the same code — redemption must dispatch one event
        $code = new PromotionIndividualCodeEntity();
        $code->setId(Uuid::randomHex());
        $code->setPromotionId(Uuid::randomHex());
        $code->setCode('existing');

        /** @var StaticEntityRepository<PromotionIndividualCodeCollection> $codeRepository */
        $codeRepository = new StaticEntityRepository([
            static fn (): PromotionIndividualCodeCollection => new PromotionIndividualCodeCollection([$code]),
        ]);

        $orderRepository = $this->createMock(EntityRepository::class);
        $dispatcher = new EventDispatcher();
        $redeemedEvents = 0;
        $dispatcher->addListener(PromotionCodeRedeemedEvent::class, static function () use (&$redeemedEvents): void {
            ++$redeemedEvents;
        });
        $redeemer = new PromotionIndividualCodeRedeemer($codeRepository, $orderRepository, $dispatcher);

        $orderId = Uuid::randomHex();
        $customer = new OrderCustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setFirstName('foo');
        $customer->setLastName('bar');
        $customer->setCustomerId(Uuid::randomHex());
        $customer->setOrderId($orderId);

        $context = Context::createDefaultContext();
        $orderRepository->method('search')->willReturn(
            new EntitySearchResult('order_customer', 1, new OrderCustomerCollection([$customer]), null, new Criteria(), $context),
        );

        $discount1 = (new OrderLineItemEntity());
        $discount1->setId(Uuid::randomHex());
        $discount1->setOrderId($orderId);
        $discount1->setType(PromotionProcessor::LINE_ITEM_TYPE);
        $discount1->setPayload(['code' => 'existing']);

        $discount2 = (new OrderLineItemEntity());
        $discount2->setId(Uuid::randomHex());
        $discount2->setOrderId($orderId);
        $discount2->setType(PromotionProcessor::LINE_ITEM_TYPE);
        $discount2->setPayload(['code' => 'existing']);

        $event = new EntityWrittenEvent(
            'order_line_item',
            [
                new EntityWriteResult($discount1->getId(), $discount1->jsonSerialize(), OrderLineItemDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
                new EntityWriteResult($discount2->getId(), $discount2->jsonSerialize(), OrderLineItemDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
            ],
            $context
        );

        $redeemer->onOrderLineItemWritten($event);

        static::assertSame(1, $redeemedEvents);
    }

    public function testAlreadyRedeemedCodeDoesNotReEmitOnRewrite(): void
    {
        $orderId = Uuid::randomHex();

        // the code is already redeemed for this order (e.g. an order edit re-writes the
        // same line items) — the persisted update is idempotent, but no new event must fire
        $code = new PromotionIndividualCodeEntity();
        $code->setId(Uuid::randomHex());
        $code->setPromotionId(Uuid::randomHex());
        $code->setCode('existing');
        $code->setPayload(['orderId' => $orderId, 'customerId' => Uuid::randomHex(), 'customerName' => 'foo bar']);

        /** @var StaticEntityRepository<PromotionIndividualCodeCollection> $codeRepository */
        $codeRepository = new StaticEntityRepository([
            static fn (): PromotionIndividualCodeCollection => new PromotionIndividualCodeCollection([$code]),
        ]);

        $orderRepository = $this->createMock(EntityRepository::class);
        $dispatcher = new EventDispatcher();
        $redeemedEvents = 0;
        $dispatcher->addListener(PromotionCodeRedeemedEvent::class, static function () use (&$redeemedEvents): void {
            ++$redeemedEvents;
        });
        $redeemer = new PromotionIndividualCodeRedeemer($codeRepository, $orderRepository, $dispatcher);

        $customer = new OrderCustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setFirstName('foo');
        $customer->setLastName('bar');
        $customer->setCustomerId(Uuid::randomHex());
        $customer->setOrderId($orderId);

        $context = Context::createDefaultContext();
        $orderRepository->method('search')->willReturn(
            new EntitySearchResult('order_customer', 1, new OrderCustomerCollection([$customer]), null, new Criteria(), $context),
        );

        $lineItem = new OrderLineItemEntity();
        $lineItem->setId(Uuid::randomHex());
        $lineItem->setOrderId($orderId);
        $lineItem->setType(PromotionProcessor::LINE_ITEM_TYPE);
        $lineItem->setPayload(['code' => 'existing']);

        $event = new EntityWrittenEvent(
            'order_line_item',
            [new EntityWriteResult($lineItem->getId(), $lineItem->jsonSerialize(), OrderLineItemDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE)],
            $context
        );

        $redeemer->onOrderLineItemWritten($event);

        static::assertSame(0, $redeemedEvents);
        // the idempotent persistence still runs
        static::assertCount(1, $codeRepository->updates);
    }

    public function testCustomersAreResolvedPerOrder(): void
    {
        // one write can carry promotion line items for several orders (sync/import); each
        // redemption event must carry its own order's customer, not the first order's
        $orderA = Uuid::randomHex();
        $orderB = Uuid::randomHex();

        $codeA = new PromotionIndividualCodeEntity();
        $codeA->setId(Uuid::randomHex());
        $codeA->setPromotionId(Uuid::randomHex());
        $codeA->setCode('code-a');

        $codeB = new PromotionIndividualCodeEntity();
        $codeB->setId(Uuid::randomHex());
        $codeB->setPromotionId(Uuid::randomHex());
        $codeB->setCode('code-b');

        /** @var StaticEntityRepository<PromotionIndividualCodeCollection> $codeRepository */
        $codeRepository = new StaticEntityRepository([
            static fn (): PromotionIndividualCodeCollection => new PromotionIndividualCodeCollection([$codeA, $codeB]),
        ]);

        $customerA = new OrderCustomerEntity();
        $customerA->setId(Uuid::randomHex());
        $customerA->setOrderId($orderA);
        $customerA->setFirstName('alice');
        $customerA->setLastName('a');
        $customerA->setCustomerId(Uuid::randomHex());

        $customerB = new OrderCustomerEntity();
        $customerB->setId(Uuid::randomHex());
        $customerB->setOrderId($orderB);
        $customerB->setFirstName('bob');
        $customerB->setLastName('b');
        $customerB->setCustomerId(Uuid::randomHex());

        $context = Context::createDefaultContext();
        $orderRepository = $this->createMock(EntityRepository::class);
        $orderRepository->expects($this->once())->method('search')->willReturn(
            new EntitySearchResult('order_customer', 2, new OrderCustomerCollection([$customerA, $customerB]), null, new Criteria(), $context),
        );

        $dispatcher = new EventDispatcher();
        /** @var array<string, PromotionCodeRedeemedEvent> $byOrder */
        $byOrder = [];
        $dispatcher->addListener(PromotionCodeRedeemedEvent::class, static function (PromotionCodeRedeemedEvent $event) use (&$byOrder): void {
            $byOrder[$event->getOrderId()] = $event;
        });
        $redeemer = new PromotionIndividualCodeRedeemer($codeRepository, $orderRepository, $dispatcher);

        $lineItemA = new OrderLineItemEntity();
        $lineItemA->setId(Uuid::randomHex());
        $lineItemA->setOrderId($orderA);
        $lineItemA->setType(PromotionProcessor::LINE_ITEM_TYPE);
        $lineItemA->setPayload(['code' => 'code-a']);

        $lineItemB = new OrderLineItemEntity();
        $lineItemB->setId(Uuid::randomHex());
        $lineItemB->setOrderId($orderB);
        $lineItemB->setType(PromotionProcessor::LINE_ITEM_TYPE);
        $lineItemB->setPayload(['code' => 'code-b']);

        $event = new EntityWrittenEvent(
            'order_line_item',
            [
                new EntityWriteResult($lineItemA->getId(), $lineItemA->jsonSerialize(), OrderLineItemDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
                new EntityWriteResult($lineItemB->getId(), $lineItemB->jsonSerialize(), OrderLineItemDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
            ],
            $context
        );

        $redeemer->onOrderLineItemWritten($event);

        static::assertCount(2, $byOrder);
        static::assertSame($customerA->getCustomerId(), $byOrder[$orderA]->getCustomerId());
        static::assertSame($customerB->getCustomerId(), $byOrder[$orderB]->getCustomerId());
    }

    public function testPayloadWithoutTypeIsSkipped(): void
    {
        /** @var StaticEntityRepository<PromotionIndividualCodeCollection> $codeRepository */
        $codeRepository = new StaticEntityRepository([]);

        $redeemer = new PromotionIndividualCodeRedeemer(
            $codeRepository,
            $this->createMock(EntityRepository::class),
            new EventDispatcher()
        );

        $customer = new OrderCustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setFirstName('foo');
        $customer->setLastName('bar');
        $customer->setCustomerId(Uuid::randomHex());

        $lineItem = new OrderLineItemEntity();
        $lineItem->setId(Uuid::randomHex());

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setLineItems(new OrderLineItemCollection([$lineItem]));
        $order->setOrderCustomer($customer);

        $lineItem->setOrderId($order->getId());

        $context = Generator::generateSalesChannelContext();

        $payload = $lineItem->jsonSerialize();
        unset($payload['type']);

        $event = new EntityWrittenEvent(
            'order_line_item',
            [
                new EntityWriteResult($lineItem->getId(), $payload, OrderLineItemDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
            ],
            $context->getContext()
        );

        $redeemer->onOrderLineItemWritten($event);

        static::assertEmpty($codeRepository->updates);
    }
}

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
        $codeRepository->expects(static::never())->method('search');
        $codeRepository->expects(static::never())->method('searchIds');
        $redeemer = new PromotionIndividualCodeRedeemer($codeRepository, $this->createMock(EntityRepository::class));

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
        $redeemer = new PromotionIndividualCodeRedeemer($codeRepository, $orderRepository);

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $customer = new OrderCustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setFirstName('foo');
        $customer->setLastName('bar');
        $customer->setCustomerId(Uuid::randomHex());

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

        $orderRepository->expects(static::once())->method('search')->willReturn(
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
    }
}

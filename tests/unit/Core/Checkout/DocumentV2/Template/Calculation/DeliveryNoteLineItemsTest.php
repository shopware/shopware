<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Template\Calculation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Template\Calculation\DeliveryNoteLineItems;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DeliveryNoteLineItems::class)]
class DeliveryNoteLineItemsTest extends TestCase
{
    public function testQuantitiesListOnlyTheLineItemsWithAPositionInTheDeliveryWithTheirPositionQuantity(): void
    {
        $order = $this->createOrder(
            $this->createLineItem('split-product', quantity: 3),
            $this->createLineItem('product-of-other-delivery', quantity: 1),
            $this->createLineItem('promotion', quantity: 1),
            $this->createLineItem('shipped-product', quantity: 1),
        );
        $delivery = $this->createDelivery(
            $this->createPosition('split-product', quantity: 2),
            $this->createPosition('shipped-product', quantity: 1),
        );
        $order->setDeliveries(new OrderDeliveryCollection([
            $delivery,
            $this->createDelivery(
                $this->createPosition('split-product', quantity: 1),
                $this->createPosition('product-of-other-delivery', quantity: 1),
            ),
        ]));

        static::assertSame(
            ['split-product' => 2, 'shipped-product' => 1],
            DeliveryNoteLineItems::quantities($order, $delivery),
        );
    }

    public function testQuantitiesListAContainerWithAllItsChildrenWhenOneOfThemShips(): void
    {
        $order = $this->createOrder(
            $this->createLineItem('container', quantity: 1),
            $this->createLineItem('shipped-child', quantity: 2, parentId: 'container'),
            $this->createLineItem('child-of-other-delivery', quantity: 4, parentId: 'container'),
            $this->createLineItem('option', quantity: 1, parentId: 'container'),
            $this->createLineItem('option-value', quantity: 1, parentId: 'option'),
            $this->createLineItem('product-of-other-delivery', quantity: 1),
        );
        $delivery = $this->createDelivery($this->createPosition('shipped-child', quantity: 2));
        $order->setDeliveries(new OrderDeliveryCollection([
            $delivery,
            $this->createDelivery(
                $this->createPosition('child-of-other-delivery', quantity: 4),
                $this->createPosition('product-of-other-delivery', quantity: 1),
            ),
        ]));

        static::assertSame(
            ['container' => 1, 'shipped-child' => 2, 'child-of-other-delivery' => 0, 'option' => 1, 'option-value' => 1],
            DeliveryNoteLineItems::quantities($order, $delivery),
        );
    }

    public function testQuantitiesAddUpSeveralPositionsOfOneLineItem(): void
    {
        $order = $this->createOrder($this->createLineItem('product', quantity: 5));
        $delivery = $this->createDelivery(
            $this->createPosition('product', quantity: 2),
            $this->createPosition('product', quantity: 1),
        );
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));

        static::assertSame(['product' => 3], DeliveryNoteLineItems::quantities($order, $delivery));
    }

    public function testQuantitiesAreEmptyForADeliveryWithoutPositions(): void
    {
        $order = $this->createOrder($this->createLineItem('product', quantity: 1));
        $delivery = $this->createDelivery();
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));

        static::assertSame([], DeliveryNoteLineItems::quantities($order, $delivery));
    }

    private function createOrder(OrderLineItemEntity ...$lineItems): OrderEntity
    {
        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection($lineItems));

        return $order;
    }

    private function createLineItem(string $id, int $quantity, ?string $parentId = null): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($id);
        $lineItem->setQuantity($quantity);

        if ($parentId !== null) {
            $lineItem->setParentId($parentId);
        }

        return $lineItem;
    }

    private function createPosition(string $lineItemId, int $quantity): OrderDeliveryPositionEntity
    {
        $position = new OrderDeliveryPositionEntity();
        $position->setId(Uuid::randomHex());
        $position->setOrderLineItemId($lineItemId);
        $position->setQuantity($quantity);

        return $position;
    }

    private function createDelivery(OrderDeliveryPositionEntity ...$positions): OrderDeliveryEntity
    {
        $delivery = new OrderDeliveryEntity();
        $delivery->setId(Uuid::randomHex());
        $delivery->setPositions(new OrderDeliveryPositionCollection($positions));

        return $delivery;
    }
}

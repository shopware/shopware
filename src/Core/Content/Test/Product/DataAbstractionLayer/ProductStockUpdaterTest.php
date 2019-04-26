<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\DataAbstractionLayer\Indexing;

use Shopware\Core\Content\Test\Product\ProductStockTestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductStockUpdaterTest extends ProductStockTestCase
{
    public function testProductStockIsUpdatedOnOrderDeliveryStateChangeToShipped(): void
    {
        $orderedQuantity = 10;
        $stock = 100;

        $product = $this->createTestProduct(Uuid::randomHex(), $stock);
        $order = $this->createTestOrder([
            [
                'product' => $product,
                'orderedQuantity' => $orderedQuantity,
            ],
        ]);
        $orderDelivery = $order->getDeliveries()->first();

        $orderDeliveryShippedState = $this->stateMachineRegistry->transition(
            $this->stateMachineRegistry->getStateMachine(OrderDeliveryStates::STATE_MACHINE, $this->context),
            $orderDelivery->getStateMachineState(),
            OrderDeliveryDefinition::getEntityName(),
            $orderDelivery->getId(),
            $this->context,
            'ship'
        );

        $this->orderDeliveryRepository->update(
            [
                [
                    'id' => $order->getDeliveries()->first()->getId(),
                    'stateId' => $orderDeliveryShippedState->getId(),
                ],
            ],
            $this->context
        );
        $product = $this->readProductFromDatabase($product->getId());
        static::assertEquals($stock - $orderedQuantity, $product->getStock());
    }

    public function testProductStockIsNotUpdatedOnOrderDeliveryStateChangeToAStateDifferentToShipped(): void
    {
        $orderedQuantity = 10;
        $stock = 100;

        $product = $this->createTestProduct(Uuid::randomHex(), $stock);
        $order = $this->createTestOrder([
            [
                'product' => $product,
                'orderedQuantity' => $orderedQuantity,
            ],
        ]);
        $orderDelivery = $order->getDeliveries()->first();

        $orderDeliveryShippedState = $this->stateMachineRegistry->transition(
            $this->stateMachineRegistry->getStateMachine(OrderDeliveryStates::STATE_MACHINE, $this->context),
            $orderDelivery->getStateMachineState(),
            OrderDeliveryDefinition::getEntityName(),
            $orderDelivery->getId(),
            $this->context,
            'ship_partially'
        );
        $this->orderDeliveryRepository->update(
            [
                [
                    'id' => $order->getDeliveries()->first()->getId(),
                    'stateId' => $orderDeliveryShippedState->getId(),
                ],
            ],
            $this->context
        );
        $product = $this->readProductFromDatabase($product->getId());
        static::assertEquals($stock, $product->getStock());
    }

    public function testProductStockIsUpdatedForAllProductsRelatedToAnOrderDelivery(): void
    {
        $productOneStock = 100;
        $productOneOrderedQuantity = 5;

        $productTwoStock = 200;
        $productTwoOrderedQuantity = 5;

        $productOne = $this->createTestProduct(Uuid::randomHex(), $productOneStock);
        static::assertEquals($productOneStock, $productOne->getStock());

        $productTwo = $this->createTestProduct(Uuid::randomHex(), $productTwoStock);
        static::assertEquals($productTwoStock, $productTwo->getStock());

        $order = $this->createTestOrder([
            [
                'product' => $productOne,
                'orderedQuantity' => $productOneOrderedQuantity,
            ],
            [
                'product' => $productTwo,
                'orderedQuantity' => $productTwoOrderedQuantity,
            ],
        ]);
        $orderDelivery = $order->getDeliveries()->first();

        $orderDeliveryShippedState = $this->stateMachineRegistry->transition(
            $this->stateMachineRegistry->getStateMachine(OrderDeliveryStates::STATE_MACHINE, $this->context),
            $orderDelivery->getStateMachineState(),
            OrderDeliveryDefinition::getEntityName(),
            $orderDelivery->getId(),
            $this->context,
            'ship'
        );
        $this->orderDeliveryRepository->update(
            [
                [
                    'id' => $order->getDeliveries()->first()->getId(),
                    'stateId' => $orderDeliveryShippedState->getId(),
                ],
            ],
            $this->context
        );

        $productOne = $this->readProductFromDatabase($productOne->getId());
        static::assertEquals($productOneStock - $productOneOrderedQuantity, $productOne->getStock());

        $productTwo = $this->readProductFromDatabase($productTwo->getId());
        static::assertEquals($productTwoStock - $productTwoOrderedQuantity, $productTwo->getStock());
    }
}

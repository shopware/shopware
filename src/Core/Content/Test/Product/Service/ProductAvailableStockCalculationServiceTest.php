<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\DataAbstractionLayer\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Test\Product\ProductStockTestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Content\Product\Service\ProductAvailableStockCalculationService;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerRegistry;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStockUpdater;

class ProductAvailableStockCalculationServiceTest extends ProductStockTestCase
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var ProductAvailableStockCalculationService
     */
    protected $productAvailableStockCalculationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productAvailableStockCalculationService = new ProductAvailableStockCalculationService(
            $this->getContainer()->get(Connection::class)
        );

        // Disable ProductAvailableStockIndexer and ProductStockUpdater in order to test the available stock
        // calculation service in isolation
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $this->eventDispatcher->removeSubscriber($this->getContainer()->get(IndexerRegistry::class));
        $this->eventDispatcher->removeSubscriber($this->getContainer()->get(ProductStockUpdater::class));
    }

    protected function tearDown(): void
    {
        // Re-enable ProductAvailableStockIndexer and ProductStockUpdater
        $this->eventDispatcher->addSubscriber($this->getContainer()->get(IndexerRegistry::class));
        $this->eventDispatcher->addSubscriber($this->getContainer()->get(ProductStockUpdater::class));
    }

    public function testAvailableStockCalculationOnProductCreation(): void
    {
        $stock = 100;
        $product = $this->createTestProduct(Uuid::randomHex(), $stock);

        $this->productAvailableStockCalculationService->recalculate([
            $product->getId()
        ], $this->context);

        $product = $this->readProductFromDatabase($product->getId());
        static::assertEquals($stock, $product->getAvailableStock());
    }

    public function testAvailableStockCalculationForProductsRelatedToOrderDeliveries(): void
    {
        $productOneStock = 100;
        $productOneOrderedQuantity = 10;
        $productOne = $this->createTestProduct(Uuid::randomHex(), $productOneStock);

        $productTwoStock = 200;
        $productTwoOrderedQuantity = 20;
        $productTwo = $this->createTestProduct(Uuid::randomHex(), $productTwoStock);

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

        $this->productAvailableStockCalculationService->recalculate([
            $productOne->getId(),
            $productTwo->getId(),
        ], $this->context);

        $productOne = $this->readProductFromDatabase($productOne->getId());
        static::assertEquals($productOneStock - $productOneOrderedQuantity, $productOne->getAvailableStock());

        $productTwo = $this->readProductFromDatabase($productTwo->getId());
        static::assertEquals($productTwoStock - $productTwoOrderedQuantity, $productTwo->getAvailableStock());

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
                    'id' => $orderDelivery->getId(),
                    'stateId' => $orderDeliveryShippedState->getId(),
                ],
            ],
            $this->context
        );

        $this->productAvailableStockCalculationService->recalculate([
            $productOne->getId(),
            $productTwo->getId(),
        ], $this->context);

        $productOne = $this->readProductFromDatabase($productOne->getId());
        static::assertEquals($productOneStock, $productOne->getAvailableStock());

        $productTwo = $this->readProductFromDatabase($productTwo->getId());
        static::assertEquals($productTwoStock, $productTwo->getAvailableStock());
    }
}

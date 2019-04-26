<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Content\Test\Product\ProductStockTestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Content\Product\Service\ProductAvailableStockCalculationService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductAvailableStockIndexer;

class ProductAvailableStockIndexerTest extends ProductStockTestCase
{
    /**
     * @var ProductAvailableStockCalculationService&MockObject
     */
    protected $productAvailableStockCalculationServiceMock;

    /**
     * @var ProductAvailableStockCalculationService
     */
    protected $productAvailableStockCalculationServiceBackup;

    /**
     * @var productAvailableStockIndexer
     */
    protected $productAvailableStockIndexer;

    protected function setUp(): void
    {
        parent::setUp();

        // Replace ProductAvailableStockCalculationService of ProductAvailableStockIndexer with service mock
        $this->productAvailableStockIndexer = $this->getContainer()->get(ProductAvailableStockIndexer::class);
        $this->productAvailableStockCalculationServiceBackup = $this->getValueOfPrivateProperty(
            $this->productAvailableStockIndexer,
            'productAvailableStockCalculationService'
        );
        $this->productAvailableStockCalculationServiceMock = $this->createMock(ProductAvailableStockCalculationService::class);
        $this->setValueOfPrivateProperty(
            $this->productAvailableStockIndexer,
            'productAvailableStockCalculationService',
            $this->productAvailableStockCalculationServiceMock
        );
    }

    protected function tearDown(): void
    {
        // Restore original instance of ProductAvailableStockCalculationService of ProductAvailableStockIndexer
        $this->setValueOfPrivateProperty(
            $this->productAvailableStockIndexer,
            'productAvailableStockCalculationService',
            $this->productAvailableStockCalculationServiceBackup
        );
    }

    public function testRecalculateIsExecutedOnProductCreation(): void
    {
        $productId = Uuid::randomHex();

        $expectedParameterValue = [$productId];
        $this->productAvailableStockCalculationServiceMock
            ->expects($this->once())
            ->method('recalculate')
            ->with(
                $this->callback(
                    function ($productIds) use ($expectedParameterValue) {
                        return count($expectedParameterValue) === count($productIds)
                            && array_diff($expectedParameterValue, $productIds) === array_diff($productIds, $expectedParameterValue);
                    }
                ),
                $this->callback(
                    function ($context) {
                        return $context instanceof \Shopware\Core\Framework\Context;
                    }
                )
            );

        $this->createTestProduct($productId, 100);
    }

    public function testRecalculateIsExecutedOnProductUpdate(): void
    {
        $product = $this->createTestProduct(Uuid::randomHex(), 100);

        $expectedParameterValue = [$product->getId()];
        $this->productAvailableStockCalculationServiceMock
            ->expects($this->once())
            ->method('recalculate')
            ->with(
                $this->callback(
                    function ($productIds) use ($expectedParameterValue) {
                        return count($expectedParameterValue) === count($productIds)
                            && array_diff($expectedParameterValue, $productIds) === array_diff($productIds, $expectedParameterValue);
                    }
                ),
                $this->callback(
                    function ($context) {
                        return $context instanceof \Shopware\Core\Framework\Context;
                    }
                )
            );

        $this->productRepository->update(
            [
                [
                    'id' => $product->getId(),
                    'stock' => $product->getStock() + 25,
                ],
            ],
            $this->context
        );
    }

    public function testRecalculateIsExecutedOnOrderDeliveryCreation(): void
    {
        $product = $this->createTestProduct(Uuid::randomHex(), 100);

        $expectedParameterValue = [$product->getId()];
        $this->productAvailableStockCalculationServiceMock
            ->expects($this->once())
            ->method('recalculate')
            ->with(
                $this->callback(
                    function ($productIds) use ($expectedParameterValue) {
                        return count($expectedParameterValue) === count($productIds)
                            && array_diff($expectedParameterValue, $productIds) === array_diff($productIds, $expectedParameterValue);
                    }
                ),
                $this->callback(
                    function ($context) {
                        return $context instanceof \Shopware\Core\Framework\Context;
                    }
                )
            );

        $this->createTestOrder([
            [
                'product' => $product,
                'orderedQuantity' => 10,
            ],
        ]);
    }

    public function testRecalculateIsExecutedOnOrderDeliveryUpdate(): void
    {
        $product = $this->createTestProduct(Uuid::randomHex(), 100);
        $order = $this->createTestOrder([
            [
                'product' => $product,
                'orderedQuantity' => 10,
            ],
        ]);
        $orderDelivery = $order->getDeliveries()->first();

        $expectedParameterValue = [$product->getId()];
        $this->productAvailableStockCalculationServiceMock
            ->expects($this->once())
            ->method('recalculate')
            ->with(
                $this->callback(
                    function ($productIds) use ($expectedParameterValue) {
                        return count($expectedParameterValue) === count($productIds)
                            && array_diff($expectedParameterValue, $productIds) === array_diff($productIds, $expectedParameterValue);
                    }
                ),
                $this->callback(
                    function ($context) {
                        return $context instanceof \Shopware\Core\Framework\Context;
                    }
                )
            );

        $orderDeliveryStateShipped = $this->stateMachineRegistry->getStateByTechnicalName(OrderDeliveryStates::STATE_MACHINE, OrderDeliveryStates::STATE_SHIPPED, $this->context);
        $this->orderDeliveryRepository->update(
            [
                [
                    'id' => $orderDelivery->getId(),
                    'stateId' => $orderDeliveryStateShipped->getId(),
                ],
            ],
            $this->context
        );
    }

    public function testRecalculateIsExecutedWithMultipleProductIds(): void
    {
        $productOne = $this->createTestProduct(Uuid::randomHex(), 100);
        $productTwo = $this->createTestProduct(Uuid::randomHex(), 200);

        $expectedParameterValue = [
            $productOne->getId(),
            $productTwo->getId(),
        ];
        $this->productAvailableStockCalculationServiceMock
            ->expects($this->once())
            ->method('recalculate')
            ->with(
                $this->callback(
                    function ($productIds) use ($expectedParameterValue) {
                        return count($expectedParameterValue) === count($productIds)
                            && array_diff($expectedParameterValue, $productIds) === array_diff($productIds, $expectedParameterValue);
                    }
                ),
                $this->callback(
                    function ($context) {
                        return $context instanceof \Shopware\Core\Framework\Context;
                    }
                )
            );

        $this->createTestOrder([
            [
                'product' => $productOne,
                'orderedQuantity' => 3,
            ],
            [
                'product' => $productTwo,
                'orderedQuantity' => 2,
            ],
        ]);
    }

    protected function getValueOfPrivateProperty(object $object, string $propertyName): object
    {
        $reflectionObject = new \ReflectionObject($object);
        $reflectionProperty = $reflectionObject->getProperty($propertyName);
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($object);
    }

    protected function setValueOfPrivateProperty(object $object, string $propertyName, object $value): void
    {
        $reflectionObject = new \ReflectionObject($object);
        $reflectionProperty = $reflectionObject->getProperty($propertyName);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($object, $value);
    }
}

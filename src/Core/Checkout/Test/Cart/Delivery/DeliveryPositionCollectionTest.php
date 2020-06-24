<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Delivery;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Uuid\Uuid;

class DeliveryPositionCollectionTest extends TestCase
{
    public function testCalculateWithMultipleQuantityLineItem(): void
    {
        $deliveryPositionCollection = new DeliveryPositionCollection();

        $lineItem = $this->getLineItem(10, 2);

        $deliveryPositionCollection->add(
            new DeliveryPosition(
                Uuid::randomHex(),
                $lineItem,
                $lineItem->getQuantity(),
                $lineItem->getPrice(),
                $this->createMock(DeliveryDate::class)
            )
        );

        static::assertEquals(20, $deliveryPositionCollection->getWeight());
    }

    public function testCalculateWithMultipleLineItems(): void
    {
        $deliveryPositionCollection = new DeliveryPositionCollection();

        $lineItem1 = $this->getLineItem(10, 1);
        $lineItem2 = $this->getLineItem(20, 1);

        $deliveryPositionCollection->add(
            new DeliveryPosition(
                Uuid::randomHex(),
                $lineItem1,
                $lineItem1->getQuantity(),
                $lineItem1->getPrice(),
                $this->createMock(DeliveryDate::class)
            )
        );

        $deliveryPositionCollection->add(
            new DeliveryPosition(
                Uuid::randomHex(),
                $lineItem2,
                $lineItem2->getQuantity(),
                $lineItem2->getPrice(),
                $this->createMock(DeliveryDate::class)
            )
        );

        static::assertEquals(30, $deliveryPositionCollection->getWeight());
    }

    public function testCalculateWithMultipleLineItemsWithMultipleQuantities(): void
    {
        $deliveryPositionCollection = new DeliveryPositionCollection();

        $lineItem1 = $this->getLineItem(10, 2);
        $lineItem2 = $this->getLineItem(20, 4);

        $deliveryPositionCollection->add(
            new DeliveryPosition(
                Uuid::randomHex(),
                $lineItem1,
                $lineItem1->getQuantity(),
                $lineItem1->getPrice(),
                $this->createMock(DeliveryDate::class)
            )
        );

        $deliveryPositionCollection->add(
            new DeliveryPosition(
                Uuid::randomHex(),
                $lineItem2,
                $lineItem2->getQuantity(),
                $lineItem2->getPrice(),
                $this->createMock(DeliveryDate::class)
            )
        );

        static::assertEquals(100, $deliveryPositionCollection->getWeight());
    }

    private function getLineItem(float $weight = 10.0, int $quantity = 1): LineItem
    {
        return (new LineItem(Uuid::randomHex(), 'product', null, $quantity))
            ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
            ->setDeliveryInformation(
                new DeliveryInformation(
                    $quantity,
                    $weight,
                    true
                )
            );
    }
}

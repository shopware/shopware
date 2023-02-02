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

/**
 * @internal
 */
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
                new DeliveryDate(new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2020-01-01')),
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
                new DeliveryDate(new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2020-01-01')),
            )
        );

        $deliveryPositionCollection->add(
            new DeliveryPosition(
                Uuid::randomHex(),
                $lineItem2,
                $lineItem2->getQuantity(),
                $lineItem2->getPrice(),
                new DeliveryDate(new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2020-01-01')),
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
                new DeliveryDate(new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2020-01-01')),
            )
        );

        $deliveryPositionCollection->add(
            new DeliveryPosition(
                Uuid::randomHex(),
                $lineItem2,
                $lineItem2->getQuantity(),
                $lineItem2->getPrice(),
                new DeliveryDate(new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2020-01-01')),
            )
        );

        static::assertEquals(100, $deliveryPositionCollection->getWeight());
    }

    public function testCalculateWithoutFreeDelivery(): void
    {
        $deliveryPositionCollection = new DeliveryPositionCollection();

        $lineItem1 = $this->getLineItem(10, 2);
        $lineItem2 = $this->getLineItem(20, 4, false);

        $deliveryPositionCollection->add(
            new DeliveryPosition(
                Uuid::randomHex(),
                $lineItem1,
                $lineItem1->getQuantity(),
                $lineItem1->getPrice(),
                new DeliveryDate(new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2020-01-01')),
            )
        );

        $deliveryPositionCollection->add(
            new DeliveryPosition(
                Uuid::randomHex(),
                $lineItem2,
                $lineItem2->getQuantity(),
                $lineItem2->getPrice(),
                new DeliveryDate(new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2020-01-01')),
            )
        );

        static::assertEquals(80, $deliveryPositionCollection->getWithoutDeliveryFree()->getWeight());
    }

    /**
     * @dataProvider volumeDataProvider
     */
    public function testCalculateVolumeWithMultipleLineItemsWithMultipleQuantities(
        ?float $height,
        ?float $width,
        ?float $length,
        float $expect
    ): void {
        $deliveryPositionCollection = new DeliveryPositionCollection();

        $lineItem1 = $this->getLineItem(10, 1, true, $height, $width, $length);
        $lineItem2 = $this->getLineItem(20, 2, true, $height, $width, $length);

        $deliveryPositionCollection->add(
            new DeliveryPosition(
                Uuid::randomHex(),
                $lineItem1,
                $lineItem1->getQuantity(),
                $lineItem1->getPrice(),
                new DeliveryDate(new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2020-01-01')),
            )
        );

        $deliveryPositionCollection->add(
            new DeliveryPosition(
                Uuid::randomHex(),
                $lineItem2,
                $lineItem2->getQuantity(),
                $lineItem2->getPrice(),
                new DeliveryDate(new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2020-01-01')),
            )
        );

        static::assertEquals($expect, $deliveryPositionCollection->getVolume());
    }

    public function volumeDataProvider(): array
    {
        return [
            'test height/width/length: -1, -1, -1' => [-1, -1, -1, 0],
            'test height/width/length: -1, -1, 1' => [-1, -1, 1, 0],
            'test height/width/length: -1, 1, -1' => [-1, 1, -1, 0],
            'test height/width/length: -1, 1, 1' => [-1, 1, 1, 0],
            'test height/width/length: 1, -1, -1' => [1, -1, -1, 0],
            'test height/width/length: 1, -1, 1' => [1, -1, 1, 0],
            'test height/width/length: 1, 1, -1' => [1, 1, -1, 0],

            'test height/width/length: 0.5, 0.5, 0.5' => [0.5, 0.5, 0.5, 0.375],
            'test height/width/length: 0.5, 0.5, 1' => [0.5, 0.5, 1, 0.75],
            'test height/width/length: 0.5, 1, 0.5' => [0.5, 1, 0.5, 0.75],
            'test height/width/length: 0.5, 1, 1' => [0.5, 1, 1, 1.5],
            'test height/width/length: 1, 0.5, 0.5' => [1, 0.5, 0.5, 0.75],
            'test height/width/length: 1, 0.5, 1' => [1, 0.5, 1, 1.5],
            'test height/width/length: 1, 1, 0.5' => [1, 1, 0.5, 1.5],

            'test height/width/length: 0, 0, 0' => [0, 0, 0, 0],
            'test height/width/length: 0, 0, 1' => [0, 0, 1, 0],
            'test height/width/length: 0, 1, 0' => [0, 1, 0, 0],
            'test height/width/length: 0, 1, 1' => [0, 1, 1, 0],
            'test height/width/length: 1, 0, 0' => [1, 0, 0, 0],
            'test height/width/length: 1, 0, 1' => [1, 0, 1, 0],
            'test height/width/length: 1, 1, 0' => [1, 1, 0, 0],
            'test height/width/length: 1, 1, 1' => [1, 1, 1, 3],

            'test height/width/length: null, null, null' => [null, null, null, 0],
            'test height/width/length: null, null, 1' => [null, null, 1, 0],
            'test height/width/length: null, 1, null' => [null, 1, null, 0],
            'test height/width/length: null, 1, 1' => [null, 1, 1, 0],
            'test height/width/length: 1, null, null' => [1, null, null, 0],
            'test height/width/length: 1, null, 1' => [1, null, 1, 0],
            'test height/width/length: 1, 1, null' => [1, 1, null, 0],
        ];
    }

    private function getLineItem(
        float $weight = 10.0,
        int $quantity = 1,
        bool $freeDelivery = true,
        ?float $height = null,
        ?float $width = null,
        ?float $length = null
    ): LineItem {
        return (new LineItem(Uuid::randomHex(), 'product', null, $quantity))
            ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
            ->setDeliveryInformation(
                new DeliveryInformation(
                    $quantity,
                    $weight,
                    $freeDelivery,
                    null,
                    null,
                    $height,
                    $width,
                    $length,
                )
            );
    }
}

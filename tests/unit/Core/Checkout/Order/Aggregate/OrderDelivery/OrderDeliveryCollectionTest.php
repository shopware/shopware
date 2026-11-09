<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Aggregate\OrderDelivery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderDeliveryCollection::class)]
class OrderDeliveryCollectionTest extends TestCase
{
    public function testGetShipmentsLeavesOutShippingDiscountsAndSortsByShippingCosts(): void
    {
        $deliveries = new OrderDeliveryCollection([
            $this->createDelivery('standard', 4.95),
            $this->createDelivery('discount', -4.95),
            $this->createDelivery('express', 9.99),
            $this->createDelivery('free', 0.0),
            $this->createDelivery('second-standard', 4.95),
        ]);

        static::assertSame(
            ['express', 'standard', 'second-standard', 'free'],
            array_values($deliveries->getShipments()->getIds())
        );
    }

    private function createDelivery(string $id, float $shippingCosts): OrderDeliveryEntity
    {
        $delivery = new OrderDeliveryEntity();
        $delivery->setId($id);
        $delivery->setShippingCosts(new CalculatedPrice($shippingCosts, $shippingCosts, new CalculatedTaxCollection(), new TaxRuleCollection()));

        return $delivery;
    }
}

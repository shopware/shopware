<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Template\Calculation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\DocumentV2\Template\Calculation\NetAmount;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(NetAmount::class)]
class NetAmountTest extends TestCase
{
    public function testIsOrderGrossTrueForGrossCart(): void
    {
        $order = new OrderEntity();
        $order->setPrice($this->createCartPrice(CartPrice::TAX_STATE_GROSS));

        static::assertTrue(NetAmount::isOrderGross($order));
    }

    public function testIsOrderGrossFalseForNetCart(): void
    {
        $order = new OrderEntity();
        $order->setPrice($this->createCartPrice(CartPrice::TAX_STATE_NET));

        static::assertFalse(NetAmount::isOrderGross($order));
    }

    public function testIsOrderGrossFalseForTaxFreeCart(): void
    {
        $order = new OrderEntity();
        $order->setPrice($this->createCartPrice(CartPrice::TAX_STATE_FREE));

        static::assertFalse(NetAmount::isOrderGross($order));
    }

    public function testFromTaxSubtractsTaxWhenGross(): void
    {
        $tax = new CalculatedTax(19.0, 19.0, 119.0);

        static::assertSame(100.0, NetAmount::fromTax($tax, null, true));
    }

    public function testFromTaxReturnsTaxPriceWhenNet(): void
    {
        $tax = new CalculatedTax(19.0, 19.0, 119.0);

        static::assertSame(119.0, NetAmount::fromTax($tax, null, false));
    }

    public function testFromTaxFallsBackToFirstCalculatedTaxOnPrice(): void
    {
        $price = new CalculatedPrice(
            100.0,
            100.0,
            new CalculatedTaxCollection([new CalculatedTax(7.0, 7.0, 107.0)]),
            new TaxRuleCollection(),
        );

        static::assertSame(100.0, NetAmount::fromTax(null, $price, true));
    }

    public function testFromTaxFallsBackToTotalPriceWhenNoTaxAvailable(): void
    {
        $price = new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection());

        static::assertSame(100.0, NetAmount::fromTax(null, $price, true));
    }

    public function testFromTaxReturnsZeroWhenNothingProvided(): void
    {
        static::assertSame(0.0, NetAmount::fromTax(null, null, true));
    }

    private function createCartPrice(string $taxState): CartPrice
    {
        return new CartPrice(0.0, 0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection(), $taxState);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Template\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TaxCategory;
use Shopware\Core\Checkout\DocumentV2\Template\View\TaxBreakdownView;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(TaxBreakdownView::class)]
class TaxBreakdownViewTest extends TestCase
{
    public function testListFromOrderEmitsEntryPerTaxBucket(): void
    {
        $order = $this->createOrder(CartPrice::TAX_STATE_NET, [
            new CalculatedTax(19.0, 19.0, 100.0),
            new CalculatedTax(7.0, 7.0, 100.0),
        ]);

        $views = TaxBreakdownView::listFromOrder($order);

        static::assertCount(2, $views);

        static::assertSame(19.0, $views[0]->calculatedAmount);
        static::assertSame(100.0, $views[0]->basisAmount);
        static::assertSame(19.0, $views[0]->taxRate);
        static::assertSame(TaxCategory::STANDARD_RATE, $views[0]->taxCategory);

        static::assertSame(7.0, $views[1]->calculatedAmount);
        static::assertSame(100.0, $views[1]->basisAmount);
        static::assertSame(7.0, $views[1]->taxRate);
    }

    public function testListFromOrderSubtractsTaxForGrossOrders(): void
    {
        $order = $this->createOrder(CartPrice::TAX_STATE_GROSS, [
            new CalculatedTax(19.0, 19.0, 119.0),
        ]);

        $view = TaxBreakdownView::listFromOrder($order)[0];

        static::assertSame(100.0, $view->basisAmount);
    }

    public function testListFromOrderUsesZeroRatedForZeroPercentBucket(): void
    {
        $order = $this->createOrder(CartPrice::TAX_STATE_NET, [
            new CalculatedTax(0.0, 0.0, 50.0),
        ]);

        $view = TaxBreakdownView::listFromOrder($order)[0];

        static::assertSame(TaxCategory::ZERO_RATED, $view->taxCategory);
        static::assertSame(0.0, $view->taxRate);
    }

    public function testListFromOrderReturnsSingleZeroRatedEntryForTaxFreeOrder(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setPrice(new CartPrice(
            100.0,
            100.0,
            0.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_FREE,
        ));

        $views = TaxBreakdownView::listFromOrder($order);

        static::assertCount(1, $views);
        static::assertSame(0.0, $views[0]->calculatedAmount);
        static::assertSame(100.0, $views[0]->basisAmount);
        static::assertSame(TaxCategory::ZERO_RATED, $views[0]->taxCategory);
        static::assertSame(0.0, $views[0]->taxRate);
    }

    public function testListFromOrderReturnsZeroRatedEntryWhenTaxCollectionIsEmpty(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setPrice(new CartPrice(
            100.0,
            100.0,
            0.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_NET,
        ));

        $views = TaxBreakdownView::listFromOrder($order);

        static::assertCount(1, $views);
        static::assertSame(0.0, $views[0]->calculatedAmount);
        static::assertSame(100.0, $views[0]->basisAmount);
        static::assertSame(TaxCategory::ZERO_RATED, $views[0]->taxCategory);
        static::assertSame(0.0, $views[0]->taxRate);
    }

    /**
     * @param list<CalculatedTax> $taxes
     */
    private function createOrder(string $taxState, array $taxes): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setPrice(new CartPrice(
            0.0,
            0.0,
            0.0,
            new CalculatedTaxCollection($taxes),
            new TaxRuleCollection(),
            $taxState,
        ));

        return $order;
    }
}

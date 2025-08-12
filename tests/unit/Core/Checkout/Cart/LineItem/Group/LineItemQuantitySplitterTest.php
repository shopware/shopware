<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\LineItem\Group;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LineItemQuantitySplitter::class)]
class LineItemQuantitySplitterTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getTaxState')->willReturn(CartPrice::TAX_STATE_GROSS);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $this->salesChannelContext = $context;
    }

    public function testSplitTaxesUnrounded(): void
    {
        $splitter = $this->createQtySplitter();

        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 10);
        $lineItem->setPrice(new CalculatedPrice(39.95, 399.50, new CalculatedTaxCollection([new CalculatedTax(19.03, 5, 399.50)]), new TaxRuleCollection([new TaxRule(5)]), 10));
        $lineItem->setStackable(true);

        $newLineItem = $splitter->split($lineItem, 1, $this->salesChannelContext);

        static::assertNotSame($lineItem, $newLineItem);
        static::assertSame(1, $newLineItem->getQuantity());
        static::assertSame(39.95, $newLineItem->getPrice()?->getTotalPrice());
        static::assertSame(1.903, $newLineItem->getPrice()->getCalculatedTaxes()->first()?->getTax());
    }

    #[DataProvider('splitProvider')]
    public function testSplit(int $itemQty, int $splitterQty, bool $calcExpects): void
    {
        $splitter = $this->createQtySplitter();

        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), $itemQty);
        $lineItem->setPrice(new CalculatedPrice(10, 10 * $itemQty, new CalculatedTaxCollection(), new TaxRuleCollection(), $itemQty));
        $lineItem->setStackable(true);

        $newLineItem = $splitter->split($lineItem, $splitterQty, $this->salesChannelContext);

        if (!$calcExpects) {
            static::assertEquals($lineItem, $newLineItem);
        } else {
            $expectedPrice = 10.0 * $splitterQty;

            static::assertNotSame($lineItem, $newLineItem);
            static::assertSame($splitterQty, $newLineItem->getQuantity());
            static::assertSame($expectedPrice, $newLineItem->getPrice()?->getTotalPrice());
        }
    }

    /**
     * @return \Generator<string, array{0: int, 1: int, 2: bool}>
     */
    public static function splitProvider(): \Generator
    {
        yield 'should not split items when item qty = 10 and splitter qty = 10' => [10, 10, false];
        yield 'should split items when item qty = 10 and splitter qty = 9' => [10, 9, true];
        yield 'should split items when item qty = 9 and splitter qty = 10' => [9, 10, true];
    }

    private function createQtySplitter(): LineItemQuantitySplitter
    {
        $taxCalculator = new TaxCalculator();
        $cashRounding = new CashRounding();

        $qtyCalc = new QuantityPriceCalculator(new GrossPriceCalculator($taxCalculator, $cashRounding), new NetPriceCalculator($taxCalculator, $cashRounding));

        return new LineItemQuantitySplitter($qtyCalc);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Framework\Twig\Extension\AnalyticsLineItemPriceExtension;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AnalyticsLineItemPriceExtension::class)]
class AnalyticsLineItemPriceExtensionTest extends TestCase
{
    private AnalyticsLineItemPriceExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new AnalyticsLineItemPriceExtension(new CashRounding());
    }

    public function testRegistersTheTwigFunction(): void
    {
        $names = array_map(static fn ($function) => $function->getName(), $this->extension->getFunctions());

        static::assertSame(['sw_analytics_line_item_prices'], $names);
    }

    public function testReportsTheUnitPriceWhenNoPromotionApplies(): void
    {
        $lineItems = new LineItemCollection([
            $this->product('product-1', 10.0, 3),
        ]);

        $prices = $this->extension->getPrices($lineItems, $this->context());

        static::assertSame(['product-1' => ['price' => 10.0, 'discount' => 0.0]], $prices);
    }

    public function testAllocatesAnAbsoluteDiscountToTheDiscountedLine(): void
    {
        $lineItems = new LineItemCollection([
            $this->product('product-1', 10.0, 3),
            $this->product('product-2', 20.0, 1),
            $this->promotion([['id' => 'product-1', 'quantity' => 3, 'discount' => 6.0]]),
        ]);

        $prices = $this->extension->getPrices($lineItems, $this->context());

        static::assertSame(['price' => 8.0, 'discount' => 2.0], $prices['product-1']);
        static::assertSame(['price' => 20.0, 'discount' => 0.0], $prices['product-2']);
    }

    public function testSumsSeveralPromotionsOnTheSameLine(): void
    {
        $lineItems = new LineItemCollection([
            $this->product('product-1', 10.0, 2),
            $this->promotion([['id' => 'product-1', 'quantity' => 2, 'discount' => 4.0]]),
            $this->promotion([['id' => 'product-1', 'quantity' => 2, 'discount' => 6.0]]),
        ]);

        $prices = $this->extension->getPrices($lineItems, $this->context());

        static::assertSame(['price' => 5.0, 'discount' => 5.0], $prices['product-1']);
    }

    /**
     * A discount does not have to apply to every unit of a line, so the aggregated discount is spread
     * over the full quantity rather than over the discounted quantity.
     */
    public function testSpreadsAPartialQuantityDiscountOverTheWholeLine(): void
    {
        $lineItems = new LineItemCollection([
            $this->product('product-1', 10.0, 4),
            $this->promotion([['id' => 'product-1', 'quantity' => 2, 'discount' => 10.0]]),
        ]);

        $prices = $this->extension->getPrices($lineItems, $this->context());

        static::assertSame(['price' => 7.5, 'discount' => 2.5], $prices['product-1']);
    }

    public function testNeverReportsANegativePrice(): void
    {
        $lineItems = new LineItemCollection([
            $this->product('product-1', 10.0, 1),
            $this->promotion([['id' => 'product-1', 'quantity' => 1, 'discount' => 25.0]]),
        ]);

        $prices = $this->extension->getPrices($lineItems, $this->context());

        static::assertSame(['price' => 0.0, 'discount' => 10.0], $prices['product-1']);
    }

    public function testIgnoresShippingDiscounts(): void
    {
        $promotion = $this->promotion([['id' => 'product-1', 'quantity' => 1, 'discount' => 5.0]]);
        $promotion->setPayloadValue('discountScope', PromotionDiscountEntity::SCOPE_DELIVERY);

        $lineItems = new LineItemCollection([
            $this->product('product-1', 10.0, 1),
            $promotion,
        ]);

        $prices = $this->extension->getPrices($lineItems, $this->context());

        static::assertSame(['price' => 10.0, 'discount' => 0.0], $prices['product-1']);
    }

    public function testSkipsDiscountAndShippingLineItems(): void
    {
        $lineItems = new LineItemCollection([
            $this->product('product-1', 10.0, 1),
            $this->promotion([['id' => 'product-1', 'quantity' => 1, 'discount' => 2.0]]),
        ]);

        $prices = $this->extension->getPrices($lineItems, $this->context());

        static::assertSame(['product-1'], array_keys($prices));
    }

    public function testRoundsToTheCurrencyItemRounding(): void
    {
        $lineItems = new LineItemCollection([
            $this->product('product-1', 10.0, 3),
            $this->promotion([['id' => 'product-1', 'quantity' => 3, 'discount' => 10.0]]),
        ]);

        $prices = $this->extension->getPrices($lineItems, $this->context());

        // 20.00 / 3 = 6.666…, rounded to the two decimals the currency uses
        static::assertSame(['price' => 6.67, 'discount' => 3.33], $prices['product-1']);
    }

    public function testHonoursACurrencyWithoutDecimals(): void
    {
        $lineItems = new LineItemCollection([
            $this->product('product-1', 10.0, 3),
            $this->promotion([['id' => 'product-1', 'quantity' => 3, 'discount' => 10.0]]),
        ]);

        $prices = $this->extension->getPrices($lineItems, $this->context(new CashRoundingConfig(0, 1.0, true)));

        static::assertSame(['price' => 7.0, 'discount' => 3.0], $prices['product-1']);
    }

    /**
     * A composition references the cart line item id, which an order line item keeps in `identifier`
     * while its own id is the primary key of the order line item. Resolving the composition against
     * the wrong one silently reports undiscounted prices on the finish page.
     */
    public function testResolvesOrderLineItemsThroughTheirIdentifier(): void
    {
        $lineItems = new OrderLineItemCollection([
            $this->orderProduct('order-line-item-1', 'product-1', 10.0, 2),
            $this->orderPromotion('order-line-item-2', [['id' => 'product-1', 'quantity' => 2, 'discount' => 4.0]]),
        ]);

        $prices = $this->extension->getPrices($lineItems, $this->context());

        static::assertSame(['order-line-item-1' => ['price' => 8.0, 'discount' => 2.0]], $prices);
    }

    public function testIgnoresLineItemsWithoutAPrice(): void
    {
        $lineItem = new LineItem('product-1', LineItem::PRODUCT_LINE_ITEM_TYPE, null, 1);
        $lineItem->setGood(true);

        $prices = $this->extension->getPrices(new LineItemCollection([$lineItem]), $this->context());

        static::assertSame([], $prices);
    }

    public function testIgnoresMalformedCompositionEntries(): void
    {
        $promotion = $this->promotion([
            ['quantity' => 1, 'discount' => 5.0],
            ['id' => 'product-1', 'quantity' => 1],
            ['id' => 'product-1', 'quantity' => 1, 'discount' => 2.0],
        ]);

        $lineItems = new LineItemCollection([
            $this->product('product-1', 10.0, 1),
            $promotion,
        ]);

        $prices = $this->extension->getPrices($lineItems, $this->context());

        static::assertSame(['price' => 8.0, 'discount' => 2.0], $prices['product-1']);
    }

    private function context(?CashRoundingConfig $itemRounding = null): SalesChannelContext
    {
        return Generator::generateSalesChannelContext(
            itemRounding: $itemRounding ?? new CashRoundingConfig(2, 0.01, true),
        );
    }

    private function product(string $id, float $unitPrice, int $quantity): LineItem
    {
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, $id, $quantity);
        $lineItem->setGood(true);
        $lineItem->setPrice($this->price($unitPrice, $quantity));

        return $lineItem;
    }

    /**
     * @param list<array<string, mixed>> $composition
     */
    private function promotion(array $composition): LineItem
    {
        $lineItem = new LineItem('promotion-' . md5(json_encode($composition, \JSON_THROW_ON_ERROR)), LineItem::PROMOTION_LINE_ITEM_TYPE);
        $lineItem->setGood(false);
        $lineItem->setPayloadValue('composition', $composition);
        $lineItem->setPrice($this->price(-1.0, 1));

        return $lineItem;
    }

    private function orderProduct(string $id, string $identifier, float $unitPrice, int $quantity): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($id);
        $lineItem->setIdentifier($identifier);
        $lineItem->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setGood(true);
        $lineItem->setQuantity($quantity);
        $lineItem->setPrice($this->price($unitPrice, $quantity));

        return $lineItem;
    }

    /**
     * @param list<array<string, mixed>> $composition
     */
    private function orderPromotion(string $id, array $composition): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($id);
        $lineItem->setIdentifier($id);
        $lineItem->setType(LineItem::PROMOTION_LINE_ITEM_TYPE);
        $lineItem->setGood(false);
        $lineItem->setQuantity(1);
        $lineItem->setPayload(['composition' => $composition]);
        $lineItem->setPrice($this->price(-1.0, 1));

        return $lineItem;
    }

    private function price(float $unitPrice, int $quantity): CalculatedPrice
    {
        return new CalculatedPrice(
            $unitPrice,
            $unitPrice * $quantity,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            $quantity
        );
    }
}

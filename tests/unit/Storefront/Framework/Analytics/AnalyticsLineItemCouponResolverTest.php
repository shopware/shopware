<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Analytics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Analytics\AnalyticsLineItemCouponResolver;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AnalyticsLineItemCouponResolver::class)]
class AnalyticsLineItemCouponResolverTest extends TestCase
{
    private AnalyticsLineItemCouponResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new AnalyticsLineItemCouponResolver();
    }

    public function testReportsNoCouponsWithoutLineItems(): void
    {
        static::assertSame([], $this->resolver->resolve(null));
    }

    public function testReportsTheCodesOfThePromotionsThatDiscountedEachProduct(): void
    {
        $coupons = $this->resolver->resolve([
            $this->product('product-1'),
            $this->product('product-2'),
            $this->product('product-3'),
            $this->promotion('SUMMER', ['product-1', 'product-2']),
            $this->promotion('VIP', ['product-2']),
        ]);

        static::assertSame([
            'product-1' => ['SUMMER'],
            'product-2' => ['SUMMER', 'VIP'],
        ], $coupons);
    }

    /**
     * A promotion with several discounts adds one line item per discount, all with the same code.
     */
    public function testReportsACodeOnce(): void
    {
        $coupons = $this->resolver->resolve([
            $this->product('product-1'),
            $this->promotion('SUMMER', ['product-1']),
            $this->promotion('SUMMER', ['product-1']),
        ]);

        static::assertSame(['product-1' => ['SUMMER']], $coupons);
    }

    public function testSkipsAutomaticPromotionsAndShippingDiscounts(): void
    {
        $coupons = $this->resolver->resolve([
            $this->product('product-1'),
            $this->promotion('', ['product-1']),
            $this->promotion('FREESHIPPING', ['product-1'], PromotionDiscountEntity::SCOPE_DELIVERY),
        ]);

        static::assertSame([], $coupons);
    }

    public function testReportsTheCodeOfADiscountOfAnExtension(): void
    {
        $discount = new LineItem('voucher', 'swag-voucher');
        $discount->setGood(false);
        $discount->setPayloadValue('code', 'GIFT-123');
        $discount->setPayloadValue('composition', [['id' => 'product-1', 'quantity' => 1, 'discount' => 5.0]]);

        static::assertSame(['product-1' => ['GIFT-123']], $this->resolver->resolve([$this->product('product-1'), $discount]));
    }

    public function testResolvesOrderLineItemsThroughTheirIdentifier(): void
    {
        $product = new OrderLineItemEntity();
        $product->setId('order-line-item-1');
        $product->setIdentifier('product-1');
        $product->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        $product->setGood(true);

        $promotion = new OrderLineItemEntity();
        $promotion->setId('order-line-item-2');
        $promotion->setIdentifier('promotion-1');
        $promotion->setType(LineItem::PROMOTION_LINE_ITEM_TYPE);
        $promotion->setGood(false);
        $promotion->setPayload(['code' => 'SUMMER', 'composition' => [['id' => 'product-1', 'quantity' => 1, 'discount' => 1.0]]]);

        static::assertSame(['order-line-item-1' => ['SUMMER']], $this->resolver->resolve([$product, $promotion]));
    }

    public function testIgnoresMalformedCompositionEntries(): void
    {
        $promotion = $this->promotion('SUMMER', []);
        $promotion->setPayloadValue('composition', ['not an entry', ['quantity' => 1], ['id' => 'product-1']]);

        static::assertSame(['product-1' => ['SUMMER']], $this->resolver->resolve([$this->product('product-1'), $promotion]));
    }

    private function product(string $id): LineItem
    {
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, $id, 1);
        $lineItem->setGood(true);

        return $lineItem;
    }

    /**
     * @param list<string> $productIds
     */
    private function promotion(string $code, array $productIds, string $scope = 'cart'): LineItem
    {
        $lineItem = new LineItem('promotion-' . $code . '-' . implode('-', $productIds) . '-' . $scope . '-' . bin2hex(random_bytes(2)), LineItem::PROMOTION_LINE_ITEM_TYPE);
        $lineItem->setGood(false);
        $lineItem->setPayloadValue('code', $code);
        $lineItem->setPayloadValue('discountScope', $scope);
        $lineItem->setPayloadValue('composition', array_map(
            static fn (string $id) => ['id' => $id, 'quantity' => 1, 'discount' => 1.0],
            $productIds
        ));

        return $lineItem;
    }
}

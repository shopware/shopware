<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Template\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\AllowanceChargeReason;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TaxCategory;
use Shopware\Core\Checkout\DocumentV2\Template\View\AllowanceChargeView;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(AllowanceChargeView::class)]
class AllowanceChargeViewTest extends TestCase
{
    public function testListFromOrderEmitsDeliveryEntryPerTax(): void
    {
        $order = $this->createOrder(
            CartPrice::TAX_STATE_NET,
            deliveries: [$this->createDelivery(10.0, [new CalculatedTax(0.7, 7.0, 10.0)])],
            lineItems: [],
        );

        $views = AllowanceChargeView::listFromOrder($order);

        static::assertCount(1, $views);
        static::assertTrue($views[0]->isCharge);
        static::assertSame(AllowanceChargeReason::DELIVERY, $views[0]->reasonCode);
        static::assertSame(AllowanceChargeReason::DELIVERY->defaultLabel(), $views[0]->reason);
        static::assertSame(10.0, $views[0]->actualAmount);
        static::assertSame(7.0, $views[0]->taxRate);
        static::assertSame(TaxCategory::STANDARD_RATE, $views[0]->taxCategory);
    }

    public function testListFromOrderEmitsZeroAmountDeliveryEntriesForV1Parity(): void
    {
        $order = $this->createOrder(
            CartPrice::TAX_STATE_NET,
            deliveries: [$this->createDelivery(0.0, [new CalculatedTax(0.0, 19.0, 0.0)])],
            lineItems: [],
        );

        $views = AllowanceChargeView::listFromOrder($order);

        static::assertCount(1, $views);
        static::assertSame(0.0, $views[0]->actualAmount);
    }

    public function testListFromOrderSubtractsTaxForGrossDelivery(): void
    {
        $order = $this->createOrder(
            CartPrice::TAX_STATE_GROSS,
            deliveries: [$this->createDelivery(10.7, [new CalculatedTax(0.7, 7.0, 10.7)])],
            lineItems: [],
        );

        $view = AllowanceChargeView::listFromOrder($order)[0];

        static::assertSame(10.0, $view->actualAmount);
    }

    public function testListFromOrderEmitsZeroRatedDeliveryEntryWithoutTaxes(): void
    {
        $order = $this->createOrder(
            CartPrice::TAX_STATE_FREE,
            deliveries: [$this->createDelivery(10.0, [])],
            lineItems: [],
        );

        $view = AllowanceChargeView::listFromOrder($order)[0];

        static::assertTrue($view->isCharge);
        static::assertSame(10.0, $view->actualAmount);
        static::assertSame(TaxCategory::ZERO_RATED, $view->taxCategory);
        static::assertSame(0.0, $view->taxRate);
    }

    public function testListFromOrderEmitsAllowanceForPercentagePromotion(): void
    {
        $promo = $this->createPromotionLineItem(
            label: 'TENOFF',
            type: LineItem::PROMOTION_LINE_ITEM_TYPE,
            unitPrice: -10.0,
            total: -10.0,
            taxes: [new CalculatedTax(-0.7, 7.0, -10.0)],
            payload: ['discountType' => PromotionDiscountEntity::TYPE_PERCENTAGE, 'value' => 10, 'maxValue' => 0],
        );

        $order = $this->createOrder(CartPrice::TAX_STATE_NET, deliveries: [], lineItems: [$promo]);

        $view = AllowanceChargeView::listFromOrder($order)[0];

        static::assertFalse($view->isCharge);
        static::assertSame(AllowanceChargeReason::DISCOUNT, $view->reasonCode);
        static::assertSame(10.0, $view->actualAmount);
        static::assertSame(10.0, $view->calculationPercent);
        static::assertSame(100.0, $view->basisAmount);
    }

    public function testListFromOrderTreatsCappedPercentagePromotionAsAbsolute(): void
    {
        $promo = $this->createPromotionLineItem(
            label: 'CAP10',
            type: LineItem::PROMOTION_LINE_ITEM_TYPE,
            unitPrice: -10.0,
            total: -10.0,
            taxes: [new CalculatedTax(-0.7, 7.0, -10.0)],
            payload: ['discountType' => PromotionDiscountEntity::TYPE_PERCENTAGE, 'value' => 10, 'maxValue' => 10],
        );

        $order = $this->createOrder(CartPrice::TAX_STATE_NET, deliveries: [], lineItems: [$promo]);

        $view = AllowanceChargeView::listFromOrder($order)[0];

        static::assertNull($view->calculationPercent);
        static::assertNull($view->basisAmount);
    }

    public function testListFromOrderEmitsAllowanceWithoutBasisForAbsolutePromotion(): void
    {
        $promo = $this->createPromotionLineItem(
            label: 'FIXED5',
            type: LineItem::PROMOTION_LINE_ITEM_TYPE,
            unitPrice: -5.0,
            total: -5.0,
            taxes: [new CalculatedTax(-0.35, 7.0, -5.0)],
            payload: ['discountType' => 'absolute', 'value' => 5],
        );

        $order = $this->createOrder(CartPrice::TAX_STATE_NET, deliveries: [], lineItems: [$promo]);

        $view = AllowanceChargeView::listFromOrder($order)[0];

        static::assertNull($view->calculationPercent);
        static::assertNull($view->basisAmount);
    }

    public function testListFromOrderEmitsZeroRatedPromotionEntryWithoutTaxes(): void
    {
        $promo = $this->createPromotionLineItem(
            label: 'FIXED5',
            type: LineItem::PROMOTION_LINE_ITEM_TYPE,
            unitPrice: -5.0,
            total: -5.0,
            taxes: [],
            payload: ['discountType' => 'absolute', 'value' => 5],
        );

        $order = $this->createOrder(CartPrice::TAX_STATE_FREE, deliveries: [], lineItems: [$promo]);

        $view = AllowanceChargeView::listFromOrder($order)[0];

        static::assertFalse($view->isCharge);
        static::assertSame(5.0, $view->actualAmount);
        static::assertSame(TaxCategory::ZERO_RATED, $view->taxCategory);
        static::assertSame(0.0, $view->taxRate);
    }

    public function testListFromOrderIncludesNestedPromotionLineItems(): void
    {
        $container = new OrderLineItemEntity();
        $container->setUniqueIdentifier(Uuid::randomHex());
        $container->setIdentifier('bundle');
        $container->setType(LineItem::CONTAINER_LINE_ITEM);
        $container->setLabel('Bundle');
        $container->setQuantity(1);
        $container->setChildren(new OrderLineItemCollection([
            $this->createPromotionLineItem(
                'PROMO',
                LineItem::PROMOTION_LINE_ITEM_TYPE,
                -5.0,
                -5.0,
                [new CalculatedTax(-0.35, 7.0, -5.0)],
                payload: [],
            ),
        ]));

        $views = AllowanceChargeView::listFromOrder($this->createOrder(CartPrice::TAX_STATE_NET, deliveries: [], lineItems: [$container]));

        static::assertCount(1, $views);
        static::assertSame('PROMO', $views[0]->reason);
    }

    public function testListFromOrderFallsBackToDefaultReasonWhenReferenceAndLabelEmpty(): void
    {
        $promo = $this->createPromotionLineItem(
            label: '',
            type: LineItem::PROMOTION_LINE_ITEM_TYPE,
            unitPrice: -5.0,
            total: -5.0,
            taxes: [new CalculatedTax(-0.35, 7.0, -5.0)],
            payload: [],
        );
        $promo->setReferencedId(null);

        $order = $this->createOrder(CartPrice::TAX_STATE_NET, deliveries: [], lineItems: [$promo]);

        $view = AllowanceChargeView::listFromOrder($order)[0];

        static::assertSame(AllowanceChargeReason::DISCOUNT->defaultLabel(), $view->reason);
    }

    public function testListFromOrderOrdersDeliveryBeforePromotion(): void
    {
        $order = $this->createOrder(
            CartPrice::TAX_STATE_NET,
            deliveries: [$this->createDelivery(10.0, [new CalculatedTax(0.7, 7.0, 10.0)])],
            lineItems: [
                $this->createPromotionLineItem(
                    'PROMO',
                    LineItem::PROMOTION_LINE_ITEM_TYPE,
                    -5.0,
                    -5.0,
                    [new CalculatedTax(-0.35, 7.0, -5.0)],
                    payload: [],
                ),
            ],
        );

        $views = AllowanceChargeView::listFromOrder($order);

        static::assertSame(AllowanceChargeReason::DELIVERY, $views[0]->reasonCode);
        static::assertSame(AllowanceChargeReason::DISCOUNT, $views[1]->reasonCode);
    }

    public function testListFromOrderSkipsRegularLineItems(): void
    {
        $product = new OrderLineItemEntity();
        $product->setUniqueIdentifier(Uuid::randomHex());
        $product->setIdentifier('p-1');
        $product->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        $product->setLabel('Widget');
        $product->setQuantity(1);
        $product->setPrice(new CalculatedPrice(
            100.0,
            100.0,
            new CalculatedTaxCollection([new CalculatedTax(7.0, 7.0, 100.0)]),
            new TaxRuleCollection(),
        ));

        $order = $this->createOrder(CartPrice::TAX_STATE_NET, deliveries: [], lineItems: [$product]);

        static::assertSame([], AllowanceChargeView::listFromOrder($order));
    }

    /**
     * @param list<OrderDeliveryEntity> $deliveries
     * @param list<OrderLineItemEntity> $lineItems
     */
    private function createOrder(string $taxState, array $deliveries, array $lineItems): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setPrice(new CartPrice(
            0.0,
            0.0,
            0.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            $taxState,
        ));
        $order->setDeliveries(new OrderDeliveryCollection($deliveries));
        $order->setLineItems(new OrderLineItemCollection($lineItems));

        return $order;
    }

    /**
     * @param list<CalculatedTax> $taxes
     */
    private function createDelivery(float $total, array $taxes): OrderDeliveryEntity
    {
        $delivery = new OrderDeliveryEntity();
        $delivery->setUniqueIdentifier(Uuid::randomHex());
        $delivery->setShippingCosts(new CalculatedPrice(
            $total,
            $total,
            new CalculatedTaxCollection($taxes),
            new TaxRuleCollection(),
        ));

        return $delivery;
    }

    /**
     * @param list<CalculatedTax> $taxes
     * @param array<string, mixed> $payload
     */
    private function createPromotionLineItem(
        string $label,
        string $type,
        float $unitPrice,
        float $total,
        array $taxes,
        array $payload,
    ): OrderLineItemEntity {
        $item = new OrderLineItemEntity();
        $item->setUniqueIdentifier(Uuid::randomHex());
        $item->setIdentifier($label);
        $item->setReferencedId($label);
        $item->setType($type);
        $item->setLabel($label);
        $item->setQuantity(1);
        $item->setPayload($payload);
        $item->setPrice(new CalculatedPrice(
            $unitPrice,
            $total,
            new CalculatedTaxCollection($taxes),
            new TaxRuleCollection(),
        ));

        return $item;
    }
}

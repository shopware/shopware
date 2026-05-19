<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Template\View;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\DocumentV2\Template\Calculation\NetAmount;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\AllowanceChargeReason;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TaxCategory;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\FloatComparator;

/**
 * Precomputed view of a single allowance-or-charge entry.
 *
 * One entry is emitted per (source × tax breakdown row); the source can be a promotion/credit
 * line item or an order delivery's shipping cost. Positive `isCharge` marks the entry as a
 * charge; everything else is treated as an allowance.
 *
 * Zero-amount rows are emitted to match v1 wire output.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class AllowanceChargeView
{
    public function __construct(
        public bool $isCharge,
        public float $actualAmount,
        public ?float $basisAmount,
        public ?float $calculationPercent,
        public AllowanceChargeReason $reasonCode,
        public string $reason,
        public TaxCategory $taxCategory,
        public float $taxRate,
    ) {
    }

    /**
     * @return list<self>
     */
    public static function listFromOrder(OrderEntity $order): array
    {
        $isGross = NetAmount::isOrderGross($order);

        return [
            ...self::fromDeliveryCosts($order, $isGross),
            ...self::fromPromotionLineItems($order, $isGross),
        ];
    }

    /**
     * @return list<self>
     */
    private static function fromDeliveryCosts(OrderEntity $order, bool $isGross): array
    {
        $views = [];

        foreach ($order->getDeliveries() ?? [] as $delivery) {
            $shippingCosts = $delivery->getShippingCosts();
            $taxes = $shippingCosts->getCalculatedTaxes();

            if ($taxes->count() === 0) {
                $views[] = new self(
                    isCharge: true,
                    actualAmount: abs(NetAmount::fromTax(null, $shippingCosts, $isGross)),
                    basisAmount: null,
                    calculationPercent: null,
                    reasonCode: AllowanceChargeReason::DELIVERY,
                    reason: AllowanceChargeReason::DELIVERY->defaultLabel(),
                    taxCategory: TaxCategory::ZERO_RATED,
                    taxRate: 0.0,
                );

                continue;
            }

            foreach ($taxes as $tax) {
                $actualAmount = abs(NetAmount::fromTax($tax, $shippingCosts, $isGross));

                $views[] = new self(
                    isCharge: true,
                    actualAmount: $actualAmount,
                    basisAmount: null,
                    calculationPercent: null,
                    reasonCode: AllowanceChargeReason::DELIVERY,
                    reason: AllowanceChargeReason::DELIVERY->defaultLabel(),
                    taxCategory: TaxCategory::fromRate($tax->getTaxRate()),
                    taxRate: $tax->getTaxRate(),
                );
            }
        }

        return $views;
    }

    /**
     * @return list<self>
     */
    private static function fromPromotionLineItems(OrderEntity $order, bool $isGross): array
    {
        $views = [];
        self::appendPromotionLineItems($order->getLineItems(), $isGross, $views);

        return $views;
    }

    /**
     * @param list<self> $views
     */
    private static function appendPromotionLineItems(
        ?OrderLineItemCollection $lineItems,
        bool $isGross,
        array &$views,
    ): void {
        foreach ($lineItems ?? [] as $lineItem) {
            self::appendPromotionLineItem($lineItem, $isGross, $views);
            self::appendPromotionLineItems($lineItem->getChildren(), $isGross, $views);
        }
    }

    /**
     * @param list<self> $views
     */
    private static function appendPromotionLineItem(
        OrderLineItemEntity $lineItem,
        bool $isGross,
        array &$views,
    ): void {
        if (!\in_array(
            $lineItem->getType(),
            [LineItem::PROMOTION_LINE_ITEM_TYPE, LineItem::CREDIT_LINE_ITEM_TYPE],
            true,
        )) {
            return;
        }

        $price = $lineItem->getPrice();

        if ($price === null) {
            return;
        }

        $payload = $lineItem->getPayload();

        $discountValue = (float) ($payload['value'] ?? 0);
        $maxValue = (float) ($payload['maxValue'] ?? 0);

        $isPercentage = ($payload['discountType'] ?? null) === PromotionDiscountEntity::TYPE_PERCENTAGE
            && !FloatComparator::equals(abs($price->getTotalPrice()), $maxValue);

        $isCharge = $price->getUnitPrice() >= 0;
        $taxes = $price->getCalculatedTaxes();

        if ($taxes->count() === 0) {
            $views[] = self::createPromotionView(
                $lineItem,
                $price,
                $isGross,
                $isCharge,
                $isPercentage,
                $discountValue,
            );

            return;
        }

        foreach ($taxes as $tax) {
            $absAmount = abs(NetAmount::fromTax($tax, $price, $isGross));

            $basisAmount = $isPercentage && $discountValue !== 0.0
                ? $absAmount * 100 / $discountValue
                : null;

            $views[] = new self(
                isCharge: $isCharge,
                actualAmount: $absAmount,
                basisAmount: $basisAmount,
                calculationPercent: $isPercentage ? $discountValue : null,
                reasonCode: AllowanceChargeReason::DISCOUNT,
                reason: $lineItem->getReferencedId() ?? ($lineItem->getLabel() ?: AllowanceChargeReason::DISCOUNT->defaultLabel()),
                taxCategory: TaxCategory::fromRate($tax->getTaxRate()),
                taxRate: $tax->getTaxRate(),
            );
        }
    }

    private static function createPromotionView(
        OrderLineItemEntity $lineItem,
        CalculatedPrice $price,
        bool $isGross,
        bool $isCharge,
        bool $isPercentage,
        float $discountValue,
    ): self {
        $absAmount = abs(NetAmount::fromTax(null, $price, $isGross));

        $basisAmount = $isPercentage && $discountValue !== 0.0
            ? $absAmount * 100 / $discountValue
            : null;

        return new self(
            isCharge: $isCharge,
            actualAmount: $absAmount,
            basisAmount: $basisAmount,
            calculationPercent: $isPercentage ? $discountValue : null,
            reasonCode: AllowanceChargeReason::DISCOUNT,
            reason: $lineItem->getReferencedId() ?? ($lineItem->getLabel() ?: AllowanceChargeReason::DISCOUNT->defaultLabel()),
            taxCategory: TaxCategory::ZERO_RATED,
            taxRate: 0.0,
        );
    }
}

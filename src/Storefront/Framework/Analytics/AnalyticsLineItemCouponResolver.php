<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Analytics;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * Resolves the promotion codes that discounted each product line item, which analytics reports as
 * the item level `coupon`. Google Analytics only defines the coupon on the items for `view_cart`,
 * while the checkout events also carry it on the event.
 *
 * A promotion line item lists the products it discounted in `payload.composition`, so no data has to
 * be loaded. Automatic promotions have no code and shipping discounts discount no product, so neither
 * is reported.
 *
 * @internal
 */
#[Package('discovery')]
class AnalyticsLineItemCouponResolver
{
    /**
     * @param iterable<LineItem|OrderLineItemEntity>|null $lineItems the top level line items of a
     *                                                               cart or an order, including the
     *                                                               discount line items
     *
     * @return array<string, list<string>> the codes per product line item id
     */
    public function resolve(?iterable $lineItems): array
    {
        if ($lineItems === null) {
            return [];
        }

        $lineItems = \is_array($lineItems) ? $lineItems : iterator_to_array($lineItems, false);

        $codesByCartId = [];
        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() !== LineItem::PROMOTION_LINE_ITEM_TYPE) {
                continue;
            }

            $payload = $lineItem->getPayload() ?? [];
            $code = $payload['code'] ?? null;

            if (!\is_string($code) || $code === '' || ($payload['discountScope'] ?? null) === PromotionDiscountEntity::SCOPE_DELIVERY) {
                continue;
            }

            foreach ($payload['composition'] ?? [] as $entry) {
                if (\is_array($entry) && \is_string($entry['id'] ?? null)) {
                    $codesByCartId[$entry['id']][$code] = $code;
                }
            }
        }

        $coupons = [];
        foreach ($lineItems as $lineItem) {
            $codes = $codesByCartId[$this->getCartLineItemId($lineItem)] ?? [];

            if ($codes !== [] && $this->isGood($lineItem)) {
                $coupons[$lineItem->getId()] = array_values($codes);
            }
        }

        return $coupons;
    }

    /**
     * A composition references the cart line item id, which an order line item keeps in `identifier`.
     */
    private function getCartLineItemId(LineItem|OrderLineItemEntity $lineItem): string
    {
        return $lineItem instanceof OrderLineItemEntity ? $lineItem->getIdentifier() : $lineItem->getId();
    }

    private function isGood(LineItem|OrderLineItemEntity $lineItem): bool
    {
        return $lineItem instanceof OrderLineItemEntity ? $lineItem->getGood() : $lineItem->isGood();
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Resolves the price and the discount an analytics integration has to report per product line item.
 *
 * Google Analytics expects `price` to be the unit price after the discount and `discount` to be the
 * discount per unit, and it does not subtract one from the other itself. Shopware keeps promotion
 * discounts in separate line items instead, so the discount has to be allocated back to the products
 * it was calculated from. Every discount line item carries that allocation in `payload.composition`.
 *
 * @internal
 */
#[Package('checkout')]
class AnalyticsLineItemPriceExtension extends AbstractExtension
{
    public function __construct(private readonly CashRounding $rounding)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('sw_analytics_line_item_prices', $this->getPrices(...)),
        ];
    }

    /**
     * Allocates the promotion discounts of a cart or an order to its product line items.
     *
     * The discount is allocated on the line total and only then divided by the quantity, because a
     * discount does not have to apply to every unit of a line item: graduation filters and the set
     * scopes discount part of a line, and a fixed unit price discounts nothing at all when the unit
     * is already cheaper. Dividing the aggregated discount by the line item quantity therefore stays
     * correct, while dividing per composition entry would not.
     *
     * @param iterable<LineItem|OrderLineItemEntity> $lineItems the top level line items, including
     *                                                          the discount line items
     *
     * @return array<string, array{price: float, discount: float}> keyed by line item id
     */
    public function getPrices(iterable $lineItems, SalesChannelContext $context): array
    {
        $lineItems = array_values(\is_array($lineItems) ? $lineItems : iterator_to_array($lineItems, false));

        $discounts = $this->collectDiscounts($lineItems);
        $rounding = $context->getItemRounding();

        $prices = [];

        foreach ($lineItems as $lineItem) {
            if (!$this->isGood($lineItem)) {
                continue;
            }

            $price = $lineItem->getPrice();
            $quantity = $lineItem->getQuantity();

            if ($price === null || $quantity < 1) {
                continue;
            }

            $total = $price->getTotalPrice();
            // a composition can never discount more than the line it was calculated from
            $discount = min($discounts[$this->getCartLineItemId($lineItem)] ?? 0.0, $total);

            $prices[$lineItem->getId()] = [
                'price' => $this->rounding->cashRound(($total - $discount) / $quantity, $rounding),
                'discount' => $this->rounding->cashRound($discount / $quantity, $rounding),
            ];
        }

        return $prices;
    }

    /**
     * @param list<LineItem|OrderLineItemEntity> $lineItems
     *
     * @return array<string, float> the discounted amount per cart line item id, as a positive value
     */
    private function collectDiscounts(array $lineItems): array
    {
        $discounts = [];

        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() !== LineItem::PROMOTION_LINE_ITEM_TYPE) {
                continue;
            }

            $payload = $lineItem->getPayload() ?? [];

            // shipping discounts reduce the delivery instead of the products, and never carry a
            // composition, but the reported shipping costs already account for them
            if (($payload['discountScope'] ?? null) === PromotionDiscountEntity::SCOPE_DELIVERY) {
                continue;
            }

            foreach ($payload['composition'] ?? [] as $entry) {
                if (!\is_array($entry) || !\is_string($entry['id'] ?? null) || !is_numeric($entry['discount'] ?? null)) {
                    continue;
                }

                $discounts[$entry['id']] = ($discounts[$entry['id']] ?? 0.0) + abs((float) $entry['discount']);
            }
        }

        return $discounts;
    }

    /**
     * A composition references the cart line item it was calculated from. An order line item keeps
     * that id in `identifier`, because its own id is the freshly generated primary key of the order
     * line item, so the finish page has to resolve compositions through `identifier`.
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

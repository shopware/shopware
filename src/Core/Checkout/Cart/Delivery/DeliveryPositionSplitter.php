<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Splits a delivery position by quantity into a part and a remainder, so one line item can be shipped in several deliveries.
 * Both keep the position's identifier, which is why they belong in different deliveries.
 * The remainder also keeps the position's extensions, such as the id of the order delivery position it was converted from, the part starts without.
 *
 * Each part carries a clone of the line item with its own quantity and price, split by the LineItemQuantitySplitter the same way promotions split line items.
 * The part prices may therefore differ from the price of the whole position by rounding.
 * Cart totals are calculated from the cart's line items and never read position prices.
 *
 * The part is served from the item's stock first: it gets the regular delivery date when the stock covers its quantity,
 * the remainder only when the stock covers the whole position, otherwise each gets the restock date.
 * Line items without a delivery time of their own use the given default, usually the one of the shipping method the parts are delivered with.
 *
 * @final
 */
#[Package('checkout')]
class DeliveryPositionSplitter
{
    /**
     * @internal
     */
    public function __construct(
        private readonly LineItemQuantitySplitter $lineItemQuantitySplitter,
    ) {
    }

    /**
     * @throws CartException
     *
     * @return array{DeliveryPosition, DeliveryPosition} the part with the given quantity and the remainder
     */
    public function split(DeliveryPosition $position, int $quantity, ?DeliveryTime $defaultDeliveryTime, SalesChannelContext $context): array
    {
        if ($quantity < 1 || $quantity >= $position->getQuantity()) {
            throw CartException::invalidDeliveryPositionSplitQuantity($position->getIdentifier(), $quantity, $position->getQuantity());
        }

        if ($position->getLineItem()->hasChildren()) {
            throw CartException::deliveryPositionNotSplittable($position->getIdentifier());
        }

        if (!$position->getLineItem()->isStackable()) {
            throw CartException::deliveryPositionNotStackable($position->getIdentifier());
        }

        $part = $this->createPart($position, $quantity, $quantity, $defaultDeliveryTime, $context);

        $remainder = $this->createPart($position, $position->getQuantity() - $quantity, $position->getQuantity(), $defaultDeliveryTime, $context);
        $remainder->setExtensions($position->getExtensions());

        return [$part, $remainder];
    }

    private function createPart(
        DeliveryPosition $position,
        int $quantity,
        int $requiredStock,
        ?DeliveryTime $defaultDeliveryTime,
        SalesChannelContext $context,
    ): DeliveryPosition {
        $lineItem = $this->lineItemQuantitySplitter->split($position->getLineItem(), $quantity, $context);
        $price = $lineItem->getPrice() ?? throw CartException::missingLineItemPrice($lineItem->getId());

        $deliveryDate = DeliveryBuilder::resolveDeliveryDate($lineItem, $requiredStock, $defaultDeliveryTime);

        return new DeliveryPosition(
            $position->getIdentifier(),
            $lineItem,
            $quantity,
            clone $price,
            $deliveryDate ?? $position->getDeliveryDate(),
        );
    }
}

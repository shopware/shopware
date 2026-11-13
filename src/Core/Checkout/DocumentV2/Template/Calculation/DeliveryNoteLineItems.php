<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Template\Calculation;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class DeliveryNoteLineItems
{
    /**
     * Returns the line items a delivery note of the given delivery lists, keyed by id, with the quantity it prints:
     * every top-level line item with a position in the delivery, together with all its children.
     * A line item is printed with its position quantity in the delivery, with 0 when another delivery ships it,
     * and with its own quantity when no delivery ships it, like a container.
     *
     * @return array<string, int>
     */
    public static function quantities(OrderEntity $order, OrderDeliveryEntity $orderDelivery): array
    {
        $lineItems = $order->getLineItems() ?? new OrderLineItemCollection();

        $deliveredQuantities = [];
        foreach ($orderDelivery->getPositions() ?? [] as $position) {
            $lineItemId = $position->getOrderLineItemId();
            $deliveredQuantities[$lineItemId] = ($deliveredQuantities[$lineItemId] ?? 0) + $position->getQuantity();
        }

        $shippedLineItemIds = [];
        foreach ($order->getDeliveries() ?? [] as $delivery) {
            foreach ($delivery->getPositions() ?? [] as $position) {
                $shippedLineItemIds[$position->getOrderLineItemId()] = true;
            }
        }

        $listedRootIds = [];
        foreach (array_keys($deliveredQuantities) as $lineItemId) {
            $listedRootIds[self::rootId($lineItems, $lineItemId)] = true;
        }

        $quantities = [];
        foreach ($lineItems as $lineItem) {
            $lineItemId = $lineItem->getId();

            if (!isset($listedRootIds[self::rootId($lineItems, $lineItemId)])) {
                continue;
            }

            $quantities[$lineItemId] = $deliveredQuantities[$lineItemId]
                ?? (isset($shippedLineItemIds[$lineItemId]) ? 0 : $lineItem->getQuantity());
        }

        return $quantities;
    }

    private static function rootId(OrderLineItemCollection $lineItems, string $lineItemId): string
    {
        while (($parentId = $lineItems->get($lineItemId)?->getParentId()) !== null) {
            $lineItemId = $parentId;
        }

        return $lineItemId;
    }
}

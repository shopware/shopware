<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Composition;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class DiscountCompositionBuilder
{
    /**
     * @param list<DiscountCompositionItem> $items
     *
     * @return array<array{id: string, quantity: int, discount: float}>
     */
    public function buildCompositionPayload(array $items): array
    {
        $payloadItems = [];

        foreach ($items as $item) {
            $payloadItems[] = [
                'id' => $item->getId(),
                'quantity' => $item->getQuantity(),
                'discount' => $item->getDiscountValue(),
            ];
        }

        return $payloadItems;
    }

    /**
     * If our discount price is greater than our actual cart price, we have to
     * adjust the calculated discount price.
     * Due to that, we also have to adjust our composition data to match the new target price.
     *
     * @param list<DiscountCompositionItem> $targetItems
     *
     * @return list<DiscountCompositionItem>
     */
    public function adjustCompositionItemValues(CalculatedPrice $targetPrice, array $targetItems): array
    {
        $compositionItems = [];

        foreach ($targetItems as $item) {
            $itemTotal = $item->getDiscountValue();

            $factor = 0.0;

            if ($targetPrice->getTotalPrice() > 0) {
                $factor = $itemTotal / $targetPrice->getTotalPrice();
            }

            $compositionItems[] = new DiscountCompositionItem(
                $item->getId(),
                $item->getQuantity(),
                abs($itemTotal) * $factor
            );
        }

        return $compositionItems;
    }

    /**
     * Iterates through all composition items and removes redundant
     * occurrences by merging items into single items and
     * aggregating their values.
     *
     * @param list<DiscountCompositionItem> $items
     *
     * @return list<DiscountCompositionItem>
     */
    public function aggregateCompositionItems(array $items): array
    {
        $aggregated = [];

        foreach ($items as $item) {
            if (!\array_key_exists($item->getId(), $aggregated)) {
                $aggregated[$item->getId()] = $item;
            } else {
                $existing = $aggregated[$item->getId()];

                $aggregated[$item->getId()] = new DiscountCompositionItem(
                    $item->getId(),
                    $existing->getQuantity() + $item->getQuantity(),
                    $existing->getDiscountValue() + $item->getDiscountValue()
                );
            }
        }

        return array_values($aggregated);
    }
}

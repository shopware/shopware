<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Collector;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;

class LineItemCollector
{
    /**
     * @var string
     */
    private $promotionLineItemPlaceholder;

    public function __construct(string $promotionLineItemType)
    {
        $this->promotionLineItemPlaceholder = $promotionLineItemType;
    }

    /**
     * @return array
     */
    public function getAllLineItemIDs(Cart $cart)
    {
        $eligibleItems = [];

        /** @var array $lineItems */
        $lineItems = $this->getNonPromotionLineItems($cart);

        /** @var LineItem $lineItem */
        foreach ($lineItems as $lineItem) {
            $eligibleItems[] = $lineItem->getKey();
        }

        return $eligibleItems;
    }

    /**
     * Gets all line items that are not of the provided type
     * These can be used to iterate through all "standard" items.
     */
    private function getNonPromotionLineItems(Cart $cart): array
    {
        $lineItems = array_filter(
            $cart->getLineItems()->getElements(),
            function (LineItem $lineItem) {
                return $lineItem->getType() !== $this->promotionLineItemPlaceholder;
            }
        );

        return $lineItems;
    }
}

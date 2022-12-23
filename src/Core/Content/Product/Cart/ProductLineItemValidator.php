<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @package checkout
 */
class ProductLineItemValidator implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $productLineItems = array_filter($cart->getLineItems()->getFlat(), static function (LineItem $lineItem) {
            return $lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE;
        });

        foreach ($productLineItems as $lineItem) {
            $productId = $lineItem->getReferencedId();
            if ($productId === null) {
                continue;
            }
            $totalQuantity = $this->getTotalQuantity($productId, $productLineItems);

            $quantityInformation = $lineItem->getQuantityInformation();
            if ($quantityInformation === null) {
                continue;
            }

            $minPurchase = $quantityInformation->getMinPurchase();
            $available = $quantityInformation->getMaxPurchase() ?? 0;
            $steps = $quantityInformation->getPurchaseSteps() ?? 1;

            if ($available >= $totalQuantity) {
                continue;
            }

            $maxAvailable = (int) (floor(($available - $minPurchase) / $steps) * $steps + $minPurchase);

            $cart->addErrors(
                new ProductStockReachedError($productId, (string) $lineItem->getLabel(), $maxAvailable, false),
            );
        }
    }

    /**
     * @param LineItem[] $productLineItems
     */
    private function getTotalQuantity(string $productId, array $productLineItems): int
    {
        $totalQuantity = 0;
        foreach ($productLineItems as $lineItem) {
            if ($lineItem->getReferencedId() === $productId) {
                $totalQuantity += $lineItem->getQuantity();
            }
        }

        return $totalQuantity;
    }
}

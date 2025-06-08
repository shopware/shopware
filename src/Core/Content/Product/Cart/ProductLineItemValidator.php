<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class ProductLineItemValidator implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $behavior = $cart->getBehavior();
        if ($behavior !== null && $behavior->hasPermission(ProductCartProcessor::SKIP_PRODUCT_STOCK_VALIDATION)) {
            return;
        }

        $productLineItems = array_filter($cart->getLineItems()->getFlat(), static fn (LineItem $lineItem) => $lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE);

        $quantities = [];
        $refs = [];
        foreach ($productLineItems as $lineItem) {
            $productId = $lineItem->getReferencedId();
            if ($productId === null) {
                continue;
            }

            $quantities[$productId] = $lineItem->getQuantity() + ($quantities[$productId] ?? 0);

            // only needed one time to check max quantity
            $refs[$productId] = $lineItem;
        }

        foreach ($quantities as $productId => $quantity) {
            $lineItem = $refs[$productId];
            $quantityInformation = $lineItem->getQuantityInformation();
            if ($quantityInformation === null) {
                continue;
            }

            $minPurchase = $quantityInformation->getMinPurchase();
            $available = $quantityInformation->getMaxPurchase() ?? 0;
            $steps = $quantityInformation->getPurchaseSteps() ?? 1;

            if ($available >= $quantity) {
                continue;
            }

            $maxAvailable = (int) (floor(($available - $minPurchase) / $steps) * $steps + $minPurchase);

            $cart->addErrors(
                new ProductStockReachedError($productId, (string) $lineItem->getLabel(), $maxAvailable, false),
            );
        }
    }
}

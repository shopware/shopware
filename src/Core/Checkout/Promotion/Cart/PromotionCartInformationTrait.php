<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;

trait PromotionCartInformationTrait
{
    /**
     * function checks if the Original Cart contains the lineItem.
     * if yes, an PromotionCartDeletedInformationError is set in the calculated cart
     */
    private function addDeleteNoticeToCart(Cart $original, Cart $calculated, LineItem $discountLineItem): void
    {
        if ($original->has($discountLineItem->getId())) {
            $error = new PromotionCartDeletedInformationError($discountLineItem);
            $calculated->addErrors($error);
        }
    }

    /**
     * function checks if the Original Cart contains the lineItem.
     * if not, an PromotionCartAddedInformationError is set in the calculated cart
     */
    private function addAddedNoticeToCart(Cart $original, Cart $calculated, LineItem $discountLineItem): void
    {
        if ($original->has($discountLineItem->getId())) {
            return;
        }
        $error = new PromotionCartAddedInformationError($discountLineItem);
        $calculated->addErrors($error);
    }
}

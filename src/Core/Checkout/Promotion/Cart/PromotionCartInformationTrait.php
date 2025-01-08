<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotEligibleError;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotFoundError;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
trait PromotionCartInformationTrait
{
    /**
     * Adds a new error to the cart if the promotion for
     * the provided code was not found as active and valid promotion.
     */
    private function addPromotionNotFoundError(string $code, Cart $cart): void
    {
        $cart->addErrors(new PromotionNotFoundError($code));
    }

    /**
     * Adds a new error to the cart if the promotion has been found
     * but somehow is not eligible for the current cart.
     */
    private function addPromotionNotEligibleError(string $name, Cart $cart): void
    {
        $cart->addErrors(new PromotionNotEligibleError($name));
    }

    /**
     * function checks if the Original Cart contains the lineItem.
     * if not, an PromotionCartAddedInformationError is set in the calculated cart
     */
    private function addPromotionAddedNotice(Cart $original, Cart $calculated, LineItem $discountLineItem): void
    {
        if ($original->has($discountLineItem->getId())) {
            return;
        }
        $error = new PromotionCartAddedInformationError($discountLineItem);
        $calculated->addErrors($error);
    }

    /**
     * function checks if the Original Cart contains the lineItem.
     * if yes, an PromotionCartDeletedInformationError is set in the calculated cart
     */
    private function addPromotionDeletedNotice(Cart $original, Cart $calculated, LineItem $discountLineItem): void
    {
        if ($original->has($discountLineItem->getId())) {
            $error = new PromotionCartDeletedInformationError($discountLineItem);
            $calculated->addErrors($error);
        }
    }
}

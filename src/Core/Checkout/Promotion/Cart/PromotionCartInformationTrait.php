<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotEligibleError;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotFoundError;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
trait PromotionCartInformationTrait
{
    /**
     * @deprecated tag:v6.8.0 - Use $cart->addErrors(new PromotionNotFoundError($code)) directly.
     */
    private function addPromotionNotFoundError(string $code, Cart $cart): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', '$cart->addErrors(new PromotionNotFoundError($code))')
        );

        $cart->addErrors(new PromotionNotFoundError($code));
    }

    /**
     * @deprecated tag:v6.8.0 - Use $cart->addErrors(new PromotionNotEligibleError($name)) directly.
     */
    private function addPromotionNotEligibleError(string $name, Cart $cart): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', '$cart->addErrors(new PromotionNotEligibleError($name))')
        );

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

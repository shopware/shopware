<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Error\IncompleteLineItemError;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemValidator implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            if ($lineItem->getLabel() === null) {
                $errors->add(new IncompleteLineItemError($lineItem->getId(), 'label'));
                $cart->getLineItems()->removeElement($lineItem);
            }

            if ($lineItem->getPrice() === null) {
                $errors->add(new IncompleteLineItemError($lineItem->getId(), 'price'));
                $cart->getLineItems()->removeElement($lineItem);
            }
        }
    }
}

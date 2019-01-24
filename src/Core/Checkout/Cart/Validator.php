<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Error\IncompleteLineItemError;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;

class Validator
{
    public function validate(Cart $cart): ErrorCollection
    {
        $errors = [];

        /** @var LineItem $lineItem */
        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            if ($lineItem->getLabel() === null) {
                $errors[] = new IncompleteLineItemError($lineItem->getKey(), 'label');
            }

            if ($lineItem->getPrice() === null) {
                $errors[] = new IncompleteLineItemError($lineItem->getKey(), 'price');
            }
        }

        return new ErrorCollection($errors);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\CheckoutContext;

class Validator
{
    public function validate(Cart $cart, CheckoutContext $context): ErrorCollection
    {
        return new ErrorCollection();
    }
}

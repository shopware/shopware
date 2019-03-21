<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\CheckoutContext;

interface CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errorCollection, CheckoutContext $checkoutContext): void;
}

<?php declare(strict_types=1);

namespace Shopware\Checkout\Cart\Cart;

use Shopware\Checkout\CustomerContext;
use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;

interface CartValidatorInterface
{
    public function isValid(CalculatedCart $cart, CustomerContext $context): bool;
}

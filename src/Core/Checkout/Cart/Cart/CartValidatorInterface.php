<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Cart;

use Shopware\Core\Checkout\CustomerContext;
use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;

interface CartValidatorInterface
{
    public function isValid(CalculatedCart $cart, CustomerContext $context): bool;
}

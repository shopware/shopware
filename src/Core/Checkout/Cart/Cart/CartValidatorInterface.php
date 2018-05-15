<?php declare(strict_types=1);

namespace Shopware\Checkout\Cart\Cart;

use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Context\Struct\StorefrontContext;

interface CartValidatorInterface
{
    public function isValid(CalculatedCart $cart, StorefrontContext $context): bool;
}

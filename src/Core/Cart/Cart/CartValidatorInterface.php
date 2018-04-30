<?php declare(strict_types=1);

namespace Shopware\Cart\Cart;

use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Context\Struct\StorefrontContext;

interface CartValidatorInterface
{
    public function isValid(CalculatedCart $cart, StorefrontContext $context): bool;
}

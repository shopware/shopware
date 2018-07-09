<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\CheckoutContext;

class Enrichment
{
    public function enrich(Cart $cart, CheckoutContext $context): Cart
    {
        return $cart;
    }
}

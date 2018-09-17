<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\ProductCollection;

interface ProductGatewayInterface
{
    public function get(array $ids, CheckoutContext $context): ProductCollection;
}

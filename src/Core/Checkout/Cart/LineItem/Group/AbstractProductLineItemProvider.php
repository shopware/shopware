<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
abstract class AbstractProductLineItemProvider
{
    abstract public function getDecorated(): AbstractProductLineItemProvider;

    abstract public function getProducts(Cart $cart): LineItemCollection;
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartRuleScope extends CheckoutRuleScope
{
    protected Cart $cart;

    public function __construct(Cart $cart, SalesChannelContext $context)
    {
        parent::__construct($context);
        $this->cart = $cart;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }
}

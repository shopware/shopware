<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('business-ops')]
class CartRuleScope extends CheckoutRuleScope
{
    public function __construct(
        protected Cart $cart,
        SalesChannelContext $context
    ) {
        parent::__construct($context);
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }
}

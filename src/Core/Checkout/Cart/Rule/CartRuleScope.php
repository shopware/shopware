<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\CheckoutRuleScope;

class CartRuleScope extends CheckoutRuleScope
{
    /**
     * @var Cart
     */
    protected $cart;

    public function __construct(Cart $cart, CheckoutContext $context)
    {
        parent::__construct($context);
        $this->cart = $cart;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }
}

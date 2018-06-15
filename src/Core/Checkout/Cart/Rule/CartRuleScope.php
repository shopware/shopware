<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\CheckoutRuleScope;

class CartRuleScope extends CheckoutRuleScope
{
    /**
     * @var CalculatedCart
     */
    protected $calculatedCart;

    public function __construct(CalculatedCart $calculatedCart, CheckoutContext $context)
    {
        parent::__construct($context);
        $this->calculatedCart = $calculatedCart;
    }

    public function getCalculatedCart(): CalculatedCart
    {
        return $this->calculatedCart;
    }
}

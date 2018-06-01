<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Rule\Specification\Scope;

use Shopware\Core\Checkout\CustomerContext;
use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;

class CartRuleScope extends RuleScope
{
    /**
     * @var CustomerContext
     */
    protected $context;

    /**
     * @var CalculatedCart
     */
    protected $calculatedCart;

    public function __construct(CalculatedCart $calculatedCart, CustomerContext $context)
    {
        $this->context = $context;
        $this->calculatedCart = $calculatedCart;
    }

    public function getCalculatedCart(): CalculatedCart
    {
        return $this->calculatedCart;
    }

    public function getContext(): CustomerContext
    {
        return $this->context;
    }
}

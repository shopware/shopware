<?php declare(strict_types=1);

namespace Shopware\Checkout\Rule\Specification\Scope;

use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;

class CartRuleScope extends RuleScope
{
    /**
     * @var StorefrontContext
     */
    protected $context;

    /**
     * @var CalculatedCart
     */
    protected $calculatedCart;

    public function __construct(CalculatedCart $calculatedCart, StorefrontContext $context)
    {
        $this->context = $context;
        $this->calculatedCart = $calculatedCart;
    }

    public function getCalculatedCart(): CalculatedCart
    {
        return $this->calculatedCart;
    }

    public function getContext(): StorefrontContext
    {
        return $this->context;
    }
}

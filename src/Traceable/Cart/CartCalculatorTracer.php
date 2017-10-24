<?php

namespace Shopware\Traceable\Cart;

use Shopware\Cart\Cart\CalculatedCart;
use Shopware\Cart\Cart\CartCalculator;
use Shopware\Cart\Cart\CartContainer;
use Shopware\Context\Struct\ShopContext;

class CartCalculatorTracer extends CartCalculator
{
    /**
     * @var CartCalculator
     */
    private $calculator;

    /**
     * @var TracedCartActions
     */
    private $actions;

    public function __construct(
        CartCalculator $calculator,
        TracedCartActions $actions
    ) {
        $this->calculator = $calculator;
        $this->actions = $actions;
    }

    public function calculate(CartContainer $cartContainer, ShopContext $context): CalculatedCart
    {
        $time = microtime(true);
        $cart = $this->calculator->calculate($cartContainer, $context);

        $required = microtime(true) - $time;
        $this->actions->calculationTime = $required;
        $this->actions->calculatedCart = $cart;
        $this->actions->cartContainer = $cartContainer;
        $this->actions->context = $context;

        return $cart;
    }
}
<?php declare(strict_types=1);

namespace Shopware\Profiling\Cart;

use Shopware\Checkout\CustomerContext;
use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Checkout\Cart\Cart\Struct\Cart;

class TracedCartActions
{
    /**
     * @var array
     */
    public $actions = [];

    /**
     * @var CalculatedCart
     */
    public $calculatedCart;

    /**
     * @var Cart
     */
    public $cart;

    /**
     * @var CustomerContext
     */
    public $context;

    /**
     * @var float
     */
    public $calculationTime;

    public function add(string $class, array $action)
    {
        $this->actions[$class][] = $action;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Cart;

use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\CheckoutContext;

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
     * @var CheckoutContext
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

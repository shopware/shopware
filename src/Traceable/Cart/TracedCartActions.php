<?php declare(strict_types=1);

namespace Shopware\Traceable\Cart;

use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\CartContainer;
use Shopware\Context\Struct\ShopContext;

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
     * @var CartContainer
     */
    public $cartContainer;

    /**
     * @var ShopContext
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

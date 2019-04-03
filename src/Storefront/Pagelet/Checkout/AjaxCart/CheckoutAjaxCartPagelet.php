<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Checkout\AjaxCart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Struct\Struct;

class CheckoutAjaxCartPagelet extends Struct
{
    /**
     * @var Cart
     */
    protected $cart;
    /**
     * @var CheckoutContext
     */
    private $context;

    public function __construct(Cart $cart, CheckoutContext $context)
    {
        $this->cart = $cart;
        $this->context = $context;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function setCart(Cart $cart): void
    {
        $this->cart = $cart;
    }

    public function getContext(): CheckoutContext
    {
        return $this->context;
    }

    public function setContext(CheckoutContext $context): CheckoutAjaxCartPagelet
    {
        $this->context = $context;

        return $this;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\DataCollector;

use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Profiling\Cart\TracedCartActions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class CartCollector extends DataCollector
{
    /**
     * @var TracedCartActions
     */
    private $cartActions;

    public function __construct(TracedCartActions $cartActions)
    {
        $this->cartActions = $cartActions;
    }

    public function reset()
    {
        $this->data = [];
    }

    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = [
            'actions' => $this->cartActions->actions,
            'cart' => $this->cartActions->cart,
            'calculatedCart' => $this->cartActions->calculatedCart,
            'context' => $this->cartActions->context,
        ];
    }

    public function getActions()
    {
        return $this->data['actions'];
    }

    public function getCart(): ?Cart
    {
        return $this->data['cart'];
    }

    public function getContext(): ?CheckoutContext
    {
        return $this->data['context'];
    }

    public function getCalculatedCart(): ?CalculatedCart
    {
        return $this->data['calculatedCart'];
    }

    public function getName()
    {
        return 'cart';
    }
}

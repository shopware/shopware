<?php declare(strict_types=1);

namespace Shopware\Traceable\DataCollector;

use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\CartContainer;
use Shopware\Context\Struct\ShopContext;
use Shopware\Traceable\Cart\TracedCartActions;
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
            'cartContainer' => $this->cartActions->cartContainer,
            'calculatedCart' => $this->cartActions->calculatedCart,
            'context' => $this->cartActions->context,
        ];
    }

    public function getActions()
    {
        return $this->data['actions'];
    }

    public function getCartContainer(): CartContainer
    {
        return $this->data['cartContainer'];
    }

    public function getContext(): ShopContext
    {
        return $this->data['context'];
    }

    public function getCalculatedCart(): CalculatedCart
    {
        return $this->data['calculatedCart'];
    }

    public function getName()
    {
        return 'cart';
    }
}

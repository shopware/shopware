<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\CartInfo;

use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CartInfoPageletLoader
{
    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    /**
     * @param CartInfoPageletRequest $request
     * @param CheckoutContext        $context
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return CartInfoPageletStruct
     */
    public function load(CartInfoPageletRequest $request, CheckoutContext $context): CartInfoPageletStruct
    {
        $page = new CartInfoPageletStruct();
        /*@todo check global ajax include config
        if (true) {
            $page->setDeferred(true);
            return $page;
        }
        */

        $cart = $this->cartService->getCart($context->getToken(), $context);

        $page->setCartQuantity($cart->getLineItems()->filterGoods()->count());
        $page->setCartAmount($cart->getPrice()->getTotalPrice());
        $page->setNotesQuantity(0);
        $page->setCustomerLoggedIn(false);

        return $page;
    }
}

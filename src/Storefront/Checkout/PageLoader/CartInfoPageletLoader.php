<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\PageLoader;

use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Checkout\Page\CartInfoPageletStruct;
use Shopware\Storefront\Framework\Page\PageRequest;
use Shopware\Storefront\Framework\PageLoader\PageLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CartInfoPageletLoader implements PageLoader
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
     * @param PageRequest     $request
     * @param CheckoutContext $context
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return CartInfoPageletStruct
     */
    public function load(PageRequest $request, CheckoutContext $context): CartInfoPageletStruct
    {
        $page = new CartInfoPageletStruct();
        $cart = $this->cartService->getCart($context->getToken(), $context);

        $page->setCartQuantity($cart->getLineItems()->filterGoods()->count());
        $page->setCartAmount($cart->getPrice()->getTotalPrice());
        $page->setNotesQuantity(0);
        $page->setUserLoggedIn(false);

        return $page;
    }
}

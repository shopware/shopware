<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\CartInfo;

use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CartInfoPageletLoader
{
    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(CartService $cartService, EventDispatcherInterface $eventDispatcher)
    {
        $this->cartService = $cartService;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    /**
     * @param InternalRequest $request
     * @param CheckoutContext $context
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return CartInfoPageletStruct
     */
    public function load(InternalRequest $request, CheckoutContext $context): CartInfoPageletStruct
    {
        $page = new CartInfoPageletStruct();

        $cart = $this->cartService->getCart($context->getToken(), $context);

        $page->setCartQuantity($cart->getLineItems()->filterGoods()->count());
        $page->setCartAmount($cart->getPrice()->getTotalPrice());
        $page->setNotesQuantity(0);
        $page->setCustomerLoggedIn(false);

        return $page;
    }
}

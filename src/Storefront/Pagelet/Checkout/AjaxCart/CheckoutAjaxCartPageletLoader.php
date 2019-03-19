<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Checkout\AjaxCart;

use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CheckoutAjaxCartPageletLoader implements PageLoaderInterface
{
    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(CartService $cartService, EventDispatcherInterface $eventDispatcher)
    {
        $this->cartService = $cartService;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     */
    public function load(InternalRequest $request, CheckoutContext $context): CheckoutAjaxCartPagelet
    {
        $page = new CheckoutAjaxCartPagelet(
            $this->cartService->getCart($context->getToken(), $context),
            $context
        );

        $this->eventDispatcher->dispatch(
            CheckoutAjaxCartPageletLoadedEvent::NAME,
            new CheckoutAjaxCartPageletLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}

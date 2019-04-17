<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Checkout\Info;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class CheckoutInfoPageletLoader implements PageLoaderInterface
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
    public function load(Request $request, SalesChannelContext $context): CheckoutInfoPagelet
    {
        $page = new CheckoutInfoPagelet(
            $this->cartService->getCart($context->getToken(), $context),
            $context
        );

        $this->eventDispatcher->dispatch(
            CheckoutInfoPageletLoadedEvent::NAME,
            new CheckoutInfoPageletLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CheckoutCartPageLoader implements PageLoaderInterface
{
    /**
     * @var PageWithHeaderLoader|PageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CartService
     */
    private $cartService;

    public function __construct(
        PageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->cartService = $cartService;
    }

    public function load(InternalRequest $request, SalesChannelContext $context)
    {
        $page = $this->genericLoader->load($request, $context);

        $page = CheckoutCartPage::createFrom($page);

        $page->setCart($this->cartService->getCart($context->getToken(), $context));

        $this->eventDispatcher->dispatch(
            CheckoutCartPageLoadedEvent::NAME,
            new CheckoutCartPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Offcanvas;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class OffcanvasCartPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var GenericPageLoader
     */
    private $genericLoader;

    /**
     * @var CartService
     */
    private $cartService;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService,
        GenericPageLoader $genericLoader
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->genericLoader = $genericLoader;
        $this->cartService = $cartService;
    }

    public function load(Request $request, SalesChannelContext $context): OffcanvasCartPage
    {
        $page = $this->genericLoader->load($request, $context);

        $page = OffcanvasCartPage::createFrom($page);

        $page->setCart(
            $this->cartService->getCart($context->getToken(), $context)
        );

        $this->eventDispatcher->dispatch(
            new OffcanvasCartPageLoadedEvent($page, $context, $request),
            OffcanvasCartPageLoadedEvent::NAME
        );

        return $page;
    }
}

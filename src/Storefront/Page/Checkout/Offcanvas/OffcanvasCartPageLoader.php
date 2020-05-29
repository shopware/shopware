<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Offcanvas;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class OffcanvasCartPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var GenericPageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var AbstractShippingMethodRoute
     */
    private $shippingMethodRoute;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService,
        GenericPageLoaderInterface $genericLoader,
        AbstractShippingMethodRoute $shippingMethodRoute
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->cartService = $cartService;
        $this->genericLoader = $genericLoader;
        $this->shippingMethodRoute = $shippingMethodRoute;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): OffcanvasCartPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = OffcanvasCartPage::createFrom($page);

        $page->setCart($this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext));

        $page->setShippingMethods($this->getShippingMethods($salesChannelContext));

        $this->eventDispatcher->dispatch(
            new OffcanvasCartPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    private function getShippingMethods(SalesChannelContext $salesChannelContext): ShippingMethodCollection
    {
        $request = new Request();
        $request->query->set('onlyAvailable', true);

        return $this->shippingMethodRoute->load($request, $salesChannelContext)->getShippingMethods();
    }
}

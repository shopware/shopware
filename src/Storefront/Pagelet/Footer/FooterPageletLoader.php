<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Footer;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\RouteRequest\PaymentMethodRouteRequestEvent;
use Shopware\Storefront\Event\RouteRequest\ShippingMethodRouteRequestEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a store-api route to get or put data.
 */
#[Package('framework')]
class FooterPageletLoader implements FooterPageletLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NavigationLoaderInterface $navigationLoader,
        private readonly AbstractPaymentMethodRoute $paymentMethodRoute,
        private readonly AbstractShippingMethodRoute $shippingMethodRoute,
    ) {
    }

    public function load(Request $request, SalesChannelContext $salesChannelContext): FooterPagelet
    {
        $footerId = $salesChannelContext->getSalesChannel()->getFooterCategoryId();

        $tree = null;
        if ($footerId) {
            $tree = $this->navigationLoader->load($footerId, $salesChannelContext, $footerId);
        }

        $pagelet = new FooterPagelet(
            $tree,
            $this->loadServiceMenu($salesChannelContext),
            $this->loadPaymentMethods($request, $salesChannelContext),
            $this->loadShippingMethods($request, $salesChannelContext),
        );

        $this->eventDispatcher->dispatch(
            new FooterPageletLoadedEvent($pagelet, $salesChannelContext, $request)
        );

        return $pagelet;
    }

    private function loadServiceMenu(SalesChannelContext $context): CategoryCollection
    {
        $serviceId = $context->getSalesChannel()->getServiceCategoryId();

        if ($serviceId === null) {
            return new CategoryCollection();
        }

        $navigation = $this->navigationLoader->load($serviceId, $context, $serviceId, 1);

        return new CategoryCollection(array_map(static fn (TreeItem $treeItem) => $treeItem->getCategory(), $navigation->getTree()));
    }

    private function loadPaymentMethods(Request $request, SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('footer-pagelet::payment-methods');

        $event = new PaymentMethodRouteRequestEvent($request, $request->duplicate(), $salesChannelContext, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->paymentMethodRoute
            ->load($event->getStoreApiRequest(), $salesChannelContext, $event->getCriteria())
            ->getPaymentMethods();
    }

    private function loadShippingMethods(Request $request, SalesChannelContext $salesChannelContext): ShippingMethodCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('footer-pagelet::shipping-methods');

        $event = new ShippingMethodRouteRequestEvent($request, $request->duplicate(), $salesChannelContext, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->shippingMethodRoute
            ->load($event->getStoreApiRequest(), $salesChannelContext, $event->getCriteria())
            ->getShippingMethods();
    }
}

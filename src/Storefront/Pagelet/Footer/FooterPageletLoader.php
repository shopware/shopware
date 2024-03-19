<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Footer;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\RouteRequest\PaymentMethodRouteRequestEvent;
use Shopware\Storefront\Event\RouteRequest\ShippingMethodRouteRequestEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a store-api route to get or put data.
 */
#[Package('storefront')]
class FooterPageletLoader implements FooterPageletLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NavigationLoaderInterface $navigationLoader,
        private readonly AbstractPaymentMethodRoute $paymentMethodRoute,
        private readonly AbstractShippingMethodRoute $shippingMethodRoute
    ) {
    }

    public function load(Request $request, SalesChannelContext $salesChannelContext): FooterPagelet
    {
        $footerId = $salesChannelContext->getSalesChannel()->getFooterCategoryId();

        $tree = null;
        if ($footerId) {
            $navigationId = $request->get('navigationId', $footerId);

            $tree = $this->navigationLoader->load($navigationId, $salesChannelContext, $footerId);
        }

        $footer = new FooterPagelet($tree);

        if (Feature::isActive('cache_rework')) {
            $footer->paymentMethods = $this->loadPaymentMethods(
                $request,
                $salesChannelContext
            );

            $footer->shippingMethods = $this->loadShippingMethods(
                $request,
                $salesChannelContext
            );
        }

        $this->eventDispatcher->dispatch(
            new FooterPageletLoadedEvent($footer, $salesChannelContext, $request)
        );

        return $footer;
    }

    private function loadShippingMethods(Request $request, SalesChannelContext $context): ShippingMethodCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('generic-page::shipping-methods');

        $event = new ShippingMethodRouteRequestEvent($request, new Request(), $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->shippingMethodRoute
            ->load($event->getStoreApiRequest(), $context, $event->getCriteria())
            ->getShippingMethods();
    }

    private function loadPaymentMethods(Request $request, SalesChannelContext $context): PaymentMethodCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('generic-page::payment-methods');

        $event = new PaymentMethodRouteRequestEvent($request, new Request(), $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->paymentMethodRoute
            ->load($event->getStoreApiRequest(), $context, $event->getCriteria())
            ->getPaymentMethods();
    }
}

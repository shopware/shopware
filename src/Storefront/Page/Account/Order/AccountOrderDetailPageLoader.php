<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('customer-order')]
class AccountOrderDetailPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractOrderRoute $orderRoute
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountOrderDetailPage
    {
        if (!$salesChannelContext->getCustomer()) {
            throw CartException::customerNotLoggedIn();
        }

        $orderId = (string) $request->get('id');

        if ($orderId === '') {
            throw RoutingException::missingRequestParameter('id');
        }

        $criteria = new Criteria([$orderId]);
        $criteria
            ->addAssociation('lineItems')
            ->addAssociation('orderCustomer')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('lineItems.cover');

        $criteria->getAssociation('transactions')
            ->addSorting(new FieldSorting('createdAt'));

        $apiRequest = new Request();

        $event = new OrderRouteRequestEvent($request, $apiRequest, $salesChannelContext, $criteria);
        $this->eventDispatcher->dispatch($event);

        $result = $this->orderRoute
            ->load($event->getStoreApiRequest(), $salesChannelContext, $criteria);

        $order = $result->getOrders()->first();

        if (!$order instanceof OrderEntity) {
            throw new NotFoundHttpException();
        }

        $page = AccountOrderDetailPage::createFrom($this->genericLoader->load($request, $salesChannelContext));
        $page->setLineItems($order->getNestedLineItems());
        $page->setOrder($order);

        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        $this->eventDispatcher->dispatch(
            new AccountOrderDetailPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }
}

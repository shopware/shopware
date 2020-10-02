<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponseStruct;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AccountOrderDetailPageLoader
{
    /**
     * @var GenericPageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AbstractOrderRoute
     */
    private $orderRoute;

    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        AbstractOrderRoute $orderRoute
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->orderRoute = $orderRoute;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountOrderDetailPage
    {
        if (!$salesChannelContext->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $orderId = (string) $request->get('id');

        if ($orderId === '') {
            throw new MissingRequestParameterException('id');
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

        /** @var OrderRouteResponseStruct $result */
        $result = $this->orderRoute
            ->load($event->getStoreApiRequest(), $salesChannelContext, $criteria)
            ->getObject();

        $order = $result->getOrders()->first();

        if (!$order instanceof OrderEntity) {
            throw new NotFoundHttpException();
        }

        $page = AccountOrderDetailPage::createFrom($this->genericLoader->load($request, $salesChannelContext));
        $page->setLineItems($order->getNestedLineItems());
        $page->setOrder($order);

        $this->eventDispatcher->dispatch(
            new AccountOrderDetailPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\OrderPaidException;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopware\Storefront\Event\RouteRequest\PaymentMethodRouteRequestEvent;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountEditOrderPageLoader
{
    private GenericPageLoaderInterface $genericLoader;

    private EventDispatcherInterface $eventDispatcher;

    private AbstractOrderRoute $orderRoute;

    private RequestCriteriaBuilder $requestCriteriaBuilder;

    private AbstractPaymentMethodRoute $paymentMethodRoute;

    private OrderConverter $orderConverter;

    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        AbstractOrderRoute $orderRoute,
        RequestCriteriaBuilder $requestCriteriaBuilder,
        AbstractPaymentMethodRoute $paymentMethodRoute,
        OrderConverter $orderConverter
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->orderRoute = $orderRoute;
        $this->requestCriteriaBuilder = $requestCriteriaBuilder;
        $this->paymentMethodRoute = $paymentMethodRoute;
        $this->orderConverter = $orderConverter;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountEditOrderPage
    {
        if (!$salesChannelContext->getCustomer() && $request->get('deepLinkCode', false) === false) {
            throw new CustomerNotLoggedInException();
        }

        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountEditOrderPage::createFrom($page);

        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        $orderRouteResponse = $this->getOrder($request, $salesChannelContext);

        $order = $orderRouteResponse->getOrders()->first();

        if ($this->isOrderPaid($order)) {
            throw new OrderPaidException($order->getId());
        }

        $page->setOrder($order);

        $isChangeable = $orderRouteResponse->getPaymentsChangeable()[$page->getOrder()->getId()] ?? true;
        $page->setPaymentChangeable($isChangeable);

        $page->setPaymentMethods($this->getPaymentMethods($salesChannelContext, $request, $order));

        $page->setDeepLinkCode($request->get('deepLinkCode'));

        $this->eventDispatcher->dispatch(
            new AccountEditOrderPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    private function getOrder(Request $request, SalesChannelContext $context): OrderRouteResponse
    {
        $criteria = $this->createCriteria($request, $context);
        $apiRequest = new Request();
        $apiRequest->query->set('checkPromotion', 'true');

        $event = new OrderRouteRequestEvent($request, $apiRequest, $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->orderRoute
            ->load($event->getStoreApiRequest(), $context, $criteria);
    }

    private function createCriteria(Request $request, SalesChannelContext $context): Criteria
    {
        if ($request->get('orderId')) {
            $criteria = new Criteria([$request->get('orderId')]);
        } else {
            $criteria = new Criteria([]);
        }
        $criteria->addAssociation('lineItems.cover')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('billingAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.country');

        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));

        if ($context->getCustomer() && $context->getCustomer()->getId()) {
            $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $context->getCustomer()->getId()));
        } elseif ($request->get('deepLinkCode')) {
            $criteria->addFilter(new EqualsFilter('deepLinkCode', $request->get('deepLinkCode')));
        } else {
            throw new CustomerNotLoggedInException();
        }

        return $criteria;
    }

    private function getPaymentMethods(SalesChannelContext $context, Request $request, OrderEntity $order): PaymentMethodCollection
    {
        $criteria = new Criteria([]);
        $criteria->addFilter(new EqualsFilter('afterOrderEnabled', true));

        $routeRequest = new Request();
        $routeRequest->query->replace($this->requestCriteriaBuilder->toArray($criteria));
        $routeRequest->query->set('onlyAvailable', '1');

        $event = new PaymentMethodRouteRequestEvent($request, $routeRequest, $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->paymentMethodRoute->load(
            $event->getStoreApiRequest(),
            $this->orderConverter->assembleSalesChannelContext($order, $context->getContext()),
            $event->getCriteria()
        )->getPaymentMethods();
    }

    private function isOrderPaid(OrderEntity $order): bool
    {
        $transactions = $order->getTransactions();

        if ($transactions === null) {
            return false;
        }

        $transaction = $transactions->last();
        if ($transaction === null) {
            return false;
        }

        $stateMachineState = $transaction->getStateMachineState();
        if ($stateMachineState === null) {
            return false;
        }

        return $stateMachineState->getTechnicalName() === OrderTransactionStates::STATE_PAID;
    }
}

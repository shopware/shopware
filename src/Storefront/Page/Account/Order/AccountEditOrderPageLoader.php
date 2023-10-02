<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopware\Storefront\Event\RouteRequest\PaymentMethodRouteRequestEvent;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('checkout')]
class AccountEditOrderPageLoader
{
    /**
     * @internal
     *
     * @deprecated tag:v6.7.0 - translator will be mandatory from 6.7
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractOrderRoute $orderRoute,
        private readonly RequestCriteriaBuilder $requestCriteriaBuilder,
        private readonly AbstractCheckoutGatewayRoute $checkoutGatewayRoute,
        private readonly OrderConverter $orderConverter,
        private readonly OrderService $orderService,
        private readonly ?AbstractTranslator $translator = null
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     * @throws OrderException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountEditOrderPage
    {
        if (!$salesChannelContext->getCustomer() && $request->get('deepLinkCode', false) === false) {
            throw CartException::customerNotLoggedIn();
        }

        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountEditOrderPage::createFrom($page);
        $this->setMetaInformation($page);

        $orderRouteResponse = $this->getOrder($request, $salesChannelContext);

        /** @var OrderEntity $order */
        $order = $orderRouteResponse->getOrders()->first();

        if ($this->isOrderPaid($order)) {
            throw OrderException::orderAlreadyPaid($order->getId());
        }

        $page->setOrder($order);
        $page->setPaymentChangeable($this->isPaymentChangeable($orderRouteResponse, $page));
        $page->setPaymentMethods($this->getPaymentMethods($salesChannelContext, $request, $order));
        $page->setDeepLinkCode($request->get('deepLinkCode'));

        $this->eventDispatcher->dispatch(
            new AccountEditOrderPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(AccountEditOrderPage $page): void
    {
        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        if ($this->translator !== null && $page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        if ($this->translator !== null) {
            $page->getMetaInformation()?->setMetaTitle(
                $this->translator->trans('account.completePaymentMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
            );
        }
    }

    private function getOrder(Request $request, SalesChannelContext $context): OrderRouteResponse
    {
        $criteria = $this->createCriteria($request, $context);
        $apiRequest = $request->duplicate();
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
            $criteria = new Criteria();
        }
        $criteria->addAssociation('lineItems.cover')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('billingAddress.salutation')
            ->addAssociation('billingAddress.country')
            ->addAssociation('billingAddress.countryState')
            ->addAssociation('deliveries.shippingOrderAddress.salutation')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.countryState')
            ->addAssociation('deliveries.stateMachineState')
            ->addAssociation('transactions.stateMachineState')
            ->addAssociation('stateMachineState');

        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));

        if ($context->getCustomer() && $context->getCustomer()->getId()) {
            $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $context->getCustomer()->getId()));
        } elseif ($request->get('deepLinkCode')) {
            $criteria->addFilter(new EqualsFilter('deepLinkCode', $request->get('deepLinkCode')));
        } else {
            throw CartException::customerNotLoggedIn();
        }

        return $criteria;
    }

    private function getPaymentMethods(SalesChannelContext $context, Request $request, OrderEntity $order): PaymentMethodCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('afterOrderEnabled', true));

        $routeRequest = $request->duplicate();
        $routeRequest->query->replace($this->requestCriteriaBuilder->toArray($criteria));

        if (!Feature::isActive('v6.7.0.0')) {
            /**
             * @deprecated tag:v6.7.0 - onlyAvailable is no longer set in query
             */
            $routeRequest->query->set('onlyAvailable', '1');
        }

        $event = new PaymentMethodRouteRequestEvent($request, $routeRequest, $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        $cart = $this->orderConverter->convertToCart($order, $context->getContext());

        $orderContext = $this->orderConverter->assembleSalesChannelContext($order, $context->getContext());
        $options = $this->checkoutGatewayRoute->load($event->getStoreApiRequest(), $cart, $orderContext);

        $paymentMethods = $options->getPaymentMethods();
        $paymentMethods->sortPaymentMethodsByPreference($context);

        return $paymentMethods;
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

    private function isPaymentChangeable(OrderRouteResponse $orderRouteResponse, AccountEditOrderPage $page): bool
    {
        $isChangeableByResponse = $orderRouteResponse->getPaymentsChangeable()[$page->getOrder()->getId()] ?? true;
        $isChangeableByTransactionState = $this->orderService->isPaymentChangeableByTransactionState($page->getOrder());

        return $isChangeableByResponse && $isChangeableByTransactionState;
    }
}

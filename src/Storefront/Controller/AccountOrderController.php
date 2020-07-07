<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractCancelOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractSetPaymentOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponseStruct;
use Shopware\Core\Checkout\Payment\Exception\PaymentProcessException;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractHandlePaymentMethodRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\RouteRequest\CancelOrderRouteRequestEvent;
use Shopware\Storefront\Event\RouteRequest\HandlePaymentMethodRouteRequestEvent;
use Shopware\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopware\Storefront\Event\RouteRequest\SetPaymentOrderRouteRequestEvent;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoader;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class AccountOrderController extends StorefrontController
{
    /**
     * @var AccountOrderPageLoader
     */
    private $orderPageLoader;

    /**
     * @var AbstractOrderRoute
     */
    private $orderRoute;

    /**
     * @var ContextSwitchRoute
     */
    private $contextSwitchRoute;

    /**
     * @var AccountEditOrderPageLoader
     */
    private $accountEditOrderPageLoader;

    /**
     * @var AbstractCancelOrderRoute
     */
    private $cancelOrderRoute;

    /**
     * @var AbstractSetPaymentOrderRoute
     */
    private $setPaymentOrderRoute;

    /**
     * @var AbstractHandlePaymentMethodRoute
     */
    private $handlePaymentMethodRoute;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        AccountOrderPageLoader $orderPageLoader,
        AbstractOrderRoute $orderRoute,
        AccountEditOrderPageLoader $accountEditOrderPageLoader,
        ContextSwitchRoute $contextSwitchRoute,
        AbstractCancelOrderRoute $cancelOrderRoute,
        AbstractSetPaymentOrderRoute $setPaymentOrderRoute,
        AbstractHandlePaymentMethodRoute $handlePaymentMethodRoute,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->orderPageLoader = $orderPageLoader;
        $this->orderRoute = $orderRoute;
        $this->contextSwitchRoute = $contextSwitchRoute;
        $this->accountEditOrderPageLoader = $accountEditOrderPageLoader;
        $this->cancelOrderRoute = $cancelOrderRoute;
        $this->setPaymentOrderRoute = $setPaymentOrderRoute;
        $this->handlePaymentMethodRoute = $handlePaymentMethodRoute;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @Route("/account/order", name="frontend.account.order.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function orderOverview(Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->orderPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/account/order-history/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/order/{deepLinkCode}", name="frontend.account.order.single.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function orderSingleOverview(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->orderPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/account/order-history/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/widgets/account/order/detail/{id}", name="widgets.account.order.detail", options={"seo"="false"}, methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function ajaxOrderDetail(Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

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

        $event = new OrderRouteRequestEvent($request, $apiRequest, $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        /** @var OrderRouteResponseStruct $result */
        $result = $this->orderRoute
            ->load($event->getStoreApiRequest(), $context, $criteria)
            ->getObject();

        $order = $result->getOrders()->first();

        if (!$order instanceof OrderEntity) {
            throw new NotFoundHttpException();
        }
        $lineItems = $order->getNestedLineItems();

        return $this->renderStorefront('@Storefront/storefront/page/account/order-history/order-detail-list.html.twig', ['orderDetails' => $lineItems, 'orderId' => $orderId]);
    }

    /**
     * @Route("/account/order/cancel", name="frontend.account.order.cancel", methods={"POST"})
     */
    public function cancelOrder(Request $request, SalesChannelContext $context): Response
    {
        $cancelOrderRequest = new Request();
        $cancelOrderRequest->request->set('orderId', $request->get('orderId'));
        $cancelOrderRequest->request->set('transition', 'cancel');

        $event = new CancelOrderRouteRequestEvent($request, $cancelOrderRequest, $context);
        $this->eventDispatcher->dispatch($event);

        $this->cancelOrderRoute->cancel($event->getStoreApiRequest(), $context);

        if ($context->getCustomer() && $context->getCustomer()->getGuest() === true) {
            return $this->redirectToRoute(
                'frontend.account.order.single.page',
                [
                    'deepLinkCode' => $request->get('deepLinkCode'),
                ]
            );
        }

        return $this->redirectToRoute('frontend.account.order.page');
    }

    /**
     * @Route("/account/order/edit/{orderId}", name="frontend.account.edit-order.page", methods={"GET"})
     */
    public function editOrder(string $orderId, Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn(true);

        $page = $this->accountEditOrderPageLoader->load($request, $context);

        if ($page->isPaymentChangeable() === false) {
            $this->addFlash('danger', $this->trans('account.editOrderPaymentNotChangeable'));
        }

        return $this->renderStorefront('@Storefront/storefront/page/account/order/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/order/payment/{orderId}", name="frontend.account.edit-order.change-payment-method", methods={"POST"})
     */
    public function orderChangePayment(string $orderId, Request $request, SalesChannelContext $context): Response
    {
        $this->contextSwitchRoute->switchContext(
            new RequestDataBag(
                [
                    SalesChannelContextService::PAYMENT_METHOD_ID => $request->get('paymentMethodId'),
                ]
            ),
            $context
        );

        return $this->redirectToRoute('frontend.account.edit-order.page', ['orderId' => $orderId]);
    }

    /**
     * @Route("/account/order/update/{orderId}", name="frontend.account.edit-order.update-order", methods={"POST"})
     */
    public function updateOrder(string $orderId, Request $request, SalesChannelContext $context): Response
    {
        $finishUrl = $this->generateUrl('frontend.checkout.finish.page', [
            'orderId' => $orderId,
            'changedPayment' => true,
        ]);

        $errorUrl = $this->generateUrl('frontend.checkout.finish.page', [
            'orderId' => $orderId,
            'changedPayment' => true,
            'paymentFailed' => true,
        ]);

        $setPaymentRequest = new Request();
        $setPaymentRequest->request->set('orderId', $orderId);
        $setPaymentRequest->request->set('paymentMethodId', $request->get('paymentMethodId'));

        $setPaymentOrderRouteRequestEvent = new SetPaymentOrderRouteRequestEvent($request, $setPaymentRequest, $context);
        $this->eventDispatcher->dispatch($setPaymentOrderRouteRequestEvent);

        $this->setPaymentOrderRoute->setPayment($setPaymentOrderRouteRequestEvent->getStoreApiRequest(), $context);

        $handlePaymentRequest = new Request();
        $handlePaymentRequest->request->set('orderId', $orderId);
        $handlePaymentRequest->request->set('finishUrl', $finishUrl);
        $handlePaymentRequest->request->set('errorUrl', $errorUrl);

        $handlePaymentMethodRouteRequestEvent = new HandlePaymentMethodRouteRequestEvent($request, $handlePaymentRequest, $context);
        $this->eventDispatcher->dispatch($handlePaymentMethodRouteRequestEvent);

        try {
            $routeResponse = $this->handlePaymentMethodRoute->load(
                $handlePaymentMethodRouteRequestEvent->getStoreApiRequest(),
                $context
            );
            $response = $routeResponse->getRedirectResponse();
        } catch (PaymentProcessException $paymentProcessException) {
            return $this->forwardToRoute(
                'frontend.checkout.finish.page',
                ['orderId' => $orderId, 'changedPayment' => true, 'paymentFailed' => true]
            );
        }

        return $response ?? $this->redirectToRoute(
            'frontend.checkout.finish.page',
            ['orderId' => $orderId, 'changedPayment' => true]
        );
    }
}

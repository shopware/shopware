<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractAccountCancelOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractAccountOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Payment\Exception\PaymentProcessException;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoader;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader;
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
     * @var AccountOrderRouteInterface
     */
    private $orderRoute;

    /**
     * @var RequestCriteriaBuilder
     */
    private $requestCriteriaBuilder;

    /**
     * @var ContextSwitchRoute
     */
    private $contextSwitchRoute;

    /**
     * @var AccountEditOrderPageLoader
     */
    private $accountEditOrderPageLoader;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var AbstractAccountCancelOrderRoute
     */
    private $cancelOrderRoute;

    public function __construct(
        AccountOrderPageLoader $orderPageLoader,
        AbstractAccountOrderRoute $orderRoute,
        RequestCriteriaBuilder $requestCriteriaBuilder,
        AccountEditOrderPageLoader $accountEditOrderPageLoader,
        ContextSwitchRoute $contextSwitchRoute,
        OrderService $orderService,
        PaymentService $paymentService,
        AbstractAccountCancelOrderRoute $cancelOrderRoute
    ) {
        $this->orderPageLoader = $orderPageLoader;
        $this->orderRoute = $orderRoute;
        $this->requestCriteriaBuilder = $requestCriteriaBuilder;
        $this->contextSwitchRoute = $contextSwitchRoute;
        $this->orderService = $orderService;
        $this->accountEditOrderPageLoader = $accountEditOrderPageLoader;
        $this->paymentService = $paymentService;
        $this->cancelOrderRoute = $cancelOrderRoute;
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

        $orderRequest = new Request();
        $orderRequest->query->replace($this->requestCriteriaBuilder->toArray($criteria));

        $order = $this->orderRoute->load($orderRequest, $context)->getOrders()->first();

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

        $this->cancelOrderRoute->load($cancelOrderRequest, $context);

        if ($context->getCustomer() && $context->getCustomer()->getGuest() === true) {
            return $this->redirectToRoute(
                'frontend.account.order.single.page',
                [
                    'deepLinkCode' => $request->get('deepLinkCode')
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

        return $this->renderStorefront('@Storefront/storefront/page/account/order/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/account/order/payment/{orderId}", name="frontend.account.edit-order.change-payment-method", methods={"POST"})
     */
    public function orderChangePayment(string $orderId, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $this->contextSwitchRoute->switchContext(
            new RequestDataBag(
                [
                    SalesChannelContextService::PAYMENT_METHOD_ID => $request->get('paymentMethodId'),
                ]
            ),
            $salesChannelContext
        );

        return $this->redirectToRoute('frontend.account.edit-order.page', ['orderId' => $orderId]);
    }

    /**
     * @Route("/account/order/update/{orderId}", name="frontend.account.edit-order.update-order", methods={"POST"})
     */
    public function updateOrder(string $orderId, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $finishUrl = $this->generateUrl('frontend.checkout.finish.page', [
            'orderId' => $orderId,
            'changedPayment' => true,
        ]);

        $this->orderService->setPaymentMethod($request->get('paymentMethodId'), $orderId, $salesChannelContext);

        try {
            $response = $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext, $finishUrl);
        } catch (PaymentProcessException $paymentProcessException) {
            $this->addFlash('danger', $this->trans('error.payment-error'));
            return $this->forwardToRoute('frontend.checkout.finish.page', ['orderId' => $orderId, 'changedPayment' => true, 'paymentFailed' => true]);
        }
        if ($response !== null) {
            return $response;
        }

        return $this->redirectToRoute('frontend.checkout.finish.page', ['orderId' => $orderId, 'changedPayment' => true]);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\Exception\GuestNotAuthenticatedException;
use Shopware\Core\Checkout\Order\Exception\WrongGuestCredentialsException;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractCancelOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractSetPaymentOrderRoute;
use Shopware\Core\Checkout\Payment\Exception\PaymentProcessException;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractHandlePaymentMethodRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\RouteRequest\CancelOrderRouteRequestEvent;
use Shopware\Storefront\Event\RouteRequest\HandlePaymentMethodRouteRequestEvent;
use Shopware\Storefront\Event\RouteRequest\SetPaymentOrderRouteRequestEvent;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoader;
use Shopware\Storefront\Page\Account\Order\AccountOrderDetailPageLoader;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
     * @var AbstractContextSwitchRoute
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

    /**
     * @var AccountOrderDetailPageLoader
     */
    private $orderDetailPageLoader;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SalesChannelContextServiceInterface
     */
    private $contextService;

    public function __construct(
        AccountOrderPageLoader $orderPageLoader,
        AccountEditOrderPageLoader $accountEditOrderPageLoader,
        AbstractContextSwitchRoute $contextSwitchRoute,
        AbstractCancelOrderRoute $cancelOrderRoute,
        AbstractSetPaymentOrderRoute $setPaymentOrderRoute,
        AbstractHandlePaymentMethodRoute $handlePaymentMethodRoute,
        EventDispatcherInterface $eventDispatcher,
        AccountOrderDetailPageLoader $orderDetailPageLoader,
        EntityRepositoryInterface $orderRepository,
        SalesChannelContextServiceInterface $contextService
    ) {
        $this->orderPageLoader = $orderPageLoader;
        $this->contextSwitchRoute = $contextSwitchRoute;
        $this->accountEditOrderPageLoader = $accountEditOrderPageLoader;
        $this->cancelOrderRoute = $cancelOrderRoute;
        $this->setPaymentOrderRoute = $setPaymentOrderRoute;
        $this->handlePaymentMethodRoute = $handlePaymentMethodRoute;
        $this->eventDispatcher = $eventDispatcher;
        $this->orderDetailPageLoader = $orderDetailPageLoader;
        $this->orderRepository = $orderRepository;
        $this->contextService = $contextService;
    }

    /**
     * @Since("6.0.0.0")
     * @LoginRequired()
     * @Route("/account/order", name="frontend.account.order.page", options={"seo"="false"}, methods={"GET", "POST"}, defaults={"XmlHttpRequest"=true})
     *
     * @throws CustomerNotLoggedInException
     */
    public function orderOverview(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->orderPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/account/order-history/index.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.2.0.0")
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
     * @Since("6.2.0.0")
     * @Route("/account/order/{deepLinkCode}", name="frontend.account.order.single.page", options={"seo"="false"}, methods={"GET", "POST"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function orderSingleOverview(Request $request, SalesChannelContext $context): Response
    {
        try {
            $page = $this->orderPageLoader->load($request, $context);
        } catch (GuestNotAuthenticatedException | WrongGuestCredentialsException $exception) {
            return $this->redirectToRoute(
                'frontend.account.guest.login.page',
                [
                    'redirectTo' => 'frontend.account.order.single.page',
                    'redirectParameters' => ['deepLinkCode' => $request->get('deepLinkCode')],
                    'loginError' => ($exception instanceof WrongGuestCredentialsException),
                ]
            );
        }

        return $this->renderStorefront('@Storefront/storefront/page/account/order-history/index.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.0.0.0")
     * @LoginRequired()
     * @Route("/widgets/account/order/detail/{id}", name="widgets.account.order.detail", options={"seo"="false"}, methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function ajaxOrderDetail(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->orderDetailPageLoader->load($request, $context);

        $response = $this->renderStorefront('@Storefront/storefront/page/account/order-history/order-detail-list.html.twig', [
            'orderDetails' => $page->getLineItems(),
            'orderId' => $page->getOrder()->getId(),
            'page' => $page,
        ]);

        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    /**
     * @Since("6.2.0.0")
     * @LoginRequired(allowGuest=true)
     * @Route("/account/order/edit/{orderId}", name="frontend.account.edit-order.page", methods={"GET"})
     */
    public function editOrder(string $orderId, Request $request, SalesChannelContext $context): Response
    {
        $order = $this->orderRepository
            ->search(new Criteria([$orderId]), $context->getContext())
            ->first();

        if ($order === null) {
            throw new OrderNotFoundException($orderId);
        }

        if ($context->getCurrency()->getId() !== $order->getCurrencyId()) {
            $this->contextSwitchRoute->switchContext(
                new RequestDataBag([SalesChannelContextService::CURRENCY_ID => $order->getCurrencyId()]),
                $context
            );

            return $this->redirectToRoute('frontend.account.edit-order.page', ['orderId' => $orderId]);
        }

        $page = $this->accountEditOrderPageLoader->load($request, $context);

        if ($page->isPaymentChangeable() === false) {
            $this->addFlash(self::DANGER, $this->trans('account.editOrderPaymentNotChangeable'));
        }

        $page->setErrorCode($request->get('error-code'));

        return $this->renderStorefront('@Storefront/storefront/page/account/order/index.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.2.0.0")
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
     * @Since("6.2.0.0")
     * @Route("/account/order/update/{orderId}", name="frontend.account.edit-order.update-order", methods={"POST"})
     */
    public function updateOrder(string $orderId, Request $request, SalesChannelContext $context): Response
    {
        $finishUrl = $this->generateUrl('frontend.checkout.finish.page', [
            'orderId' => $orderId,
            'changedPayment' => true,
        ]);

        $order = $this->orderRepository
            ->search(new Criteria([$orderId]), $context->getContext())
            ->first();

        if ($order === null) {
            throw new OrderNotFoundException($orderId);
        }

        if ($context->getCurrency()->getId() !== $order->getCurrencyId()) {
            $this->contextSwitchRoute->switchContext(
                new RequestDataBag([SalesChannelContextService::CURRENCY_ID => $order->getCurrencyId()]),
                $context
            );

            $context = $this->contextService->get(
                new SalesChannelContextServiceParameters(
                    $context->getSalesChannelId(),
                    $context->getToken(),
                    $context->getContext()->getLanguageId()
                )
            );
        }

        $errorUrl = $this->generateUrl('frontend.account.edit-order.page', ['orderId' => $orderId]);

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

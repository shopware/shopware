<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\InvalidCartException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Order\Exception\EmptyCartException;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\PaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\AffiliateTracking\AffiliateTrackingListener;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoader;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoader;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class CheckoutController extends StorefrontController
{
    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var CheckoutCartPageLoader
     */
    private $cartPageLoader;

    /**
     * @var CheckoutConfirmPageLoader
     */
    private $confirmPageLoader;

    /**
     * @var CheckoutFinishPageLoader
     */
    private $finishPageLoader;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var OffcanvasCartPageLoader
     */
    private $offcanvasCartPageLoader;

    /**
     * @var SystemConfigService
     */
    private $config;

    /**
     * @var AbstractLogoutRoute
     */
    private $logoutRoute;

    public function __construct(
        CartService $cartService,
        CheckoutCartPageLoader $cartPageLoader,
        CheckoutConfirmPageLoader $confirmPageLoader,
        CheckoutFinishPageLoader $finishPageLoader,
        OrderService $orderService,
        PaymentService $paymentService,
        OffcanvasCartPageLoader $offcanvasCartPageLoader,
        SystemConfigService $config,
        AbstractLogoutRoute $logoutRoute
    ) {
        $this->cartService = $cartService;
        $this->cartPageLoader = $cartPageLoader;
        $this->confirmPageLoader = $confirmPageLoader;
        $this->finishPageLoader = $finishPageLoader;
        $this->orderService = $orderService;
        $this->paymentService = $paymentService;
        $this->offcanvasCartPageLoader = $offcanvasCartPageLoader;
        $this->config = $config;
        $this->logoutRoute = $logoutRoute;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/checkout/cart", name="frontend.checkout.cart.page", options={"seo"="false"}, methods={"GET"})
     */
    public function cartPage(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->cartPageLoader->load($request, $context);

        $this->addCartErrors($page->getCart());

        return $this->renderStorefront('@Storefront/storefront/page/checkout/cart/index.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/checkout/confirm", name="frontend.checkout.confirm.page", options={"seo"="false"}, methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function confirmPage(Request $request, SalesChannelContext $context): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.checkout.register.page');
        }

        if ($this->cartService->getCart($context->getToken(), $context)->getLineItems()->count() === 0) {
            return $this->redirectToRoute('frontend.checkout.cart.page');
        }

        $page = $this->confirmPageLoader->load($request, $context);

        $this->addCartErrors($page->getCart());

        return $this->renderStorefront('@Storefront/storefront/page/checkout/confirm/index.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/checkout/finish", name="frontend.checkout.finish.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     * @throws MissingRequestParameterException
     * @throws OrderNotFoundException
     */
    public function finishPage(Request $request, SalesChannelContext $context, RequestDataBag $dataBag): Response
    {
        if ($context->getCustomer() === null) {
            return $this->redirectToRoute('frontend.checkout.register.page');
        }

        $page = $this->finishPageLoader->load($request, $context);

        if ($page->isPaymentFailed() === true) {
            return $this->redirectToRoute(
                'frontend.account.edit-order.page',
                [
                    'orderId' => $request->get('orderId'),
                    'error-code' => 'CHECKOUT__UNKNOWN_ERROR',
                ]
            );
        }

        if ($context->getCustomer()->getGuest() && $this->config->get('core.cart.logoutGuestAfterCheckout', $context->getSalesChannelId())) {
            $this->logoutRoute->logout($context, $dataBag);
        }

        return $this->renderStorefront('@Storefront/storefront/page/checkout/finish/index.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/checkout/order", name="frontend.checkout.finish.order", options={"seo"="false"}, methods={"POST"})
     */
    public function order(RequestDataBag $data, SalesChannelContext $context, Request $request): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.checkout.register.page');
        }

        try {
            $this->addAffiliateTracking($data, $request->getSession());

            $orderId = $this->orderService->createOrder($data, $context);
        } catch (ConstraintViolationException $formViolations) {
            return $this->forwardToRoute('frontend.checkout.confirm.page', ['formViolations' => $formViolations]);
        } catch (InvalidCartException | Error | EmptyCartException $error) {
            $this->addCartErrors(
                $this->cartService->getCart($context->getToken(), $context)
            );

            return $this->forwardToRoute('frontend.checkout.confirm.page');
        }

        try {
            $finishUrl = $this->generateUrl('frontend.checkout.finish.page', ['orderId' => $orderId]);
            $errorUrl = $this->generateUrl('frontend.account.edit-order.page', ['orderId' => $orderId]);

            $response = $this->paymentService->handlePaymentByOrder($orderId, $data, $context, $finishUrl, $errorUrl);

            return $response ?? new RedirectResponse($finishUrl);
        } catch (PaymentProcessException | InvalidOrderException | UnknownPaymentMethodException $e) {
            return $this->forwardToRoute('frontend.checkout.finish.page', ['orderId' => $orderId, 'changedPayment' => false, 'paymentFailed' => true]);
        }
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/widgets/checkout/info", name="frontend.checkout.info", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     *
     * @throws CartTokenNotFoundException
     */
    public function info(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->offcanvasCartPageLoader->load($request, $context);

        $response = $this->renderStorefront('@Storefront/storefront/layout/header/actions/cart-widget.html.twig', ['page' => $page]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/checkout/offcanvas", name="frontend.cart.offcanvas", options={"seo"="false"}, methods={"GET"}, defaults={"XmlHttpRequest"=true})
     *
     * @throws CartTokenNotFoundException
     */
    public function offcanvas(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->offcanvasCartPageLoader->load($request, $context);
        if ($request->cookies->get('sf_redirect') === null) {
            $cart = $page->getCart();
            $this->addCartErrors($cart);
            $cart->getErrors()->clear();
        }

        return $this->renderStorefront('@Storefront/storefront/component/checkout/offcanvas-cart.html.twig', ['page' => $page]);
    }

    private function addAffiliateTracking(RequestDataBag $dataBag, SessionInterface $session): void
    {
        $affiliateCode = $session->get(AffiliateTrackingListener::AFFILIATE_CODE_KEY);
        $campaignCode = $session->get(AffiliateTrackingListener::CAMPAIGN_CODE_KEY);
        if ($affiliateCode) {
            $dataBag->set(AffiliateTrackingListener::AFFILIATE_CODE_KEY, $affiliateCode);
        }

        if ($campaignCode) {
            $dataBag->set(AffiliateTrackingListener::CAMPAIGN_CODE_KEY, $campaignCode);
        }
    }
}

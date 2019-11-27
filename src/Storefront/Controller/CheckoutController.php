<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\SyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoader;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoader;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    public function __construct(
        CartService $cartService,
        CheckoutCartPageLoader $cartPageLoader,
        CheckoutConfirmPageLoader $confirmPageLoader,
        CheckoutFinishPageLoader $finishPageLoader,
        OrderService $orderService,
        PaymentService $paymentService,
        OffcanvasCartPageLoader $offcanvasCartPageLoader,
        EntityRepositoryInterface $orderRepository
    ) {
        $this->cartService = $cartService;
        $this->cartPageLoader = $cartPageLoader;
        $this->confirmPageLoader = $confirmPageLoader;
        $this->finishPageLoader = $finishPageLoader;
        $this->orderService = $orderService;
        $this->paymentService = $paymentService;
        $this->offcanvasCartPageLoader = $offcanvasCartPageLoader;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @Route("/checkout/cart", name="frontend.checkout.cart.page", options={"seo"="false"}, methods={"GET"})
     */
    public function cartPage(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->cartPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/checkout/cart/index.html.twig', ['page' => $page]);
    }

    /**
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

        return $this->renderStorefront('@Storefront/storefront/page/checkout/confirm/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/checkout/finish", name="frontend.checkout.finish.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     * @throws MissingRequestParameterException
     * @throws OrderNotFoundException
     */
    public function finishPage(Request $request, SalesChannelContext $context): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.checkout.register.page');
        }

        $page = $this->finishPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/checkout/finish/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/checkout/order", name="frontend.checkout.finish.order", options={"seo"="false"}, methods={"POST"})
     */
    public function order(RequestDataBag $data, SalesChannelContext $context, Request $request): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.checkout.register.page');
        }

        $formViolations = null;

        try {
            $orderId = $this->orderService->createOrder($data, $context);
            $this->addAffiliateTracking($orderId, $request, $context);
            $finishUrl = $this->generateUrl('frontend.checkout.finish.page', [
                'orderId' => $orderId,
            ]);

            $response = $this->paymentService->handlePaymentByOrder($orderId, $data, $context, $finishUrl);

            if ($response !== null) {
                return $response;
            }

            return new RedirectResponse($finishUrl);
        } catch (ConstraintViolationException $formViolations) {
        } catch (Error $blockedError) {
        } catch (AsyncPaymentProcessException | InvalidOrderException | SyncPaymentProcessException | UnknownPaymentMethodException $e) {
            // TODO: Handle errors which might occur during payment process
            throw $e;
        }

        return $this->forwardToRoute('frontend.checkout.confirm.page', ['formViolations' => $formViolations]);
    }

    /**
     * @Route("/widgets/checkout/info", name="frontend.checkout.info", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     *
     * @throws CartTokenNotFoundException
     */
    public function info(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->offcanvasCartPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/layout/header/actions/cart-widget.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/checkout/offcanvas", name="frontend.cart.offcanvas", options={"seo"="false"}, methods={"GET"}, defaults={"XmlHttpRequest"=true})
     *
     * @throws CartTokenNotFoundException
     */
    public function offcanvas(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->offcanvasCartPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/component/checkout/offcanvas-cart.html.twig', ['page' => $page]);
    }

    private function addAffiliateTracking(string $orderId, Request $request, SalesChannelContext $context): void
    {
        if ($request->getSession()->get('affiliateCode') && $request->getSession()->get('campaignCode')) {
            $this->orderRepository->update([[
                'id' => $orderId,
                'affiliateCode' => $request->getSession()->get('affiliateCode'),
                'campaignCode' => $request->getSession()->get('campaignCode'),
            ]], $context->getContext());
        }
    }
}

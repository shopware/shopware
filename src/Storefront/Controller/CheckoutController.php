<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Order\OrderStruct;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\TermQuery;
use Shopware\Core\Framework\Exception\InvalidParameterException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Storefront\Page\Checkout\PaymentMethodLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class CheckoutController extends StorefrontController
{
    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var RepositoryInterface
     */
    private $orderRepository;

    /**
     * @var PaymentMethodLoader
     */
    private $paymentMethodLoader;

    /**
     * @var CheckoutContextPersister
     */
    private $contextPersister;

    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var RouterInterface
     */
    private $router;

    public function __construct(
        CartService $cartService,
        RepositoryInterface $orderRepository,
        PaymentMethodLoader $paymentMethodLoader,
        CheckoutContextPersister $contextPersister,
        PaymentService $paymentService,
        RouterInterface $router
    ) {
        $this->cartService = $cartService;
        $this->orderRepository = $orderRepository;
        $this->paymentMethodLoader = $paymentMethodLoader;
        $this->contextPersister = $contextPersister;
        $this->paymentService = $paymentService;
        $this->router = $router;
    }

    /**
     * @Route("/checkout", name="checkout_index", options={"seo"="false"})
     */
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('checkout_cart');
    }

    /**
     * @Route("/checkout/cart", name="checkout_cart", options={"seo"="false"})
     */
    public function cart(CheckoutContext $context): Response
    {
        return $this->renderStorefront('@Storefront/frontend/checkout/cart.html.twig', [
            'cart' => $this->cartService->getCart($context),
        ]);
    }

    /**
     * @Route("/checkout/shippingPayment", name="checkout_shipping_payment", options={"seo"="false"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function shippingPayment(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        return $this->renderStorefront('@Storefront/frontend/checkout/shipping_payment.html.twig', [
            'paymentMethods' => $this->paymentMethodLoader->load($request, $context->getContext()),
        ]);
    }

    /**
     * @Route("/checkout/saveShippingPayment", name="checkout_save_shipping_payment", options={"seo"="false"}, methods={"POST"})
     *
     * @throws UnknownPaymentMethodException
     * @throws CustomerNotLoggedInException
     */
    public function saveShippingPayment(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $paymentMethodId = (string) $request->request->get('paymentMethodId', '');

        if (!Uuid::isValid($paymentMethodId)) {
            throw new UnknownPaymentMethodException($paymentMethodId);
        }

        $this->contextPersister->save(
            $context->getToken(),
            [CheckoutContextService::PAYMENT_METHOD_ID => $paymentMethodId],
            $context->getTenantId()
        );

        // todo validate, process and store custom template data
        return $this->redirectToRoute('checkout_confirm');
    }

    /**
     * @Route("/checkout/confirm", name="checkout_confirm", options={"seo"="false"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function confirm(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        if ($this->cartService->getCart($context)->getLineItems()->count() === 0) {
            return $this->redirectToRoute('checkout_cart');
        }

        return $this->renderStorefront('@Storefront/frontend/checkout/confirm.html.twig', [
            'cart' => $this->cartService->getCart($context),
            'redirectTo' => urlencode($request->getRequestUri()),
        ]);
    }

    /**
     * @Route("/checkout/order", name="checkout_order", options={"seo"="false"})
     */
    public function order(CheckoutContext $context): RedirectResponse
    {
        $cart = $this->cartService->getCart($context);
        // customer is not inside transaction loop and tries to finish the order
        if ($cart->getLineItems()->count() === 0) {
            return $this->redirectToRoute('checkout_cart');
        }

        // save order and start transaction loop
        $orderId = $this->cartService->order($context);

        return $this->redirectToRoute('checkout_pay', ['orderId' => $orderId]);
    }

    /**
     * @Route("/checkout/pay", name="checkout_pay", options={"seo"="false"})
     *
     * @throws InvalidOrderException
     * @throws UnknownPaymentMethodException
     * @throws CustomerNotLoggedInException
     */
    public function pay(Request $request, CheckoutContext $context): RedirectResponse
    {
        $this->denyAccessUnlessLoggedIn();

        $orderId = $request->query->get('orderId');
        $finishUrl = $this->router->generate('checkout_finish', ['orderId' => $orderId]);

        $response = $this->paymentService->handlePaymentByOrder($orderId, $context, $finishUrl);

        return $response ?? $this->redirectToRoute('checkout_finish', ['orderId' => $orderId]);
    }

    /**
     * @Route("/checkout/finish", name="checkout_finish", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     * @throws OrderNotFoundException
     * @throws InvalidParameterException
     */
    public function finish(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $orderId = $request->get('orderId');
        if (!Uuid::isValid($orderId)) {
            throw new InvalidParameterException('orderId');
        }
        $this->getOrder($orderId, $context);

        //todo@dr restore cart from order - NEXT-406 - can be realised with order recalculation/order converter

        return $this->renderStorefront('@Storefront/frontend/checkout/finish.html.twig', [
//            'cart' => $cart,
            'customer' => $context->getCustomer(),
        ]);
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws OrderNotFoundException
     */
    private function getOrder(string $orderId, CheckoutContext $context): OrderStruct
    {
        $customer = $context->getCustomer();
        if ($customer === null) {
            throw new CustomerNotLoggedInException();
        }

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('order.orderCustomer.customerId', $customer->getId()));
        $criteria->addFilter(new TermQuery('order.id', $orderId));

        $searchResult = $this->orderRepository->search($criteria, $context->getContext());

        if ($searchResult->count() !== 1) {
            throw new OrderNotFoundException($orderId);
        }

        return $searchResult->first();
    }
}

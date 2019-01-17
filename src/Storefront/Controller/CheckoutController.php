<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Exception\InvalidParameterException;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Pagelet\CheckoutPaymentMethod\CheckoutPaymentMethodPageletLoader;
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
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CheckoutPaymentMethodPageletLoader
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
        EntityRepositoryInterface $orderRepository,
        CheckoutPaymentMethodPageletLoader $paymentMethodLoader,
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
     * @Route("/checkout", name="frontend.checkout.forward", options={"seo"="false"}, methods={"GET"})
     */
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('frontend.checkout.cart.page');
    }

    /**
     * @Route("/checkout/cart", name="frontend.checkout.cart.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CartTokenNotFoundException
     */
    public function cart(CheckoutContext $context): Response
    {
        return $this->renderStorefront('@Storefront/frontend/checkout/cart.html.twig', [
            'cart' => $this->cartService->getCart($context->getToken(), $context),
        ]);
    }

    /**
     * @Route("/checkout/shipping-payment", name="frontend.checkout.shipping-payment.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function shippingPayment(InternalRequest $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();
        $page = $this->paymentMethodLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/checkout/shipping_payment.html.twig', [
                'page' => $page,
            ]
        );
    }

    /**
     * @Route("/checkout/shipping-payment", name="frontend.checkout.shipping-payment.update", options={"seo"="false"}, methods={"POST"})
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
            [CheckoutContextService::PAYMENT_METHOD_ID => $paymentMethodId]
        );

        return $this->redirectToRoute('frontend.checkout.confirm.page');
    }

    /**
     * @Route("/checkout/confirm", name="frontend.checkout.confirm.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     * @throws CartTokenNotFoundException
     */
    public function confirm(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        if ($this->cartService->getCart($context->getToken(), $context)->getLineItems()->count() === 0) {
            return $this->redirectToRoute('frontend.checkout.cart.page');
        }

        return $this->renderStorefront('@Storefront/frontend/checkout/confirm.html.twig', [
            'cart' => $this->cartService->getCart($context->getToken(), $context),
            'redirectTo' => urlencode($request->getRequestUri()),
        ]);
    }

    /**
     * @Route("/checkout/order", name="frontend.checkout.order", options={"seo"="false"}, methods={"POST"})
     *
     * @throws CartTokenNotFoundException
     */
    public function order(CheckoutContext $context): RedirectResponse
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);
        // customer is not inside transaction loop and tries to finish the order
        if ($cart->getLineItems()->count() === 0) {
            return $this->redirectToRoute('frontend.checkout.cart.page');
        }

        // save order and start transaction loop
        $orderId = $this->cartService->order($cart, $context);

        return $this->redirectToRoute('frontend.checkout.pay', ['orderId' => $orderId], Response::HTTP_TEMPORARY_REDIRECT);
    }

    /**
     * @Route("/checkout/pay", name="frontend.checkout.pay", options={"seo"="false"}, methods={"POST"})
     *
     * @throws InvalidOrderException
     * @throws UnknownPaymentMethodException
     * @throws CustomerNotLoggedInException
     */
    public function pay(Request $request, CheckoutContext $context): RedirectResponse
    {
        $this->denyAccessUnlessLoggedIn();

        $orderId = $request->query->get('orderId');
        $finishUrl = $this->router->generate('frontend.checkout.finish.page', ['orderId' => $orderId]);

        $response = $this->paymentService->handlePaymentByOrder($orderId, $context, $finishUrl);

        return $response ?? $this->redirectToRoute('frontend.checkout.finish.page', ['orderId' => $orderId]);
    }

    /**
     * @Route("/checkout/finish", name="frontend.checkout.finish.page", options={"seo"="false"}, methods={"GET"})
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

        return $this->renderStorefront('@Storefront/frontend/checkout/finish.html.twig', [
//            'cart' => $cart,
            'customer' => $context->getCustomer(),
        ]);
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws OrderNotFoundException
     */
    private function getOrder(string $orderId, CheckoutContext $context): OrderEntity
    {
        $customer = $context->getCustomer();
        if ($customer === null) {
            throw new CustomerNotLoggedInException();
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $customer->getId()));
        $criteria->addFilter(new EqualsFilter('order.id', $orderId));

        $searchResult = $this->orderRepository->search($criteria, $context->getContext());

        if ($searchResult->count() !== 1) {
            throw new OrderNotFoundException($orderId);
        }

        return $searchResult->first();
    }
}

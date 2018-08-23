<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Order\OrderStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionChainProcessor;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTransactionTokenFactory;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\InvalidTokenException;
use Shopware\Core\Checkout\Payment\Exception\InvalidTransactionException;
use Shopware\Core\Checkout\Payment\Exception\TokenExpiredException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentMethodStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Storefront\Page\Checkout\PaymentMethodLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
     * @var PaymentTransactionChainProcessor
     */
    private $paymentProcessor;

    /**
     * @var RepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var PaymentTransactionTokenFactory
     */
    private $tokenFactory;

    /**
     * @var CheckoutContextPersister
     */
    private $contextPersister;

    /**
     * @var PaymentHandlerRegistry
     */
    private $paymentHandlerRegistry;

    public function __construct(
        CartService $cartService,
        RepositoryInterface $orderRepository,
        PaymentMethodLoader $paymentMethodLoader,
        PaymentTransactionChainProcessor $paymentProcessor,
        PaymentTransactionTokenFactory $tokenFactory,
        RepositoryInterface $paymentMethodRepository,
        CheckoutContextPersister $contextPersister,
        PaymentHandlerRegistry $paymentHandlerRegistry
    ) {
        $this->cartService = $cartService;
        $this->orderRepository = $orderRepository;
        $this->paymentMethodLoader = $paymentMethodLoader;
        $this->paymentProcessor = $paymentProcessor;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->tokenFactory = $tokenFactory;
        $this->contextPersister = $contextPersister;
        $this->paymentHandlerRegistry = $paymentHandlerRegistry;
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
     */
    public function saveShippingPayment(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $paymentMethodId = (string) $request->request->get('paymentMethodId', '');

        if (!Uuid::isValid($paymentMethodId)) {
            throw new UnknownPaymentMethodException(sprintf('Unknown payment method with with id %s', $paymentMethodId));
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
     * @param Request         $request
     * @param CheckoutContext $context
     *
     * @return RedirectResponse|Response
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
     * @Route("/checkout/pay", name="checkout_pay", options={"seo"="false"})
     *
     * @param Request         $request
     * @param CheckoutContext $context
     *
     * @throws InvalidOrderException
     * @throws InvalidTransactionException
     * @throws UnknownPaymentMethodException
     *
     * @return RedirectResponse
     */
    public function pay(Request $request, CheckoutContext $context): RedirectResponse
    {
        $this->denyAccessUnlessLoggedIn();

        $applicationContext = $context->getContext();
        $transaction = $request->get('transaction');

        // check if customer is inside transaction loop
        if ($transaction && Uuid::isValid($transaction)) {
            $orderId = $this->getOrderIdByTransactionId($transaction, $context);

            return $this->processPayment($orderId, $applicationContext);
        }

        $cart = $this->cartService->getCart($context);
        // customer is not inside transaction loop and tries to finish the order
        if ($cart->getLineItems()->count() === 0) {
            return $this->redirectToRoute('checkout_cart');
        }

        // save order and start transaction loop
        $orderId = $this->cartService->order($context);

        return $this->processPayment($orderId, $applicationContext);
    }

    /**
     * @Route("/checkout/finalize-transaction", name="checkout_finalize_transaction", options={"seo"="false"})
     *
     * @param Request         $request
     * @param CheckoutContext $context
     *
     * @throws UnknownPaymentMethodException
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     * @throws InvalidTokenException
     * @throws TokenExpiredException
     *
     * @return RedirectResponse
     */
    public function finalizeTransaction(Request $request, CheckoutContext $context): RedirectResponse
    {
        $this->denyAccessUnlessLoggedIn();

        $paymentToken = $this->tokenFactory->validateToken(
            $request->get('_sw_payment_token'),
            $context->getContext()
        );

        $this->tokenFactory->invalidateToken(
            $paymentToken->getToken(),
            $context->getContext()
        );

        $paymentHandler = $this->getPaymentHandlerById($paymentToken->getPaymentMethodId(), $context->getContext());
        $paymentHandler->finalize($paymentToken->getTransactionId(), $request, $context->getContext());

        return $this->redirectToRoute('checkout_pay', ['transaction' => $paymentToken->getTransactionId()]);
    }

    /**
     * @Route("/checkout/finish", name="checkout_finish", options={"seo"="false"}, methods={"GET"})
     *
     * @throws \Exception
     */
    public function finish(Request $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $this->getOrder($request->get('order'), $context);

        //todo@dr restore cart from order - NEXT-406
//        $cart = $this->serializer->denormalize(json_decode($order->getPayload(), true), 'json');

        return $this->renderStorefront('@Storefront/frontend/checkout/finish.html.twig', [
//            'cart' => $cart,
            'customer' => $context->getCustomer(),
        ]);
    }

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

    /**
     * @throws InvalidTransactionException
     */
    private function getOrderIdByTransactionId(string $transactionId, CheckoutContext $context): string
    {
        $customer = $context->getCustomer();
        if ($customer === null) {
            throw new CustomerNotLoggedInException();
        }

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('order.orderCustomer.customerId', $customer->getId()));
        $criteria->addFilter(new TermQuery('order.transactions.id', $transactionId));

        $searchResult = $this->orderRepository->searchIds($criteria, $context->getContext());

        if ($searchResult->getTotal() !== 1) {
            throw new InvalidTransactionException($transactionId);
        }

        $ids = $searchResult->getIds();

        return array_shift($ids);
    }

    private function processPayment(string $orderId, Context $applicationContext): RedirectResponse
    {
        $redirect = $this->paymentProcessor->process($orderId, $applicationContext);

        if ($redirect) {
            return $redirect;
        }

        return $this->redirectToRoute('checkout_finish', ['order' => $orderId]);
    }

    private function getPaymentHandlerById(string $paymentMethodId, Context $context): PaymentHandlerInterface
    {
        $paymentMethods = $this->paymentMethodRepository->read(new ReadCriteria([$paymentMethodId]), $context);

        /** @var PaymentMethodStruct $paymentMethod */
        $paymentMethod = $paymentMethods->get($paymentMethodId);
        if (!$paymentMethod) {
            throw new UnknownPaymentMethodException($paymentMethodId);
        }

        return $this->paymentHandlerRegistry->get($paymentMethod->getClass());
    }
}

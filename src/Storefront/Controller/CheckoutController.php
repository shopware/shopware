<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Psr\Container\NotFoundExceptionInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\Query\TermQuery;
use Shopware\Checkout\Order\OrderRepository;
use Shopware\Checkout\Order\Struct\OrderBasicStruct;
use Shopware\Checkout\Payment\PaymentMethodRepository;
use Shopware\Checkout\Cart\StoreFrontCartService;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\Uuid;
use Shopware\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Checkout\Payment\Exception\InvalidTransactionException;
use Shopware\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface;
use Shopware\Checkout\Payment\Cart\PaymentTransactionChainProcessor;
use Shopware\Checkout\Payment\Cart\Token\PaymentTransactionTokenFactory;
use Shopware\Storefront\Page\Checkout\PaymentMethodLoader;
use Shopware\StorefrontApi\Context\StorefrontContextPersister;
use Shopware\StorefrontApi\Context\StorefrontContextService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;

class CheckoutController extends StorefrontController
{
    /**
     * @var \Shopware\Checkout\Cart\StoreFrontCartService
     */
    private $cartService;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var PaymentMethodLoader
     */
    private $paymentMethodLoader;

    /**
     * @var \Shopware\Checkout\Payment\Cart\PaymentTransactionChainProcessor
     */
    private $paymentProcessor;

    /**
     * @var \Shopware\Checkout\Payment\PaymentMethodRepository
     */
    private $paymentMethodRepository;

    /**
     * @var PaymentTransactionTokenFactory
     */
    private $tokenFactory;

    /**
     * @var StorefrontContextPersister
     */
    private $contextPersister;

    /**
     * @var Serializer
     */
    private $serializer;

    public function __construct(
        StoreFrontCartService $cartService,
        OrderRepository $orderRepository,
        PaymentMethodLoader $paymentMethodLoader,
        PaymentTransactionChainProcessor $paymentProcessor,
        PaymentTransactionTokenFactory $tokenFactory,
        PaymentMethodRepository $paymentMethodRepository,
        StorefrontContextPersister $contextPersister,
        Serializer $serializer
    ) {
        $this->cartService = $cartService;
        $this->orderRepository = $orderRepository;
        $this->paymentMethodLoader = $paymentMethodLoader;
        $this->paymentProcessor = $paymentProcessor;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->tokenFactory = $tokenFactory;
        $this->contextPersister = $contextPersister;
        $this->serializer = $serializer;
    }

    /**
     * @Route("/checkout", name="checkout_index", options={"seo"="false"})
     */
    public function index()
    {
        return $this->redirectToRoute('checkout_cart');
    }

    /**
     * @Route("/checkout/cart", name="checkout_cart", options={"seo"="false"})
     */
    public function cart(StorefrontContext $context): Response
    {
        return $this->renderStorefront('@Storefront/frontend/checkout/cart.html.twig', [
            'cart' => $this->cartService->getCalculatedCart($context),
        ]);
    }

    /**
     * @Route("/checkout/shippingPayment", name="checkout_shipping_payment", options={"seo"="false"})
     */
    public function shippingPayment(Request $request, StorefrontContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        return $this->renderStorefront('@Storefront/frontend/checkout/shipping_payment.html.twig', [
            'paymentMethods' => $this->paymentMethodLoader->load($request, $context->getApplicationContext()),
        ]);
    }

    /**
     * @Route("/checkout/saveShippingPayment", name="checkout_save_shipping_payment", options={"seo"="false"})
     * @Method({"POST"})
     *
     * @throws UnknownPaymentMethodException
     */
    public function saveShippingPayment(Request $request, StorefrontContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $paymentMethodId = (string) $request->request->get('paymentMethodId', '');

        if (!Uuid::isValid($paymentMethodId)) {
            throw new UnknownPaymentMethodException(sprintf('Unknown payment method with with id %s', $paymentMethodId));
        }

        $this->contextPersister->save(
            $context->getToken(),
            [StorefrontContextService::PAYMENT_METHOD_ID => $paymentMethodId],
            $context->getTenantId()
        );

        // todo validate, process and store custom template data
        return $this->redirectToRoute('checkout_confirm');
    }

    /**
     * @Route("/checkout/confirm", name="checkout_confirm", options={"seo"="false"})
     *
     * @param Request           $request
     * @param StorefrontContext $context
     *
     * @return RedirectResponse|Response
     */
    public function confirm(Request $request, StorefrontContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        if ($this->cartService->getCalculatedCart($context)->getCalculatedLineItems()->count() === 0) {
            return $this->redirectToRoute('checkout_cart');
        }

        return $this->renderStorefront('@Storefront/frontend/checkout/confirm.html.twig', [
            'cart' => $this->cartService->getCalculatedCart($context),
            'redirectTo' => urlencode($request->getRequestUri()),
        ]);
    }

    /**
     * @Route("/checkout/pay", name="checkout_pay", options={"seo"="false"})
     *
     * @param Request           $request
     * @param StorefrontContext $context
     *
     * @throws InvalidOrderException
     * @throws InvalidTransactionException
     * @throws UnknownPaymentMethodException
     *
     * @return RedirectResponse
     */
    public function pay(Request $request, StorefrontContext $context): RedirectResponse
    {
        $this->denyAccessUnlessLoggedIn();

        $applicationContext = $context->getApplicationContext();
        $transaction = $request->get('transaction');

        // check if customer is inside transaction loop
        if ($transaction && Uuid::isValid($transaction)) {
            $orderId = $this->getOrderIdByTransactionId($transaction, $context);

            return $this->processPayment($orderId, $applicationContext);
        }

        $cart = $this->cartService->getCalculatedCart($context);
        // customer is not inside transaction loop and tries to finish the order
        if ($cart->getCalculatedLineItems()->count() === 0) {
            return $this->redirectToRoute('checkout_cart');
        }

        // save order and start transaction loop
        $orderId = $this->cartService->order($context);

        return $this->processPayment($orderId, $applicationContext);
    }

    /**
     * @Route("/checkout/finalize-transaction", name="checkout_finalize_transaction", options={"seo"="false"})
     *
     * @param Request           $request
     * @param StorefrontContext $context
     *
     * @throws UnknownPaymentMethodException
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     * @throws \Shopware\Checkout\Payment\Exception\InvalidTokenException
     * @throws \Shopware\Checkout\Payment\Exception\TokenExpiredException
     *
     * @return RedirectResponse
     */
    public function finalizeTransaction(Request $request, StorefrontContext $context): RedirectResponse
    {
        $this->denyAccessUnlessLoggedIn();

        $paymentToken = $this->tokenFactory->validateToken(
            $request->get('_sw_payment_token'),
            $context->getApplicationContext()
        );

        $this->tokenFactory->invalidateToken(
            $paymentToken->getToken(),
            $context->getApplicationContext()
        );

        $paymentHandler = $this->getPaymentHandlerById($paymentToken->getPaymentMethodId(), $context->getApplicationContext());
        $paymentHandler->finalize($paymentToken->getTransactionId(), $request, $context->getApplicationContext());

        return $this->redirectToRoute('checkout_pay', ['transaction' => $paymentToken->getTransactionId()]);
    }

    /**
     * @Route("/checkout/finish", name="checkout_finish", options={"seo"="false"})
     * @Method({"GET"})
     *
     * @throws \Exception
     */
    public function finish(Request $request, StorefrontContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $order = $this->getOrder($request->get('order'), $context);

        $calculatedCart = $this->serializer->denormalize(json_decode($order->getPayload(), true), 'json');

        return $this->renderStorefront('@Storefront/frontend/checkout/finish.html.twig', [
            'cart' => $calculatedCart,
            'customer' => $context->getCustomer(),
        ]);
    }

    private function getOrder(string $orderId, StorefrontContext $context): OrderBasicStruct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('order.customer.id', $context->getCustomer()->getId()));
        $criteria->addFilter(new TermQuery('order.id', $orderId));

        $searchResult = $this->orderRepository->search($criteria, $context->getApplicationContext());

        if ($searchResult->count() !== 1) {
            throw new \Exception(sprintf('Unable to find order with id: %s', $orderId));
        }

        return $searchResult->first();
    }

    /**
     * @throws InvalidTransactionException
     */
    private function getOrderIdByTransactionId(string $transactionId, StorefrontContext $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('order.customer.id', $context->getCustomer()->getId()));
        $criteria->addFilter(new TermQuery('order.transactions.id', $transactionId));

        $searchResult = $this->orderRepository->searchIds($criteria, $context->getApplicationContext());

        if ($searchResult->getTotal() !== 1) {
            throw new InvalidTransactionException($transactionId);
        }

        $ids = $searchResult->getIds();

        return array_shift($ids);
    }

    private function processPayment(string $orderId, ApplicationContext $applicationContext): RedirectResponse
    {
        $redirect = $this->paymentProcessor->process($orderId, $applicationContext);

        if ($redirect) {
            return $redirect;
        }

        return $this->redirectToRoute('checkout_finish', ['order' => $orderId]);
    }

    private function getPaymentHandlerById(string $paymentMethodId, ApplicationContext $context): PaymentHandlerInterface
    {
        $paymentMethods = $this->paymentMethodRepository->readBasic([$paymentMethodId], $context);

        $paymentMethod = $paymentMethods->get($paymentMethodId);
        if (!$paymentMethod) {
            throw new UnknownPaymentMethodException($paymentMethodId);
        }

        try {
            return $this->container->get($paymentMethod->getClass());
        } catch (NotFoundExceptionInterface $e) {
            throw new UnknownPaymentMethodException($paymentMethod->getClass());
        }
    }
}

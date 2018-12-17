<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Storefront;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\Api\Response\Type\Storefront\JsonType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Storefront\Account\Page\AccountService;
use Shopware\Storefront\Account\Page\RegistrationRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;

class StorefrontCheckoutController extends AbstractController
{
    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var CheckoutContextPersister
     */
    private $contextPersister;

    /**
     * @var CheckoutContextFactory
     */
    private $checkoutContextFactory;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var RepositoryInterface
     */
    private $orderRepository;

    public function __construct(
        PaymentService $paymentService,
        CartService $cartService,
        CheckoutContextPersister $contextPersister,
        CheckoutContextFactory $checkoutContextFactory,
        AccountService $accountService,
        Serializer $serializer,
        RepositoryInterface $orderRepository
    ) {
        $this->paymentService = $paymentService;
        $this->cartService = $cartService;
        $this->contextPersister = $contextPersister;
        $this->checkoutContextFactory = $checkoutContextFactory;
        $this->accountService = $accountService;
        $this->orderRepository = $orderRepository;
        $this->serializer = $serializer;
    }

    /**
     * @Route("/storefront-api/checkout/order", name="storefront.api.checkout.order.deprecated", methods={"POST"})
     *
     * @deprecated
     */
    public function createOrderDeprecated(Request $request, CheckoutContext $context): JsonResponse
    {
        return $this->createOrder($request, $context);
    }

    /**
     * @Route("/storefront-api/v{version}/checkout/order", name="storefront-api.checkout.order.create", methods={"POST"})
     *
     * @throws OrderNotFoundException
     * @throws CartTokenNotFoundException
     */
    public function createOrder(Request $request, CheckoutContext $context): JsonResponse
    {
        $token = $request->request->getAlnum('token', $context->getToken());
        $cart = $this->cartService->getCart($token, $context);

        $orderId = $this->cartService->order($cart, $context);
        $order = $this->getOrderById($orderId, $context);

        $this->contextPersister->save($context->getToken(), ['cartToken' => null]);

        return new JsonResponse(
            $this->serialize($order)
        );
    }

    /**
     * @Route("/storefront-api/checkout/guest-order", name="storefront.api.checkout.guest-order.deprecated", methods={"POST"})
     *
     * @deprecated
     */
    public function createGuestOrderDeprecated(Request $request, CheckoutContext $context): JsonResponse
    {
        return $this->createGuestOrder($request, $context);
    }

    /**
     * @Route("/storefront-api/v{version}/checkout/guest-order", name="storefront-api.checkout.guest-order.create", methods={"POST"})
     *
     * @throws OrderNotFoundException
     * @throws CartTokenNotFoundException
     */
    public function createGuestOrder(Request $request, CheckoutContext $context): JsonResponse
    {
        $token = $request->request->getAlnum('token', $context->getToken());
        $request->request->remove('token');
        $registrationRequest = new RegistrationRequest();
        $registrationRequest->assign($request->request->all());
        $registrationRequest->setGuest(true);

        $customerId = $this->accountService->createNewCustomer($registrationRequest, $context);

        $orderContext = $this->createOrderContext($customerId, $context);

        $cart = $this->cartService->getCart($token, $orderContext);
        $orderId = $this->cartService->order($cart, $orderContext);
        $this->contextPersister->save($context->getToken(), ['cartToken' => null]);

        return new JsonResponse(
            $this->serialize($this->getOrderById($orderId, $context))
        );
    }

    /**
     * @Route("/storefront-api/checkout/guest-order/{id}", name="storefront.api.checkout.guest-order.deep-link.deprecated", methods={"GET"})
     *
     * @deprecated
     */
    public function getDeepLinkOrderDeprecated(string $id, Request $request, Context $context): Response
    {
        return $this->getDeepLinkOrder($id, $request, $context);
    }

    /**
     * @Route("/storefront-api/v{version}/checkout/guest-order/{id}", name="storefront-api.checkout.guest-order.detail", methods={"GET"})
     *
     * @throws OrderNotFoundException
     */
    public function getDeepLinkOrder(string $id, Request $request, Context $context): Response
    {
        $deepLinkCode = (string) $request->query->get('accessCode');
        $order = $this->cartService->getOrderByDeepLinkCode($id, $deepLinkCode, $context);

        return new JsonResponse(
            $this->serialize($order)
        );
    }

    /**
     * @Route("/storefront-api/checkout/pay/order/{orderId}", name="storefront.api.checkout.pay.order.deprecated", methods={"POST"})
     *
     * @deprecated use payOrder instead
     */
    public function payOrderDeprecated(string $orderId, Request $request, CheckoutContext $context): Response
    {
        return $this->payOrder($orderId, $request, $context);
    }

    /**
     * @Route("/storefront-api/v{version}/checkout/order/{orderId}/pay", name="storefront-api.checkout.order.pay", methods={"POST"})
     *
     * @throws InvalidOrderException
     * @throws UnknownPaymentMethodException
     */
    public function payOrder(string $orderId, Request $request, CheckoutContext $context): Response
    {
        $finishUrl = $request->request->get('finishUrl');
        $response = $this->paymentService->handlePaymentByOrder($orderId, $context, $finishUrl);

        if ($response) {
            return new JsonResponse(['paymentUrl' => $response->getTargetUrl()]);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws OrderNotFoundException
     */
    private function getOrderById(string $orderId, CheckoutContext $context): OrderEntity
    {
        $criteria = new ReadCriteria([$orderId]);
        $order = $this->orderRepository->read($criteria, $context->getContext())->get($orderId);

        if (!$order) {
            throw new OrderNotFoundException($orderId);
        }

        return $order;
    }

    private function createOrderContext(string $customerId, CheckoutContext $context): CheckoutContext
    {
        $orderContext = $this->checkoutContextFactory->create(
            $context->getToken(),
            $context->getSalesChannel()->getId(),
            [CheckoutContextService::CUSTOMER_ID => $customerId]
        );

        return $orderContext;
    }

    private function serialize($data): array
    {
        $decoded = $this->serializer->normalize($data);

        return [
            'data' => JsonType::format($decoded),
        ];
    }
}

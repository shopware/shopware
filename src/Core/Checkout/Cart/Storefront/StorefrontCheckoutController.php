<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Storefront;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Storefront\AccountRegistrationService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\SyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactoryInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
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
     * @var SalesChannelContextPersister
     */
    private $contextPersister;

    /**
     * @var SalesChannelContextFactoryInterface
     */
    private $checkoutContextFactory;

    /**
     * @var AccountRegistrationService
     */
    private $accountRegisterService;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    public function __construct(
        PaymentService $paymentService,
        CartService $cartService,
        SalesChannelContextPersister $contextPersister,
        SalesChannelContextFactoryInterface $checkoutContextFactory,
        Serializer $serializer,
        EntityRepositoryInterface $orderRepository,
        AccountRegistrationService $accountRegisterService
    ) {
        $this->paymentService = $paymentService;
        $this->cartService = $cartService;
        $this->contextPersister = $contextPersister;
        $this->checkoutContextFactory = $checkoutContextFactory;
        $this->orderRepository = $orderRepository;
        $this->serializer = $serializer;
        $this->accountRegisterService = $accountRegisterService;
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

        return new JsonResponse($this->serialize($order));
    }

    /**
     * @Route("/storefront-api/v{version}/checkout/guest-order", name="storefront-api.checkout.guest-order.create", methods={"POST"})
     *
     * @throws OrderNotFoundException
     * @throws CartTokenNotFoundException
     */
    public function createGuestOrder(Request $request, RequestDataBag $data, CheckoutContext $context): JsonResponse
    {
        $token = $request->request->getAlnum('token', $context->getToken());
        $request->request->remove('token');

        $customerId = $this->accountRegisterService->register($data, true, $context);

        $orderContext = $this->createCheckoutContext($customerId, $context);

        $cart = $this->cartService->getCart($token, $orderContext);
        $orderId = $this->cartService->order($cart, $orderContext);
        $this->contextPersister->save($context->getToken(), ['cartToken' => null]);

        return new JsonResponse($this->serialize($this->getOrderById($orderId, $context)));
    }

    /**
     * @Route("/storefront-api/v{version}/checkout/guest-order/{id}", name="storefront-api.checkout.guest-order.detail", methods={"GET"})
     *
     * @throws OrderNotFoundException
     */
    public function getDeepLinkOrder(string $id, Request $request, Context $context): JsonResponse
    {
        $deepLinkCode = (string) $request->query->get('accessCode');

        if ($id === '' || \strlen($deepLinkCode) !== 32) {
            throw new OrderNotFoundException($id);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $id));
        $criteria->addFilter(new EqualsFilter('deepLinkCode', $deepLinkCode));

        $orders = $this->orderRepository->search($criteria, $context);
        if ($orders->getTotal() === 0) {
            throw new OrderNotFoundException($id);
        }

        return new JsonResponse($this->serialize($orders->first()));
    }

    /**
     * @Route("/storefront-api/v{version}/checkout/order/{orderId}/pay", name="storefront-api.checkout.order.pay", methods={"POST"})
     *
     * @throws AsyncPaymentProcessException
     * @throws InvalidOrderException
     * @throws SyncPaymentProcessException
     * @throws UnknownPaymentMethodException
     */
    public function payOrder(string $orderId, Request $request, CheckoutContext $context): Response
    {
        $finishUrl = $request->request->get('finishUrl');
        $response = $this->paymentService->handlePaymentByOrder($orderId, $context, $finishUrl);

        if ($response) {
            return new JsonResponse(['paymentUrl' => $response->getTargetUrl()]);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws OrderNotFoundException
     */
    private function getOrderById(string $orderId, CheckoutContext $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $order = $this->orderRepository->search($criteria, $context->getContext())->get($orderId);

        if ($order === null) {
            throw new OrderNotFoundException($orderId);
        }

        return $order;
    }

    private function createCheckoutContext(string $customerId, CheckoutContext $context): CheckoutContext
    {
        $checkoutContext = $this->checkoutContextFactory->create(
            $context->getToken(),
            $context->getSalesChannel()->getId(),
            [SalesChannelContextService::CUSTOMER_ID => $customerId]
        );

        // todo: load matching rules
        $checkoutContext->setRuleIds($context->getRuleIds());

        return $checkoutContext;
    }

    private function serialize($data): array
    {
        $decoded = $this->serializer->normalize($data);

        return [
            'data' => $decoded,
        ];
    }
}

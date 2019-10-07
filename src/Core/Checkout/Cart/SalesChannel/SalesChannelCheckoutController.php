<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountRegistrationService;
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
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;

/**
 * @RouteScope(scopes={"sales-channel-api"})
 */
class SalesChannelCheckoutController extends AbstractController
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
     * @var SalesChannelContextFactory
     */
    private $salesChannelContextFactory;

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
        SalesChannelContextFactory $salesChannelContextFactory,
        Serializer $serializer,
        EntityRepositoryInterface $orderRepository,
        AccountRegistrationService $accountRegisterService
    ) {
        $this->paymentService = $paymentService;
        $this->cartService = $cartService;
        $this->contextPersister = $contextPersister;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->orderRepository = $orderRepository;
        $this->serializer = $serializer;
        $this->accountRegisterService = $accountRegisterService;
    }

    /**
     * @Route("/sales-channel-api/v{version}/checkout/order", name="sales-channel-api.checkout.order.create", methods={"POST"})
     *
     * @throws OrderNotFoundException
     * @throws CartTokenNotFoundException
     */
    public function createOrder(Request $request, SalesChannelContext $context): JsonResponse
    {
        $token = $request->request->getAlnum('token', $context->getToken());
        $cart = $this->cartService->getCart($token, $context);

        $orderId = $this->cartService->order($cart, $context);
        $order = $this->getOrderById($orderId, $context);

        $this->contextPersister->save($context->getToken(), ['cartToken' => null]);

        return new JsonResponse($this->serialize($order));
    }

    /**
     * @Route("/sales-channel-api/v{version}/checkout/guest-order", name="sales-channel-api.checkout.guest-order.create", methods={"POST"})
     *
     * @throws OrderNotFoundException
     * @throws CartTokenNotFoundException
     */
    public function createGuestOrder(Request $request, RequestDataBag $data, SalesChannelContext $context): JsonResponse
    {
        $token = $request->request->getAlnum('token', $context->getToken());
        $request->request->remove('token');

        $customerId = $this->accountRegisterService->register($data, true, $context);

        $salesChannelContext = $this->createSalesChannelContext($customerId, $context);

        $cart = $this->cartService->getCart($token, $salesChannelContext);
        $orderId = $this->cartService->order($cart, $salesChannelContext);
        $this->contextPersister->save($context->getToken(), ['cartToken' => null]);

        return new JsonResponse($this->serialize($this->getOrderById($orderId, $context)));
    }

    /**
     * @Route("/sales-channel-api/v{version}/checkout/guest-order/{id}", name="sales-channel-api.checkout.guest-order.detail", methods={"GET"})
     *
     * @throws OrderNotFoundException
     */
    public function getDeepLinkOrder(string $id, Request $request, Context $context): JsonResponse
    {
        $deepLinkCode = (string) $request->query->get('accessCode');

        if ($id === '' || \mb_strlen($deepLinkCode) !== 32) {
            throw new OrderNotFoundException($id);
        }

        $criteria = new Criteria();
        $criteria->addAssociation('addresses');
        $criteria->addFilter(new EqualsFilter('id', $id));
        $criteria->addFilter(new EqualsFilter('deepLinkCode', $deepLinkCode));

        $orders = $this->orderRepository->search($criteria, $context);
        if ($orders->getTotal() === 0) {
            throw new OrderNotFoundException($id);
        }

        return new JsonResponse($this->serialize($orders->first()));
    }

    /**
     * @Route("/sales-channel-api/v{version}/checkout/order/{orderId}/pay", name="sales-channel-api.checkout.order.pay", methods={"POST"})
     *
     * @throws AsyncPaymentProcessException
     * @throws InvalidOrderException
     * @throws SyncPaymentProcessException
     * @throws UnknownPaymentMethodException
     */
    public function payOrder(string $orderId, RequestDataBag $dataBag, SalesChannelContext $context): Response
    {
        $finishUrl = $dataBag->get('finishUrl');
        $response = $this->paymentService->handlePaymentByOrder($orderId, $dataBag, $context, $finishUrl);

        if ($response) {
            return new JsonResponse(['paymentUrl' => $response->getTargetUrl()]);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws OrderNotFoundException
     */
    private function getOrderById(string $orderId, SalesChannelContext $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('addresses.country');

        $order = $this->orderRepository->search($criteria, $context->getContext())->get($orderId);

        if ($order === null) {
            throw new OrderNotFoundException($orderId);
        }

        return $order;
    }

    private function createSalesChannelContext(string $customerId, SalesChannelContext $context): SalesChannelContext
    {
        $salesChannelContext = $this->salesChannelContextFactory->create(
            $context->getToken(),
            $context->getSalesChannel()->getId(),
            [SalesChannelContextService::CUSTOMER_ID => $customerId]
        );

        // todo: load matching rules
        $salesChannelContext->setRuleIds($context->getRuleIds());

        return $salesChannelContext;
    }

    private function serialize($data): array
    {
        $decoded = $this->serializer->normalize($data);

        return [
            'data' => $decoded,
        ];
    }
}

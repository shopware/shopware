<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountRegistrationService;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\SyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
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
     * @var SalesChannelContextFactory
     */
    private $salesChannelContextFactory;

    /**
     * @var AccountRegistrationService
     */
    private $accountRegistrationService;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var ApiVersionConverter
     */
    private $apiVersionConverter;

    public function __construct(
        PaymentService $paymentService,
        CartService $cartService,
        SalesChannelContextFactory $salesChannelContextFactory,
        Serializer $serializer,
        EntityRepositoryInterface $orderRepository,
        AccountRegistrationService $accountRegistrationService,
        AccountService $accountService,
        ApiVersionConverter $apiVersionConverter
    ) {
        $this->paymentService = $paymentService;
        $this->cartService = $cartService;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->orderRepository = $orderRepository;
        $this->serializer = $serializer;
        $this->accountRegistrationService = $accountRegistrationService;
        $this->accountService = $accountService;
        $this->apiVersionConverter = $apiVersionConverter;
    }

    /**
     * @Route("/sales-channel-api/v{version}/checkout/order", name="sales-channel-api.checkout.order.create", methods={"POST"})
     *
     * @throws OrderNotFoundException
     * @throws CartTokenNotFoundException
     */
    public function createOrder(int $version, SalesChannelContext $context): JsonResponse
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);

        $orderId = $this->cartService->order($cart, $context);
        $order = $this->getOrderById($orderId, $context);

        return new JsonResponse($this->serialize($this->apiVersionConverter->convertEntity(
            $this->orderRepository->getDefinition(),
            $order,
            $version
        )));
    }

    /**
     * @Route("/sales-channel-api/v{version}/checkout/guest-order", name="sales-channel-api.checkout.guest-order.create", methods={"POST"})
     *
     * @throws OrderNotFoundException
     * @throws CartTokenNotFoundException
     */
    public function createGuestOrder(int $version, RequestDataBag $data, SalesChannelContext $salesChannelContext): JsonResponse
    {
        $customerId = $this->accountRegistrationService->register($data, true, $salesChannelContext);
        $newContextToken = $this->accountService->login($data->get('email'), $salesChannelContext, true);

        $newSalesChannelContext = $this->createSalesChannelContext(
            $newContextToken,
            $customerId,
            $salesChannelContext
        );

        $cart = $this->cartService->getCart($newSalesChannelContext->getToken(), $newSalesChannelContext);
        $orderId = $this->cartService->order($cart, $newSalesChannelContext);

        $responseData = $this->serialize($this->apiVersionConverter->convertEntity(
            $this->orderRepository->getDefinition(),
            $this->getOrderById($orderId, $newSalesChannelContext),
            $version
        ));
        $responseData[PlatformRequest::HEADER_CONTEXT_TOKEN] = $newContextToken;

        return new JsonResponse($responseData);
    }

    /**
     * @Route("/sales-channel-api/v{version}/checkout/guest-order/{id}", name="sales-channel-api.checkout.guest-order.detail", methods={"GET"})
     *
     * @throws OrderNotFoundException
     */
    public function getDeepLinkOrder(string $id, int $version, Request $request, Context $context): JsonResponse
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

        return new JsonResponse($this->serialize($this->apiVersionConverter->convertEntity(
            $this->orderRepository->getDefinition(),
            $orders->first(),
            $version
        )));
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
        $errorUrl = $dataBag->get('errorUrl');
        $response = $this->paymentService->handlePaymentByOrder($orderId, $dataBag, $context, $finishUrl, $errorUrl);

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

    /**
     * Since the guest customer was logged in, the context changed in the system,
     * but this doesn't effect the context given as parameter.
     * Because of that, a new context for the following operations is created
     */
    private function createSalesChannelContext(string $newToken, string $customerId, SalesChannelContext $context): SalesChannelContext
    {
        $salesChannelContext = $this->salesChannelContextFactory->create(
            $newToken,
            $context->getSalesChannel()->getId(),
            [SalesChannelContextService::CUSTOMER_ID => $customerId]
        );

        // todo: load matching rules
        $salesChannelContext->setRuleIds($context->getRuleIds());

        return $salesChannelContext;
    }

    private function serialize(array $data): array
    {
        $decoded = $this->serializer->normalize($data);

        return [
            'data' => $decoded,
        ];
    }
}

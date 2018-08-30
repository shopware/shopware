<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Storefront;

use Shopware\Core\Checkout\Cart\Exception\CustomerAccountExistsException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderStruct;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\InvalidTransactionException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\Api\Response\ResponseFactory;
use Shopware\Core\Framework\Api\Response\Type\JsonType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Storefront\Exception\CustomerNotFoundException;
use Shopware\Storefront\Page\Account\AccountService;
use Shopware\Storefront\Page\Account\RegistrationRequest;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;

class StorefrontCheckoutController extends Controller
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
     * @var ResponseFactory
     */
    private $responseFactory;

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
        ResponseFactory $responseFactory,
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
        $this->responseFactory = $responseFactory;
    }

    /**
     * @Route("/storefront-api/checkout/order", name="storefront.api.checkout.order", methods={"POST"})
     *
     * @throws OrderNotFoundException
     */
    public function createOrder(CheckoutContext $context): JsonResponse
    {
        $orderId = $this->cartService->order($context);
        $order = $this->getOrderById($orderId, $context);

        $this->contextPersister->save($context->getToken(), ['cartToken' => null], $context->getTenantId());

        return new JsonResponse(
            $this->serialize($order)
        );
    }

    /**
     * @Route("/storefront-api/checkout/guest-order", name="storefront.api.checkout.guest-order", methods={"POST"})
     *
     * @throws CustomerAccountExistsException
     * @throws OrderNotFoundException
     */
    public function createGuestOrder(Request $request, CheckoutContext $context): JsonResponse
    {
        $registrationRequest = new RegistrationRequest();
        $registrationRequest->assign($request->request->all());
        $registrationRequest->setGuest(true);

        $customerId = null;

        try {
            $customer = $this->accountService->getCustomerByEmail($registrationRequest->getEmail(), $context);

            if (!$customer->getGuest()) {
                throw new CustomerAccountExistsException($registrationRequest->getEmail());
            }
        } catch (CustomerNotFoundException $exception) {
            // Check if customer already exists and has a real account.
            // The empty catch is therefore intended.
        }

        $customerId = $this->accountService->createNewCustomer($registrationRequest, $context);

        $orderId = $this->cartService->order($this->createOrderContext($customerId, $context));
        $this->contextPersister->save($context->getToken(), ['cartToken' => null], $context->getTenantId());

        return new JsonResponse(
            $this->serialize($this->getOrderById($orderId, $context))
        );
    }

    /**
     * @Route("/storefront-api/checkout/guest-order/{id}", name="storefront.api.checkout.guest-order.deep-link", methods={"GET"})
     *
     * @throws OrderNotFoundException
     */
    public function getDeepLinkOrder(string $id, Request $request, Context $context): Response
    {
        $deepLinkCode = (string) $request->query->get('accessCode');
        $order = $this->cartService->getOrderByDeepLinkCode($id, $deepLinkCode, $context);

        return $this->responseFactory->createDetailResponse(
            $order,
            OrderDefinition::class,
            $request,
            $context
        );
    }

    /**
     * @Route("/storefront-api/checkout/pay/order/{orderId}", name="storefront.api.checkout.pay.order", methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     * @throws InvalidTransactionException
     * @throws InvalidOrderException
     * @throws UnknownPaymentMethodException
     */
    public function payOrder(string $orderId, Request $request, CheckoutContext $context): Response
    {
        $finishUrl = $request->request->get('finishUrl', null);
        $response = $this->paymentService->handlePaymentByOrder($orderId, $context, $finishUrl);

        if ($response) {
            return new JsonResponse(
                [
                    'paymentUrl' => $response->getTargetUrl(),
                ],
                200,
                ['Location' => $response->getTargetUrl()]
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws OrderNotFoundException
     */
    private function getOrderById(string $orderId, CheckoutContext $context): OrderStruct
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
            $context->getTenantId(),
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

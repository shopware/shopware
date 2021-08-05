<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Administration\Service\AdminOrderCartService;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCollector;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @RouteScope(scopes={"api"})
 */
class SalesChannelProxyController extends AbstractController
{
    private const CUSTOMER_ID = SalesChannelContextService::CUSTOMER_ID;

    private const SALES_CHANNEL_ID = 'salesChannelId';

    private const ADMIN_ORDER_PERMISSIONS = [
        ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true,
    ];

    /**
     * @var DataValidator
     */
    protected $validator;

    /**
     * @var SalesChannelContextPersister
     */
    protected $contextPersister;

    /**
     * @var Processor
     */
    protected $processor;

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var SalesChannelRequestContextResolver
     */
    private $requestContextResolver;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AdminOrderCartService
     */
    private $adminOrderCartService;

    /**
     * @var SalesChannelContextServiceInterface
     */
    private $contextService;

    /**
     * @var AbstractCartOrderRoute
     */
    private $orderRoute;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        KernelInterface $kernel,
        EntityRepositoryInterface $salesChannelRepository,
        DataValidator $validator,
        SalesChannelContextPersister $contextPersister,
        SalesChannelRequestContextResolver $requestContextResolver,
        SalesChannelContextServiceInterface $contextService,
        EventDispatcherInterface $eventDispatcher,
        AdminOrderCartService $adminOrderCartService,
        AbstractCartOrderRoute $orderRoute,
        CartService $cartService,
        Connection $connection
    ) {
        $this->kernel = $kernel;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->validator = $validator;
        $this->contextPersister = $contextPersister;
        $this->requestContextResolver = $requestContextResolver;
        $this->contextService = $contextService;
        $this->eventDispatcher = $eventDispatcher;
        $this->adminOrderCartService = $adminOrderCartService;
        $this->orderRoute = $orderRoute;
        $this->cartService = $cartService;
        $this->connection = $connection;
    }

    /**
     * @Since("6.2.0.0")
     * @Route("/api/_proxy/store-api/{salesChannelId}/{_path}", name="api.proxy.store-api", requirements={"_path" = ".*"})
     *
     * @throws InvalidSalesChannelIdException
     * @throws InconsistentCriteriaIdsException
     */
    public function proxy(string $_path, string $salesChannelId, Request $request, Context $context): Response
    {
        $salesChannel = $this->fetchSalesChannel($salesChannelId, $context);

        $salesChannelApiRequest = $this->setUpSalesChannelApiRequest($_path, $salesChannelId, $request, $salesChannel);

        return $this->wrapInSalesChannelApiRoute($salesChannelApiRequest, function () use ($salesChannelApiRequest): Response {
            return $this->kernel->handle($salesChannelApiRequest, HttpKernelInterface::SUB_REQUEST);
        });
    }

    /**
     * @Since("6.3.4.0")
     * @Route("/api/_proxy-order/{salesChannelId}", name="api.proxy-order.create")
     *
     * @throws InvalidSalesChannelIdException
     * @throws InconsistentCriteriaIdsException
     */
    public function proxyCreateOrder(string $salesChannelId, Request $request, Context $context, RequestDataBag $data): Response
    {
        $this->fetchSalesChannel($salesChannelId, $context);

        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $request, $context);

        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        $order = $this->orderRoute->order($cart, $salesChannelContext, $data)->getOrder();

        $orderId = $order->getId();
        $userId = $context->getSource() instanceof AdminApiSource ? $context->getSource()->getUserId() : null;
        $userId = $userId ? Uuid::fromHexToBytes($userId) : null;

        $context->scope(Context::SYSTEM_SCOPE, function () use ($orderId, $userId): void {
            $this->connection->executeUpdate(
                'UPDATE `order` SET `created_by_id` = :createdById WHERE `id` = :id',
                ['createdById' => $userId, 'id' => Uuid::fromHexToBytes($orderId)]
            );
        });

        return new JsonResponse($order);
    }

    /**
     * @Since("6.2.0.0")
     * @Route("/api/_proxy/switch-customer", name="api.proxy.switch-customer", methods={"PATCH"})
     *
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidSalesChannelIdException
     * @throws MissingRequestParameterException
     */
    public function assignCustomer(Request $request, Context $context): Response
    {
        if (!$request->request->has(self::SALES_CHANNEL_ID)) {
            throw new MissingRequestParameterException(self::SALES_CHANNEL_ID);
        }

        $salesChannelId = (string) $request->request->get('salesChannelId');

        if (!$request->request->has(self::CUSTOMER_ID)) {
            throw new MissingRequestParameterException(self::CUSTOMER_ID);
        }

        $this->fetchSalesChannel($salesChannelId, $context);

        $this->persistPermissions($request);

        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $request, $context);

        $this->updateCustomerToContext($request->get(self::CUSTOMER_ID), $salesChannelContext);

        $content = json_encode([
            PlatformRequest::HEADER_CONTEXT_TOKEN => $salesChannelContext->getToken(),
        ], \JSON_THROW_ON_ERROR);
        $response = new Response();
        $response->setContent($content ?: null);

        return $response;
    }

    /**
     * @Since("6.2.0.0")
     * @Route("/api/_proxy/modify-shipping-costs", name="api.proxy.modify-shipping-costs", methods={"PATCH"})
     *
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidSalesChannelIdException
     * @throws MissingRequestParameterException
     */
    public function modifyShippingCosts(Request $request, Context $context): JsonResponse
    {
        if (!$request->request->has(self::SALES_CHANNEL_ID)) {
            throw new MissingRequestParameterException(self::SALES_CHANNEL_ID);
        }

        $salesChannelId = (string) $request->request->get('salesChannelId');

        $this->fetchSalesChannel($salesChannelId, $context);

        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $request, $context);

        $calculatedPrice = $this->parseCalculatedPriceByRequest($request);

        $cart = $this->adminOrderCartService->updateShippingCosts($calculatedPrice, $salesChannelContext);

        return new JsonResponse(['data' => $cart]);
    }

    /**
     * @Since("6.2.0.0")
     * @Route("/api/_proxy/disable-automatic-promotions", name="api.proxy.disable-automatic-promotions", methods={"PATCH"})
     */
    public function disableAutomaticPromotions(Request $request): JsonResponse
    {
        if (!$request->request->has(self::SALES_CHANNEL_ID)) {
            throw new MissingRequestParameterException(self::SALES_CHANNEL_ID);
        }

        $contextToken = $this->getContextToken($request);

        $salesChannelId = (string) $request->request->get('salesChannelId');

        $this->adminOrderCartService->addPermission($contextToken, PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS, $salesChannelId);

        return new JsonResponse();
    }

    /**
     * @Since("6.2.0.0")
     * @Route("/api/_proxy/enable-automatic-promotions", name="api.proxy.enable-automatic-promotions", methods={"PATCH"})
     */
    public function enableAutomaticPromotions(Request $request): JsonResponse
    {
        if (!$request->request->has(self::SALES_CHANNEL_ID)) {
            throw new MissingRequestParameterException(self::SALES_CHANNEL_ID);
        }

        $contextToken = $this->getContextToken($request);

        $salesChannelId = (string) $request->request->get('salesChannelId');

        $this->adminOrderCartService->deletePermission($contextToken, PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS, $salesChannelId);

        return new JsonResponse();
    }

    private function wrapInSalesChannelApiRoute(Request $request, callable $call): Response
    {
        /** @var RequestStack $requestStack */
        $requestStack = $this->get('request_stack');

        $requestStackBackup = $this->clearRequestStackWithBackup($requestStack);
        $requestStack->push($request);

        try {
            return $call();
        } finally {
            $this->restoreRequestStack($requestStack, $requestStackBackup);
        }
    }

    private function setUpSalesChannelApiRequest(string $path, string $salesChannelId, Request $request, SalesChannelEntity $salesChannel): Request
    {
        $contextToken = $this->getContextToken($request);

        $server = array_merge($request->server->all(), ['REQUEST_URI' => '/store-api/' . $path]);
        $subrequest = $request->duplicate(null, null, [], null, null, $server);

        $subrequest->headers->set(PlatformRequest::HEADER_ACCESS_KEY, $salesChannel->getAccessKey());
        $subrequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $contextToken);
        $subrequest->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $salesChannel->getAccessKey());

        $this->requestContextResolver->handleSalesChannelContext(
            $subrequest,
            $salesChannelId,
            $contextToken
        );

        return $subrequest;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidSalesChannelIdException
     */
    private function fetchSalesChannel(string $salesChannelId, Context $context): SalesChannelEntity
    {
        /** @var SalesChannelEntity|null $salesChannel */
        $salesChannel = $this->salesChannelRepository->search(new Criteria([$salesChannelId]), $context)->get($salesChannelId);

        if ($salesChannel === null) {
            throw new InvalidSalesChannelIdException($salesChannelId);
        }

        return $salesChannel;
    }

    private function getContextToken(Request $request): string
    {
        $contextToken = $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        if ($contextToken === null) {
            $contextToken = Random::getAlphanumericString(32);
        }

        return $contextToken;
    }

    private function clearRequestStackWithBackup(RequestStack $requestStack): array
    {
        $requestStackBackup = [];

        while ($requestStack->getMainRequest()) {
            $requestStackBackup[] = $requestStack->pop();
        }

        return $requestStackBackup;
    }

    private function restoreRequestStack(RequestStack $requestStack, array $requestStackBackup): void
    {
        $this->clearRequestStackWithBackup($requestStack);

        foreach ($requestStackBackup as $backedUpRequest) {
            $requestStack->push($backedUpRequest);
        }
    }

    private function fetchSalesChannelContext(string $salesChannelId, Request $request, Context $originalContext): SalesChannelContext
    {
        $contextToken = $this->getContextToken($request);

        $salesChannelContext = $this->contextService->get(
            new SalesChannelContextServiceParameters(
                $salesChannelId,
                $contextToken,
                $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID),
                $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID),
                null,
                $originalContext
            )
        );

        return $salesChannelContext;
    }

    private function updateCustomerToContext(string $customerId, SalesChannelContext $context): void
    {
        $data = new DataBag();
        $data->set(self::CUSTOMER_ID, $customerId);

        $definition = new DataValidationDefinition('context_switch');
        $parameters = $data->only(
            self::CUSTOMER_ID
        );

        $customerCriteria = new Criteria();
        $customerCriteria->addFilter(new EqualsFilter('customer.id', $parameters[self::CUSTOMER_ID]));

        $definition
            ->add(self::CUSTOMER_ID, new EntityExists(['entity' => 'customer', 'context' => $context->getContext(), 'criteria' => $customerCriteria]))
        ;

        $this->validator->validate($parameters, $definition);

        $isSwitchNewCustomer = true;
        if ($context->getCustomer()) {
            // Check if customer switch to another customer or not
            $isSwitchNewCustomer = $context->getCustomer()->getId() !== $parameters[self::CUSTOMER_ID];
        }

        if (!$isSwitchNewCustomer) {
            return;
        }

        $this->contextPersister->save(
            $context->getToken(),
            [
                'customerId' => $parameters[self::CUSTOMER_ID],
                'billingAddressId' => null,
                'shippingAddressId' => null,
                'shippingMethodId' => null,
                'paymentMethodId' => null,
                'languageId' => null,
                'currencyId' => null,
            ],
            $context->getSalesChannel()->getId()
        );
        $event = new SalesChannelContextSwitchEvent($context, $data);
        $this->eventDispatcher->dispatch($event);
    }

    private function persistPermissions(Request $request): void
    {
        $contextToken = $this->getContextToken($request);

        $salesChannelId = (string) $request->request->get('salesChannelId');

        $payload = $this->contextPersister->load($contextToken, $salesChannelId);

        if (!\in_array(SalesChannelContextService::PERMISSIONS, $payload, true)) {
            $payload[SalesChannelContextService::PERMISSIONS] = self::ADMIN_ORDER_PERMISSIONS;
            $this->contextPersister->save($contextToken, $payload, $salesChannelId);
        }
    }

    private function parseCalculatedPriceByRequest(Request $request): CalculatedPrice
    {
        $this->validateShippingCostsParameters($request);

        $shippingCosts = $request->get('shippingCosts');

        return new CalculatedPrice($shippingCosts['unitPrice'], $shippingCosts['totalPrice'], new CalculatedTaxCollection(), new TaxRuleCollection());
    }

    private function validateShippingCostsParameters(Request $request): void
    {
        if (!$request->request->has('shippingCosts')) {
            throw new MissingRequestParameterException('shippingCosts');
        }

        $validation = new DataValidationDefinition('shipping-cost');
        $validation->add('unitPrice', new NotBlank(), new Type('numeric'), new GreaterThanOrEqual(['value' => 0]));
        $validation->add('totalPrice', new NotBlank(), new Type('numeric'), new GreaterThanOrEqual(['value' => 0]));
        $this->validator->validate($request->request->all('shippingCosts'), $validation);
    }
}

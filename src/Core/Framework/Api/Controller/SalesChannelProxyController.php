<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Administration\Service\AdminOrderCartService;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCollector;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
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
     * @var SalesChannelContextFactory
     */
    private $salesChannelContextFactory;

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

    public function __construct(
        KernelInterface $kernel,
        EntityRepositoryInterface $salesChannelRepository,
        DataValidator $validator,
        SalesChannelContextPersister $contextPersister,
        SalesChannelContextFactory $salesChannelContextFactory,
        SalesChannelRequestContextResolver $requestContextResolver,
        SalesChannelContextServiceInterface $contextService,
        EventDispatcherInterface $eventDispatcher,
        AdminOrderCartService $adminOrderCartService
    ) {
        $this->kernel = $kernel;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->validator = $validator;
        $this->contextPersister = $contextPersister;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->requestContextResolver = $requestContextResolver;
        $this->contextService = $contextService;
        $this->eventDispatcher = $eventDispatcher;
        $this->adminOrderCartService = $adminOrderCartService;
    }

    /**
     * @Route("/api/v{version}/_proxy/sales-channel-api/{salesChannelId}/{_path}", name="api.proxy.sales-channel", requirements={"_path" = ".*"})
     * @Route("/api/v{version}/_proxy/store-api/{salesChannelId}/{_path}", name="api.proxy.store-api", requirements={"_path" = ".*"})
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
     * @Route("/api/v{version}/_proxy/switch-customer", name="api.proxy.switch-customer", methods={"PATCH"})
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

        $salesChannelId = $request->request->get('salesChannelId');

        if (!$request->request->has(self::CUSTOMER_ID)) {
            throw new MissingRequestParameterException(self::CUSTOMER_ID);
        }

        $this->fetchSalesChannel($salesChannelId, $context);

        $this->persistPermissions($request);

        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $request);

        $this->updateCustomerToContext($request->get(self::CUSTOMER_ID), $salesChannelContext);

        $response = new Response();
        $response->setContent(json_encode([
            PlatformRequest::HEADER_CONTEXT_TOKEN => $salesChannelContext->getToken(),
        ]));

        return $response;
    }

    /**
     * @Route("/api/v{version}/_proxy/modify-shipping-costs", name="api.proxy.modify-shipping-costs", methods={"PATCH"})
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

        $salesChannelId = $request->request->get('salesChannelId');

        $this->fetchSalesChannel($salesChannelId, $context);

        $this->adminOrderCartService->addPermission($this->getContextToken($request), DeliveryProcessor::SKIP_DELIVERY_PRICE_RECALCULATION);

        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $request);

        $calculatedPrice = $this->parseCalculatedPriceByRequest($request);

        $cart = $this->adminOrderCartService->updateShippingCosts($calculatedPrice, $salesChannelContext);

        return new JsonResponse(['data' => $cart]);
    }

    /**
     * @Route("/api/v{version}/_proxy/disable-automatic-promotions", name="api.proxy.disable-automatic-promotions", methods={"PATCH"})
     */
    public function disableAutomaticPromotions(Request $request): JsonResponse
    {
        $contextToken = $this->getContextToken($request);

        $this->adminOrderCartService->addPermission($contextToken, PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS);

        return new JsonResponse();
    }

    /**
     * @Route("/api/v{version}/_proxy/enable-automatic-promotions", name="api.proxy.enable-automatic-promotions", methods={"PATCH"})
     */
    public function enableAutomaticPromotions(Request $request): JsonResponse
    {
        $contextToken = $this->getContextToken($request);

        $this->adminOrderCartService->deletePermission($contextToken, PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS);

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

        $prefix = '/sales-channel-api/';

        if ($request->attributes->get('_route') === 'api.proxy.store-api') {
            $prefix = '/store-api/';
        }

        $server = array_merge($request->server->all(), ['REQUEST_URI' => $prefix . $path]);
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

        if (!$contextToken) {
            $contextToken = Random::getAlphanumericString(32);
        }

        return (string) $contextToken;
    }

    private function clearRequestStackWithBackup(RequestStack $requestStack): array
    {
        $requestStackBackup = [];

        while ($requestStack->getMasterRequest()) {
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

    private function fetchSalesChannelContext(string $salesChannelId, Request $request): SalesChannelContext
    {
        $contextToken = $this->getContextToken($request);

        $salesChannelContext = $this->contextService->get(
            $salesChannelId,
            $contextToken,
            $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID)
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
            ]
        );
        $event = new SalesChannelContextSwitchEvent($context, $data);
        $this->eventDispatcher->dispatch($event);
    }

    private function persistPermissions(Request $request): void
    {
        $contextToken = $this->getContextToken($request);

        $payload = $this->contextPersister->load($contextToken);

        if (!in_array(SalesChannelContextService::PERMISSIONS, $payload, true)) {
            $payload[SalesChannelContextService::PERMISSIONS] = self::ADMIN_ORDER_PERMISSIONS;
            $this->contextPersister->save($contextToken, $payload);
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
        $this->validator->validate($request->request->get('shippingCosts'), $validation);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\ApiOrderCartService;
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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
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

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('core')]
class SalesChannelProxyController extends AbstractController
{
    private const CUSTOMER_ID = SalesChannelContextService::CUSTOMER_ID;

    private const SALES_CHANNEL_ID = 'salesChannelId';

    private const ADMIN_ORDER_PERMISSIONS = [
        ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true,
    ];

    protected Processor $processor;

    /**
     * @internal
     */
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly EntityRepository $salesChannelRepository,
        protected DataValidator $validator,
        protected SalesChannelContextPersister $contextPersister,
        private readonly SalesChannelRequestContextResolver $requestContextResolver,
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ApiOrderCartService $adminOrderCartService,
        private readonly AbstractCartOrderRoute $orderRoute,
        private readonly CartService $cartService,
        private readonly Connection $connection,
        private readonly RequestStack $requestStack
    ) {
    }

    #[Route(path: '/api/_proxy/store-api/{salesChannelId}/{_path}', name: 'api.proxy.store-api', requirements: ['_path' => '.*'])]
    public function proxy(string $_path, string $salesChannelId, Request $request, Context $context): Response
    {
        $salesChannel = $this->fetchSalesChannel($salesChannelId, $context);

        $salesChannelApiRequest = $this->setUpSalesChannelApiRequest($_path, $salesChannelId, $request, $salesChannel);

        return $this->wrapInSalesChannelApiRoute($salesChannelApiRequest, fn (): Response => $this->kernel->handle($salesChannelApiRequest, HttpKernelInterface::SUB_REQUEST));
    }

    #[Route(path: '/api/_proxy-order/{salesChannelId}', name: 'api.proxy-order.create')]
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
            $this->connection->executeStatement(
                'UPDATE `order` SET `created_by_id` = :createdById WHERE `id` = :id',
                ['createdById' => $userId, 'id' => Uuid::fromHexToBytes($orderId)]
            );
        });

        return new JsonResponse($order);
    }

    #[Route(path: '/api/_proxy/switch-customer', name: 'api.proxy.switch-customer', methods: ['PATCH'], defaults: ['_acl' => ['api_proxy_switch-customer']])]
    public function assignCustomer(Request $request, Context $context): Response
    {
        if (!$request->request->has(self::SALES_CHANNEL_ID)) {
            throw RoutingException::missingRequestParameter(self::SALES_CHANNEL_ID);
        }

        $salesChannelId = (string) $request->request->get('salesChannelId');

        if (!$request->request->has(self::CUSTOMER_ID)) {
            throw RoutingException::missingRequestParameter(self::CUSTOMER_ID);
        }

        $this->fetchSalesChannel($salesChannelId, $context);

        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $request, $context);

        $this->persistPermissions($request, $salesChannelContext);

        $this->updateCustomerToContext($request->get(self::CUSTOMER_ID), $salesChannelContext);

        $content = json_encode([
            PlatformRequest::HEADER_CONTEXT_TOKEN => $salesChannelContext->getToken(),
        ], \JSON_THROW_ON_ERROR);
        $response = new Response();
        $response->headers->set('content-type', 'application/json');
        $response->setContent($content ?: null);

        return $response;
    }

    #[Route(path: '/api/_proxy/modify-shipping-costs', name: 'api.proxy.modify-shipping-costs', methods: ['PATCH'])]
    public function modifyShippingCosts(Request $request, Context $context): JsonResponse
    {
        if (!$request->request->has(self::SALES_CHANNEL_ID)) {
            throw RoutingException::missingRequestParameter(self::SALES_CHANNEL_ID);
        }

        $salesChannelId = (string) $request->request->get('salesChannelId');

        $this->fetchSalesChannel($salesChannelId, $context);

        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $request, $context);

        $calculatedPrice = $this->parseCalculatedPriceByRequest($request);

        $cart = $this->adminOrderCartService->updateShippingCosts($calculatedPrice, $salesChannelContext);

        return new JsonResponse(['data' => $cart]);
    }

    #[Route(path: '/api/_proxy/disable-automatic-promotions', name: 'api.proxy.disable-automatic-promotions', methods: ['PATCH'])]
    public function disableAutomaticPromotions(Request $request): JsonResponse
    {
        if (!$request->request->has(self::SALES_CHANNEL_ID)) {
            throw RoutingException::missingRequestParameter(self::SALES_CHANNEL_ID);
        }

        $contextToken = $this->getContextToken($request);

        $salesChannelId = (string) $request->request->get('salesChannelId');

        $this->adminOrderCartService->addPermission($contextToken, PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS, $salesChannelId);

        return new JsonResponse();
    }

    #[Route(path: '/api/_proxy/enable-automatic-promotions', name: 'api.proxy.enable-automatic-promotions', methods: ['PATCH'])]
    public function enableAutomaticPromotions(Request $request): JsonResponse
    {
        if (!$request->request->has(self::SALES_CHANNEL_ID)) {
            throw RoutingException::missingRequestParameter(self::SALES_CHANNEL_ID);
        }

        $contextToken = $this->getContextToken($request);

        $salesChannelId = (string) $request->request->get('salesChannelId');

        $this->adminOrderCartService->deletePermission($contextToken, PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS, $salesChannelId);

        return new JsonResponse();
    }

    /**
     * @param callable(): Response $call
     */
    private function wrapInSalesChannelApiRoute(Request $request, callable $call): Response
    {
        $requestStackBackup = $this->clearRequestStackWithBackup($this->requestStack);
        $this->requestStack->push($request);

        try {
            return $call();
        } finally {
            $this->restoreRequestStack($this->requestStack, $requestStackBackup);
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

    private function persistPermissions(Request $request, SalesChannelContext $salesChannelContext): void
    {
        $contextToken = $salesChannelContext->getToken();

        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $payload = $this->contextPersister->load($contextToken, $salesChannelId);
        $requestPermissions = $request->get(SalesChannelContextService::PERMISSIONS);

        if (\in_array(SalesChannelContextService::PERMISSIONS, $payload, true) && !$requestPermissions) {
            return;
        }

        $payload[SalesChannelContextService::PERMISSIONS] = $requestPermissions
            ? \array_fill_keys($requestPermissions, true)
            : [self::ADMIN_ORDER_PERMISSIONS];

        $this->contextPersister->save($contextToken, $payload, $salesChannelId);
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
            throw RoutingException::missingRequestParameter('shippingCosts');
        }

        $validation = new DataValidationDefinition('shipping-cost');
        $validation->add('unitPrice', new NotBlank(), new Type('numeric'), new GreaterThanOrEqual(['value' => 0]));
        $validation->add('totalPrice', new NotBlank(), new Type('numeric'), new GreaterThanOrEqual(['value' => 0]));
        $this->validator->validate($request->request->all('shippingCosts'), $validation);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotRemovableException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidPriceFieldTypeException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;

/**
 * @RouteScope(scopes={"api"})
 */
class OrderController extends AbstractController
{
    private const LANGUAGE_ID = 'languageId';

    /**
     * @var Processor
     */
    protected $processor;

    /**
     * @var SalesChannelContextPersister
     */
    protected $contextPersister;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var SalesChannelContextFactory
     */
    private $salesChannelContextFactory;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var CartPersisterInterface
     */
    private $cartPersister;

    /**
     * @var CartRuleLoader
     */
    private $cartRuleLoader;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductLineItemFactory
     */
    private $productLineItemFactory;

    /**
     * @var ApiVersionConverter
     */
    private $apiVersionConverter;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    public function __construct(
        EntityRepositoryInterface $salesChannelRepository,
        CartService $cartService,
        CartPersisterInterface $cartPersister,
        CartRuleLoader $cartRuleLoader,
        SalesChannelContextPersister $contextPersister,
        SalesChannelContextFactory $salesChannelContextFactory,
        Serializer $serializer,
        Processor $processor,
        EventDispatcherInterface $eventDispatcher,
        ProductLineItemFactory $productLineItemFactory,
        ApiVersionConverter $apiVersionConverter,
        EntityRepositoryInterface $orderRepository
    ) {
        $this->salesChannelRepository = $salesChannelRepository;
        $this->contextPersister = $contextPersister;
        $this->cartService = $cartService;
        $this->cartPersister = $cartPersister;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->serializer = $serializer;
        $this->processor = $processor;
        $this->cartRuleLoader = $cartRuleLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->productLineItemFactory = $productLineItemFactory;
        $this->apiVersionConverter = $apiVersionConverter;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @Route("/api/v{version}/sales-channel/{salesChannelId}/checkout/cart", name="api.checkout.cart.detail", methods={"GET"})
     */
    public function getCart(string $salesChannelId, Request $request, Context $context): JsonResponse
    {
        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $context, $request);

        $name = $request->request->getAlnum('name', CartService::SALES_CHANNEL);

        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext, $name);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/api/v{version}/sales-channel/{salesChannelId}/checkout/cart", name="api.checkout.cart.create", methods={"POST"})
     */
    public function createCart(string $salesChannelId, Request $request, Context $context): JsonResponse
    {
        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $context, $request);

        $name = $request->request->getAlnum('name', CartService::SALES_CHANNEL);

        $this->cartService->createNew($salesChannelContext->getToken(), $name);

        return new JsonResponse(
            [PlatformRequest::HEADER_CONTEXT_TOKEN => $salesChannelContext->getToken()],
            JsonResponse::HTTP_OK,
            [PlatformRequest::HEADER_CONTEXT_TOKEN => $salesChannelContext->getToken()]
        );
    }

    /**
     * @Route("/api/v{version}/sales-channel/{salesChannelId}/checkout/cart/product/{id}", name="api.checkout.cart.product.add", methods={"POST"})
     *
     * @throws MixedLineItemTypeException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     */
    public function addProduct(string $salesChannelId, string $id, Request $request, Context $context): JsonResponse
    {
        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $context, $request);

        $quantity = $request->request->get('quantity');

        $lineItem = $this->productLineItemFactory->create($id, ['quantity' => $quantity]);

        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext, CartService::SALES_CHANNEL);

        $cart = $this->cartService->add($cart, $lineItem, $salesChannelContext);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/api/v{version}/sales-channel/{salesChannelId}/checkout/cart/line-item/{id}", name="api.checkout.cart.line-item.add", methods={"POST"})
     *
     * @throws MissingRequestParameterException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws InvalidPriceFieldTypeException
     * @throws MixedLineItemTypeException
     */
    public function addLineItem(string $salesChannelId, string $id, Request $request, Context $context): JsonResponse
    {
        if (!$request->request->has('type')) {
            throw new MissingRequestParameterException('type');
        }

        $name = $request->request->getAlnum('name', CartService::SALES_CHANNEL);
        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $context, $request);

        $lineItemtype = $request->request->get('type');
        $quantity = $request->request->get('quantity');

        $lineItem = new LineItem($id, $lineItemtype, null, $quantity);
        $this->updateLineItemByRequest($lineItem, $request, $salesChannelContext->getContext());

        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext, $name);

        $cart = $this->cartService->add($cart, $lineItem, $salesChannelContext);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/api/v{version}/sales-channel/{salesChannelId}/checkout/cart/line-item/{id}", name="api.checkout.cart.line-item.update", methods={"PATCH"})"
     *
     * @throws InvalidQuantityException
     * @throws LineItemNotFoundException
     * @throws LineItemNotStackableException
     * @throws InvalidPriceFieldTypeException
     */
    public function updateLineItem(string $salesChannelId, string $id, Request $request, Context $context): JsonResponse
    {
        $name = $request->request->getAlnum('name', CartService::SALES_CHANNEL);

        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $context, $request);

        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext, $name);

        if (!$cart->has($id)) {
            throw new LineItemNotFoundException($id);
        }

        $lineItem = $cart->getLineItems()->get($id);
        $this->updateLineItemByRequest($lineItem, $request, $salesChannelContext->getContext());

        $cart = $this->cartService->recalculate($cart, $salesChannelContext);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/api/v{version}/sales-channel/{salesChannelId}/checkout/cart", name="api.checkout.cart.cancel", methods={"DELETE"})
     */
    public function cancelCart(string $salesChannelId, Request $request, Context $context): JsonResponse
    {
        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $context, $request);
        $this->cartService->deleteCart($salesChannelContext);

        return new JsonResponse();
    }

    /**
     * @Route("/api/v{version}/sales-channel/{salesChannelId}/checkout/cart/line-items/delete", name="api.checkout.cart.line-items.delete", methods={"POST"})"
     *
     * @throws LineItemNotFoundException
     * @throws LineItemNotRemovableException
     * @throws MissingRequestParameterException
     */
    public function removeLineItems(string $salesChannelId, Request $request, Context $context): JsonResponse
    {
        if (!$request->request->has('keys')) {
            throw new MissingRequestParameterException('keys');
        }

        $lineItemKeys = $request->request->get('keys');

        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $context, $request);

        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext, CartService::SALES_CHANNEL);

        foreach ($lineItemKeys as $lineItemKey) {
            if (!$cart->has($lineItemKey)) {
                continue;
            }

            $cart = $this->cartService->remove($cart, $lineItemKey, $salesChannelContext);
        }

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/api/v{version}/sales-channel/{salesChannelId}/checkout/order", name="api.checkout.order.create", methods={"POST"})
     */
    public function createOrder(string $salesChannelId, int $version, Request $request, Context $context): JsonResponse
    {
        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $context, $request);

        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext, CartService::SALES_CHANNEL);

        $orderId = $this->cartService->order($cart, $salesChannelContext);
        $order = $this->getOrderById($orderId, $salesChannelContext);

        return new JsonResponse($this->serialize($this->apiVersionConverter->convertEntity(
            $this->orderRepository->getDefinition(),
            $order,
            $version
        )));
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
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws InvalidPriceFieldTypeException
     */
    private function updateLineItemByRequest(LineItem $lineItem, Request $request, Context $context): void
    {
        $quantity = $request->get('quantity');
        $label = $request->get('label');
        $description = $request->get('description');
        $referencedId = $request->get('referencedId');
        $priceDefinition = $request->get('priceDefinition');

        $lineItem->setStackable(true);
        $lineItem->setRemovable(true);

        if ($quantity) {
            $lineItem->setQuantity($quantity);
        }

        if ($referencedId) {
            $lineItem->setReferencedId($referencedId);
        }

        if ($label !== null) {
            $lineItem->setLabel($label);
        }

        if ($description !== null) {
            $lineItem->setDescription($description);
        }

        if ($priceDefinition !== null) {
            $priceDefinitionType = $this->initPriceDefinition($context, $priceDefinition, $lineItem->getType());
            $lineItem->setPriceDefinition($priceDefinitionType);
            if ($lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE) {
                $lineItem->setExtensions([ProductCartProcessor::CUSTOM_PRICE => true]);
            }
        }
    }

    private function fetchSalesChannelContext(string $salesChannelId, Context $context, Request $request): SalesChannelContext
    {
        $this->validationSalesChannel($salesChannelId, $context);

        $contextToken = $this->getContextToken($request);

        $parameters = $this->contextPersister->load($contextToken);
        $languageId = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID);

        if ($languageId) {
            $parameters[self::LANGUAGE_ID] = $languageId;
        }

        $salesChannelContext = $this->salesChannelContextFactory->create($contextToken, $salesChannelId, $parameters);
        $salesChannelContext->addExtension(ProductCartProcessor::IS_ADMIN_ORDER, new ArrayEntity());

        return $salesChannelContext;
    }

    private function getContextToken(Request $request): string
    {
        $contextToken = $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        if (!$contextToken) {
            $contextToken = Random::getAlphanumericString(32);
        }

        return (string) $contextToken;
    }

    private function serialize($data): array
    {
        $decoded = $this->serializer->normalize($data);

        return [
            'data' => $decoded,
        ];
    }

    /**
     * @throws InvalidSalesChannelIdException
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function validationSalesChannel(string $salesChannelId, Context $context): void
    {
        /** @var SalesChannelEntity|null $salesChannel */
        $salesChannel = $this->salesChannelRepository->search(new Criteria([$salesChannelId]), $context)->get($salesChannelId);

        if ($salesChannel === null) {
            throw new InvalidSalesChannelIdException($salesChannelId);
        }
    }

    private function initPriceDefinition(Context $context, $priceDefinition, $lineItemType)
    {
        if (!isset($priceDefinition['type'])) {
            throw new InvalidPriceFieldTypeException('none');
        }

        $priceDefinition['precision'] = $priceDefinition['precision'] ?? $context->getCurrencyPrecision();

        switch ($priceDefinition['type']) {
            case QuantityPriceDefinition::TYPE:
                return QuantityPriceDefinition::fromArray($priceDefinition);
            case AbsolutePriceDefinition::TYPE:
                $rules = new LineItemOfTypeRule(Rule::OPERATOR_NEQ, $lineItemType);

                return new AbsolutePriceDefinition($priceDefinition['price'], $priceDefinition['precision'], $rules);
            case PercentagePriceDefinition::TYPE:
                $rules = new LineItemOfTypeRule(Rule::OPERATOR_NEQ, $lineItemType);

                return new PercentagePriceDefinition($priceDefinition['percentage'], $priceDefinition['precision'], $rules);
        }

        throw new InvalidPriceFieldTypeException($priceDefinition['type']);
    }
}

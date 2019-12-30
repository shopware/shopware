<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Event\LineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidPriceFieldTypeException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    public function __construct(
        EntityRepositoryInterface $salesChannelRepository,
        CartService $cartService,
        CartPersisterInterface $cartPersister,
        CartRuleLoader $cartRuleLoader,
        SalesChannelContextPersister $contextPersister,
        SalesChannelContextFactory $salesChannelContextFactory,
        Serializer $serializer,
        Processor $processor,
        EventDispatcherInterface $eventDispatcher
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
    }

    /**
     * @Route("/api/v{version}/sales-channel/{salesChannelId}/checkout/cart/line-item/{id}", name="api.checkout.cart.line-item.add", methods={"POST"})
     *
     * @throws MissingRequestParameterException
     * @throws MixedLineItemTypeException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws InvalidPriceFieldTypeException
     */
    public function addLineItem(string $salesChannelId, string $id, Request $request, Context $context): Response
    {
        if (!$request->request->has('type')) {
            throw new MissingRequestParameterException('type');
        }

        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $context, $request);

        $lineItemtype = $request->request->get('type');
        $quantity = $request->request->get('quantity');

        $lineItem = new LineItem($id, $lineItemtype, null, $quantity);

        $this->updateLineItemByRequest($lineItem, $request, $salesChannelContext->getContext());

        $cart = $this->addLineItemToCart($salesChannelContext, $lineItem);

        $cart = $this->recalculateCart($cart, $salesChannelContext);

        //Save Cart to Cache and DB
        $this->saveCart($cart, $salesChannelContext);

        $response = new Response();
        $response->setContent(json_encode($this->serialize($cart)));

        return $response;
    }

    /**
     * @Route("/api/v{version}/sales-channel/{salesChannelId}/checkout/cart/line-item/{id}", name="api.checkout.cart.line-item.update", methods={"PATCH"})"
     *
     * @throws InvalidQuantityException
     * @throws LineItemNotFoundException
     * @throws LineItemNotStackableException
     * @throws InvalidPriceFieldTypeException
     */
    public function updateLineItem(string $salesChannelId, string $id, Request $request, Context $context): Response
    {
        $salesChannelContext = $this->fetchSalesChannelContext($salesChannelId, $context, $request);

        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        if (!$cart->has($id)) {
            throw new LineItemNotFoundException($id);
        }

        $lineItem = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext)->getLineItems()->get($id);

        $this->updateLineItemByRequest($lineItem, $request, $salesChannelContext->getContext());

        $cart = $this->recalculateCart($cart, $salesChannelContext);

        //Save Cart to Cache and DB
        $this->saveCart($cart, $salesChannelContext);

        $response = new Response();
        $response->setContent(json_encode($this->serialize($cart)));

        return $response;
    }

    /**
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     */
    private function addLineItemToCart(SalesChannelContext $salesChannelContext, LineItem $lineItem): Cart
    {
        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        $cart->add($lineItem);

        $cart->markModified();
        $lineItem->markModified();

        $this->eventDispatcher->dispatch(new LineItemAddedEvent($lineItem, $cart, $salesChannelContext));

        return $cart;
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
        }
    }

    private function recalculateCart(Cart $cart, SalesChannelContext $context): Cart
    {
        $behavior = (new CartBehavior())
            ->setIsRecalculation(true);

        // all prices are now prepared for calculation -  starts the cart calculation
        $cart = $this->processor->process($cart, $context, $behavior);

        // validate cart against the context rules
        $validated = $this->cartRuleLoader->loadByCart($context, $cart, $behavior);

        return $validated->getCart();
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

    private function saveCart(Cart $cart, SalesChannelContext $salesChannelContext): void
    {
        $this->cartService->setCart($cart);
        $this->cartPersister->save($cart, $salesChannelContext);
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

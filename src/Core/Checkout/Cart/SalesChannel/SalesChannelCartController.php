<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemCoverNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotRemovableException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidPriceFieldTypeException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\ContextTokenRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;

/**
 * @RouteScope(scopes={"sales-channel-api"})
 * @ContextTokenRequired()
 */
class SalesChannelCartController extends AbstractController
{
    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var ProductLineItemFactory
     */
    private $productLineItemFactory;

    /**
     * @var PromotionItemBuilder
     */
    private $promotionItemBuilder;

    public function __construct(
        CartService $service,
        EntityRepositoryInterface $mediaRepository,
        Serializer $serializer,
        ProductLineItemFactory $productLineItemFactory,
        PromotionItemBuilder $promotionItemBuilder
    ) {
        $this->serializer = $serializer;
        $this->cartService = $service;
        $this->mediaRepository = $mediaRepository;
        $this->productLineItemFactory = $productLineItemFactory;
        $this->promotionItemBuilder = $promotionItemBuilder;
    }

    /**
     * @Route("/sales-channel-api/v{version}/checkout/cart", name="sales-channel-api.checkout.cart.detail", methods={"GET"})
     *
     * @throws CartTokenNotFoundException
     */
    public function getCart(Request $request, SalesChannelContext $context): JsonResponse
    {
        $token = $request->query->getAlnum('token', $context->getToken());
        $name = $request->query->getAlnum('name', CartService::SALES_CHANNEL);

        $cart = $this->cartService->getCart($token, $context, $name);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @OA\Post(
     *      path="/checkout/cart",
     *      description="Create a new Cart",
     *      operationId="createCart",
     *      tags={"Sales Channel Api"},
     *      @OA\Parameter(
     *          parameter="token",
     *          name="token",
     *          in="query",
     *          description="the cart token",
     *          @OA\Schema(type="string", format="uuid"),
     *      ),
     *      @OA\Parameter(
     *          parameter="name",
     *          name="name",
     *          in="query",
     *          description="the carts name",
     *          @OA\Schema(type="string"),
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="The cart identified by given token",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="sw-context-token",
     *                  type="string",
     *                  format="uuid",
     *                  description="The token of the newly created cart",
     *              )
     *          ),
     *          @OA\Header(
     *              header="sw-context-token",
     *              description="The token of the newly created cart",
     *              @OA\Schema(type="string", format="uuid")
     *          ),
     *     ),
     *     @OA\Response(
     *          response="404",
     *          ref="#/components/responses/404"
     *     ),
     * )
     *
     * @Route("/sales-channel-api/v{version}/checkout/cart", name="sales-channel-api.checkout.cart.create", methods={"POST"})
     *
     * @throws CartTokenNotFoundException
     */
    public function createCart(Request $request, SalesChannelContext $context): JsonResponse
    {
        $token = $request->request->getAlnum('token', $context->getToken());
        $name = $request->request->getAlnum('name', CartService::SALES_CHANNEL);

        $this->cartService->createNew($token, $name);

        return new JsonResponse(
            [PlatformRequest::HEADER_CONTEXT_TOKEN => $context->getToken()],
            JsonResponse::HTTP_OK,
            [PlatformRequest::HEADER_CONTEXT_TOKEN => $context->getToken()]
        );
    }

    /**
     * @Route("/sales-channel-api/v{version}/checkout/cart/product/{id}", name="sales-channel-api.checkout.frontend.cart.product.add", methods={"POST"})
     *
     * @throws MixedLineItemTypeException
     * @throws InvalidQuantityException
     * @throws InvalidPayloadException
     * @throws CartTokenNotFoundException
     */
    public function addProduct(string $id, Request $request, SalesChannelContext $context): JsonResponse
    {
        $token = $request->request->getAlnum('token', $context->getToken());
        $quantity = $request->request->getInt('quantity', 1);

        $lineItem = $this->productLineItemFactory->create($id, ['quantity' => $quantity]);

        $cart = $this->cartService->add($this->cartService->getCart($token, $context), $lineItem, $context);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * Adds the provided promotion code to the cart and recalculates it.
     * The code will be added as a separate promotion placeholder line item.
     * That one will be replaced with a real promotion (if valid) within the
     * promotion collector service.
     *
     * @Route("/sales-channel-api/v{version}/checkout/cart/code/{code}", name="sales-channel-api.checkout.frontend.cart.code.add", methods={"POST"})
     *
     * @throws CartTokenNotFoundException
     * @throws InvalidQuantityException
     * @throws MixedLineItemTypeException
     */
    public function addCode(string $code, Request $request, SalesChannelContext $context): JsonResponse
    {
        $token = $request->request->getAlnum('token', $context->getToken());

        $lineItem = $this->promotionItemBuilder->buildPlaceholderItem($code, $context->getContext()->getCurrencyPrecision());

        $cart = $this->cartService->add($this->cartService->getCart($token, $context), $lineItem, $context);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/sales-channel-api/v{version}/checkout/cart/line-item/{id}", name="sales-channel-api.checkout.cart.line-item.add", methods={"POST"})
     *
     * @throws MissingRequestParameterException
     * @throws MixedLineItemTypeException
     * @throws InvalidQuantityException
     * @throws LineItemCoverNotFoundException
     * @throws LineItemNotStackableException
     * @throws InvalidPayloadException
     */
    public function addLineItem(string $id, RequestDataBag $requestDataBag, SalesChannelContext $context): JsonResponse
    {
        // todo support price definition (NEXT-573)
        $token = $requestDataBag->getAlnum('token', $context->getToken());

        $type = $requestDataBag->getAlnum('type');
        $quantity = $requestDataBag->getInt('quantity', 1);
        $requestDataBag->remove('quantity');

        if (!$type) {
            throw new MissingRequestParameterException('type');
        }

        $lineItem = new LineItem($id, $type, null, $quantity);
        $this->updateLineItemByRequest($lineItem, $requestDataBag, $context);

        $cart = $this->cartService->add($this->cartService->getCart($token, $context), $lineItem, $context);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/sales-channel-api/v{version}/checkout/cart/line-item/{id}", name="sales-channel-api.checkout.cart.line-item.delete", methods={"DELETE"})
     *
     * @throws LineItemNotFoundException
     * @throws LineItemNotRemovableException
     * @throws CartTokenNotFoundException
     */
    public function removeLineItem(string $id, Request $request, SalesChannelContext $context): JsonResponse
    {
        $token = $request->request->getAlnum('token', $context->getToken());

        $cart = $this->cartService->getCart($token, $context);

        if (!$cart->has($id)) {
            throw new LineItemNotFoundException($id);
        }

        $cart = $this->cartService->remove($cart, $id, $context);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/sales-channel-api/v{version}/checkout/cart/line-item/{id}", name="sales-channel-api.checkout.cart.line-item.update", methods={"PATCH"})
     *
     * @throws InvalidQuantityException
     * @throws LineItemNotFoundException
     * @throws LineItemNotStackableException
     * @throws LineItemCoverNotFoundException
     * @throws InvalidPayloadException
     */
    public function updateLineItem(string $id, RequestDataBag $requestDataBag, SalesChannelContext $context): JsonResponse
    {
        $token = $requestDataBag->getAlnum('token', $context->getToken());
        $cart = $this->cartService->getCart($token, $context);

        if (!$cart->has($id)) {
            throw new LineItemNotFoundException($id);
        }

        $lineItem = $this->cartService->getCart($token, $context)->getLineItems()->get($id);

        $this->updateLineItemByRequest($lineItem, $requestDataBag, $context);

        $cart = $this->cartService->recalculate($cart, $context);

        $quantity = $requestDataBag->get('quantity');
        if ($quantity) {
            $cart = $this->cartService->changeQuantity($cart, $id, $quantity, $context);
        }

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/sales-channel-api/v{version}/checkout/cart", name="sales-channel-api.checkout.cart.cancel", methods={"DELETE"})
     */
    public function cancelCart(SalesChannelContext $context): JsonResponse
    {
        $this->cartService->deleteCart($context);

        return new JsonResponse();
    }

    /**
     * @Route("/sales-channel-api/v{version}/checkout/cart/line-items/delete", name="sales-channel-api.checkout.cart.line-items.delete", methods={"POST"})"
     *
     * @throws LineItemNotFoundException
     * @throws LineItemNotRemovableException
     * @throws MissingRequestParameterException
     */
    public function removeLineItems(Request $request, SalesChannelContext $context): JsonResponse
    {
        if (!$request->request->has('keys')) {
            throw new MissingRequestParameterException('keys');
        }

        $lineItemKeys = $request->request->get('keys');

        $cart = $this->cartService->getCart($context->getToken(), $context, CartService::SALES_CHANNEL);

        foreach ($lineItemKeys as $lineItemKey) {
            if (!$cart->has($lineItemKey)) {
                continue;
            }

            $cart = $this->cartService->remove($cart, $lineItemKey, $context);
        }

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @throws InvalidQuantityException
     * @throws LineItemCoverNotFoundException
     * @throws LineItemNotStackableException
     * @throws InvalidPayloadException
     */
    private function updateLineItemByRequest(LineItem $lineItem, RequestDataBag $requestDataBag, SalesChannelContext $context): void
    {
        $payload = $requestDataBag->get('payload', []);
        $stackable = $requestDataBag->get('stackable');
        $removable = $requestDataBag->get('removable');
        $label = $requestDataBag->get('label');
        $description = $requestDataBag->get('description');
        $coverId = $requestDataBag->get('coverId');
        $referencedId = $requestDataBag->get('referencedId');

        $lineItem->setPayload($payload);

        if ($referencedId) {
            $lineItem->setReferencedId($referencedId);
        }

        if ($stackable !== null) {
            $lineItem->setStackable($stackable);
        }

        if ($removable !== null) {
            $lineItem->setRemovable($removable);
        }

        if ($label !== null) {
            $lineItem->setLabel($label);
        }

        if ($description !== null) {
            $lineItem->setDescription($description);
        }

        if ($coverId !== null) {
            $cover = $this->mediaRepository->search(new Criteria([$coverId]), $context->getContext())->get($coverId);

            if (!$cover) {
                throw new LineItemCoverNotFoundException($coverId, $lineItem->getId());
            }

            $lineItem->setCover($cover);
        }

        if ($requestDataBag->get('priceDefinition') !== null && $context->hasPermission(ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES)) {
            $priceDefinition = $requestDataBag->get('priceDefinition')->all();
            $priceDefinitionType = $this->initPriceDefinition($context->getContext(), $priceDefinition, $lineItem->getType());
            $lineItem->setPriceDefinition($priceDefinitionType);

            if ($lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE) {
                $lineItem->addExtension(ProductCartProcessor::CUSTOM_PRICE, new ArrayEntity());
            }
        }
    }

    private function initPriceDefinition(Context $context, array $priceDefinition, string $lineItemType)
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

    private function serialize(Cart $data): array
    {
        $decoded = $this->serializer->normalize($data);

        return [
            'data' => $decoded,
        ];
    }
}

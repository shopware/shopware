<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemCoverNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotRemovableException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Promotion\Cart\Builder\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\Cart\CartPromotionsCollector;
use Shopware\Core\Content\Product\Cart\ProductCollector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;

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

    public function __construct(
        CartService $service,
        EntityRepositoryInterface $mediaRepository,
        Serializer $serializer
    ) {
        $this->serializer = $serializer;
        $this->cartService = $service;
        $this->mediaRepository = $mediaRepository;
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
        $payload = $request->request->get('payload', []);
        $payload = array_replace_recursive(['id' => $id], $payload);

        $lineItem = (new LineItem($id, ProductCollector::LINE_ITEM_TYPE, $quantity))
            ->setPayload($payload)
            ->setRemovable(true)
            ->setStackable(true);

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
        /** @var string $token */
        $token = $request->request->getAlnum('token', $context->getToken());

        $itemBuilder = new PromotionItemBuilder(CartPromotionsCollector::LINE_ITEM_TYPE);

        $lineItem = $itemBuilder->buildPlaceholderItem(
            $code,
            $context->getContext()->getCurrencyPrecision()
        );

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
     * @throws CartTokenNotFoundException
     */
    public function addLineItem(string $id, Request $request, SalesChannelContext $context): JsonResponse
    {
        // todo support price definition (NEXT-573)
        $token = $request->request->getAlnum('token', $context->getToken());

        $type = $request->request->getAlnum('type');
        $quantity = $request->request->getInt('quantity', 1);
        $request->request->remove('quantity');

        if (!$type) {
            throw new MissingRequestParameterException('type');
        }

        $lineItem = new LineItem($id, $type, $quantity);
        $this->updateLineItemByRequest($lineItem, $request, $context->getContext());

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
     * @throws CartTokenNotFoundException
     * @throws InvalidPayloadException
     */
    public function updateLineItem(string $id, Request $request, SalesChannelContext $context): JsonResponse
    {
        $token = $request->request->getAlnum('token', $context->getToken());
        $cart = $this->cartService->getCart($token, $context);

        if (!$cart->has($id)) {
            throw new LineItemNotFoundException($id);
        }

        $lineItem = $this->cartService->getCart($token, $context)->getLineItems()->get($id);

        $this->updateLineItemByRequest($lineItem, $request, $context->getContext());

        $cart = $this->cartService->recalculate($cart, $context);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @throws InvalidQuantityException
     * @throws LineItemCoverNotFoundException
     * @throws LineItemNotStackableException
     * @throws InvalidPayloadException
     */
    private function updateLineItemByRequest(LineItem $lineItem, Request $request, Context $context): void
    {
        $quantity = $request->request->get('quantity');
        $payload = $request->request->get('payload', []);
        $payload = array_replace_recursive(['id' => $lineItem->getKey()], $payload);
        $stackable = $request->request->get('stackable');
        $removable = $request->request->get('removable');
        $priority = $request->request->get('priority');
        $label = $request->request->get('label');
        $description = $request->request->get('description');
        $coverId = $request->request->get('coverId');

        $lineItem->setPayload($payload);

        if ($quantity) {
            $lineItem->setQuantity($quantity);
        }

        if ($stackable !== null) {
            $lineItem->setStackable($stackable);
        }

        if ($removable !== null) {
            $lineItem->setRemovable($removable);
        }

        if ($priority !== null) {
            $lineItem->setPriority($priority);
        }

        if ($label !== null) {
            $lineItem->setLabel($label);
        }

        if ($description !== null) {
            $lineItem->setDescription($description);
        }

        if ($coverId !== null) {
            $cover = $this->mediaRepository->search(new Criteria([$coverId]), $context)->get($coverId);

            if (!$cover) {
                throw new LineItemCoverNotFoundException($coverId, $lineItem->getKey());
            }

            $lineItem->setCover($cover);
        }
    }

    private function serialize($data): array
    {
        $decoded = $this->serializer->normalize($data);

        return [
            'data' => $decoded,
        ];
    }
}

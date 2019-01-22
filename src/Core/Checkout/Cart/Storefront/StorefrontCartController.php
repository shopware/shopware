<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Storefront;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemCoverNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotRemovableException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Cart\ProductCollector;
use Shopware\Core\Framework\Api\Response\Type\Storefront\JsonType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\Exception\MissingParameterException;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;

class StorefrontCartController extends AbstractController
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
     * @Route("/storefront-api/v{version}/checkout/cart", name="storefront-api.checkout.cart.detail", methods={"GET"})
     *
     * @throws CartTokenNotFoundException
     */
    public function getCart(Request $request, CheckoutContext $context): JsonResponse
    {
        $token = $request->query->getAlnum('token', $context->getToken());
        $name = $request->query->getAlnum('name', CartService::STOREFRONT);

        $cart = $this->cartService->getCart($token, $context, $name);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/storefront-api/v{version}/checkout/cart", name="storefront-api.checkout.cart.create", methods={"POST"})
     *
     * @throws CartTokenNotFoundException
     */
    public function createCart(Request $request, CheckoutContext $context): JsonResponse
    {
        $token = $request->request->getAlnum('token', $context->getToken());
        $name = $request->request->getAlnum('name', CartService::STOREFRONT);

        $this->cartService->createNew($token, $name);

        return new JsonResponse(
            [PlatformRequest::HEADER_CONTEXT_TOKEN => $context->getToken()],
            JsonResponse::HTTP_OK,
            [PlatformRequest::HEADER_CONTEXT_TOKEN => $context->getToken()]
        );
    }

    /**
     * @Route("/storefront-api/v{version}/checkout/cart/product/{id}", name="storefront-api.checkout.frontend.cart.product.add", methods={"POST"})
     *
     * @throws MixedLineItemTypeException
     * @throws InvalidQuantityException
     * @throws InvalidPayloadException
     * @throws CartTokenNotFoundException
     */
    public function addProduct(string $id, Request $request, CheckoutContext $context): JsonResponse
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
     * @Route("/storefront-api/v{version}/checkout/cart/line-item/{id}", name="storefront-api.checkout.cart.line-item.add", methods={"POST"})
     *
     * @throws MissingParameterException
     * @throws MixedLineItemTypeException
     * @throws InvalidQuantityException
     * @throws LineItemCoverNotFoundException
     * @throws LineItemNotStackableException
     * @throws InvalidPayloadException
     * @throws CartTokenNotFoundException
     */
    public function addLineItem(string $id, Request $request, CheckoutContext $context): JsonResponse
    {
        // todo support price definition (NEXT-573)
        $token = $request->request->getAlnum('token', $context->getToken());

        $type = $request->request->getAlnum('type');
        $quantity = $request->request->getInt('quantity', 1);
        $request->request->remove('quantity');

        if (!$type) {
            throw new MissingParameterException('type');
        }

        $lineItem = new LineItem($id, $type, $quantity);
        $this->updateLineItemByRequest($lineItem, $request, $context->getContext());

        $cart = $this->cartService->add($this->cartService->getCart($token, $context), $lineItem, $context);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/storefront-api/v{version}/checkout/cart/line-item/{id}", name="storefront-api.checkout.cart.line-item.delete", methods={"DELETE"})
     *
     * @throws LineItemNotFoundException
     * @throws LineItemNotRemovableException
     * @throws CartTokenNotFoundException
     */
    public function removeLineItem(string $id, Request $request, CheckoutContext $context): JsonResponse
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
     * @Route("/storefront-api/v{version}/checkout/cart/line-item/{id}", name="storefront-api.checkout.cart.line-item.update", methods={"PATCH"})
     *
     * @throws InvalidQuantityException
     * @throws LineItemNotFoundException
     * @throws LineItemNotStackableException
     * @throws LineItemCoverNotFoundException
     * @throws CartTokenNotFoundException
     * @throws InvalidPayloadException
     */
    public function updateLineItem(string $id, Request $request, CheckoutContext $context): JsonResponse
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
            $cover = $this->mediaRepository->read(new ReadCriteria([$coverId]), $context)->get($coverId);

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
            'data' => JsonType::format($decoded),
        ];
    }
}

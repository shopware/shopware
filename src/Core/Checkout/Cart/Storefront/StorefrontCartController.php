<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Storefront;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemCoverNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotRemoveableException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Content\Product\Cart\ProductCollector;
use Shopware\Core\Framework\Api\Response\Type\JsonType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Exception\MissingParameterException;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Serializer;

class StorefrontCartController extends Controller
{
    public const CART_NAME = CartService::CART_NAME;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var RepositoryInterface
     */
    private $orderRepository;

    /**
     * @var RepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var CheckoutContextPersister
     */
    private $contextPersister;

    public function __construct(
        CartService $service,
        RepositoryInterface $orderRepository,
        RepositoryInterface $mediaRepository,
        Serializer $serializer,
        CheckoutContextPersister $contextPersister
    ) {
        $this->orderRepository = $orderRepository;
        $this->serializer = $serializer;
        $this->contextPersister = $contextPersister;
        $this->cartService = $service;
        $this->mediaRepository = $mediaRepository;
    }

    /**
     * @Route("/storefront-api/checkout/cart", name="storefront.api.checkout.cart.get")
     * @Method({"GET"})
     */
    public function getCart(CheckoutContext $context): JsonResponse
    {
        $cart = $this->cartService->getCart($context);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/storefront-api/checkout/cart", name="storefront.api.checkout.cart.create")
     * @Method({"POST"})
     */
    public function createCart(CheckoutContext $context): JsonResponse
    {
        $this->cartService->createNew($context);

        return new JsonResponse(
            [PlatformRequest::HEADER_CONTEXT_TOKEN => $context->getToken()],
            JsonResponse::HTTP_OK,
            [PlatformRequest::HEADER_CONTEXT_TOKEN => $context->getToken()]
        );
    }

    /**
     * @Route("/storefront-api/checkout/cart/product/{id}", name="storefront.api.checkout.cart.product.add")
     * @Method({"POST"})
     *
     * @throws MixedLineItemTypeException
     * @throws InvalidQuantityException
     */
    public function addProduct(string $id, Request $request, CheckoutContext $context): JsonResponse
    {
        $quantity = $request->request->getInt('quantity', 1);
        $payload = $request->request->get('payload', []);
        $payload = array_replace_recursive(['id' => $id], $payload);

        $lineItem = (new LineItem($id, ProductCollector::LINE_ITEM_TYPE, $quantity))
            ->setPayload($payload)
            ->setRemoveable(true)
            ->setStackable(true);

        $cart = $this->cartService->add($lineItem, $context);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/storefront-api/checkout/cart/line-item/{id}", name="storefront.api.checkout.cart.line-item.add")
     * @Method({"POST"})
     *
     * @throws MissingParameterException
     * @throws MixedLineItemTypeException
     * @throws InvalidQuantityException
     * @throws LineItemCoverNotFoundException
     * @throws LineItemNotFoundException
     * @throws LineItemNotStackableException
     */
    public function addLineItem(string $id, Request $request, CheckoutContext $context): JsonResponse
    {
        // todo support price definition (NEXT-528)
        $type = $request->request->getAlnum('type', null);
        $quantity = $request->request->getInt('quantity', 1);
        $request->request->remove('quantity');

        if (!$type) {
            throw new MissingParameterException('type');
        }
        if (!$id) {
            throw new MissingParameterException('id');
        }

        $lineItem = new LineItem($id, $type, $quantity);
        $this->updateLineItemByRequest($lineItem, $request, $context->getContext());

        $cart = $this->cartService->add($lineItem, $context);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/storefront-api/checkout/cart/line-item/{id}", name="storefront.api.checkout.cart.line-item.delete")
     * @Method({"DELETE"})
     *
     * @throws LineItemNotFoundException
     * @throws LineItemNotRemoveableException
     */
    public function removeLineItem(string $id, CheckoutContext $context): JsonResponse
    {
        $cart = $this->cartService->getCart($context);

        if (!$cart->has($id)) {
            throw new LineItemNotFoundException($id);
        }

        $cart = $this->cartService->remove($id, $context);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/storefront-api/checkout/cart/line-item/{id}/quantity/{quantity}", name="storefront.api.checkout.cart.line-item.quatity.update")
     * @Method({"PATCH"})
     *
     * @throws LineItemNotFoundException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     */
    public function setLineItemQuantity(string $id, int $quantity, CheckoutContext $context): JsonResponse
    {
        $cart = $this->cartService->getCart($context);

        if (!$cart->has($id)) {
            throw new LineItemNotFoundException($id);
        }

        $cart = $this->cartService->changeQuantity($id, $quantity, $context);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/storefront-api/checkout/cart/line-item/{id}", name="storefront.api.checkout.cart.line-item.update")
     * @Method({"PATCH"})
     *
     * @throws InvalidQuantityException
     * @throws LineItemNotFoundException
     * @throws LineItemNotStackableException
     * @throws LineItemCoverNotFoundException
     */
    public function updateLineItem(string $id, Request $request, CheckoutContext $context): JsonResponse
    {
        $cart = $this->cartService->getCart($context);

        if (!$cart->has($id)) {
            throw new LineItemNotFoundException($id);
        }

        $lineItem = $this->cartService->getCart($context)->getLineItems()->get($id);

        $this->updateLineItemByRequest($lineItem, $request, $context->getContext());

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/storefront-api/checkout/order", name="storefront.api.checkout.order")
     * @Method({"POST"})
     */
    public function createOrder(CheckoutContext $context): JsonResponse
    {
        $orderId = $this->cartService->order($context);

        $criteria = new ReadCriteria([$orderId]);
        $order = $this->orderRepository->read($criteria, $context->getContext());

        $this->contextPersister->save($context->getToken(), ['cartToken' => null], $context->getTenantId());

        return new JsonResponse(
            $this->serialize($order->get($orderId))
        );
    }

    /**
     * @throws InvalidQuantityException
     * @throws LineItemCoverNotFoundException
     * @throws LineItemNotStackableException
     */
    private function updateLineItemByRequest(LineItem $lineItem, Request $request, Context $context)
    {
        $quantity = $request->request->get('quantity', null);
        $payload = $request->request->get('payload', []);
        $payload = array_replace_recursive(['id' => $lineItem->getKey()], $payload);
        $stackable = $request->request->get('stackable', null);
        $removeable = $request->request->get('removeable', null);
        $priority = $request->request->get('priority', null);
        $label = $request->request->get('label', null);
        $description = $request->request->get('description', null);
        $coverId = $request->request->get('coverId', null);

        $lineItem->setPayload($payload);

        if ($quantity) {
            $lineItem->setQuantity($quantity);
        }

        if ($stackable !== null) {
            $lineItem->setStackable($stackable);
        }

        if ($removeable !== null) {
            $lineItem->setRemoveable($removeable);
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

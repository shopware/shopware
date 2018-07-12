<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Storefront;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Content\Product\Cart\ProductCollector;
use Shopware\Core\Framework\Api\Response\Type\JsonType;
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
        Serializer $serializer,
        CheckoutContextPersister $contextPersister
    ) {
        $this->orderRepository = $orderRepository;
        $this->serializer = $serializer;
        $this->contextPersister = $contextPersister;
        $this->cartService = $service;
    }

    /**
     * @Route("/storefront-api/checkout/cart", name="storefront.api.checkout.cart.get")
     * @Method({"GET"})
     *
     * @param CheckoutContext $context
     *
     * @return JsonResponse
     */
    public function getCart(CheckoutContext $context): JsonResponse
    {
        $cart = $this->cartService->getCart($context);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/storefront-api/checkout/cart", name="storefront.api.checkout.cart.create")
     * @Method({"POST"})
     *
     * @param CheckoutContext $context
     *
     * @return JsonResponse
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
     * @param string          $id
     * @param Request         $request
     * @param CheckoutContext $context
     *
     * @return JsonResponse
     */
    public function addProduct(string $id, Request $request, CheckoutContext $context): JsonResponse
    {
        $quantity = $request->request->getInt('quantity', 1);
        $payload = $request->request->get('payload', []);
        $payload = array_replace_recursive(['id' => $id], $payload);

        $lineItem = (new LineItem($id, ProductCollector::LINE_ITEM_TYPE, $quantity))
            ->setPayload($payload);

        $cart = $this->cartService->add($lineItem, $context);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/storefront-api/checkout/cart/line-item/{id}", name="storefront.api.checkout.cart.line-item.add")
     * @Method({"POST"})
     *
     * @param string          $id
     * @param Request         $request
     * @param CheckoutContext $context
     *
     * @throws MissingParameterException
     *
     * @return JsonResponse
     */
    public function addLineItem(string $id, Request $request, CheckoutContext $context): JsonResponse
    {
        $quantity = $request->request->getInt('quantity', 1);
        $type = $request->request->getAlnum('type', null);
        $payload = $request->request->get('payload', []);
        $payload = array_replace_recursive(['id' => $id], $payload);

        if (!$type) {
            throw new MissingParameterException('type');
        }
        if (!$id) {
            throw new MissingParameterException('id');
        }

        $lineItem = (new LineItem($id, $type, $quantity))
            ->setPayload($payload);

        $cart = $this->cartService->add($lineItem, $context);

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/storefront-api/checkout/cart/line-item/{id}", name="storefront.api.checkout.cart.line-item.delete")
     * @Method({"DELETE"})
     *
     * @param string          $id
     * @param CheckoutContext $context
     *
     * @throws LineItemNotFoundException
     *
     * @return JsonResponse
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
     * @param string          $id
     * @param int             $quantity
     * @param CheckoutContext $context
     *
     * @throws LineItemNotFoundException
     *
     * @return JsonResponse
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
     * @param string          $id
     * @param Request         $request
     * @param CheckoutContext $context
     *
     * @throws LineItemNotFoundException
     *
     * @return JsonResponse
     */
    public function updateLineItem(string $id, Request $request, CheckoutContext $context): JsonResponse
    {
        $cart = $this->cartService->getCart($context);

        if (!$cart->has($id)) {
            throw new LineItemNotFoundException($id);
        }

        $quantity = $request->request->getInt('quantity', null);

        if ($quantity) {
            $cart = $this->cartService->changeQuantity($id, $quantity, $context);
        }

        return new JsonResponse($this->serialize($cart));
    }

    /**
     * @Route("/storefront-api/checkout/order", name="storefront.api.checkout.order")
     * @Method({"POST"})
     *
     * @param CheckoutContext $context
     *
     * @return JsonResponse
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

    private function serialize($data): array
    {
        $decoded = $this->serializer->normalize($data);

        return [
            'data' => JsonType::format($decoded),
        ];
    }
}

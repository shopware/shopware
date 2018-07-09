<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Storefront;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Core\Checkout\Cart\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Cart\CircularCartCalculation;
use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderPersisterInterface;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Product\Cart\ProductProcessor;
use Shopware\Core\Framework\Api\Response\Type\JsonType;
use Shopware\Core\Framework\Exception\MissingParameterException;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Serializer;

class StorefrontCartController extends Controller
{
    public const CART_NAME = CartService::CART_NAME;

    /**
     * @var CircularCartCalculation
     */
    private $calculation;

    /**
     * @var CartPersisterInterface
     */
    private $persister;

    /**
     * @var OrderPersisterInterface
     */
    private $orderPersister;

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
        CircularCartCalculation $calculation,
        CartPersisterInterface $persister,
        OrderPersisterInterface $orderPersister,
        RepositoryInterface $orderRepository,
        Serializer $serializer,
        CheckoutContextPersister $contextPersister
    ) {
        $this->calculation = $calculation;
        $this->persister = $persister;
        $this->orderPersister = $orderPersister;
        $this->orderRepository = $orderRepository;
        $this->serializer = $serializer;
        $this->contextPersister = $contextPersister;
    }

    /**
     * @Route("/storefront-api/checkout/cart", name="storefront.api.checkout.cart.get")
     * @Method({"GET"})
     */
    public function getCart(CheckoutContext $context): JsonResponse
    {
        $cart = $this->loadCart($context->getToken(), $context);

        $calculated = $this->calculation->calculate($cart, $context);

        return new JsonResponse(
            $this->serialize($calculated)
        );
    }

    /**
     * @Route("/storefront-api/checkout/cart", name="storefront.api.checkout.cart.create")
     * @Method({"POST"})
     */
    public function createCart(CheckoutContext $context): JsonResponse
    {
        $this->persister->delete($context->getToken(), self::CART_NAME, $context);

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
     * @throws InvalidQuantityException
     */
    public function addProduct(string $id, Request $request, CheckoutContext $context): JsonResponse
    {
        $quantity = $request->request->getInt('quantity', 1);
        $payload = $request->request->get('payload', []);
        $payload = array_replace_recursive(['id' => $id], $payload);

        $calculated = $this->addLineItemToCart($context, $id, ProductProcessor::TYPE_PRODUCT, $quantity, $payload);

        return new JsonResponse(
            $this->serialize($calculated)
        );
    }

    /**
     * @Route("/storefront-api/checkout/cart/line-item/{id}", name="storefront.api.checkout.cart.line-item.add")
     * @Method({"POST"})
     *
     * @throws InvalidQuantityException
     * @throws MissingParameterException
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

        $calculated = $this->addLineItemToCart($context, $id, $type, $quantity, $payload);

        return new JsonResponse(
            $this->serialize($calculated)
        );
    }

    /**
     * @Route("/storefront-api/checkout/cart/line-item/{id}", name="storefront.api.checkout.cart.line-item.delete")
     * @Method({"DELETE"})
     *
     * @throws LineItemNotFoundException
     */
    public function removeLineItem(string $id, CheckoutContext $context): JsonResponse
    {
        $cart = $this->loadCart($context->getToken(), $context);

        if (!$lineItem = $cart->getLineItems()->get($id)) {
            throw new LineItemNotFoundException($id);
        }

        $cart->getLineItems()->remove($id);

        $calculated = $this->calculation->calculate($cart, $context);
        $this->saveCart($calculated, $context);

        return new JsonResponse(
            $this->serialize($calculated)
        );
    }

    /**
     * @Route("/storefront-api/checkout/cart/line-item/{id}/quantity/{quantity}", name="storefront.api.checkout.cart.line-item.quatity.update")
     * @Method({"PATCH"})
     *
     * @throws LineItemNotFoundException
     */
    public function setLineItemQuantity(string $id, int $quantity, CheckoutContext $context): JsonResponse
    {
        $cart = $this->loadCart($context->getToken(), $context);

        if (!$lineItem = $cart->getLineItems()->get($id)) {
            throw new LineItemNotFoundException($id);
        }

        $lineItem->setQuantity($quantity);

        $calculated = $this->calculation->calculate($cart, $context);
        $this->saveCart($calculated, $context);

        return new JsonResponse(
            $this->serialize($calculated)
        );
    }

    /**
     * @Route("/storefront-api/checkout/cart/line-item/{id}", name="storefront.api.checkout.cart.line-item.update")
     * @Method({"PATCH"})
     *
     * @throws LineItemNotFoundException
     */
    public function updateLineItem(string $id, Request $request, CheckoutContext $context): JsonResponse
    {
        $cart = $this->loadCart($context->getToken(), $context);

        if (!$lineItem = $cart->getLineItems()->get($id)) {
            throw new LineItemNotFoundException($id);
        }

        $quantity = $request->request->getInt('quantity', null);

        if ($quantity) {
            $lineItem->setQuantity($quantity);
        }

        $calculated = $this->calculation->calculate($cart, $context);
        $this->saveCart($calculated, $context);

        return new JsonResponse(
            $this->serialize($calculated)
        );
    }

    /**
     * @Route("/storefront-api/checkout/order", name="storefront.api.checkout.order.create")
     * @Method({"POST"})
     */
    public function createOrder(CheckoutContext $context): JsonResponse
    {
        $cart = $this->loadCart($context->getToken(), $context);
        $calculated = $this->calculation->calculate($cart, $context);

        $events = $this->orderPersister->persist($calculated, $context);
        $orders = $events->getEventByDefinition(OrderDefinition::class);
        $ids = $orders->getIds();
        $orderId = array_shift($ids);

        $order = $this->orderRepository->read(new ReadCriteria([$orderId]), $context->getContext());

        $this->contextPersister->save($context->getToken(), ['cartToken' => null], $context->getTenantId());

        return new JsonResponse(
            $this->serialize($order->get($orderId))
        );
    }

    private function loadCart(?string $token, CheckoutContext $context): Cart
    {
        if (!$token) {
            $token = Uuid::uuid4()->getHex();
        }

        try {
            $cart = $this->persister->load($token, self::CART_NAME, $context);
        } catch (CartTokenNotFoundException $e) {
            return Cart::createNew(self::CART_NAME, $token);
        }

        return $cart;
    }

    private function serialize($data): array
    {
        $decoded = $this->serializer->normalize($data);

        return [
            'data' => JsonType::format($decoded),
        ];
    }

    private function saveCart(CalculatedCart $calculated, CheckoutContext $context): void
    {
        $this->persister->save($calculated, $context);
    }

    /**
     * @throws InvalidQuantityException
     */
    private function addLineItemToCart(CheckoutContext $context, string $identifier, string $type, int $quantity, array $payload): CalculatedCart
    {
        $cart = $this->loadCart($context->getToken(), $context);

        $lineItem = new LineItem($identifier, $type, $quantity, $payload);
        $cart->getLineItems()->add($lineItem);

        $calculated = $this->calculation->calculate($cart, $context);
        $this->saveCart($calculated, $context);

        return $calculated;
    }
}

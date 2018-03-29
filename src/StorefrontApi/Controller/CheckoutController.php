<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\Order\Definition\OrderDefinition;
use Shopware\Api\Order\Repository\OrderRepository;
use Shopware\Cart\Cart\CartPersisterInterface;
use Shopware\Cart\Cart\CircularCartCalculation;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\Cart;
use Shopware\Cart\Exception\CartTokenNotFoundException;
use Shopware\Cart\Exception\LineItemNotFoundException;
use Shopware\Cart\LineItem\LineItem;
use Shopware\Cart\Order\OrderPersisterInterface;
use Shopware\CartBridge\Product\ProductProcessor;
use Shopware\CartBridge\Service\StoreFrontCartService;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Application\ApplicationResolver;
use Shopware\Framework\Struct\Uuid;
use Shopware\Rest\Response\Type\JsonType;
use Shopware\StorefrontApi\Context\StorefrontContextPersister;
use Shopware\StorefrontApi\Context\StorefrontContextValueResolver;
use Shopware\StorefrontApi\Firewall\ApplicationAuthenticator;
use Shopware\StorefrontApi\Firewall\ContextUser;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Serializer\Serializer;

class CheckoutController extends Controller
{
    public const CART_NAME = StoreFrontCartService::CART_NAME;

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
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var StorefrontContextPersister
     */
    private $contextPersister;

    public function __construct(
        CircularCartCalculation $calculation,
        CartPersisterInterface $persister,
        OrderPersisterInterface $orderPersister,
        OrderRepository $orderRepository,
        Serializer $serializer,
        StorefrontContextPersister $contextPersister
    ) {
        $this->calculation = $calculation;
        $this->persister = $persister;
        $this->orderPersister = $orderPersister;
        $this->orderRepository = $orderRepository;
        $this->serializer = $serializer;
        $this->contextPersister = $contextPersister;
    }

    /**
     * @Route("/storefront-api/checkout", name="storefront.api.checkout.get")
     * @Method({"GET"})
     */
    public function getAction(): JsonResponse
    {
        /** @var ContextUser $user */
        $user = $this->getUser();

        $cart = $this->loadCart($user->getContextToken());

        $calculated = $this->calculation->calculate($cart, $user->getContext());

        return new JsonResponse(
            $this->serialize($calculated)
        );
    }

    /**
     * @Route("/storefront-api/checkout", name="storefront.api.checkout.create")
     * @Method({"POST"})
     */
    public function createAction(): JsonResponse
    {
        /** @var ContextUser $user */
        $user = $this->getUser();

        $this->persister->delete($user->getContext()->getToken(), self::CART_NAME);

        return new JsonResponse(
            [ApplicationResolver::CONTEXT_HEADER => $user->getContextToken()],
            JsonResponse::HTTP_OK,
            [ApplicationResolver::CONTEXT_HEADER => $user->getContextToken()]
        );
    }

    /**
     * @Route("/storefront-api/checkout/add-product/{identifier}", name="storefront.api.checkout.add.product")
     * @Method({"POST"})
     */
    public function addProductAction(string $identifier, Request $request): JsonResponse
    {
        /** @var ContextUser $user */
        $user = $this->getUser();

        $post = $this->getPost($request);

        $quantity = isset($post['quantity']) ? (int) $post['quantity'] : 1;

        $payload = isset($post['payload']) ? $post['payload'] : [];

        $payload = array_replace_recursive(['id' => $identifier], $payload);

        $calculated = $this->addLineItem($user->getContext(), $identifier, ProductProcessor::TYPE_PRODUCT, $quantity, $payload);

        return new JsonResponse(
            $this->serialize($calculated)
        );
    }

    /**
     * @Route("/storefront-api/checkout/add", name="storefront.api.checkout.add")
     * @Method({"POST"})
     */
    public function addAction(Request $request): JsonResponse
    {
        /** @var ContextUser $user */
        $user = $this->getUser();

        $post = $this->getPost($request);

        if (!isset($post['identifier'])) {
            throw new InvalidParameterException('Parameter identifier missing');
        }
        if (!isset($post['type'])) {
            throw new InvalidParameterException('Parameter type missing');
        }
        if (!isset($post['quantity'])) {
            throw new InvalidParameterException('Parameter quantity missing');
        }
        if (!isset($post['payload'])) {
            throw new InvalidParameterException('Parameter payload missing');
        }

        $identifier = $post['identifier'];
        $quantity = (int) $post['quantity'];
        $type = $post['type'];
        $payload = $post['payload'];

        $calculated = $this->addLineItem($user->getContext(), $identifier, $type, $quantity, $payload);

        return new JsonResponse(
            $this->serialize($calculated)
        );
    }

    /**
     * @Route("/storefront-api/checkout/{identifier}", name="storefront.api.checkout.delete")
     * @Method({"DELETE"})
     */
    public function removeAction(string $identifier): JsonResponse
    {
        /** @var ContextUser $user */
        $user = $this->getUser();

        $cart = $this->loadCart($user->getContextToken());

        if (!$lineItem = $cart->getLineItems()->get($identifier)) {
            throw new LineItemNotFoundException($identifier);
        }

        $cart->getLineItems()->remove($identifier);

        $calculated = $this->calculation->calculate($cart, $user->getContext());

        $this->save($calculated, $user->getContext());

        return new JsonResponse(
            $this->serialize($calculated)
        );
    }

    /**
     * @Route("/storefront-api/checkout/set-quantity/{identifier}", name="storefront.api.checkout.set-quantity")
     * @Method({"PUT"})
     */
    public function setQuantityAction(string $identifier, Request $request): JsonResponse
    {
        /** @var ContextUser $user */
        $user = $this->getUser();

        $cart = $this->loadCart($user->getContextToken());

        $post = $this->getPost($request);

        if (!isset($post['quantity'])) {
            throw new \InvalidArgumentException('Parameter quantity missing');
        }

        if (!$lineItem = $cart->getLineItems()->get($identifier)) {
            throw new LineItemNotFoundException($identifier);
        }

        $quantity = (int) $post['quantity'];

        $lineItem->setQuantity($quantity);

        $calculated = $this->calculation->calculate($cart, $user->getContext());

        $this->save($calculated, $user->getContext());

        return new JsonResponse(
            $this->serialize($calculated)
        );
    }

    /**
     * @Route("/storefront-api/checkout/order", name="storefront.api.checkout.order")
     * @Method({"POST"})
     */
    public function orderAction(): JsonResponse
    {
        /** @var ContextUser $user */
        $user = $this->getUser();

        $cart = $this->loadCart($user->getContextToken());

        $calculated = $this->calculation->calculate($cart, $user->getContext());

        $events = $this->orderPersister->persist($calculated, $user->getContext());

        $orders = $events->getEventByDefinition(OrderDefinition::class);

        $ids = $orders->getIds();

        $orderId = array_shift($ids);

        $order = $this->orderRepository->readDetail([$orderId], $user->getContext()->getApplicationContext());

        $this->contextPersister->save($user->getContextToken(), ['cartToken' => null]);

        return new JsonResponse(
            $this->serialize($order->get($orderId))
        );
    }

    private function loadCart(?string $token): Cart
    {
        if (!$token) {
            $token = Uuid::uuid4()->getHex();
        }

        try {
            $cart = $this->persister->load($token, self::CART_NAME);
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

    private function save(CalculatedCart $calculated, StorefrontContext $context): void
    {
        $this->persister->save($calculated, $context);
    }

    private function getPost(Request $request): array
    {
        if (empty($request->getContent())) {
            return [];
        }

        return $this->serializer->decode($request->getContent(), 'json');
    }

    private function addLineItem(StorefrontContext $context, string $identifier, string $type, int $quantity, array $payload): CalculatedCart
    {
        $cart = $this->loadCart($context->getToken());

        $lineItem = new LineItem($identifier, $type, $quantity, $payload);

        $cart->getLineItems()->add($lineItem);

        $calculated = $this->calculation->calculate($cart, $context);

        $this->save($calculated, $context);

        return $calculated;
    }
}

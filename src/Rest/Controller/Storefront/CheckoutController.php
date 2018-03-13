<?php declare(strict_types=1);

namespace Shopware\Rest\Controller\Storefront;

use Ramsey\Uuid\Uuid;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\Order\Repository\OrderRepository;
use Shopware\Cart\Cart\CartPersisterInterface;
use Shopware\Cart\Cart\CircularCartCalculation;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\Cart;
use Shopware\Cart\Exception\CartTokenNotFoundException;
use Shopware\Cart\Exception\LineItemNotFoundException;
use Shopware\Cart\LineItem\LineItem;
use Shopware\Cart\Order\OrderPersisterInterface;
use Shopware\CartBridge\Service\StoreFrontCartService;
use Shopware\Rest\Context\ApiStorefrontContext;
use Shopware\Rest\Context\ApiStorefrontContextPersister;
use Shopware\Rest\Context\ApiStorefrontContextValueResolver;
use Shopware\Rest\Response\Type\JsonType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
     * @var ApiStorefrontContextPersister
     */
    private $contextPersister;

    public function __construct(
        CircularCartCalculation $calculation,
        CartPersisterInterface $persister,
        OrderPersisterInterface $orderPersister,
        OrderRepository $orderRepository,
        Serializer $serializer,
        ApiStorefrontContextPersister $contextPersister
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
     *
     * @param \Shopware\Rest\Context\ApiStorefrontContext $context
     *
     * @return JsonResponse
     */
    public function getAction(ApiStorefrontContext $context)
    {
        $cart = $this->loadCart($context->getCartToken());

        $calculated = $this->calculation->calculate($cart, $context);

        return new JsonResponse(
            $this->serialize($calculated)
        );
    }

    /**
     * @Route("/storefront-api/checkout", name="storefront.api.checkout.create")
     * @Method({"POST"})
     *
     * @param ApiStorefrontContext $context
     *
     * @return JsonResponse
     */
    public function createAction(ApiStorefrontContext $context)
    {
        $cart = Cart::createNew(self::CART_NAME);

        $calculated = $this->calculation->calculate($cart, $context);

        $this->persister->save($calculated, $context);

        $this->save($calculated, $context);

        return new JsonResponse([
            ApiStorefrontContextValueResolver::CONTEXT_TOKEN_KEY => $context->getContextHash(),
        ]);
    }

    /**
     * @Route("/storefront-api/checkout", name="storefront.api.checkout.add")
     * @Method({"PUT"})
     *
     * @param Request                                     $request
     * @param \Shopware\Rest\Context\ApiStorefrontContext $context
     *
     * @return JsonResponse
     */
    public function addAction(Request $request, ApiStorefrontContext $context)
    {
        $cart = $this->loadCart($context->getCartToken());

        $post = $this->getPost($request);

        if (!isset($post['identifier'])) {
            throw new InvalidParameterException('Parameter identifier missing');
        }
        if (!isset($post['type'])) {
            throw new InvalidParameterException('Parameter type missing');
        }
        if (!isset($post['quantity'])) {
            throw new InvalidParameterException('Parameter type missing');
        }
        if (!isset($post['payload'])) {
            throw new InvalidParameterException('Parameter type missing');
        }

        $lineItem = new LineItem(
            $post['identifier'],
            $post['type'],
            (int) $post['quantity'],
            $post['payload']
        );

        $cart->getLineItems()->add($lineItem);

        $calculated = $this->calculation->calculate($cart, $context);

        $this->save($calculated, $context);

        return new JsonResponse(
            $this->serialize($calculated)
        );
    }

    /**
     * @Route("/storefront-api/checkout/{identifier}", name="storefront.api.checkout.delete")
     * @Method({"DELETE"})
     *
     * @param string                                      $identifier
     * @param \Shopware\Rest\Context\ApiStorefrontContext $context
     *
     * @throws LineItemNotFoundException
     *
     * @return JsonResponse
     */
    public function removeAction(string $identifier, ApiStorefrontContext $context)
    {
        $cart = $this->loadCart($context->getCartToken());

        if (!$lineItem = $cart->getLineItems()->get($identifier)) {
            throw new LineItemNotFoundException($identifier);
        }

        $cart->getLineItems()->remove($identifier);

        $calculated = $this->calculation->calculate($cart, $context);

        $this->save($calculated, $context);

        return new JsonResponse(
            $this->serialize($calculated)
        );
    }

    /**
     * @Route("/storefront-api/checkout/{identifier}/{quantity}", name="storefront.api.checkout.set-quantity")
     * @Method({"PUT"})
     *
     * @param string                                      $identifier
     * @param int                                         $quantity
     * @param \Shopware\Rest\Context\ApiStorefrontContext $context
     *
     * @throws LineItemNotFoundException
     *
     * @return JsonResponse
     */
    public function setQuantityAction(string $identifier, int $quantity, ApiStorefrontContext $context)
    {
        $cart = $this->loadCart($context->getCartToken());

        if (!$lineItem = $cart->getLineItems()->get($identifier)) {
            throw new LineItemNotFoundException($identifier);
        }

        $lineItem->setQuantity($quantity);

        $calculated = $this->calculation->calculate($cart, $context);

        $this->save($calculated, $context);

        return new JsonResponse(
            $this->serialize($calculated)
        );
    }

    /**
     * @Route("/storefront-api/checkout/order", name="storefront.api.checkout.order")
     * @Method({"POST"})
     *
     * @param \Shopware\Rest\Context\ApiStorefrontContext $context
     *
     * @return Response
     */
    public function orderAction(ApiStorefrontContext $context)
    {
        $cart = $this->loadCart($context->getCartToken());

        $calculated = $this->calculation->calculate($cart, $context);

        $orderId = $this->orderPersister->persist($calculated, $context);

        $order = $this->orderRepository->readDetail([$orderId], $context->getShopContext());

        $this->contextPersister->save($context->getContextHash(), ['cartToken' => null]);

        return new JsonResponse(
            $this->serialize($order->get($orderId))
        );
    }

    /**
     * @param string $token
     *
     * @return Cart
     */
    private function loadCart(?string $token): Cart
    {
        if (!$token) {
            $token = Uuid::uuid4()->toString();
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

    private function save(CalculatedCart $calculated, ApiStorefrontContext $context): void
    {
        $this->persister->save($calculated, $context);

        $this->contextPersister->save(
            $context->getContextHash(),
            ['cartToken' => $calculated->getToken()]
        );
    }

    private function getPost(Request $request): array
    {
        return $this->serializer->decode($request->getContent(), 'json');
    }
}

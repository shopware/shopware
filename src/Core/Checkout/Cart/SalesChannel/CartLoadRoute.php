<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Event\CartCreatedEvent;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class CartLoadRoute extends AbstractCartLoadRoute
{
    /**
     * @var CartPersisterInterface
     */
    private $persister;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CartCalculator
     */
    private $cartCalculator;

    public function __construct(CartPersisterInterface $persister, EventDispatcherInterface $eventDispatcher, CartCalculator $cartCalculator)
    {
        $this->persister = $persister;
        $this->eventDispatcher = $eventDispatcher;
        $this->cartCalculator = $cartCalculator;
    }

    public function getDecorated(): AbstractCartLoadRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Get(
     *      path="/checkout/cart",
     *      description="Fetch current cart",
     *      operationId="readCart",
     *      tags={"Store API", "Cart"},
     *      @OA\Response(
     *          response="200",
     *          description="Cart",
     *          @OA\JsonContent(ref="#/definitions/Cart")
     *     )
     * )
     * @Route("/store-api/v{version}/checkout/cart", name="store-api.checkout.cart.read", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context): CartResponse
    {
        $name = $request->get('name', CartService::SALES_CHANNEL);
        $token = $request->get('token', $context->getToken());

        try {
            $cart = $this->persister->load($token, $context);
        } catch (CartTokenNotFoundException $e) {
            $cart = $this->createNew($token, $name);
        }

        return new CartResponse($this->cartCalculator->calculate($cart, $context));
    }

    private function createNew($token, $name): Cart
    {
        $cart = new Cart($name, $token);

        $this->eventDispatcher->dispatch(new CartCreatedEvent($cart));

        return $cart;
    }
}

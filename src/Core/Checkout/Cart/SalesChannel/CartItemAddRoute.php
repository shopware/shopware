<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Event\LineItemAddedEvent;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class CartItemAddRoute extends AbstractCartItemAddRoute
{
    /**
     * @var CartCalculator
     */
    private $cartCalculator;

    /**
     * @var CartPersisterInterface
     */
    private $cartPersister;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var LineItemFactoryRegistry
     */
    private $lineItemFactory;

    public function __construct(
        CartCalculator $cartCalculator,
        CartPersisterInterface $cartPersister,
        EventDispatcherInterface $eventDispatcher,
        LineItemFactoryRegistry $lineItemFactory
    ) {
        $this->cartCalculator = $cartCalculator;
        $this->cartPersister = $cartPersister;
        $this->eventDispatcher = $eventDispatcher;
        $this->lineItemFactory = $lineItemFactory;
    }

    public function getDecorated(): AbstractCartItemAddRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/checkout/cart/line-item",
     *      description="Add new line item entries",
     *      operationId="addLineItem",
     *      tags={"Store API", "Cart"},
     *      @OA\RequestBody(@OA\JsonContent(ref="#/definitions/CartItems")),
     *      @OA\Response(
     *          response="200",
     *          description="Cart",
     *          @OA\JsonContent(ref="#/definitions/Cart")
     *     )
     * )
     * @Route("/store-api/v{version}/checkout/cart/line-item", name="store-api.checkout.cart.add", methods={"POST"})
     */
    public function add(Request $request, Cart $cart, SalesChannelContext $context, ?array $items): CartResponse
    {
        if ($items === null) {
            $items = [];

            foreach ($request->request->get('items', []) as $item) {
                $items[] = $this->lineItemFactory->create($item, $context);
            }
        }

        foreach ($items as $item) {
            $alreadyExists = $cart->has($item->getId());
            $cart->add($item);
            $this->eventDispatcher->dispatch(new LineItemAddedEvent($item, $cart, $context, $alreadyExists));
        }

        $cart->markModified();

        $cart = $this->cartCalculator->calculate($cart, $context);
        $this->cartPersister->save($cart, $context);

        return new CartResponse($cart);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Event\LineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class CartItemRemoveRoute extends AbstractCartItemRemoveRoute
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CartCalculator
     */
    private $cartCalculator;

    /**
     * @var CartPersisterInterface
     */
    private $cartPersister;

    public function __construct(EventDispatcherInterface $eventDispatcher, CartCalculator $cartCalculator, CartPersisterInterface $cartPersister)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->cartCalculator = $cartCalculator;
        $this->cartPersister = $cartPersister;
    }

    public function getDecorated(): AbstractCartItemRemoveRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Delete(
     *      path="/checkout/cart/line-item",
     *      description="Remove line item entries",
     *      operationId="removeLineItem",
     *      tags={"Store API", "Cart"},
     *      @OA\RequestBody(@OA\JsonContent(ref="#/definitions/CartItemsDelete")),
     *      @OA\Response(
     *          response="200",
     *          description="Cart",
     *          @OA\JsonContent(ref="#/definitions/Cart")
     *     )
     * )
     * @Route("/store-api/v{version}/checkout/cart/line-item", name="store-api.checkout.cart.remove-item", methods={"DELETE"})
     */
    public function remove(Request $request, Cart $cart, SalesChannelContext $context): CartResponse
    {
        $ids = $request->get('ids');

        foreach ($ids as $id) {
            $lineItem = $cart->get($id);

            if (!$lineItem) {
                throw new LineItemNotFoundException($id);
            }

            $cart->remove($id);

            $this->eventDispatcher->dispatch(new LineItemRemovedEvent($lineItem, $cart, $context));

            $cart->markModified();
        }

        $cart = $this->cartCalculator->calculate($cart, $context);
        $this->cartPersister->save($cart, $context);

        return new CartResponse($cart);
    }
}

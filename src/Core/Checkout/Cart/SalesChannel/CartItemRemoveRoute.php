<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Event\AfterLineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\Event\CartChangedEvent;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
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
     * @Since("6.3.0.0")
     * @OA\Delete(
     *      path="/checkout/cart/line-item",
     *      summary="Remove items from the cart",
     *      description="This route removes items from the cart and recalculates it.

Example: [Working with the cart - Guide](https://developer.shopware.com/docs/guides/integrations-api/store-api-guide/work-with-the-cart#deleting-items-in-the-cart)",
     *      operationId="removeLineItem",
     *      tags={"Store API", "Cart"},
     *      @OA\Parameter(
     *          name="ids",
     *          description="A list of product identifiers.",
     *          @OA\Schema(type="array",
     *              @OA\Items(type="string", pattern="^[0-9a-f]{32}$")
     *          ),
     *          in="query",
     *          required=true
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="The updated cart.",
     *          @OA\JsonContent(ref="#/components/schemas/Cart")
     *     )
     * )
     * @Route("/store-api/checkout/cart/line-item", name="store-api.checkout.cart.remove-item", methods={"DELETE"})
     */
    public function remove(Request $request, Cart $cart, SalesChannelContext $context): CartResponse
    {
        $ids = $request->get('ids');
        $lineItems = [];

        foreach ($ids as $id) {
            $lineItem = $cart->get($id);
            $lineItems[] = $lineItem;

            if (!$lineItem) {
                throw new LineItemNotFoundException($id);
            }

            $cart->remove($id);

            $this->eventDispatcher->dispatch(new BeforeLineItemRemovedEvent($lineItem, $cart, $context));

            $cart->markModified();
        }

        $cart = $this->cartCalculator->calculate($cart, $context);
        $this->cartPersister->save($cart, $context);

        $this->eventDispatcher->dispatch(new AfterLineItemRemovedEvent($lineItems, $cart, $context));

        $this->eventDispatcher->dispatch(new CartChangedEvent($cart, $context));

        return new CartResponse($cart);
    }
}

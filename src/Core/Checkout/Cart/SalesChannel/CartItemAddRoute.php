<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Event\AfterLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\CartChangedEvent;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
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
     * @Since("6.3.0.0")
     * @OA\Post(
     *      path="/checkout/cart/line-item",
     *      summary="Add items to the cart",
     *      description="This route adds items to the cart. An item can be a product or promotion for example. They are referenced by the `referencedId`-parameter.

Example: [Working with the cart - Guide](https://developer.shopware.com/docs/guides/integrations-api/store-api-guide/work-with-the-cart#adding-new-items-to-the-cart)",
     *      operationId="addLineItem",
     *      tags={"Store API", "Cart"},
     *      @OA\RequestBody(
     *          @OA\JsonContent(ref="#/components/schemas/CartItems")
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="The updated cart.",
     *          @OA\JsonContent(ref="#/components/schemas/Cart")
     *     )
     * )
     * @Route("/store-api/checkout/cart/line-item", name="store-api.checkout.cart.add", methods={"POST"})
     */
    public function add(Request $request, Cart $cart, SalesChannelContext $context, ?array $items): CartResponse
    {
        if ($items === null) {
            $items = [];

            /** @var array $item */
            foreach ($request->request->all('items') as $item) {
                $items[] = $this->lineItemFactory->create($item, $context);
            }
        }

        foreach ($items as $item) {
            $alreadyExists = $cart->has($item->getId());
            $cart->add($item);

            $this->eventDispatcher->dispatch(new BeforeLineItemAddedEvent($item, $cart, $context, $alreadyExists));
        }

        $cart->markModified();

        $cart = $this->cartCalculator->calculate($cart, $context);
        $this->cartPersister->save($cart, $context);

        $this->eventDispatcher->dispatch(new AfterLineItemAddedEvent($items, $cart, $context));
        $this->eventDispatcher->dispatch(new CartChangedEvent($cart, $context));

        return new CartResponse($cart);
    }
}

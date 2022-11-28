<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\Event\AfterLineItemQuantityChangedEvent;
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
 * @package checkout
 *
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class CartItemUpdateRoute extends AbstractCartItemUpdateRoute
{
    private AbstractCartPersister $cartPersister;

    private CartCalculator $cartCalculator;

    private LineItemFactoryRegistry $lineItemFactory;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * @internal
     */
    public function __construct(AbstractCartPersister $cartPersister, CartCalculator $cartCalculator, LineItemFactoryRegistry $lineItemFactory, EventDispatcherInterface $eventDispatcher)
    {
        $this->cartPersister = $cartPersister;
        $this->cartCalculator = $cartCalculator;
        $this->lineItemFactory = $lineItemFactory;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getDecorated(): AbstractCartItemUpdateRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.0.0")
     * @Route("/store-api/checkout/cart/line-item", name="store-api.checkout.cart.update-lineitem", methods={"PATCH"})
     */
    public function change(Request $request, Cart $cart, SalesChannelContext $context): CartResponse
    {
        $itemsToUpdate = $request->request->all('items');

        /** @var array<mixed> $item */
        foreach ($itemsToUpdate as $item) {
            $this->lineItemFactory->update($cart, $item, $context);
        }

        $cart->markModified();

        $cart = $this->cartCalculator->calculate($cart, $context);
        $this->cartPersister->save($cart, $context);

        $this->eventDispatcher->dispatch(new AfterLineItemQuantityChangedEvent($cart, $itemsToUpdate, $context));
        $this->eventDispatcher->dispatch(new CartChangedEvent($cart, $context));

        return new CartResponse($cart);
    }
}

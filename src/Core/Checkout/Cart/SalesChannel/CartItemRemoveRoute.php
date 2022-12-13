<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Event\AfterLineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\Event\CartChangedEvent;
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
class CartItemRemoveRoute extends AbstractCartItemRemoveRoute
{
    private EventDispatcherInterface $eventDispatcher;

    private CartCalculator $cartCalculator;

    private AbstractCartPersister $cartPersister;

    /**
     * @internal
     */
    public function __construct(EventDispatcherInterface $eventDispatcher, CartCalculator $cartCalculator, AbstractCartPersister $cartPersister)
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
                throw CartException::lineItemNotFound($id);
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

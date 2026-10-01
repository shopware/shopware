<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartLocker;
use Shopware\Core\Checkout\Cart\Event\AfterLineItemQuantityChangedEvent;
use Shopware\Core\Checkout\Cart\Event\CartChangedEvent;
use Shopware\Core\Checkout\Cart\Extension\CartItemUpdateRouteExtension;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CartItemUpdateRoute extends AbstractCartItemUpdateRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCartPersister $cartPersister,
        private readonly CartCalculator $cartCalculator,
        private readonly LineItemFactoryRegistry $lineItemFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CartLocker $cartLocker,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractCartItemUpdateRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/checkout/cart/line-item', name: 'store-api.checkout.cart.update-lineitem', methods: ['PATCH'])]
    public function change(Request $request, Cart $cart, SalesChannelContext $context): CartResponse
    {
        return $this->extensions->publish(
            name: CartItemUpdateRouteExtension::NAME,
            extension: new CartItemUpdateRouteExtension($request, $cart, $context),
            function: $this->_change(...),
        );
    }

    private function _change(Request $request, Cart $cart, SalesChannelContext $context): CartResponse
    {
        return $this->cartLocker->locked($context, function () use ($request, $cart, $context) {
            $itemsToUpdate = $request->request->all('items');

            foreach ($itemsToUpdate as $item) {
                $this->lineItemFactory->update($cart, $item, $context);
            }

            $cart->markModified();

            $cart = $this->cartCalculator->calculate($cart, $context);
            $this->cartPersister->save($cart, $context);

            $this->eventDispatcher->dispatch(new AfterLineItemQuantityChangedEvent($cart, $itemsToUpdate, $context));
            $this->eventDispatcher->dispatch(new CartChangedEvent($cart, $context));

            return new CartResponse($cart);
        });
    }
}

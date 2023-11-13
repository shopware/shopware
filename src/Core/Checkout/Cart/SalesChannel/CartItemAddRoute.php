<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\Event\AfterLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\CartChangedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class CartItemAddRoute extends AbstractCartItemAddRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CartCalculator $cartCalculator,
        private readonly AbstractCartPersister $cartPersister,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LineItemFactoryRegistry $lineItemFactory,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    public function getDecorated(): AbstractCartItemAddRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @param array<LineItem> $items
     */
    #[Route(path: '/store-api/checkout/cart/line-item', name: 'store-api.checkout.cart.add', methods: ['POST'])]
    public function add(Request $request, Cart $cart, SalesChannelContext $context, ?array $items): CartResponse
    {
        if ($items === null) {
            $items = [];

            /** @var array<mixed> $item */
            foreach ($request->request->all('items') as $item) {
                $items[] = $this->lineItemFactory->create($item, $context);
            }
        }

        foreach ($items as $item) {
            if ($request->getClientIp() !== null) {
                $cacheKey = ($item->getReferencedId() ?? $item->getId()) . '-' . $request->getClientIp();
                $this->rateLimiter->ensureAccepted(RateLimiter::CART_ADD_LINE_ITEM, $cacheKey);
            }

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

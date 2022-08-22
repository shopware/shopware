<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

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
 * @Route(defaults={"_routeScope"={"store-api"}})
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

    /**
     * @internal
     */
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

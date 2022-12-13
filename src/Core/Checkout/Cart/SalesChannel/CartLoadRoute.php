<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\Event\CartCreatedEvent;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
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
class CartLoadRoute extends AbstractCartLoadRoute
{
    private AbstractCartPersister $persister;

    private EventDispatcherInterface $eventDispatcher;

    private CartCalculator $cartCalculator;

    /**
     * @internal
     */
    public function __construct(
        AbstractCartPersister $persister,
        EventDispatcherInterface $eventDispatcher,
        CartCalculator $cartCalculator
    ) {
        $this->persister = $persister;
        $this->eventDispatcher = $eventDispatcher;
        $this->cartCalculator = $cartCalculator;
    }

    public function getDecorated(): AbstractCartLoadRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.0.0")
     * @Route("/store-api/checkout/cart", name="store-api.checkout.cart.read", methods={"GET", "POST"})
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

    private function createNew(string $token, string $name): Cart
    {
        $cart = new Cart($name, $token);

        $this->eventDispatcher->dispatch(new CartCreatedEvent($cart));

        return $cart;
    }
}

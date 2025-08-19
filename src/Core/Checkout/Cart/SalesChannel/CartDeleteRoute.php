<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\CartLocker;
use Shopware\Core\Checkout\Cart\Event\CartDeletedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('checkout')]
class CartDeleteRoute extends AbstractCartDeleteRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCartPersister $cartPersister,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CartLocker $cartLocker
    ) {
    }

    public function getDecorated(): AbstractCartDeleteRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/checkout/cart', name: 'store-api.checkout.cart.delete', methods: ['DELETE'])]
    public function delete(SalesChannelContext $context): NoContentResponse
    {
        return $this->cartLocker->locked($context, function () use ($context) {
            $this->cartPersister->delete($context->getToken(), $context);

            $cartDeleteEvent = new CartDeletedEvent($context);
            $this->eventDispatcher->dispatch($cartDeleteEvent);

            return new NoContentResponse();
        });
    }
}

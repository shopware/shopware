<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Event\CartCreatedEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
class CartFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?string $source = null,
    ) {
    }

    public function createNew(string $token): Cart
    {
        $cart = new Cart(token: $token, new: true);

        if ($this->source) {
            $cart->setSource($this->source);
        }

        $this->eventDispatcher->dispatch(new CartCreatedEvent($cart));

        return $cart;
    }
}

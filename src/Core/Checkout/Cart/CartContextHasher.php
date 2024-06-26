<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Event\CartContextHashEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
class CartContextHasher
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function isMatching(string $hash, Cart $cart, SalesChannelContext $context): bool
    {
        return $hash === $this->generate($cart, $context);
    }

    /**
     * @throws \JsonException
     */
    public function generate(Cart $cart, SalesChannelContext $context): string
    {
        $struct = new CartContextHashStruct();

        $struct->setPrice($cart->getPrice()->getRawTotal());
        $struct->setShippingMethod($context->getShippingMethod()->getId());
        $struct->setPaymentMethod($context->getPaymentMethod()->getId());

        foreach ($cart->getLineItems()->getElements() as $item) {
            $struct->addLineItem($item->getId(), $item->getHashContent());
        }

        $event = $this
            ->eventDispatcher
            ->dispatch(new CartContextHashEvent($context, $cart, $struct));

        return \hash('sha256', \json_encode($event->getHashStruct(), \JSON_THROW_ON_ERROR) ?: '');
    }
}

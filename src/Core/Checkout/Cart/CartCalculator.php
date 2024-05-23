<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Event\CartCalculatedEvent;
use Shopware\Core\Checkout\Cart\Tax\CountryTaxCalculator;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
class CartCalculator
{
    public function __construct(
        private readonly Processor $processor,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CartRuleLoader $cartRuleLoader,
        private readonly CountryTaxCalculator $taxCalculator
    ) {
    }

    public function calculate(Cart $cart, SalesChannelContext $context): Cart
    {
        return Profiler::trace('cart-calculation', function () use ($cart, $context) {
            if (Feature::isActive('cache_rework')) {
                $behavior = new CartBehavior($context->getPermissions());

                $cart = $this->processor->process($cart, $context, $behavior);

                $cart->markUnmodified();
                foreach ($cart->getLineItems()->getFlat() as $lineItem) {
                    $lineItem->markUnmodified();
                }

                $cart = $this->taxCalculator->calculate(
                    cart: $cart,
                    context: $context,
                    behavior: $behavior
                );

                $this->dispatcher->dispatch(new CartCalculatedEvent($cart, $context));

                return $cart;
            }

            // validate cart against the context rules
            $cart = $this->cartRuleLoader
                ->loadByCart($context, $cart, new CartBehavior($context->getPermissions()))
                ->getCart();

            $cart->markUnmodified();
            foreach ($cart->getLineItems()->getFlat() as $lineItem) {
                $lineItem->markUnmodified();
            }

            return $cart;
        });
    }
}

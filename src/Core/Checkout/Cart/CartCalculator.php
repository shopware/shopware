<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 * This class is used to recalculate a modified shopping cart. For this it uses the CartRuleLoader class.
 * The rule loader recalculates the cart and validates the current rules.
 */
#[Package('checkout')]
class CartCalculator
{
    public function __construct(private readonly CartRuleLoader $cartRuleLoader)
    {
    }

    public function calculate(Cart $cart, SalesChannelContext $context): Cart
    {
        return Profiler::trace('cart-calculation', function () use ($cart, $context) {
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

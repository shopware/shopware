<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Telemetry\CartMetricsInstrumentor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 * This class is used to recalculate a modified shopping cart. For this it uses the CartRuleLoader class.
 * The rule loader recalculates the cart and validates the current rules.
 */
#[Package('checkout')]
class CartCalculator
{
    public function __construct(
        private readonly CartRuleLoader $cartRuleLoader,
        private readonly CartContextHasher $cartContextHasher,
        private readonly CartMetricsInstrumentor $cartMetrics,
    ) {
    }

    public function calculate(Cart $cart, SalesChannelContext $context): Cart
    {
        return $this->cartMetrics->measure($context, function () use ($cart, $context): Cart {
            // validate cart against the context rules
            $cart = $this->cartRuleLoader
                ->loadByCart($context, $cart, new CartBehavior($context->getPermissions()))
                ->getCart();

            return $this->finalize($cart, $context);
        });
    }

    /**
     * Applies the state a cart carries once it went through a full calculation. Only call this for a cart
     * that was calculated through the CartRuleLoader already, otherwise use `calculate()`.
     */
    public function finalize(Cart $cart, SalesChannelContext $context): Cart
    {
        $cart->setHash($this->cartContextHasher->generate($cart, $context));

        $cart->markUnmodified();
        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            $lineItem->markUnmodified();
        }

        return $cart;
    }
}

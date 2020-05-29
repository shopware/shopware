<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 * This class is used to recalculate a modified shopping cart. For this it uses the CartRuleLoader class.
 * The rule loader recalculates the cart and validates the current rules.
 */
class CartCalculator
{
    /**
     * @var CartRuleLoader
     */
    private $cartRuleLoader;

    public function __construct(CartRuleLoader $cartRuleLoader)
    {
        $this->cartRuleLoader = $cartRuleLoader;
    }

    public function calculate(Cart $cart, SalesChannelContext $context): Cart
    {
        // validate cart against the context rules
        $cart = $this->cartRuleLoader
            ->loadByCart($context, $cart, new CartBehavior($context->getPermissions()))
            ->getCart();

        $cart->markUnmodified();
        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            $lineItem->markUnmodified();
        }

        return $cart;
    }
}

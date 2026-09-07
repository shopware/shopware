<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Lets a plugin claim a code submitted to /checkout/promotion/add and handle its own redemption,
 * instead of it being treated as a Shopware Promotion code. Tag a service with
 * "shopware.cart.code_claim_handler" to register one.
 */
#[Package('checkout')]
interface CartCodeClaimHandlerInterface
{
    /**
     * Return true if this handler wants to own redemption of the given code. Called once per
     * registered handler for every non-blank code submitted — keep it cheap and side-effect-free,
     * not a full redemption/validation pass. If a second handler also claims the code,
     * CartException::ambiguousCodeClaim() is thrown instead of calling handle(); if none claims it,
     * the code falls back to the default Promotion path.
     */
    public function claim(string $code, SalesChannelContext $context): bool;

    /**
     * Runs only for the single handler that claimed the code. Free to mutate/replace the cart
     * however it needs (add a line item, set a cart extension and recalculate, etc.) and must return
     * the resulting Cart. A domain-level failure (e.g. "code already redeemed") should be signalled
     * via Cart::addErrors() exactly like any other cart validation error.
     */
    public function handle(string $code, Cart $cart, SalesChannelContext $context): Cart;

    /**
     * Called once, immediately after this same instance's handle() returns without throwing.
     * Return a snippet key to flash as a success message, or null to show nothing.
     */
    public function getSuccessMessage(): ?string;
}

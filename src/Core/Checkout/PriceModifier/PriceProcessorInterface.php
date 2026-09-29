<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\PriceModifier;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Applies a cart-level monetary adjustment (a reduction or a surcharge) by modifying the
 * PriceCollections consumed by AmountCalculator right before Cart::price is calculated.
 *
 * Use PriceModifierResult::$additionalCosts for an adjustment that should affect the total (and its
 * tax, proportionally) without folding into positionPrice/subtotal. Use $taxExemptAdjustment instead
 * for one that must change the total WITHOUT affecting tax at all (e.g. a multi-purpose voucher,
 * where tax is only due at issuance). Both are signed: negative reduces, positive surcharges.
 *
 * $additionalCosts and $taxExemptAdjustment hold what every earlier processor in the chain already
 * contributed, so a reduction can be capped against what is actually still eligible:
 * `$prices + $shippingCosts + $additionalCosts + $taxExemptAdjustment` for a tax-exempt one. Return
 * only this processor's own contribution, never the values passed in. Processor still caps the
 * aggregated tax-exempt reduction at the remaining total and trims the tax-exempt reduction
 * modifiers to match, so the displayed modifiers always add up to what was actually applied.
 *
 * Avoid mutating $toCalculate->getDeliveries() directly to express an adjustment — that would
 * misrepresent Delivery::shippingCosts as the delivery's real cost.
 *
 * Tag a service with "shopware.cart.price_processor" (supports "priority") to register one. Runs
 * once per cart calculation, after every "shopware.cart.processor", for both a live storefront cart
 * and an admin order recalculation.
 */
#[Package('checkout')]
interface PriceProcessorInterface
{
    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        PriceCollection $prices,
        PriceCollection $shippingCosts,
        PriceCollection $additionalCosts,
        float $taxExemptAdjustment,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): PriceModifierResult;
}

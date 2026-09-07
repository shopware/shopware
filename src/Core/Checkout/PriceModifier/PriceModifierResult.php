<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\PriceModifier;

use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
final class PriceModifierResult
{
    public function __construct(
        public readonly PriceCollection $prices,
        public readonly PriceCollection $shippingCosts,
        public readonly PriceCollection $additionalCosts = new PriceCollection(),
        /**
         * A signed gross amount added to (positive) or subtracted from (negative) Cart::price's
         * totalPrice AFTER AmountCalculator has already computed netPrice/calculatedTaxes/taxRules --
         * those stay untouched. Use instead of $additionalCosts whenever the adjustment must never
         * affect tax (e.g. a tax-neutral multi-purpose voucher).
         */
        public readonly float $taxExemptAdjustment = 0.0,
        public readonly PriceModifierCollection $modifiers = new PriceModifierCollection()
    ) {
    }
}

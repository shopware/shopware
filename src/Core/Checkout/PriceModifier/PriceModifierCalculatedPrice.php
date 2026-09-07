<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\PriceModifier;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * A PriceModifier's fully-calculated result -- not CalculatedPrice, since a cart-level adjustment
 * has no unitPrice/quantity/listPrice concept behind it, only a total plus a tax breakdown.
 */
#[Package('checkout')]
final class PriceModifierCalculatedPrice extends Struct
{
    public function __construct(
        protected float $totalPrice,
        protected CalculatedTaxCollection $calculatedTaxes,
        protected TaxRuleCollection $taxRules,
    ) {
    }

    public static function fromCalculatedPrice(CalculatedPrice $price): self
    {
        return new self($price->getTotalPrice(), $price->getCalculatedTaxes(), $price->getTaxRules());
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    public function getCalculatedTaxes(): CalculatedTaxCollection
    {
        return $this->calculatedTaxes;
    }

    public function getTaxRules(): TaxRuleCollection
    {
        return $this->taxRules;
    }

    public function getApiAlias(): string
    {
        return 'cart_price_modifier_calculated_price';
    }
}

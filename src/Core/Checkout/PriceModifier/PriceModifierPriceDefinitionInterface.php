<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\PriceModifier;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * How a PriceModifier's amount was (or should be re-) computed -- the PriceModifier counterpart to
 * LineItem's own PriceDefinitionInterface.
 */
#[Package('checkout')]
interface PriceModifierPriceDefinitionInterface extends \JsonSerializable
{
    public function getType(): string;

    /**
     * Restricts the modifier to only affect line items taxed at one of these rates (membership, not
     * a weighted split). NULL means unrestricted: applies proportionally across every rate present.
     */
    public function getTaxRules(): ?TaxRuleCollection;

    /**
     * Whether this modifier's amount was applied via PriceModifierResult::$taxExemptAdjustment,
     * leaving Cart::price's netPrice/calculatedTaxes untouched.
     */
    public function isTaxExempt(): bool;
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
interface PriceDefinitionInterface
{
    /**
     * Return the type of this PriceDefinition. At the moment three definitions exist: AbsolutePriceDefinition,
     * QuantityPriceDefinition and PercentagePriceDefinition. The type of the definition changes how a price is
     * calculated, but they all share the same datastructure. See the corresponding classes for exactly how each
     * of them works.
     *
     * @see QuantityPriceDefinition
     * @see AbsolutePriceDefinition
     * @see PercentagePriceDefinition
     * @see \Shopware\Core\Checkout\Cart\Calculator
     */
    public function getType(): string;

    /**
     * Returns the priority of this price definitions, which determines in which order prices are calculated.
     * Some PriceDefinitions change the final price based on the amount of the prices already calculated, and thus
     * can only be calculated after all others have finished. This applies for example to percental discounts.
     * The default order of calculation for the base definitions is:
     * 1. QuantityPriceDefinition
     * 2. AbsolutePriceDefinition
     * 3. PercentagePriceDefinition
     */
    public function getPriority(): int;

    /**
     * Returns the constraints of this PriceDefinitions. These are used by PriceDefinitions which calculate their final
     * price from the results of other PriceDefinitions to filter the items they want to apply to. This is used for
     * example to create discounts, which only apply to items of a certain type.
     *
     * @see PercentagePriceDefinition
     * @see Rule
     */
    public static function getConstraints(): array;
}

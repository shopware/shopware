<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Util\FloatComparator;

#[Package('checkout')]
class CalculatedPrice extends Struct
{
    public function __construct(
        protected float $unitPrice,
        protected float $totalPrice,
        protected CalculatedTaxCollection $calculatedTaxes,
        protected TaxRuleCollection $taxRules,
        protected int $quantity = 1,
        protected ?ReferencePrice $referencePrice = null,
        protected ?ListPrice $listPrice = null,
        protected ?RegulationPrice $regulationPrice = null
    ) {
        $this->unitPrice = FloatComparator::cast($unitPrice);
        $this->totalPrice = FloatComparator::cast($totalPrice);
    }

    public function getTotalPrice(): float
    {
        return FloatComparator::cast($this->totalPrice);
    }

    public function getCalculatedTaxes(): CalculatedTaxCollection
    {
        return $this->calculatedTaxes;
    }

    public function setCalculatedTaxes(CalculatedTaxCollection $calculatedTaxes): void
    {
        $this->calculatedTaxes = $calculatedTaxes;
    }

    public function getTaxRules(): TaxRuleCollection
    {
        return $this->taxRules;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getReferencePrice(): ?ReferencePrice
    {
        return $this->referencePrice;
    }

    public function getListPrice(): ?ListPrice
    {
        return $this->listPrice;
    }

    public function getRegulationPrice(): ?RegulationPrice
    {
        return $this->regulationPrice;
    }

    public function getApiAlias(): string
    {
        return 'calculated_price';
    }

    /**
     * Changing a price should always be a full change, otherwise you have
     * mismatching information regarding the unit, total and tax values.
     */
    public function overwrite(float $unitPrice, float $totalPrice, CalculatedTaxCollection $taxes): void
    {
        $this->unitPrice = $unitPrice;
        $this->totalPrice = $totalPrice;
        $this->calculatedTaxes = $taxes;
    }
}

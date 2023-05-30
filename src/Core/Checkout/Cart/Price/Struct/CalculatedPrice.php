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
    /**
     * @var float
     */
    protected $unitPrice;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var float
     */
    protected $totalPrice;

    /**
     * @var CalculatedTaxCollection
     */
    protected $calculatedTaxes;

    /**
     * @var TaxRuleCollection
     */
    protected $taxRules;

    /**
     * @var ReferencePrice|null
     */
    protected $referencePrice;

    /**
     * @var ListPrice|null
     */
    protected $listPrice;

    /**
     * @var RegulationPrice|null
     */
    protected $regulationPrice;

    public function __construct(
        float $unitPrice,
        float $totalPrice,
        CalculatedTaxCollection $calculatedTaxes,
        TaxRuleCollection $taxRules,
        int $quantity = 1,
        ?ReferencePrice $referencePrice = null,
        ?ListPrice $listPrice = null,
        ?RegulationPrice $regulationPrice = null
    ) {
        $this->unitPrice = FloatComparator::cast($unitPrice);
        $this->totalPrice = FloatComparator::cast($totalPrice);
        $this->calculatedTaxes = $calculatedTaxes;
        $this->taxRules = $taxRules;
        $this->quantity = $quantity;
        $this->referencePrice = $referencePrice;
        $this->listPrice = $listPrice;
        $this->regulationPrice = $regulationPrice;
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

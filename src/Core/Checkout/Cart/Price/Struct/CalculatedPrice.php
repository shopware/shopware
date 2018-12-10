<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Struct\Struct;

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

    public function __construct(
        float $unitPrice,
        float $totalPrice,
        CalculatedTaxCollection $calculatedTaxes,
        TaxRuleCollection $taxRules,
        int $quantity = 1
    ) {
        $this->unitPrice = $unitPrice;
        $this->totalPrice = $totalPrice;
        $this->calculatedTaxes = $calculatedTaxes;
        $this->taxRules = $taxRules;
        $this->quantity = $quantity;
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

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function add(self $price): void
    {
        $this->unitPrice += $price->getUnitPrice();
        $this->totalPrice += $price->getTotalPrice();
        $this->calculatedTaxes = $this->calculatedTaxes->merge(
            $price->getCalculatedTaxes()
        );
        $this->taxRules = $this->taxRules->merge(
            $price->getTaxRules()
        );
    }

    public function sub(self $price): void
    {
        $this->unitPrice -= $price->getUnitPrice();
        $this->totalPrice -= $price->getTotalPrice();
        $this->calculatedTaxes = $this->calculatedTaxes->merge(
            $price->getCalculatedTaxes()
        );
        $this->taxRules = $this->taxRules->merge(
            $price->getTaxRules()
        );
    }
}
